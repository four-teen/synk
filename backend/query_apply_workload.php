<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

function respond($status, $message, $extra = [])
{
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $extra));
    exit;
}

function time_overlap($s1, $e1, $s2, $e2)
{
    return ($s1 < $e2) && ($e1 > $s2);
}

function normalize_days($days_json)
{
    $days = json_decode((string)$days_json, true);
    if (!is_array($days)) {
        return [];
    }

    $out = [];
    foreach ($days as $d) {
        $val = strtoupper(trim((string)$d));
        if ($val !== '') {
            $out[$val] = true;
        }
    }
    return array_keys($out);
}

function days_overlap($a, $b)
{
    return !empty(array_intersect($a, $b));
}

function fmt_time($t)
{
    return date('g:iA', strtotime($t));
}

function fmt_days($days)
{
    return empty($days) ? '-' : implode('', $days);
}

function esc($txt)
{
    return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8');
}

function has_conflict($candidate, $other)
{
    return days_overlap($candidate['days'], $other['days']) &&
           time_overlap($candidate['time_start'], $candidate['time_end'], $other['time_start'], $other['time_end']);
}

/* ===============================
   SECURITY
================================ */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'scheduler'
) {
    respond('error', 'Unauthorized.');
}

$college_id = (int)($_SESSION['college_id'] ?? 0);
if ($college_id <= 0) {
    respond('error', 'Missing college context.');
}

/* ===============================
   INPUTS
================================ */
$faculty_id = (int)($_POST['faculty_id'] ?? 0);
$ay_id      = (int)($_POST['ay_id'] ?? 0);
$semester   = (int)($_POST['semester'] ?? 0); // 1,2,3
$schedules  = $_POST['schedule_ids'] ?? [];

if (
    !$faculty_id ||
    !$ay_id ||
    !$semester ||
    !is_array($schedules) ||
    count($schedules) === 0
) {
    respond('error', 'Invalid input.');
}

$scheduleMap = [];
foreach ($schedules as $rawId) {
    $sid = (int)$rawId;
    if ($sid > 0) {
        $scheduleMap[$sid] = true;
    }
}
$schedule_ids = array_keys($scheduleMap);
if (empty($schedule_ids)) {
    respond('error', 'No valid schedules selected.');
}

/* ===============================
   LOAD CANDIDATE SCHEDULES
   - scoped to scheduler college and selected term
   - only currently unassigned schedules
================================ */
$inList = implode(',', array_map('intval', $schedule_ids));

$candidateSql = "
    SELECT
        cs.schedule_id,
        cs.schedule_type,
        cs.days_json,
        cs.time_start,
        cs.time_end,
        sm.sub_code,
        sec.section_name
    FROM tbl_class_schedule cs
    INNER JOIN tbl_prospectus_offering o
        ON o.offering_id = cs.offering_id
    INNER JOIN tbl_program p
        ON p.program_id = o.program_id
    INNER JOIN tbl_sections sec
        ON sec.section_id = o.section_id
    INNER JOIN tbl_prospectus_subjects ps
        ON ps.ps_id = o.ps_id
    INNER JOIN tbl_subject_masterlist sm
        ON sm.sub_id = ps.sub_id
    WHERE cs.schedule_id IN ($inList)
      AND o.ay_id = ?
      AND o.semester = ?
      AND p.college_id = ?
      AND NOT EXISTS (
            SELECT 1
            FROM tbl_faculty_workload_sched fwx
            WHERE fwx.schedule_id = cs.schedule_id
              AND fwx.ay_id = ?
              AND fwx.semester = ?
      )
    ORDER BY cs.time_start, cs.schedule_id
";

$candStmt = $conn->prepare($candidateSql);
$candStmt->bind_param('iiiii', $ay_id, $semester, $college_id, $ay_id, $semester);
$candStmt->execute();
$candRes = $candStmt->get_result();

$candidates = [];
while ($row = $candRes->fetch_assoc()) {
    $row['days'] = normalize_days($row['days_json']);
    $candidates[] = $row;
}
$candStmt->close();

if (empty($candidates)) {
    respond('error', 'No eligible schedules found for assignment.');
}

