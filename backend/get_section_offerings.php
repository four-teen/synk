<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

/* =====================================================
   GET SUBJECTS OFFERED FOR A SECTION (LEVEL 3 DRILLDOWN)
   -----------------------------------------------------
   LOGGING ENABLED (log_section_offerings.txt)
   Purpose:
   - Verify section_id / ay_id / semester received
   - Verify row count returned
   - Catch SQL/prepare/execute errors
===================================================== */


/* ------------------------------
   READ PARAMETERS
-------------------------------- */
$section_id = intval($_GET['section_id'] ?? 0);
$ay_id      = intval($_GET['ay_id'] ?? 0);
$semester   = intval($_GET['semester'] ?? 0);


if (!$section_id || !$ay_id || !$semester) {

    echo json_encode(["status" => "error", "message" => "Invalid parameters"]);
    exit;
}

/* ------------------------------
   MAIN QUERY
-------------------------------- */
$sql = "
SELECT
    sm.sub_code,
    sm.sub_description,
    ps.total_units
FROM tbl_prospectus_offering po

INNER JOIN tbl_sections s
    ON s.section_id = po.section_id

INNER JOIN tbl_prospectus_subjects ps
    ON ps.ps_id = po.ps_id

INNER JOIN tbl_subject_masterlist sm
    ON sm.sub_id = ps.sub_id

WHERE po.section_id = ?
  AND po.program_id = s.program_id      -- 🔒 PROGRAM GUARD
  AND po.year_level = s.year_level      -- 🔒 YEAR LEVEL GUARD
  AND po.ay_id = ?
  AND po.semester = ?

ORDER BY ps.sort_order, sm.sub_code;

";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status"  => "error",
        "message" => "Prepare failed",
        "debug"   => $conn->error
    ]);
    exit;
}

/* ------------------------------
   EXECUTE
-------------------------------- */
$stmt->bind_param("iii", $section_id, $ay_id, $semester);

if (!$stmt->execute()) {
    echo json_encode([
        "status"  => "error",
        "message" => "Execute failed",
        "debug"   => $stmt->error
    ]);
    exit;
}

$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "status" => "success",
    "data"   => $data
]);
