<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$faculty_id = intval($_POST['faculty_id'] ?? 0);
$ay_id      = intval($_POST['ay_id'] ?? 0);
$semester   = intval($_POST['semester'] ?? 0);

if (!$faculty_id || !$ay_id || !$semester) {
    echo json_encode([]);
    exit;
}

$sql = "
SELECT
    fw.workload_id,
    sm.sub_code,
    sm.sub_description,
    sec.section_name,
    cs.days_json,
    cs.time_start,
    cs.time_end,
    r.room_code,
    ps.total_units
FROM tbl_faculty_workload_sched fw
JOIN tbl_class_schedule cs ON cs.schedule_id = fw.schedule_id
JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
JOIN tbl_sections sec ON sec.section_id = o.section_id
JOIN tbl_prospectus_subjects ps ON ps.ps_id = o.ps_id
JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_rooms r ON r.room_id = cs.room_id
WHERE
    fw.faculty_id = ?
AND fw.ay_id = ?
AND fw.semester = ?
ORDER BY sec.section_name, sm.sub_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $faculty_id, $ay_id, $semester);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $days = json_decode($row['days_json'], true) ?: [];

    $data[] = [
        'workload_id' => $row['workload_id'],
        'sub_code'    => $row['sub_code'],
        'desc'        => $row['sub_description'],
        'section'     => $row['section_name'],
        'days'        => implode(", ", $days),
        'time'        => date("g:iA", strtotime($row['time_start'])) .
                         "–" .
                         date("g:iA", strtotime($row['time_end'])),
        'room'        => $row['room_code'],
        'units'       => $row['total_units']
    ];
}

echo json_encode($data);
exit;
