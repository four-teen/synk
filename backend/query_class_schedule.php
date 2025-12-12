<?php
// backend/query_class_schedule.php
session_start();
include 'db.php';

header('Content-Type: application/json');

function respond($status, $message = "", $extra = []) {
    echo json_encode(array_merge([
        "status"  => $status,
        "message" => $message
    ], $extra));
    exit;
}

/* ============================
   VALIDATE REQUEST
============================ */
if (!isset($_POST['save_schedule'])) {
    respond("error", "Invalid request.");
}

$offering_id = intval($_POST['offering_id'] ?? 0);

/**
 * FACULTY IS OPTIONAL
 * - If not provided → NULL
 */
$faculty_id = isset($_POST['faculty_id']) && $_POST['faculty_id'] !== ''
    ? intval($_POST['faculty_id'])
    : null;

$room_id    = intval($_POST['room_id'] ?? 0);
$time_start = $_POST['time_start'] ?? '';
$time_end   = $_POST['time_end'] ?? '';
$days_json  = $_POST['days_json'] ?? '';

/* REQUIRED: offering, room, time, days */
if (!$offering_id || !$room_id || !$time_start || !$time_end || !$days_json) {
    respond("error", "Missing schedule fields.");
}

if ($time_end <= $time_start) {
    respond("error", "End time must be later than start time.");
}

$newDays = json_decode($days_json, true);
if (!is_array($newDays) || count($newDays) === 0) {
    respond("error", "Invalid days selection.");
}

/* ============================
   1. LOAD OFFERING CONTEXT
============================ */
$ctxSql = "
    SELECT
        o.offering_id,
        o.section_id,
        o.ay_id,
        o.semester,
        sec.section_name
    FROM tbl_prospectus_offering o
    JOIN tbl_sections sec ON sec.section_id = o.section_id
    WHERE o.offering_id = ?
    LIMIT 1
";
$ctxStmt = $conn->prepare($ctxSql);
$ctxStmt->bind_param("i", $offering_id);
$ctxStmt->execute();
$ctxRes = $ctxStmt->get_result();

if ($ctxRes->num_rows === 0) {
    respond("error", "Offering not found.");
}

$ctx = $ctxRes->fetch_assoc();

$section_id   = $ctx['section_id'];
$ay_id        = $ctx['ay_id'];
$semester     = $ctx['semester'];
$section_name = $ctx['section_name'];

/* ============================
   2. CHECK IF SCHEDULE EXISTS
============================ */
$existingSql = "
    SELECT schedule_id
    FROM tbl_class_schedule
    WHERE offering_id = ?
    LIMIT 1
";
$existingStmt = $conn->prepare($existingSql);
$existingStmt->bind_param("i", $offering_id);
$existingStmt->execute();
$existingRes = $existingStmt->get_result();

$current_schedule_id = null;
if ($existingRes->num_rows > 0) {
    $current_schedule_id = $existingRes->fetch_assoc()['schedule_id'];
}

/* ============================
   HELPERS
============================ */
function time_fmt($t) {
    return date("h:i A", strtotime($t));
}
function days_fmt($arr) {
    return is_array($arr) ? implode("", $arr) : "";
}
function days_overlap($a, $b) {
    return is_array($a) && is_array($b) && count(array_intersect($a, $b)) > 0;
}

/* ============================
   3. LOAD CONFLICT CANDIDATES
============================ */
$candSql = "
    SELECT
        cs.schedule_id,
        cs.faculty_id,
        cs.room_id,
        cs.days_json,
        cs.time_start,
        cs.time_end,
        o.section_id,
        sec.section_name
    FROM tbl_class_schedule cs
    JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
    JOIN tbl_sections sec ON sec.section_id = o.section_id
    WHERE o.ay_id = ?
      AND o.semester = ?
      AND cs.time_start < ?
      AND cs.time_end > ?
";

$params = [$ay_id, $semester, $time_end, $time_start];
$types  = "iiss";

if ($current_schedule_id !== null) {
    $candSql .= " AND cs.schedule_id <> ? ";
    $params[] = $current_schedule_id;
    $types   .= "i";
}

$cStmt = $conn->prepare($candSql);
$cStmt->bind_param($types, ...$params);
$cStmt->execute();
$cRes = $cStmt->get_result();

/* ============================
   4. CONFLICT DETECTION
============================ */
while ($x = $cRes->fetch_assoc()) {

    $xDays = json_decode($x['days_json'], true);
    if (!days_overlap($newDays, $xDays)) continue;

    $xWhen = days_fmt($xDays) . " " .
             time_fmt($x['time_start']) . " - " .
             time_fmt($x['time_end']);

    // ROOM conflict
    if ($x['room_id'] == $room_id) {
        respond("conflict", "
            <b>Room Conflict</b><br>
            Room is already used by Section <b>{$x['section_name']}</b><br>
            {$xWhen}
        ");
    }

    // SECTION conflict
    if ($x['section_id'] == $section_id) {
        respond("conflict", "
            <b>Section Conflict</b><br>
            Section <b>{$section_name}</b> already has a class<br>
            {$xWhen}
        ");
    }

    // FACULTY conflict — ONLY IF faculty is assigned
    if ($faculty_id !== null && $x['faculty_id'] == $faculty_id) {
        respond("conflict", "
            <b>Faculty Conflict</b><br>
            Instructor is already assigned elsewhere<br>
            {$xWhen}
        ");
    }
}

/* ============================
   5. SAVE SCHEDULE
============================ */
$user_id = $_SESSION['user_id'] ?? null;

if ($current_schedule_id !== null) {

    $updSql = "
        UPDATE tbl_class_schedule
        SET faculty_id = ?, room_id = ?, days_json = ?, time_start = ?, time_end = ?
        WHERE schedule_id = ?
    ";
    $uStmt = $conn->prepare($updSql);
    $uStmt->bind_param(
        "iisssi",
        $faculty_id,
        $room_id,
        $days_json,
        $time_start,
        $time_end,
        $current_schedule_id
    );
    $uStmt->execute();

} else {

    $insSql = "
        INSERT INTO tbl_class_schedule
        (offering_id, faculty_id, room_id, days_json, time_start, time_end, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $iStmt = $conn->prepare($insSql);
    $iStmt->bind_param(
        "iiisssi",
        $offering_id,
        $faculty_id,
        $room_id,
        $days_json,
        $time_start,
        $time_end,
        $user_id
    );
    $iStmt->execute();
}

respond("ok", "Schedule saved successfully.");
