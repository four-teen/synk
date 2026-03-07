<?php
session_start();
ob_start();
include 'db.php';
require_once __DIR__ . '/academic_term_helper.php';
require_once __DIR__ . '/offering_scope_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['college_id'])) {
    echo json_encode([
        'programs' => 0,
        'faculty' => 0,
        'prospectus_items' => 0,
        'unscheduled_classes' => 0
    ]);
    exit;
}

$college_id = (int)$_SESSION['college_id'];
$currentTerm = synk_fetch_current_academic_term($conn);
$currentAyId = (int)$currentTerm['ay_id'];
$currentSemester = (int)$currentTerm['semester'];

if ($currentAyId <= 0 || $currentSemester <= 0) {
    echo json_encode([
        'programs' => 0,
        'faculty' => 0,
        'prospectus_items' => 0,
        'unscheduled_classes' => 0
    ]);
    exit;
}

$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

// Single round-trip aggregate query to reduce DB overhead per dashboard load.
$sql = "
    SELECT
        (SELECT COUNT(DISTINCT o.program_id)
         FROM tbl_prospectus_offering o
         {$liveOfferingJoins}
         INNER JOIN tbl_program p ON p.program_id = o.program_id
         WHERE p.college_id = ?
           AND p.status = 'active'
           AND o.ay_id = ?
           AND o.semester = ?) AS programs,

        (SELECT COUNT(DISTINCT fw.faculty_id)
         FROM tbl_faculty_workload_sched fw
         INNER JOIN tbl_class_schedule cs ON cs.schedule_id = fw.schedule_id
         INNER JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
         {$liveOfferingJoins}
         INNER JOIN tbl_program p ON p.program_id = o.program_id
         INNER JOIN tbl_faculty f ON f.faculty_id = fw.faculty_id
         WHERE p.college_id = ?
           AND p.status = 'active'
           AND f.status = 'active'
           AND o.ay_id = ?
           AND o.semester = ?
           AND fw.ay_id = ?
           AND fw.semester = ?) AS faculty,

        (SELECT COUNT(*)
         FROM tbl_prospectus_offering o
         {$liveOfferingJoins}
         INNER JOIN tbl_program p ON p.program_id = o.program_id
         WHERE p.college_id = ?
           AND p.status = 'active'
           AND o.ay_id = ?
           AND o.semester = ?) AS prospectus_items,

        (SELECT COUNT(*)
         FROM tbl_prospectus_offering o
         {$liveOfferingJoins}
         INNER JOIN tbl_program p ON p.program_id = o.program_id
         LEFT JOIN tbl_class_schedule cs ON cs.offering_id = o.offering_id
         WHERE p.college_id = ?
           AND p.status = 'active'
           AND o.ay_id = ?
           AND o.semester = ?
           AND cs.schedule_id IS NULL) AS unscheduled_classes
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iiiiiiiiiiiiii",
    $college_id,
    $currentAyId,
    $currentSemester,
    $college_id,
    $currentAyId,
    $currentSemester,
    $currentAyId,
    $currentSemester,
    $college_id,
    $currentAyId,
    $currentSemester,
    $college_id,
    $currentAyId,
    $currentSemester
);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'programs' => (int)($row['programs'] ?? 0),
    'faculty' => (int)($row['faculty'] ?? 0),
    'prospectus_items' => (int)($row['prospectus_items'] ?? 0),
    'unscheduled_classes' => (int)($row['unscheduled_classes'] ?? 0)
]);
exit;
?>
