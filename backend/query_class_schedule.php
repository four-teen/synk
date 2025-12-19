<?php
// backend/query_class_schedule.php
session_start();
include 'db.php';

header('Content-Type: application/json');

/* =====================================================
   RESPONSE HELPER
===================================================== */
function respond($status, $message = "", $extra = []) {
    echo json_encode(array_merge([
        "status"  => $status,
        "message" => $message
    ], $extra));
    exit;
}

/* =====================================================
   HELPERS
===================================================== */
function time_fmt($t) {
    return date("h:i A", strtotime($t));
}
function days_fmt($arr) {
    return is_array($arr) ? implode("", $arr) : "";
}
function days_overlap($a, $b) {
    return is_array($a) && is_array($b) && count(array_intersect($a, $b)) > 0;
}
function time_overlap($s1, $e1, $s2, $e2) {
    return ($s1 < $e2) && ($e1 > $s2);
}

/* =====================================================
   LOAD OFFERING CONTEXT
===================================================== */
function load_context($conn, $offering_id) {
    $sql = "
        SELECT o.section_id, o.ay_id, o.semester, sec.section_name
        FROM tbl_prospectus_offering o
        JOIN tbl_sections sec ON sec.section_id = o.section_id
        WHERE o.offering_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $offering_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/* =====================================================
   STANDARD CONFLICT CHECK (SECTION + ROOM)
   - ignores same offering_id
===================================================== */
function check_conflict($conn, $ctx, $offering_id, $room_id, $time_start, $time_end, $days, $label) {

    $sql = "
        SELECT cs.room_id, cs.days_json, cs.time_start, cs.time_end,
               o.section_id, sec.section_name
        FROM tbl_class_schedule cs
        JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
        JOIN tbl_sections sec ON sec.section_id = o.section_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND cs.offering_id <> ?
          AND cs.time_start < ?
          AND cs.time_end > ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iiiss",
        $ctx['ay_id'],
        $ctx['semester'],
        $offering_id,
        $time_end,
        $time_start
    );
    $stmt->execute();
    $res = $stmt->get_result();

    while ($x = $res->fetch_assoc()) {

        $xDays = json_decode($x['days_json'], true);
        if (!days_overlap($days, $xDays)) continue;

        $when = days_fmt($xDays) . " " .
                time_fmt($x['time_start']) . " - " .
                time_fmt($x['time_end']);

        // SECTION conflict
        if ($x['section_id'] == $ctx['section_id']) {
            respond(
                "conflict",
                "<b>Section Conflict ({$label})</b><br>
                 Section <b>{$ctx['section_name']}</b> already has a class<br>
                 {$when}"
            );
        }

        // ROOM conflict
        if ($x['room_id'] == $room_id) {
            respond(
                "conflict",
                "<b>Room Conflict ({$label})</b><br>
                 Room is already used by Section <b>{$x['section_name']}</b><br>
                 {$when}"
            );
        }
    }
}

/* =====================================================
   LOAD DUAL SCHEDULE (EDIT MODE)
===================================================== */
if (isset($_POST['load_dual_schedule'])) {

    $offering_id = (int)($_POST['offering_id'] ?? 0);
    if (!$offering_id) respond("error", "Missing offering reference.");

    $sql = "
        SELECT schedule_type, schedule_group_id,
               room_id, days_json, time_start, time_end
        FROM tbl_class_schedule
        WHERE offering_id = ?
        ORDER BY schedule_type
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $offering_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) respond("error", "No schedules found.");

    $data = ["group_id"=>null,"LEC"=>null,"LAB"=>null];

    while ($r = $res->fetch_assoc()) {
        $data['group_id'] = $r['schedule_group_id'];
        $data[$r['schedule_type']] = [
            "room_id"    => $r['room_id'],
            "time_start" => $r['time_start'],
            "time_end"   => $r['time_end'],
            "days"       => json_decode($r['days_json'], true)
        ];
    }

    respond("ok","Loaded",$data);
}

