<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    echo "<div class='text-center text-danger py-4'>Unauthorized access.</div>";
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo "<div class='text-center text-danger py-4'>CSRF validation failed.</div>";
    exit;
}

$prospectus_id = $_POST['prospectus_id'] ?? '';
$ay_id = $_POST['ay_id'] ?? '';
$semester = $_POST['semester'] ?? '';

if ($prospectus_id === '' || $ay_id === '' || $semester === '') {
    echo "<div class='text-center text-danger py-4'>Missing filters.</div>";
    exit;
}

$college_id = (int)($_SESSION['college_id'] ?? 0);
if ($college_id <= 0) {
    echo "<div class='text-center text-danger py-4'>Missing college context.</div>";
    exit;
}

function year_label($year) {
    $year = (string)$year;
    if ($year === '1') return '1st Year';
    if ($year === '2') return '2nd Year';
    if ($year === '3') return '3rd Year';
    if ($year === '4') return '4th Year';
    if ($year === '5') return '5th Year';
    if ($year === '6') return '6th Year';
    return 'Year ' . $year;
}

function block_badge_html($type, $sequence) {
    $type = synk_normalize_schedule_type((string)$type);
    $badgeClass = $type === 'LAB'
        ? 'bg-label-success text-success'
        : 'bg-label-primary text-primary';
    $prefix = $type === 'LAB' ? 'LAB' : 'LEC';
    return "<span class='badge {$badgeClass} me-1'>{$prefix} {$sequence}</span>";
}

function offering_schedule_status_badge(array $row): array
{
    $required = synk_required_minutes_by_type(
        (float)$row['lec_units'],
        (float)$row['lab_units'],
        (float)$row['total_units']
    );
    $scheduled = synk_sum_scheduled_minutes_by_type($row['entries']);
    $requiredTypes = ((float)$row['lab_units'] > 0) ? ['LEC', 'LAB'] : ['LEC'];
    $hasAny = false;
    $isComplete = true;

    foreach ($requiredTypes as $type) {
        $requiredMinutes = (int)($required[$type] ?? 0);
        $scheduledMinutes = (int)($scheduled[$type] ?? 0);

        if ($scheduledMinutes > 0) {
            $hasAny = true;
        }

        if ($requiredMinutes > 0 && $scheduledMinutes < $requiredMinutes) {
            $isComplete = false;
        }
    }

    if (!$hasAny) {
        return [
            'badge' => "<span class='badge bg-secondary'>Not Scheduled</span>",
            'button_label' => 'Schedule',
            'button_class' => 'btn-primary'
        ];
    }

    if ($isComplete) {
        return [
            'badge' => "<span class='badge bg-success'>Scheduled</span>",
            'button_label' => 'Edit',
            'button_class' => 'btn-warning'
        ];
    }

    return [
        'badge' => "<span class='badge bg-warning text-dark'>Incomplete</span>",
        'button_label' => 'Edit',
        'button_class' => 'btn-warning'
    ];
}

$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

$sql = "
SELECT
    o.offering_id,
    sec.section_name,
    sec.year_level,
    sm.sub_code,
    sm.sub_description,
    ps.lab_units,
    ps.lec_units,
    ps.total_units,
    cs.schedule_id,
    cs.schedule_type,
    cs.room_id,
    cs.days_json,
    cs.time_start,
    cs.time_end,
    r.room_name,
    r.room_code
FROM tbl_prospectus_offering o
{$liveOfferingJoins}
INNER JOIN tbl_program p ON p.program_id = o.program_id
INNER JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_class_schedule cs
    ON cs.offering_id = o.offering_id
   AND cs.schedule_type IN ('LEC', 'LAB')
LEFT JOIN tbl_rooms r ON r.room_id = cs.room_id
WHERE o.prospectus_id = ?
  AND o.ay_id = ?
  AND o.semester = ?
  AND p.college_id = ?
ORDER BY
    sec.year_level ASC,
    sec.section_name ASC,
    sm.sub_code ASC,
    FIELD(cs.schedule_type, 'LEC', 'LAB'),
    cs.schedule_id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $prospectus_id, $ay_id, $semester, $college_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<div class='text-center text-muted py-4'>No class offerings found.</div>";
    exit;
}

$grouped = [];
while ($row = $res->fetch_assoc()) {
    $yearLevel = (int)$row['year_level'];
    $offeringId = (int)$row['offering_id'];

    if (!isset($grouped[$yearLevel][$offeringId])) {
        $grouped[$yearLevel][$offeringId] = [
            'offering_id' => $offeringId,
            'section_name' => (string)$row['section_name'],
            'sub_code' => (string)$row['sub_code'],
            'sub_description' => (string)$row['sub_description'],
            'lab_units' => (float)$row['lab_units'],
            'lec_units' => (float)$row['lec_units'],
            'total_units' => (float)$row['total_units'],
            'entries' => []
        ];
    }

    $scheduleType = strtoupper(trim((string)($row['schedule_type'] ?? '')));
    if (!empty($row['schedule_id']) && in_array($scheduleType, ['LEC', 'LAB'], true)) {
        $roomLabel = trim((string)($row['room_code'] ?? ''));
        if ($roomLabel === '') {
            $roomLabel = trim((string)($row['room_name'] ?? ''));
        }

        $days = synk_normalize_schedule_days(json_decode((string)($row['days_json'] ?? ''), true));

        $grouped[$yearLevel][$offeringId]['entries'][] = [
            'schedule_id' => (int)$row['schedule_id'],
            'schedule_type' => synk_normalize_schedule_type($scheduleType),
            'room_id' => (int)($row['room_id'] ?? 0),
            'days_json' => json_encode($days),
            'days' => $days,
            'time_start' => (string)($row['time_start'] ?? ''),
            'time_end' => (string)($row['time_end'] ?? ''),
            'room_label' => $roomLabel
        ];
    }
}
ksort($grouped);

