<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schema_helper.php';

header('Content-Type: application/json');

define('LAB_LOAD_MULTIPLIER', 0.75);
define('LAB_CONTACT_HOURS_PER_UNIT', 3.0);

/* =====================================================
   SECURITY
===================================================== */
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

/* =====================================================
   COLLEGE CONTEXT (SCHEDULER SCOPE)
===================================================== */
if (!isset($_SESSION['college_id'])) {
    echo json_encode([]);
    exit;
}

$college_id = (int)$_SESSION['college_id'];

/* =====================================================
   INPUTS
===================================================== */
$faculty_id = (int)($_POST['faculty_id'] ?? 0);
$ay_id = (int)($_POST['ay_id'] ?? 0);
$semester = (int)($_POST['semester_num'] ?? 0);
$ay_raw = trim($_POST['ay'] ?? '');
$semester_raw = trim($_POST['semester'] ?? '');

/* =====================================================
   SEMESTER MAP (UI -> DB)
===================================================== */
$semester_map = [
    '1st' => 1,
    '2nd' => 2,
    'Midyear' => 3
];

if ($semester <= 0) {
    if ($semester_raw === '' || !isset($semester_map[$semester_raw])) {
        echo json_encode([]);
        exit;
    }

    $semester = $semester_map[$semester_raw];
}

