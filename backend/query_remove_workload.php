<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) exit;

$ids = [];

if (isset($_POST['workload_ids'])) {
    $rawIds = $_POST['workload_ids'];

    if (!is_array($rawIds)) {
        $rawIds = explode(',', (string)$rawIds);
    }

    foreach ($rawIds as $rawId) {
        $id = intval($rawId);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
}

if (empty($ids)) {
    $id = intval($_POST['workload_id'] ?? 0);
    if ($id > 0) {
        $ids[$id] = $id;
    }
}

if (empty($ids)) exit;

$ids = array_values($ids);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$stmt = $conn->prepare("
    DELETE FROM tbl_faculty_workload_sched
    WHERE workload_id IN ($placeholders)
");

if (!$stmt) exit;

$params = [$types];
foreach ($ids as $index => $idValue) {
    $params[] = &$ids[$index];
}

call_user_func_array([$stmt, 'bind_param'], $params);
$stmt->execute();
$stmt->close();
exit;
