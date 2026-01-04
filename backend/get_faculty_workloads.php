<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

/*
|=====================================================
| GET FACULTY WORKLOADS (SCHEDULED CLASSES)
|-----------------------------------------------------
| RULES:
| - Current academic term only
| - One row = one class schedule
| - Include unassigned faculty
| - Include no room (TBA)
| - Sorted by time_start
|=====================================================
*/

$ay_id    = intval($_GET['ay_id'] ?? 0);
$semester = intval($_GET['semester'] ?? 0);

if (!$ay_id || !$semester) {
    echo json_encode(["status" => "error", "message" => "Invalid academic term"]);
    exit;
}

$sql = "
SELECT
  sm.sub_code,
  s.section_name,
  p.program_code,
  p.program_name,
  col.college_name,
  camp.campus_name,
  cs.days_json,
  cs.time_start,
  cs.time_end,
  r.room_name,
  CONCAT(f.last_name, ', ', f.first_name) AS faculty_name
FROM tbl_class_schedule cs

INNER JOIN tbl_prospectus_offering po
  ON po.offering_id = cs.offering_id

INNER JOIN tbl_sections s
  ON s.section_id = po.section_id

INNER JOIN tbl_program p
  ON p.program_id = s.program_id

INNER JOIN tbl_college col
  ON col.college_id = p.college_id

INNER JOIN tbl_campus camp
  ON camp.campus_id = col.campus_id

INNER JOIN tbl_prospectus_subjects ps
  ON ps.ps_id = po.ps_id

INNER JOIN tbl_subject_masterlist sm
  ON sm.sub_id = ps.sub_id

LEFT JOIN tbl_rooms r
  ON r.room_id = cs.room_id

LEFT JOIN tbl_faculty_workload_sched fws
  ON fws.schedule_id = cs.schedule_id
  AND fws.ay_id = ?
  AND fws.semester = ?

LEFT JOIN tbl_faculty f
  ON f.faculty_id = fws.faculty_id

WHERE po.ay_id = ?
  AND po.semester = ?

ORDER BY cs.time_start ASC
";


$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $ay_id, $semester, $ay_id, $semester);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "status" => "success",
    "data"   => $data
]);
