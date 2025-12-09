<?php
session_start();
ob_start();
include 'db.php';

$prospectus_id = $_POST['prospectus_id'] ?? '';
$ay = $_POST['ay'] ?? '';
$semester = $_POST['semester'] ?? '';

if (!$prospectus_id || !$ay || !$semester) {
    echo "<tr><td colspan='10' class='text-center text-danger'>Missing filters.</td></tr>";
    exit;
}

$sql = "
SELECT 
    o.offering_id,
    o.section_id,
    o.status,

    sm.sub_code,
    sm.sub_description,
    ps.total_units,

    cs.faculty_id,
    cs.room_id,
    cs.days_json,
    cs.time_start,
    cs.time_end,

    CONCAT(f.last_name, ', ', f.first_name) AS faculty_name,
    r.room_name,
    sec.section_name

FROM tbl_prospectus_offering o
LEFT JOIN tbl_prospectus_subjects ps 
       ON ps.ps_id = o.ps_id
LEFT JOIN tbl_subject_masterlist sm 
       ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_sections sec 
       ON sec.section_id = o.section_id
LEFT JOIN tbl_class_schedule cs 
       ON cs.offering_id = o.offering_id
LEFT JOIN tbl_faculty f
       ON f.faculty_id = cs.faculty_id
LEFT JOIN tbl_rooms r
       ON r.room_id = cs.room_id

WHERE o.prospectus_id = ?
  AND o.ay = ?
  AND o.semester = ?

ORDER BY sec.section_name, sm.sub_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $prospectus_id, $ay, $semester);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo "<tr><td colspan='10' class='text-center text-muted'>No offerings found.</td></tr>";
    exit;
}

while ($row = $res->fetch_assoc()) {

    $faculty = $row['faculty_name'] ?: "Unassigned";
    $room    = $row['room_name'] ?: "Unassigned";
    $days    = $row['days_json'] ? implode("", json_decode($row['days_json'])) : "—";
    $time    = ($row['time_start'] && $row['time_end']) ? 
                date("g:i A", strtotime($row['time_start'])) . " - " . date("g:i A", strtotime($row['time_end'])) 
                : "—";

    $status_badge = "<span class='badge bg-warning'>PLANNED</span>";
    if ($row['faculty_id']) {
        $status_badge = "<span class='badge bg-success'>SCHEDULED</span>";
    }

    echo "
    <tr>
        <td>{$row['section_name']}</td>
        <td>{$row['sub_code']}</td>
        <td>{$row['sub_description']}</td>
        <td>{$row['total_units']}</td>
        <td>$faculty</td>
        <td>$days</td>
        <td>$time</td>
        <td>$room</td>
        <td>$status_badge</td>

        <td>
            <button class='btn btn-sm btn-outline-primary btn-schedule'
                data-offering-id='{$row['offering_id']}'
                data-sub-code='{$row['sub_code']}'
                data-sub-desc=\"" . htmlspecialchars($row['sub_description']) . "\"
                data-section='{$row['section_name']}'
                data-faculty-id='{$row['faculty_id']}'
                data-room-id='{$row['room_id']}'
                data-days-json='{$row['days_json']}'
                data-time-start='{$row['time_start']}'
                data-time-end='{$row['time_end']}'
            >
                <i class='bx bx-calendar'></i> Schedule
            </button>
        </td>
    </tr>
    ";
}
?>
