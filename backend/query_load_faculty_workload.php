<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['rows' => [], 'meta' => []]);
    exit;
}

$faculty_id = (int)($_POST['faculty_id'] ?? 0);
$ay_id = (int)($_POST['ay_id'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);
$college_id = (int)($_SESSION['college_id'] ?? 0);

if ($faculty_id <= 0 || $ay_id <= 0 || $semester <= 0) {
    echo json_encode(['rows' => [], 'meta' => []]);
    exit;
}

define('LAB_LOAD_MULTIPLIER', 0.75);
define('LAB_CONTACT_HOURS_PER_UNIT', 3.0);

function workload_title_case(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = strtolower($value);
    $value = preg_replace_callback('/(^|[\s,\/-])([a-z])/', static function ($matches) {
        return $matches[1] . strtoupper($matches[2]);
    }, $value);

    return (string)$value;
}

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
    sec.full_section     AS full_section,
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

$rows = [];
$preparations = [];

while ($row = $res->fetch_assoc()) {
    $days_arr = json_decode((string)$row['days_json'], true);
    if (!is_array($days_arr)) {
        $days_arr = [];
    }

    $rowType = strtoupper(trim((string)($row['type'] ?? 'LEC')));
    $lecUnits = (float)($row['lec_units'] ?? 0);
    $labValue = (float)($row['lab_units'] ?? 0);
    $totalUnits = (float)($row['total_units'] ?? 0);

    $labIsCredit = ($labValue > 0) && (abs(($lecUnits + $labValue) - $totalUnits) < 0.0001);
    $labHours = $labIsCredit ? ($labValue * LAB_CONTACT_HOURS_PER_UNIT) : $labValue;
    $subjectUnits = $totalUnits > 0
        ? $totalUnits
        : ($lecUnits + ($labIsCredit ? $labValue : 0));
    $subjectLoad = $lecUnits + ($labHours * LAB_LOAD_MULTIPLIER);

    $fullSection = trim((string)($row['full_section'] ?? ''));
    if ($fullSection === '') {
        $fullSection = trim((string)($row['section'] ?? ''));
    }

    $rows[] = [
        'workload_id' => (int)$row['workload_id'],
        'offering_id' => (int)$row['offering_id'],
        'group_id' => (int)($row['group_id'] ?? 0),
        'sub_code' => (string)$row['sub_code'],
        'desc' => (string)$row['desc'],
        'course' => $fullSection,
        'section' => (string)$row['section'],
        'type' => $rowType,
        'days' => implode(", ", $days_arr),
        'time' => date("g:iA", strtotime((string)$row['time_start'])) . "-" .
                  date("g:iA", strtotime((string)$row['time_end'])),
        'room' => (string)($row['room'] ?? ''),
        'units' => round($subjectUnits, 2),
        'lec' => round($lecUnits, 2),
        'lab' => round($labHours, 2),
        'faculty_load' => round($subjectLoad, 2),
        'student_count' => 0
    ];

    $preparationKey = trim((string)$row['sub_code']);
    if ($preparationKey !== '') {
        $preparations[$preparationKey] = true;
    }
}

$stmt->close();

$designationName = '';
$designationUnits = 0.0;

if ($college_id > 0) {
    $designationSql = "
        SELECT
            d.designation_name,
            d.designation_units
        FROM tbl_college_faculty cf
        INNER JOIN tbl_faculty f
            ON f.faculty_id = cf.faculty_id
        LEFT JOIN tbl_designation d
            ON d.designation_id = f.designation_id
           AND d.status = 'active'
        WHERE cf.college_id = ?
          AND cf.faculty_id = ?
          AND cf.ay_id = ?
          AND cf.semester = ?
          AND cf.status = 'active'
        ORDER BY cf.college_faculty_id DESC
        LIMIT 1
    ";

    $designationStmt = $conn->prepare($designationSql);
    $designationStmt->bind_param("iiii", $college_id, $faculty_id, $ay_id, $semester);
    $designationStmt->execute();
    $designationRes = $designationStmt->get_result();
    $designationRow = $designationRes->fetch_assoc();
    $designationStmt->close();

    if (is_array($designationRow)) {
        $designationName = trim((string)($designationRow['designation_name'] ?? ''));
        $designationUnits = (float)($designationRow['designation_units'] ?? 0);
    }
}

echo json_encode([
    'rows' => $rows,
    'meta' => [
        'designation_name' => $designationName,
        'designation_label' => workload_title_case($designationName),
        'designation_units' => round($designationUnits, 2),
        'total_preparations' => count($preparations)
    ]
]);
exit;
