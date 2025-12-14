<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

/* ===============================
   SECURITY
================================ */
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

/* ===============================
   INPUTS
================================ */
$faculty_id = intval($_POST['faculty_id'] ?? 0);
$ay_id      = intval($_POST['ay_id'] ?? 0);
$semester   = intval($_POST['semester'] ?? 0); // 1,2,3
$schedules  = $_POST['schedule_ids'] ?? [];

if (
    !$faculty_id ||
    !$ay_id ||
    !$semester ||
    !is_array($schedules) ||
    count($schedules) === 0
) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input'
    ]);
    exit;
}

/* ===============================
   PREPARE INSERT
================================ */
$sql = "
    INSERT IGNORE INTO tbl_faculty_workload_sched
        (schedule_id, faculty_id, ay_id, semester)
    VALUES (?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

$inserted = 0;

foreach ($schedules as $schedule_id) {
    $sid = intval($schedule_id);
    if ($sid <= 0) continue;

    $stmt->bind_param(
        "iiii",
        $sid,
        $faculty_id,
        $ay_id,
        $semester
    );

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $inserted++;
    }
}

$stmt->close();

/* ===============================
   RESPONSE
================================ */
echo json_encode([
    'status'   => 'success',
    'inserted' => $inserted
]);
exit;