/* =====================================================
   VALIDATE REQUEST
===================================================== */
if (!isset($_POST['save_schedule']) && !isset($_POST['save_dual_schedule'])) {
    respond("error","Invalid request.");
}

/* =====================================================
   SINGLE SCHEDULE (LECTURE ONLY)
===================================================== */
if (isset($_POST['save_schedule'])) {

    $offering_id = (int)$_POST['offering_id'];
    $room_id     = (int)$_POST['room_id'];
    $time_start  = $_POST['time_start'];
    $time_end    = $_POST['time_end'];
    $days        = json_decode($_POST['days_json'], true);

    if (!$offering_id || !$room_id || !$time_start || !$time_end || empty($days))
        respond("error","Missing schedule fields.");

    if ($time_end <= $time_start)
        respond("error","End time must be later than start time.");

    $ctx = load_context($conn, $offering_id);
    if (!$ctx) respond("error","Offering not found.");

    check_conflict($conn, $ctx, $offering_id, $room_id, $time_start, $time_end, $days, "LEC");

    // EDIT SAFE
    $del = $conn->prepare("
        DELETE FROM tbl_class_schedule
        WHERE offering_id = ? AND schedule_type = 'LEC'
    ");
    $del->bind_param("i", $offering_id);
    $del->execute();

    $stmt = $conn->prepare("
        INSERT INTO tbl_class_schedule
        (offering_id,schedule_type,room_id,days_json,time_start,time_end,created_by)
        VALUES (?, 'LEC', ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iisssi",
        $offering_id,
        $room_id,
        $_POST['days_json'],
        $time_start,
        $time_end,
        $_SESSION['user_id']
    );
    $stmt->execute();

    respond("ok","Lecture schedule saved.");
}

/* =====================================================
   DUAL SCHEDULE (LECTURE + LAB)
===================================================== */
if (isset($_POST['save_dual_schedule'])) {

    $offering_id = (int)$_POST['offering_id'];
    $schedules   = $_POST['schedules'];

    if (!$offering_id || empty($schedules))
        respond("error","Invalid dual schedule.");

    $ctx = load_context($conn, $offering_id);
    if (!$ctx) respond("error","Offering not found.");

    // Normalize
    $norm = [];
    foreach ($schedules as $s) {
        $days = json_decode($s['days_json'], true);
        if ($s['time_end'] <= $s['time_start'])
            respond("error","{$s['type']} end time must be later than start.");
        $norm[] = [
            "type"=>$s['type'],
            "room"=>$s['room_id'],
            "start"=>$s['time_start'],
            "end"=>$s['time_end'],
            "days"=>$days,
            "days_json"=>$s['days_json']
        ];
    }

    // INTERNAL LEC vs LAB
    if (count($norm)==2 &&
        days_overlap($norm[0]['days'],$norm[1]['days']) &&
        time_overlap($norm[0]['start'],$norm[0]['end'],$norm[1]['start'],$norm[1]['end'])
    ) {
        respond("conflict","Lecture and Laboratory schedules overlap.");
    }

    // EXTERNAL CHECK
    foreach ($norm as $n) {
        check_conflict(
            $conn,$ctx,$offering_id,
            $n['room'],$n['start'],$n['end'],$n['days'],$n['type']
        );
    }

    // EDIT SAFE
    $del = $conn->prepare("
        DELETE FROM tbl_class_schedule
        WHERE offering_id = ? AND schedule_type IN ('LEC','LAB')
    ");
    $del->bind_param("i", $offering_id);
    $del->execute();

    $group_id = time();
    foreach ($norm as $n) {
        $stmt = $conn->prepare("
            INSERT INTO tbl_class_schedule
            (offering_id,schedule_type,schedule_group_id,
             room_id,days_json,time_start,time_end,created_by)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "ississsi",
            $offering_id,
            $n['type'],
            $group_id,
            $n['room'],
            $n['days_json'],
            $n['start'],
            $n['end'],
            $_SESSION['user_id']
        );
        $stmt->execute();
    }

    respond("ok","Lecture and Laboratory schedules saved.");
}
