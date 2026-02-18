<?php
session_start();
ob_start();
include 'db.php';

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

// Single round-trip aggregate query to reduce DB overhead per dashboard load.
$sql = "
    SELECT
        (SELECT COUNT(*)
         FROM tbl_program
         WHERE college_id = ? AND status = 'active') AS programs,

        (SELECT COUNT(*)
         FROM tbl_college_faculty
         WHERE college_id = ? AND status = 'active') AS faculty,

        (SELECT COUNT(ps.ps_id)
         FROM tbl_prospectus_subjects ps
         INNER JOIN tbl_prospectus_year_sem pys ON ps.pys_id = pys.pys_id
         INNER JOIN tbl_prospectus_header ph ON pys.prospectus_id = ph.prospectus_id
         INNER JOIN tbl_program p ON ph.program_id = p.program_id
         WHERE p.college_id = ?) AS prospectus_items,

        (SELECT COUNT(*)
         FROM tbl_prospectus_offering o
         INNER JOIN tbl_program p ON p.program_id = o.program_id
         LEFT JOIN tbl_class_schedule cs ON cs.offering_id = o.offering_id
         WHERE p.college_id = ?
           AND cs.schedule_id IS NULL) AS unscheduled_classes
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $college_id, $college_id, $college_id, $college_id);
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