/* =====================================================
   RESOLVE AY -> ay_id
===================================================== */
if ($ay_id <= 0) {
    if ($ay_raw === '') {
        echo json_encode([]);
        exit;
    }

    $ayStmt = $conn->prepare("
        SELECT ay_id
        FROM tbl_academic_years
        WHERE ay = ?
        LIMIT 1
    ");
    $ayStmt->bind_param("s", $ay_raw);
    $ayStmt->execute();
    $ayRes = $ayStmt->get_result();

    if ($ayRes->num_rows === 0) {
        $ayStmt->close();
        echo json_encode([]);
        exit;
    }

    $ay_id = (int)$ayRes->fetch_assoc()['ay_id'];
    $ayStmt->close();
}

/* =====================================================
   MAIN QUERY
   PURPOSE:
   - Load scheduled class entries from the live synced offering set
   - Exclude schedule rows that already exist in faculty workload
===================================================== */
$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
$classScheduleHasGroupId = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_group_id');
$classScheduleHasType = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_type');

$selectParts = [
    'cs.schedule_id',
    $classScheduleHasGroupId ? 'cs.schedule_group_id' : 'NULL AS schedule_group_id',
    $classScheduleHasType ? 'cs.schedule_type' : "'LEC' AS schedule_type",
    'sm.sub_code',
    'sm.sub_description',
    'sec.section_name',
    'cs.days_json',
    'cs.time_start',
    'cs.time_end',
    'r.room_code',
    'ps.total_units AS total_units',
    'ps.lec_units AS lec_units',
    'ps.lab_units AS lab_units',
    $classScheduleHasGroupId ? 'partner_cs.schedule_id AS partner_schedule_id' : 'NULL AS partner_schedule_id',
    ($classScheduleHasGroupId && $classScheduleHasType)
        ? 'partner_cs.schedule_type AS partner_schedule_type'
        : "'' AS partner_schedule_type",
    $classScheduleHasGroupId ? 'partner_fw.faculty_id AS partner_faculty_id' : 'NULL AS partner_faculty_id'
];

$joinParts = [
    'FROM tbl_class_schedule cs',
    'JOIN tbl_prospectus_offering o',
    '    ON o.offering_id = cs.offering_id',
    trim($liveOfferingJoins),
    'JOIN tbl_program p',
    '    ON p.program_id = o.program_id',
    'JOIN tbl_subject_masterlist sm',
    '    ON sm.sub_id = ps.sub_id',
    'LEFT JOIN tbl_rooms r',
    '    ON r.room_id = cs.room_id',
    'LEFT JOIN tbl_faculty_workload_sched assigned_fw',
    '    ON assigned_fw.schedule_id = cs.schedule_id',
    '   AND assigned_fw.ay_id = ?',
    '   AND assigned_fw.semester = ?'
];

if ($classScheduleHasGroupId) {
    $joinParts[] = 'LEFT JOIN tbl_class_schedule partner_cs';
    $joinParts[] = '    ON cs.schedule_group_id IS NOT NULL';
    $joinParts[] = '   AND partner_cs.schedule_group_id = cs.schedule_group_id';
    $joinParts[] = '   AND partner_cs.schedule_id <> cs.schedule_id';
    $joinParts[] = 'LEFT JOIN tbl_faculty_workload_sched partner_fw';
    $joinParts[] = '    ON partner_fw.schedule_id = partner_cs.schedule_id';
    $joinParts[] = '   AND partner_fw.ay_id = ?';
    $joinParts[] = '   AND partner_fw.semester = ?';
}

$sql = "
SELECT
    " . implode(",\n    ", $selectParts) . "
" . implode("\n", $joinParts) . "
WHERE
    o.ay_id = ?
AND o.semester = ?
AND p.college_id = ?
AND assigned_fw.schedule_id IS NULL
" . ($classScheduleHasGroupId ? "
AND (
    cs.schedule_group_id IS NULL
    OR partner_fw.faculty_id IS NULL
    OR partner_fw.faculty_id = ?
)" : '') . "
ORDER BY
    sec.section_name,
    sm.sub_code,
    " . ($classScheduleHasType ? "FIELD(cs.schedule_type, 'LEC', 'LAB')," : '') . "
    cs.time_start
";

$stmt = $conn->prepare($sql);
$bindTypes = 'ii';
$bindParams = [$ay_id, $semester];

if ($classScheduleHasGroupId) {
    $bindTypes .= 'ii';
    $bindParams[] = $ay_id;
    $bindParams[] = $semester;
}

$bindTypes .= 'iii';
$bindParams[] = $ay_id;
$bindParams[] = $semester;
$bindParams[] = $college_id;

if ($classScheduleHasGroupId) {
    $bindTypes .= 'i';
    $bindParams[] = $faculty_id;
}

synk_bind_dynamic_params($stmt, $bindTypes, $bindParams);
$stmt->execute();
$res = $stmt->get_result();

/* =====================================================
   FORMAT OUTPUT
===================================================== */
$data = [];

while ($row = $res->fetch_assoc()) {
    $days_arr = json_decode($row['days_json'], true);
    if (!is_array($days_arr)) {
        $days_arr = [];
    }

    $scheduleType = strtoupper(trim((string)($row['schedule_type'] ?? 'LEC')));
    $lecUnits = (float)$row['lec_units'];
    $labValue = (float)$row['lab_units'];
    $totalUnits = (float)$row['total_units'];
    $labIsCredit = ($labValue > 0) && (abs(($lecUnits + $labValue) - $totalUnits) < 0.0001);
    $labHours = $labIsCredit ? ($labValue * LAB_CONTACT_HOURS_PER_UNIT) : $labValue;

    $componentUnits = $scheduleType === 'LAB'
        ? ($labIsCredit ? $labValue : 0)
        : $lecUnits;
    $componentLec = $scheduleType === 'LEC' ? $lecUnits : 0;
    $componentLab = $scheduleType === 'LAB' ? $labHours : 0;
    $componentLoad = $scheduleType === 'LAB'
        ? round($labHours * LAB_LOAD_MULTIPLIER, 2)
        : round($lecUnits, 2);
    $partnerScheduleId = (int)($row['partner_schedule_id'] ?? 0);
    $partnerFacultyId = (int)($row['partner_faculty_id'] ?? 0);
    $partnerType = strtoupper(trim((string)($row['partner_schedule_type'] ?? '')));
    $partnerStatus = 'none';
    $partnerNote = '';

    if ($partnerScheduleId > 0) {
        if ($partnerFacultyId > 0) {
            if ($faculty_id > 0 && $partnerFacultyId === $faculty_id) {
                $partnerStatus = 'same_faculty';
                if ($partnerType !== '') {
                    $partnerNote = "Paired with assigned {$partnerType}";
                }
            } else {
                $partnerStatus = 'other_faculty';
                $partnerNote = 'Paired with another faculty';
            }
        } else {
            $partnerStatus = 'unassigned';
            if ($partnerType !== '') {
                $partnerNote = "Has {$partnerType} partner";
            }
        }
    }

    $data[] = [
        'schedule_id' => (int)$row['schedule_id'],
        'group_id' => $row['schedule_group_id'],
        'schedule_type' => $scheduleType,
        'subject_code' => $row['sub_code'],
        'subject_description' => $row['sub_description'],
        'section_name' => $row['section_name'],
        'days' => implode(", ", $days_arr),
        'time' => date("g:iA", strtotime($row['time_start'])) . "-" .
                  date("g:iA", strtotime($row['time_end'])),
        'room_code' => $row['room_code'] ?? '',
        'units' => $componentUnits,
        'hours_lec' => $componentLec,
        'hours_lab' => round($componentLab, 2),
        'faculty_load' => $componentLoad,
        'partner_schedule_id' => $partnerScheduleId,
        'partner_type' => $partnerType,
        'partner_status' => $partnerStatus,
        'partner_note' => $partnerNote
    ];
}

echo json_encode($data);
exit;
