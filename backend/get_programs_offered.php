<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| SECURITY CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| GET CURRENT ACADEMIC SETTINGS
|--------------------------------------------------------------------------
*/
$currentAyId = null;
$currentSem  = null;

$termRes = $conn->query("
    SELECT current_ay_id, current_semester
    FROM tbl_academic_settings
    LIMIT 1
");

if ($termRes && $termRes->num_rows > 0) {
    $termRow     = $termRes->fetch_assoc();
    $currentAyId = (int)$termRow['current_ay_id'];
    $currentSem  = (int)$termRow['current_semester'];
}

if (!$currentAyId || !$currentSem) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Academic term not set'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| PROGRAMS OFFERED QUERY (CANONICAL)
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        p.program_code,
        p.program_name,
        p.major,
        col.college_name,
        camp.campus_name,
        COUNT(DISTINCT s.section_id) AS section_count
    FROM tbl_prospectus_offering po

    INNER JOIN tbl_sections s
        ON s.section_id = po.section_id

    INNER JOIN tbl_program p
        ON p.program_id = s.program_id

    INNER JOIN tbl_college col
        ON col.college_id = p.college_id

    INNER JOIN tbl_campus camp
        ON camp.campus_id = col.campus_id

    WHERE po.ay_id = ?
      AND po.semester = ?
      AND p.status = 'active'

    GROUP BY
        p.program_code,
        p.program_name,
        p.major,
        col.college_name,
        camp.campus_name

    ORDER BY
        camp.campus_name ASC,
        col.college_name ASC,
        p.program_code ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $currentAyId, $currentSem);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/
echo json_encode([
    'status' => 'success',
    'data'   => $data
]);
