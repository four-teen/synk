<?php
/*
|--------------------------------------------------------------------------
| GET CURRENT ACADEMIC SETTINGS
|--------------------------------------------------------------------------
| Purpose:
| - Returns the currently active academic year & semester
| - Used by Academic Settings UI and dashboards
|--------------------------------------------------------------------------
*/

session_start();
include 'db.php';
header('Content-Type: application/json');

$sql = "
    SELECT 
        s.current_ay_id,
        ay.ay AS academic_year,
        s.current_semester
    FROM tbl_academic_settings s
    JOIN tbl_academic_years ay ON ay.ay_id = s.current_ay_id
    LIMIT 1
";

$res = mysqli_query($conn, $sql);

if ($row = mysqli_fetch_assoc($res)) {

    $semester_map = [
        1 => '1st Semester',
        2 => '2nd Semester',
        3 => 'Midyear'
    ];

    echo json_encode([
        'status' => 'success',
        'academic_year' => $row['academic_year'],
        'semester' => $semester_map[$row['current_semester']] ?? 'Unknown'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Academic settings not found.'
    ]);
}
