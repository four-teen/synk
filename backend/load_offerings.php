<?php
//../backend/load_offerings.php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    echo "<tr><td colspan='7' class='text-center text-danger'>Unauthorized access.</td></tr>";
    exit;
}

$college_id = intval($_SESSION['college_id'] ?? 0);
if ($college_id <= 0) {
    echo "<tr><td colspan='7' class='text-center text-danger'>Missing college context.</td></tr>";
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo "<tr><td colspan='7' class='text-center text-danger'>CSRF validation failed.</td></tr>";
    exit;
}

$prospectus_id = $_POST['prospectus_id'] ?? '';
$ay_id         = $_POST['ay_id'] ?? '';
$semester      = $_POST['semester'] ?? '';

if (!$prospectus_id || !$ay_id || !$semester) {
    echo "<tr><td colspan='7' class='text-center text-muted'>Missing filters.</td></tr>";
    exit;
}

$sql = "
SELECT
    o.offering_id,
    sec.section_name,
    sm.sub_code,
    sm.sub_description,
    ps.lec_units,
    ps.lab_units,
    ps.total_units,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM tbl_class_schedule cs
            WHERE cs.offering_id = o.offering_id
        ) THEN 'scheduled'
        WHEN o.status = 'locked' THEN 'locked'
        ELSE 'pending'
    END AS display_status
FROM tbl_prospectus_offering o
JOIN tbl_prospectus_subjects ps ON ps.ps_id = o.ps_id
JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
JOIN tbl_program p ON p.program_id = o.program_id
LEFT JOIN tbl_sections sec ON sec.section_id = o.section_id
WHERE o.prospectus_id = ?
  AND o.ay_id = ?
  AND o.semester = ?
  AND p.college_id = ?
  AND o.section_id IS NOT NULL
ORDER BY sec.section_name, sm.sub_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $prospectus_id, $ay_id, $semester, $college_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<tr><td colspan='7' class='text-center text-muted'>No offerings found.</td></tr>";
    exit;
}

while ($row = $res->fetch_assoc()) {

    $statusRaw = strtolower(trim((string)($row['display_status'] ?? 'pending')));
    $status = ucfirst($statusRaw);
    $badgeClass = 'bg-secondary';
    if ($statusRaw === 'scheduled') {
        $badgeClass = 'bg-success';
    } elseif ($statusRaw === 'locked') {
        $badgeClass = 'bg-dark';
    }

    $sc = strtoupper($row['sub_code']);
    $sd = strtoupper($row['sub_description']);
    echo "
    <tr>
        <td>{$row['section_name']}</td>
        <td>{$sc}</td>
        <td>{$sd}</td>
        <td class='text-center'>{$row['lec_units']}</td>
        <td class='text-center'>{$row['lab_units']}</td>
        <td class='text-center'>{$row['total_units']}</td>
        <td class='text-center'>
            <span class='badge {$badgeClass}'>{$status}</span>
        </td>
    </tr>
    ";
}