/* ===============================
   LOAD FACULTY EXISTING LOAD (TERM)
   - no college filter on purpose:
     faculty cannot teach overlapping classes anywhere
================================ */
$existingSql = "
    SELECT
        cs.schedule_id,
        cs.schedule_type,
        cs.days_json,
        cs.time_start,
        cs.time_end,
        sm.sub_code,
        sec.section_name
    FROM tbl_faculty_workload_sched fw
    INNER JOIN tbl_class_schedule cs
        ON cs.schedule_id = fw.schedule_id
    INNER JOIN tbl_prospectus_offering o
        ON o.offering_id = cs.offering_id
    INNER JOIN tbl_sections sec
        ON sec.section_id = o.section_id
    INNER JOIN tbl_prospectus_subjects ps
        ON ps.ps_id = o.ps_id
    INNER JOIN tbl_subject_masterlist sm
        ON sm.sub_id = ps.sub_id
    WHERE fw.faculty_id = ?
      AND fw.ay_id = ?
      AND fw.semester = ?
";

$existingStmt = $conn->prepare($existingSql);
$existingStmt->bind_param('iii', $faculty_id, $ay_id, $semester);
$existingStmt->execute();
$existingRes = $existingStmt->get_result();

$existing = [];
while ($row = $existingRes->fetch_assoc()) {
    $row['days'] = normalize_days($row['days_json']);
    $existing[] = $row;
}
$existingStmt->close();

/* ===============================
   CONFLICT CHECK + INSERT
================================ */
$insSql = "
    INSERT IGNORE INTO tbl_faculty_workload_sched
        (schedule_id, faculty_id, ay_id, semester)
    VALUES (?, ?, ?, ?)
";
$insStmt = $conn->prepare($insSql);

$inserted = 0;
$acceptedBatch = [];
$conflicts = [];

foreach ($candidates as $cand) {
    $hit = null;

    foreach ($existing as $other) {
        if (has_conflict($cand, $other)) {
            $hit = $other;
            break;
        }
    }

    if ($hit === null) {
        foreach ($acceptedBatch as $other) {
            if (has_conflict($cand, $other)) {
                $hit = $other;
                break;
            }
        }
    }

    if ($hit !== null) {
        $conflicts[] = [
            'candidate' => $cand,
            'against' => $hit
        ];
        continue;
    }

    $sid = (int)$cand['schedule_id'];
    $insStmt->bind_param('iiii', $sid, $faculty_id, $ay_id, $semester);
    if ($insStmt->execute() && $insStmt->affected_rows > 0) {
        $inserted++;
        $acceptedBatch[] = $cand;
    }
}

$insStmt->close();

/* ===============================
   RESPONSE
================================ */
if (!empty($conflicts)) {
    $items = [];
    $limit = 8;
    $max = min($limit, count($conflicts));

    for ($i = 0; $i < $max; $i++) {
        $c = $conflicts[$i]['candidate'];
        $a = $conflicts[$i]['against'];

        $candLabel = esc(strtoupper($c['sub_code'])) . " (" . esc($c['section_name']) . ", " . esc($c['schedule_type']) . ")";
        $candWhen  = esc(fmt_days($c['days'])) . " " . esc(fmt_time($c['time_start'])) . "-" . esc(fmt_time($c['time_end']));

        $hitLabel = esc(strtoupper($a['sub_code'])) . " (" . esc($a['section_name']) . ", " . esc($a['schedule_type']) . ")";
        $hitWhen  = esc(fmt_days($a['days'])) . " " . esc(fmt_time($a['time_start'])) . "-" . esc(fmt_time($a['time_end']));

        $items[] = "<li><b>{$candLabel}</b> conflicts with <b>{$hitLabel}</b><br><small>{$candWhen} vs {$hitWhen}</small></li>";
    }

    if (count($conflicts) > $limit) {
        $extra = count($conflicts) - $limit;
        $items[] = "<li><small>...and {$extra} more conflict(s).</small></li>";
    }

    $summary = $inserted > 0
        ? "<p class='mb-2'><b>{$inserted}</b> class(es) were applied. Some selections were skipped due to faculty conflicts:</p>"
        : "<p class='mb-2'>No class was applied because all selected schedules conflict with this faculty's workload:</p>";

    $html = "<div class='text-start'>{$summary}<ul class='mb-0'>" . implode('', $items) . "</ul></div>";

    respond(
        $inserted > 0 ? 'partial' : 'conflict',
        $html,
        [
            'inserted' => $inserted,
            'conflicts' => count($conflicts)
        ]
    );
}

respond(
    'success',
    $inserted . ' class(es) added to workload.',
    ['inserted' => $inserted]
);
