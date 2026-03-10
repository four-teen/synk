<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schema_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';

header('Content-Type: application/json');

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
    'o.offering_id',
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
    'ps.lab_units AS lab_units'
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

$sql = "
SELECT
    " . implode(",\n    ", $selectParts) . "
" . implode("\n", $joinParts) . "
WHERE
    o.ay_id = ?
AND o.semester = ?
AND p.college_id = ?
AND assigned_fw.schedule_id IS NULL
ORDER BY
    sec.section_name,
    sm.sub_code,
    " . ($classScheduleHasType ? "FIELD(cs.schedule_type, 'LEC', 'LAB')," : '') . "
    cs.time_start
";

$stmt = $conn->prepare($sql);
$bindTypes = 'ii';
$bindParams = [$ay_id, $semester];

$bindTypes .= 'iii';
$bindParams[] = $ay_id;
$bindParams[] = $semester;
$bindParams[] = $college_id;

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
    $subjectUnits = synk_subject_units_total(
        (float)$row['lec_units'],
        (float)$row['lab_units'],
        (float)$row['total_units']
    );
    $metrics = synk_schedule_block_metrics_from_row([
        'schedule_type' => $scheduleType,
        'days' => $days_arr,
        'time_start' => (string)$row['time_start'],
        'time_end' => (string)$row['time_end'],
        'lec_units' => (float)$row['lec_units'],
        'lab_units' => (float)$row['lab_units'],
        'total_units' => (float)$row['total_units']
    ]);

    $data[] = [
        'schedule_id' => (int)$row['schedule_id'],
        'offering_id' => (int)$row['offering_id'],
        'group_id' => $row['schedule_group_id'],
        'schedule_type' => $scheduleType,
        'subject_code' => $row['sub_code'],
        'subject_description' => $row['sub_description'],
        'section_name' => $row['section_name'],
        'days' => implode(", ", $days_arr),
        'time' => date("g:iA", strtotime($row['time_start'])) . "-" .
                  date("g:iA", strtotime($row['time_end'])),
        'room_code' => $row['room_code'] ?? '',
        'lec_units' => (float)$row['lec_units'],
        'lab_units' => (float)$row['lab_units'],
        'subject_units' => round($subjectUnits, 2),
        'units' => $metrics['units'],
        'hours_lec' => $metrics['hours_lec'],
        'hours_lab' => $metrics['hours_lab'],
        'faculty_load' => $metrics['faculty_load'],
        'weekly_minutes' => $metrics['weekly_minutes'],
        'coverage_ratio' => $metrics['coverage_ratio']
    ];
}

echo json_encode($data);
exit;
