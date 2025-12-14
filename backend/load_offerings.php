<?php
session_start();
include 'db.php';

$prospectus_id = $_POST['prospectus_id'] ?? '';
$ay_id         = $_POST['ay_id'] ?? '';
$semester      = $_POST['semester'] ?? '';

if (!$prospectus_id || !$ay_id || !$semester) {
    echo "<tr><td colspan='5' class='text-center text-muted'>Missing filters.</td></tr>";
    exit;
}

$sql = "
SELECT
    o.offering_id,
    sec.section_name,
    sm.sub_code,
    sm.sub_description,
    ps.total_units,
    o.status
FROM tbl_prospectus_offering o
JOIN tbl_prospectus_subjects ps ON ps.ps_id = o.ps_id
JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_sections sec ON sec.section_id = o.section_id
WHERE o.prospectus_id = ?
  AND o.ay_id = ?
  AND o.semester = ?
ORDER BY sec.section_name, sm.sub_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $prospectus_id, $ay_id, $semester);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<tr><td colspan='5' class='text-center text-muted'>No offerings found.</td></tr>";
    exit;
}

while ($row = $res->fetch_assoc()) {

    $status = ucfirst($row['status']);
    $sc = strtoupper($row['sub_code']);
    $sd = strtoupper($row['sub_description']);
    echo "
    <tr>
        <td>{$row['section_name']}</td>
        <td>{$sc}</td>
        <td>{$sd}</td>
        <td class='text-center'>{$row['total_units']}</td>
        <td class='text-center'>
            <span class='badge bg-secondary'>{$status}</span>
        </td>
    </tr>
    ";
}
