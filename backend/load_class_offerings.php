<?php
session_start();
include 'db.php';

/* ===============================
   INPUTS
================================ */
$prospectus_id = $_POST['prospectus_id'] ?? '';
$ay_id         = $_POST['ay_id'] ?? '';
$semester      = $_POST['semester'] ?? '';

if ($prospectus_id === '' || $ay_id === '' || $semester === '') {
    echo "<tr><td colspan='10' class='text-center text-danger'>Missing filters.</td></tr>";
    exit;
}

/* ===============================
   QUERY
================================ */
$sql = "
SELECT
    o.offering_id,

    sec.section_name,

    sm.sub_code,
    sm.sub_description,

    cs.schedule_id,
    cs.faculty_id,
    cs.room_id,
    cs.days_json,
    cs.time_start,
    cs.time_end,

    r.room_name,
    TRIM(CONCAT(
        f.last_name, ', ',
        f.first_name, ' ',
        IF(f.middle_name <> '' AND f.middle_name IS NOT NULL,
           CONCAT(LEFT(f.middle_name,1),'. '), ''),
        IF(f.ext_name <> '' AND f.ext_name IS NOT NULL,
           CONCAT(f.ext_name,' '), '')
    )) AS faculty_name

FROM tbl_prospectus_offering o

INNER JOIN tbl_sections sec
    ON sec.section_id = o.section_id

INNER JOIN tbl_prospectus_subjects ps
    ON ps.ps_id = o.ps_id

INNER JOIN tbl_subject_masterlist sm
    ON sm.sub_id = ps.sub_id

LEFT JOIN tbl_class_schedule cs
    ON cs.offering_id = o.offering_id

LEFT JOIN tbl_rooms r
    ON r.room_id = cs.room_id

LEFT JOIN tbl_faculty f
    ON f.faculty_id = cs.faculty_id

WHERE o.prospectus_id = ?
  AND o.ay_id = ?
  AND o.semester = ?

ORDER BY sec.section_name ASC, sm.sub_code ASC
";

$stmt = $conn->prepare($sql);

/* 🔥 FIX: correct variables + correct types */
$stmt->bind_param(
    "iii",
    $prospectus_id,
    $ay_id,
    $semester
);

$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<tr><td colspan='10' class='text-center text-muted'>No class offerings found.</td></tr>";
    exit;
}

/* ===============================
   RENDER ROWS
================================ */
while ($row = $res->fetch_assoc()) {

    $isScheduled = !empty($row['schedule_id']);

    $statusBadge = $isScheduled
        ? "<span class='badge bg-success'>Scheduled</span>"
        : "<span class='badge bg-secondary'>Not Scheduled</span>";

    // Days
    $daysText = "<span class='text-muted'>—</span>";
    if (!empty($row['days_json'])) {
        $d = json_decode($row['days_json'], true);
        if (is_array($d)) $daysText = implode("", $d);
    }

    // Time
    $timeText = "<span class='text-muted'>—</span>";
    if (!empty($row['time_start']) && !empty($row['time_end'])) {
        $timeText = date("h:i A", strtotime($row['time_start'])) .
                    " - " .
                    date("h:i A", strtotime($row['time_end']));
    }

    $facultyText = $row['faculty_name'] ?: "<span class='text-muted'>—</span>";
    $roomText    = $row['room_name'] ?: "<span class='text-muted'>—</span>";

    $btnLabel = $isScheduled ? "Edit" : "Schedule";
    $btnClass = $isScheduled ? "btn-warning" : "btn-primary";

    echo "
    <tr>
        <td>{$row['section_name']}</td>
        <td>{$row['sub_code']}</td>
        <td>{$row['sub_description']}</td>
        <td class='text-center'><span class='text-muted'>—</span></td>
        <td>{$facultyText}</td>
        <td class='text-center'>{$daysText}</td>
        <td class='text-center'>{$timeText}</td>
        <td class='text-center'>{$roomText}</td>
        <td class='text-center'>{$statusBadge}</td>
        <td class='text-center'>
            <button
                class='btn {$btnClass} btn-sm btn-schedule'
                data-offering-id='{$row['offering_id']}'
                data-sub-code='{$row['sub_code']}'
                data-sub-desc='{$row['sub_description']}'
                data-section='{$row['section_name']}'
                data-faculty-id='{$row['faculty_id']}'
                data-room-id='{$row['room_id']}'
                data-time-start='{$row['time_start']}'
                data-time-end='{$row['time_end']}'
                data-days-json='".htmlspecialchars($row['days_json'], ENT_QUOTES)."'>
                {$btnLabel}
            </button>
        </td>
    </tr>
    ";
}
