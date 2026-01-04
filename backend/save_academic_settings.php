<?php
/*
|--------------------------------------------------------------------------
| SAVE ACADEMIC SETTINGS
|--------------------------------------------------------------------------
| Purpose:
| - Overwrites the SINGLE academic settings row
| - Sets the current Academic Year and Semester globally
|
| Expected POST:
| - ay_id        (int)
| - semester     (int: 1,2,3)
|
| Behavior:
| - UPDATE existing row
| - No INSERT
|--------------------------------------------------------------------------
*/

session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$ay_id    = intval($_POST['ay_id'] ?? 0);
$semester = intval($_POST['semester'] ?? 0);
$user_id  = intval($_SESSION['user_id']);

if ($ay_id <= 0 || !in_array($semester, [1,2,3])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid academic year or semester.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| OVERWRITE SETTINGS (SINGLE ROW)
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    UPDATE tbl_academic_settings
    SET current_ay_id = ?,
        current_semester = ?,
        updated_by = ?,
        date_updated = NOW()
    LIMIT 1
");
$stmt->bind_param("iii", $ay_id, $semester, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Academic settings updated successfully.'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update academic settings.'
    ]);
}

$stmt->close();
