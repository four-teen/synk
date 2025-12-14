<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['college_id'])) {
    echo json_encode([]);
    exit;
}

$college_id = (int)$_SESSION['college_id'];

/*
 PURPOSE:
 - Get total teaching units per faculty
 - Filtered by college
 - Current AY only (latest active)
*/

$sql = "
SELECT
    f.faculty_id,
    CONCAT(f.last_name) AS faculty_name,
    SUM(ps.total_units) AS total_units
FROM tbl_faculty_workload_sched fw
JOIN tbl_faculty f
    ON f.faculty_id = fw.faculty_id
JOIN tbl_class_schedule cs
    ON cs.schedule_id = fw.schedule_id
JOIN tbl_prospectus_offering o
    ON o.offering_id = cs.offering_id
JOIN tbl_prospectus_subjects ps
    ON ps.ps_id = o.ps_id
JOIN tbl_college_faculty cf
    ON cf.faculty_id = f.faculty_id
WHERE
    cf.college_id = ?
    AND fw.ay_id = (
        SELECT ay_id 
        FROM tbl_academic_years 
        WHERE status = 'active'
        LIMIT 1
    )
GROUP BY f.faculty_id
ORDER BY faculty_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $college_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = [
        "faculty" => $row['faculty_name'],
        "units"   => (int)$row['total_units']
    ];
}

echo json_encode($data);
