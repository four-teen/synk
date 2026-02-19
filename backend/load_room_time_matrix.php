<?php
session_start();
include 'db.php';

/*
 EXPECTED POST:
 - prospectus_id
 - ay_id
 - semester
 - college_id
*/

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    echo "<div class='text-danger text-center'>Unauthorized access.</div>";
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo "<div class='text-danger text-center'>CSRF validation failed.</div>";
    exit;
}

$college_id = (int)($_SESSION['college_id'] ?? 0);

$prospectus_id = isset($_POST['prospectus_id']) ? (int)$_POST['prospectus_id'] : 0;
$ay_id         = isset($_POST['ay_id']) ? (int)$_POST['ay_id'] : 0;
$semester      = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;

/* ---------- VALIDATION ---------- */
if (!$prospectus_id || !$ay_id || !$semester) {
    echo "<div class='text-danger text-center'>Missing filters.</div>";
    exit;
}

if ($college_id <= 0) {
    echo "<div class='text-danger text-center'>Invalid college context.</div>";
    exit;
}

/* =========================================================
   1. BUILD 30-MINUTE TIME SLOTS (07:30 AM – 05:30 PM)
========================================================= */
$timeSlots = [];
$start = strtotime("07:30");
$end   = strtotime("17:30");

while ($start < $end) {
    $slotStart = date("H:i:s", $start);
    $slotEnd   = date("H:i:s", strtotime("+30 minutes", $start));
    $timeSlots[] = [$slotStart, $slotEnd];
    $start = strtotime("+30 minutes", $start);
}

/* =========================================================
   2. LOAD TERM-ACCESSIBLE ROOMS
========================================================= */
$rooms = [];

$hasAccessTable = $conn->query("SHOW TABLES LIKE 'tbl_room_college_access'");
if (!$hasAccessTable || $hasAccessTable->num_rows === 0) {
    echo "<div class='text-muted text-center'>Room access table is missing.</div>";
    exit;
}

$roomsStmt = $conn->prepare("
    SELECT DISTINCT r.room_id, r.room_name
    FROM tbl_room_college_access acc
    INNER JOIN tbl_rooms r ON r.room_id = acc.room_id
    WHERE acc.college_id = ?
      AND acc.ay_id = ?
      AND acc.semester = ?
      AND r.status = 'active'
    ORDER BY r.room_name
");

$roomsStmt->bind_param("iii", $college_id, $ay_id, $semester);
$roomsStmt->execute();
$roomsQ = $roomsStmt->get_result();

while ($r = $roomsQ->fetch_assoc()) {
    $rooms[$r['room_id']] = $r['room_name'];
}

if (empty($rooms)) {
    echo "<div class='text-muted text-center'>No active rooms found.</div>";
    exit;
}

/* =========================================================
   3. LOAD SCHEDULED CLASSES
========================================================= */
$schedStmt = $conn->prepare("
    SELECT
        cs.room_id,
        cs.time_start,
        cs.time_end,
        cs.days_json,
        sm.sub_code,
        sec.section_name
    FROM tbl_class_schedule cs
    JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
    JOIN tbl_prospectus_subjects ps ON ps.ps_id = o.ps_id
    JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
    JOIN tbl_sections sec ON sec.section_id = o.section_id
    WHERE o.prospectus_id = ?
      AND o.ay_id = ?
      AND o.semester = ?
");

$schedStmt->bind_param("iii", $prospectus_id, $ay_id, $semester);
$schedStmt->execute();
$schedRes = $schedStmt->get_result();

/* ---------- INDEX SCHEDULES BY ROOM ---------- */
$schedules = [];
while ($row = $schedRes->fetch_assoc()) {
    $row['days'] = json_decode($row['days_json'], true);
    if (!is_array($row['days'])) {
        $row['days'] = [];
    }
    $schedules[$row['room_id']][] = $row;
}

/* =========================================================
   4. HELPER FUNCTIONS
========================================================= */
function timeLabel($t) {
    return date("h:i A", strtotime($t));
}

function overlaps($aStart, $aEnd, $bStart, $bEnd) {
    return ($aStart < $bEnd && $aEnd > $bStart);
}

/* =========================================================
   5. RENDER MATRIX
========================================================= */
echo "<div class='table-responsive'>";
echo "<table class='table table-bordered matrix-table'>";

/* ---------- HEADER ---------- */
echo "<thead><tr>";
echo "<th class='matrix-room'>ROOM</th>";

foreach ($timeSlots as $slot) {
    echo "<th class='text-center small'>";
    echo timeLabel($slot[0]) . "<br>–<br>" . timeLabel($slot[1]);
    echo "</th>";
}
echo "</tr></thead>";

/* ---------- BODY ---------- */
echo "<tbody>";

foreach ($rooms as $room_id => $room_name) {

    echo "<tr>";
    echo "<td class='fw-bold matrix-room'>{$room_name}</td>";

    $slotIndex = 0;
    $slotCount = count($timeSlots);

    while ($slotIndex < $slotCount) {

        $slotStart = $timeSlots[$slotIndex][0];
        $slotEnd   = $timeSlots[$slotIndex][1];
        $matched   = null;

        if (isset($schedules[$room_id])) {
            foreach ($schedules[$room_id] as $sc) {
                if (overlaps($slotStart, $slotEnd, $sc['time_start'], $sc['time_end'])) {
                    $matched = $sc;
                    break;
                }
            }
        }

        /* ---------- VACANT ---------- */
        if ($matched === null) {
            echo "<td><div class='matrix-cell matrix-vacant'>—</div></td>";
            $slotIndex++;
            continue;
        }

        /* ---------- OCCUPIED → MERGE ---------- */
        $colspan = 1;
        for ($i = $slotIndex + 1; $i < $slotCount; $i++) {
            if (overlaps(
                $timeSlots[$i][0],
                $timeSlots[$i][1],
                $matched['time_start'],
                $matched['time_end']
            )) {
                $colspan++;
            } else {
                break;
            }
        }

        $days = implode("", $matched['days']);

        echo "
            <td colspan='{$colspan}'>
                <div class='matrix-cell matrix-occupied'>
                    <strong>{$matched['sub_code']}</strong><br>
                    <small>{$matched['section_name']} {$days}</small>
                </div>
            </td>
        ";

        $slotIndex += $colspan;
    }

    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

/* ---------- LEGEND ---------- */
echo "
<div class='mt-3 small text-muted'>
    <span class='badge bg-secondary me-2'>Vacant</span>
    <span class='badge bg-primary'>Occupied</span>
</div>
";

echo "</div>";
