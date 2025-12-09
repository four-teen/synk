<?php
session_start();
    ob_start();
    include '../backend/db.php';

$pid = intval($_POST['prospectus_id'] ?? 0);
$ay  = $_POST['ay'] ?? '';
$sem = $_POST['semester'] ?? '';

if (!$pid || !$ay || !$sem) {
    echo "<tr><td colspan='5' class='text-center text-muted'>Missing filters.</td></tr>";
    exit;
}

$sql = "
    SELECT 
        o.*,
        subj.sub_code,
        subj.sub_description AS sub_desc,
        sec.section_name,
        ps.total_units AS units
    FROM tbl_prospectus_offering o
    LEFT JOIN tbl_prospectus_subjects ps 
        ON ps.ps_id = o.ps_id
    LEFT JOIN tbl_subject_masterlist subj
        ON subj.sub_id = ps.sub_id
    LEFT JOIN tbl_sections sec
        ON sec.section_id = o.section_id
    WHERE o.prospectus_id = ?
      AND o.ay = ?
      AND o.semester = ?
    ORDER BY sec.section_name, subj.sub_code
";



$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $pid, $ay, $sem);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<tr><td colspan='5' class='text-center text-muted'>No offerings found.</td></tr>";
    exit;
}

while ($r = $result->fetch_assoc()) {
    echo "
    <tr>
        <td>{$r['section_name']}</td>
        <td>{$r['sub_code']}</td>
        <td>{$r['sub_desc']}</td>
        <td>{$r['units']}</td>
        <td>{$r['status']}</td>
    </tr>";
}

