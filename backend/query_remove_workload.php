<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) exit;

$id = intval($_POST['workload_id'] ?? 0);
if (!$id) exit;

$stmt = $conn->prepare("
    DELETE FROM tbl_faculty_workload_sched
    WHERE workload_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
exit;
