<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';

header('Content-Type: application/json');

/* =====================================================
   SECURITY
===================================================== */
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

/* =====================================================
   INPUTS
===================================================== */
$faculty_id = intval($_POST['faculty_id'] ?? 0);
$ay_id = intval($_POST['ay_id'] ?? 0);
$semester = intval($_POST['semester'] ?? 0);

if (!$faculty_id || !$ay_id || !$semester) {
    echo json_encode([]);
    exit;
}

/* =====================================================
   WORKLOAD COMPUTATION CONFIG
===================================================== */
define('LAB_LOAD_MULTIPLIER', 0.75);
define('LAB_CONTACT_HOURS_PER_UNIT', 3.0);

/* =====================================================
   MAIN QUERY
===================================================== */
$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

$sql = "
SELECT
    fw.workload_id,
    o.offering_id,
    cs.schedule_group_id AS group_id,
    cs.schedule_type     AS type,

    sm.sub_code,
    sm.sub_description   AS `desc`,
    sec.section_name     AS section,

    cs.days_json,
    cs.time_start,
    cs.time_end,

    r.room_code          AS room,

    ps.lec_units,
    ps.lab_units,
    ps.total_units
FROM tbl_faculty_workload_sched fw
JOIN tbl_class_schedule cs       ON cs.schedule_id = fw.schedule_id
JOIN tbl_prospectus_offering o   ON o.offering_id = cs.offering_id
{$liveOfferingJoins}
JOIN tbl_subject_masterlist sm   ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_rooms r            ON r.room_id = cs.room_id
WHERE
    fw.faculty_id = ?
AND fw.ay_id      = ?
AND fw.semester   = ?
ORDER BY
    sec.section_name,
    sm.sub_code,
    COALESCE(cs.schedule_group_id, cs.schedule_id),
    FIELD(cs.schedule_type, 'LEC', 'LAB'),
    cs.time_start
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $faculty_id, $ay_id, $semester);
$stmt->execute();
$res = $stmt->get_result();

/* =====================================================
   BUILD RESPONSE
===================================================== */
$data = [];

while ($row = $res->fetch_assoc()) {
    $days_arr = json_decode($row['days_json'], true);
    if (!is_array($days_arr)) {
        $days_arr = [];
    }
    $days = implode(", ", $days_arr);

    $time = date("g:iA", strtotime($row['time_start'])) .
            "-" .
            date("g:iA", strtotime($row['time_end']));

    $rowType = strtoupper(trim((string)($row['type'] ?? 'LEC')));
    $lecUnits = (float)$row['lec_units'];
    $labValue = (float)$row['lab_units'];
    $totalUnits = (float)$row['total_units'];

    $labIsCredit = ($labValue > 0) && (abs(($lecUnits + $labValue) - $totalUnits) < 0.0001);
    $labHours = $labIsCredit
        ? ($labValue * LAB_CONTACT_HOURS_PER_UNIT)
        : $labValue;

    $displayUnits = $rowType === 'LAB'
        ? ($labIsCredit ? $labValue : 0)
        : $lecUnits;
    $displayLec = $rowType === 'LEC' ? $lecUnits : 0;
    $displayLab = $rowType === 'LAB' ? round($labHours, 2) : 0;
    $facultyLoad = $rowType === 'LAB'
        ? round($labHours * LAB_LOAD_MULTIPLIER, 2)
        : round($lecUnits, 2);

    $data[] = [
        'workload_id' => (int)$row['workload_id'],
        'offering_id' => (int)$row['offering_id'],
        'group_id' => $row['group_id'],
        'sub_code' => $row['sub_code'],
        'desc' => $row['desc'],
        'section' => $row['section'],
        'type' => $rowType,
        'days' => $days,
        'time' => $time,
        'room' => $row['room'] ?? '',
        'units' => $displayUnits,
        'lec' => $displayLec,
        'lab' => $displayLab,
        'faculty_load' => $facultyLoad
    ];
}

echo json_encode($data);
exit;
