<?php
session_start();
ob_start();
include 'db.php';

$college_id = $_SESSION['college_id'];
$response = [];

/* --------------------------------------------------
   1️⃣ COUNT PROGRAMS (per college)
-------------------------------------------------- */
$sql = "SELECT COUNT(*) AS total 
        FROM tbl_program 
        WHERE college_id = ? AND status='active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $college_id);
$stmt->execute();
$response['programs'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();


/* --------------------------------------------------
   2️⃣ COUNT ASSIGNED FACULTY
-------------------------------------------------- */
$sql = "SELECT COUNT(*) AS total 
        FROM tbl_college_faculty 
        WHERE college_id = ? AND status='active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $college_id);
$stmt->execute();
$response['faculty'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();


/* --------------------------------------------------
   3️⃣ COUNT PROSPECTUS SUBJECT ITEMS
   (Uses: tbl_prospectus_subjects, tbl_prospectus_year_sem, tbl_prospectus_header)
-------------------------------------------------- */
$sql = "
    SELECT COUNT(ps.ps_id) AS total
    FROM tbl_prospectus_subjects ps
    INNER JOIN tbl_prospectus_year_sem pys ON ps.pys_id = pys.pys_id
    INNER JOIN tbl_prospectus_header ph ON pys.prospectus_id = ph.prospectus_id
    INNER JOIN tbl_program p ON ph.program_id = p.program_id
    WHERE p.college_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $college_id);
$stmt->execute();
$response['prospectus_items'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();


/* --------------------------------------------------
   4️⃣ COUNT UNSCHEDULED CLASSES
   (No schedule table exists yet → ALWAYS 0)
-------------------------------------------------- */
$response['unscheduled_classes'] = 0;


/* --------------------------------------------------
   RETURN JSON
-------------------------------------------------- */
echo json_encode($response);
exit;
?>
