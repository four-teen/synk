<?php
//../backend/load_offerings.php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schedule_merge_helper.php';

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

$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
$scheduledOfferingJoin = synk_schedule_merge_scheduled_offering_join_sql($conn, 'sched', 'o');

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
        WHEN sched.offering_id IS NOT NULL THEN 'scheduled'
        WHEN o.status = 'locked' THEN 'locked'
        ELSE 'pending'
    END AS display_status
FROM tbl_prospectus_offering o
{$liveOfferingJoins}
{$scheduledOfferingJoin}
JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
JOIN tbl_program p ON p.program_id = o.program_id
WHERE o.prospectus_id = ?
  AND o.ay_id = ?
  AND o.semester = ?
  AND p.college_id = ?
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

    $sectionName = htmlspecialchars((string)$row['section_name'], ENT_QUOTES, 'UTF-8');
    $sc = htmlspecialchars(strtoupper((string)$row['sub_code']), ENT_QUOTES, 'UTF-8');
    $sd = htmlspecialchars(strtoupper((string)$row['sub_description']), ENT_QUOTES, 'UTF-8');
    $statusLabel = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
    echo "
    <tr>
        <td>{$sectionName}</td>
        <td>{$sc}</td>
        <td>{$sd}</td>
        <td class='text-center'>{$row['lec_units']}</td>
        <td class='text-center'>{$row['lab_units']}</td>
        <td class='text-center'>{$row['total_units']}</td>
        <td class='text-center'>
            <span class='badge {$badgeClass}'>{$statusLabel}</span>
        </td>
    </tr>
    ";
}