foreach ($grouped as $yearLevel => $rows) {
    $offerings = array_values($rows);

    echo "<div class='schedule-group-card mb-3'>";
    echo "  <div class='schedule-group-header px-3 py-2 d-flex justify-content-between align-items-center'>";
    echo "    <span>" . htmlspecialchars(year_label($yearLevel)) . "</span>";
    echo "    <span class='badge bg-label-primary schedule-group-count' data-total-count='" . count($offerings) . "'>" . count($offerings) . " class(es)</span>";
    echo "  </div>";
    echo "  <div class='table-responsive'>";
    echo "    <table class='table table-bordered table-hover mb-0'>";
    echo "      <thead>";
    echo "        <tr>";
    echo "          <th>Section</th>";
    echo "          <th>Subject</th>";
    echo "          <th>Description</th>";
    echo "          <th>Days</th>";
    echo "          <th>Time</th>";
    echo "          <th>Room</th>";
    echo "          <th>Status</th>";
    echo "          <th>Action</th>";
    echo "        </tr>";
    echo "      </thead>";
    echo "      <tbody>";

    foreach ($offerings as $row) {
        $status = offering_schedule_status_badge($row);
        $daysParts = [];
        $timeParts = [];
        $roomParts = [];
        $sequenceByType = ['LEC' => 0, 'LAB' => 0];

        foreach ($row['entries'] as $entry) {
            $type = synk_normalize_schedule_type((string)$entry['schedule_type']);
            $sequenceByType[$type]++;
            $label = block_badge_html($type, $sequenceByType[$type]);
            $daysText = !empty($entry['days']) ? implode('', $entry['days']) : '-';
            $timeText = ($entry['time_start'] !== '' && $entry['time_end'] !== '')
                ? htmlspecialchars(date('h:i A', strtotime($entry['time_start']))) . " - " . htmlspecialchars(date('h:i A', strtotime($entry['time_end'])))
                : '-';
            $roomText = $entry['room_label'] !== ''
                ? htmlspecialchars($entry['room_label'])
                : '-';

            $daysParts[] = "<div class='text-nowrap mb-1'>{$label}" . htmlspecialchars($daysText) . "</div>";
            $timeParts[] = "<div class='text-nowrap mb-1'>{$label}{$timeText}</div>";
            $roomParts[] = "<div class='text-nowrap mb-1'>{$label}{$roomText}</div>";
        }

        if (empty($daysParts)) {
            $daysParts[] = "<div class='text-nowrap mb-1 text-muted'>-</div>";
            $timeParts[] = "<div class='text-nowrap mb-1 text-muted'>-</div>";
            $roomParts[] = "<div class='text-nowrap mb-1 text-muted'>-</div>";
        }

        $searchText = strtolower(trim(
            (string)$row['section_name'] . ' ' .
            (string)$row['sub_code'] . ' ' .
            (string)$row['sub_description']
        ));

        echo "<tr class='schedule-offering-row' data-search-text='" . htmlspecialchars($searchText, ENT_QUOTES) . "'>";
        echo "  <td>" . htmlspecialchars((string)$row['section_name']) . "</td>";
        echo "  <td class='text-nowrap'>" . htmlspecialchars(strtoupper((string)$row['sub_code'])) . "</td>";
        echo "  <td>" . htmlspecialchars(strtoupper((string)$row['sub_description'])) . "</td>";
        echo "  <td class='text-center'>" . implode('', $daysParts) . "</td>";
        echo "  <td class='text-center'>" . implode('', $timeParts) . "</td>";
        echo "  <td class='text-center'>" . implode('', $roomParts) . "</td>";
        echo "  <td class='text-center'>{$status['badge']}</td>";
        echo "  <td class='text-center'>";
        echo "    <button class='btn {$status['button_class']} btn-sm btn-schedule'";
        echo "      data-offering-id='" . (int)$row['offering_id'] . "'";
        echo "      data-sub-code='" . htmlspecialchars((string)$row['sub_code'], ENT_QUOTES) . "'";
        echo "      data-sub-desc='" . htmlspecialchars((string)$row['sub_description'], ENT_QUOTES) . "'";
        echo "      data-section='" . htmlspecialchars((string)$row['section_name'], ENT_QUOTES) . "'";
        echo "      data-lab-units='" . (float)$row['lab_units'] . "'";
        echo "      data-lec-units='" . (float)$row['lec_units'] . "'";
        echo "      data-total-units='" . (float)$row['total_units'] . "'>";
        echo "      {$status['button_label']}";
        echo "    </button>";
        echo "  </td>";
        echo "</tr>";
    }

    echo "      </tbody>";
    echo "    </table>";
    echo "  </div>";
    echo "</div>";
}
?>
