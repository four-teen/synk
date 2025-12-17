<?php
// backend/query_class_schedule.php
session_start();
include 'db.php';

header('Content-Type: application/json');



/* =====================================================
   LOAD DUAL SCHEDULE (EDIT MODE)
===================================================== */
if (isset($_POST['load_dual_schedule'])) {

    $offering_id = intval($_POST['offering_id'] ?? 0);

    if (!$offering_id) {
        respond("error", "Missing offering reference.");
    }

    $sql = "
        SELECT
            schedule_id,
            schedule_type,
            schedule_group_id,
            room_id,
            days_json,
            time_start,
            time_end
        FROM tbl_class_schedule
        WHERE offering_id = ?
        ORDER BY schedule_type ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $offering_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        respond("error", "No schedules found.");
    }

    $data = [
        "group_id" => null,
        "LEC" => null,
        "LAB" => null
    ];

    while ($r = $res->fetch_assoc()) {
        $data['group_id'] = $r['schedule_group_id'];
        $data[$r['schedule_type']] = [
            "schedule_id" => $r['schedule_id'],
            "room_id"     => $r['room_id'],
            "time_start"  => $r['time_start'],
            "time_end"    => $r['time_end'],
            "days"        => json_decode($r['days_json'], true)
        ];
    }

    respond("ok", "Loaded", $data);
}



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

/* =====================================================
   VALIDATE REQUEST
===================================================== */
if (
    !isset($_POST['save_schedule']) &&
    !isset($_POST['save_dual_schedule'])
) {
    respond("error", "Invalid request.");
}

/* =====================================================
   SINGLE SCHEDULE (LECTURE ONLY)
===================================================== */
if (isset($_POST['save_schedule'])) {

    $offering_id = intval($_POST['offering_id'] ?? 0);
    $faculty_id  = ($_POST['faculty_id'] ?? '') !== '' ? intval($_POST['faculty_id']) : null;
    $room_id     = intval($_POST['room_id'] ?? 0);
    $time_start  = $_POST['time_start'] ?? '';
    $time_end    = $_POST['time_end'] ?? '';
    $days_json   = $_POST['days_json'] ?? '';

    if (!$offering_id || !$room_id || !$time_start || !$time_end || !$days_json) {
        respond("error", "Missing schedule fields.");
    }

    if ($time_end <= $time_start) {
        respond("error", "End time must be later than start time.");
    }

    $newDays = json_decode($days_json, true);
    if (!is_array($newDays) || empty($newDays)) {
        respond("error", "Invalid days selection.");
    }

    /* -----------------------------
       LOAD OFFERING CONTEXT
    ----------------------------- */
    $ctxSql = "
        SELECT o.section_id, o.ay_id, o.semester, sec.section_name
        FROM tbl_prospectus_offering o
        JOIN tbl_sections sec ON sec.section_id = o.section_id
        WHERE o.offering_id = ?
        LIMIT 1
    ";
    $ctxStmt = $conn->prepare($ctxSql);
    $ctxStmt->bind_param("i", $offering_id);
    $ctxStmt->execute();
    $ctx = $ctxStmt->get_result()->fetch_assoc();

    if (!$ctx) {
        respond("error", "Offering not found.");
    }

    /* -----------------------------
       CONFLICT CHECK
    ----------------------------- */
    $candSql = "
        SELECT cs.*, o.section_id, sec.section_name
        FROM tbl_class_schedule cs
        JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
        JOIN tbl_sections sec ON sec.section_id = o.section_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND cs.time_start < ?
          AND cs.time_end > ?
    ";
    $cStmt = $conn->prepare($candSql);
    $cStmt->bind_param(
        "iiss",
        $ctx['ay_id'],
        $ctx['semester'],
        $time_end,
        $time_start
    );
    $cStmt->execute();
    $cRes = $cStmt->get_result();

    while ($x = $cRes->fetch_assoc()) {
        $xDays = json_decode($x['days_json'], true);
        if (!days_overlap($newDays, $xDays)) continue;

        $when = days_fmt($xDays) . " " .
                time_fmt($x['time_start']) . " - " .
                time_fmt($x['time_end']);

        if ($x['room_id'] == $room_id) {
            respond("conflict", "Room conflict<br>{$when}");
        }
        if ($x['section_id'] == $ctx['section_id']) {
            respond("conflict", "Section conflict<br>{$when}");
        }
        if ($faculty_id && $x['faculty_id'] == $faculty_id) {
            respond("conflict", "Faculty conflict<br>{$when}");
        }
    }

    /* -----------------------------
       SAVE (LEC)
    ----------------------------- */
    $sql = "
        INSERT INTO tbl_class_schedule
        (offering_id, schedule_type, schedule_group_id,
         faculty_id, room_id, days_json, time_start, time_end, created_by)
        VALUES (?, 'LEC', NULL, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iiisssi",
        $offering_id,
        $faculty_id,
        $room_id,
        $days_json,
        $time_start,
        $time_end,
        $_SESSION['user_id']
    );
    $stmt->execute();

    respond("ok", "Lecture schedule saved.");
}

/* =====================================================
   DUAL SCHEDULE (LECTURE + LAB)
===================================================== */
if (isset($_POST['save_dual_schedule'])) {

    $offering_id = intval($_POST['offering_id'] ?? 0);
    $schedules   = $_POST['schedules'] ?? [];

    if (!$offering_id || !is_array($schedules) || empty($schedules)) {
        respond("error", "Invalid dual schedule data.");
    }

    /* -------------------------------------------------
       LOAD OFFERING CONTEXT (AY / SEM / SECTION)
    ------------------------------------------------- */
    $ctxSql = "
        SELECT o.section_id, o.ay_id, o.semester, sec.section_name
        FROM tbl_prospectus_offering o
        JOIN tbl_sections sec ON sec.section_id = o.section_id
        WHERE o.offering_id = ?
        LIMIT 1
    ";
    $ctxStmt = $conn->prepare($ctxSql);
    $ctxStmt->bind_param("i", $offering_id);
    $ctxStmt->execute();
    $ctx = $ctxStmt->get_result()->fetch_assoc();

    if (!$ctx) {
        respond("error", "Offering not found.");
    }

    /* -------------------------------------------------
       CONFLICT CHECK (BEFORE DELETE)
    ------------------------------------------------- */
    foreach ($schedules as $sched) {

        $room_id    = intval($sched['room_id'] ?? 0);
        $time_start = $sched['time_start'] ?? '';
        $time_end   = $sched['time_end'] ?? '';
        $days_json  = $sched['days_json'] ?? '';
        $type       = $sched['type'] ?? '';

        if (!$room_id || !$time_start || !$time_end || !$days_json || !$type) {
            respond("error", "Incomplete {$type} schedule.");
        }

        if ($time_end <= $time_start) {
            respond("error", "{$type} end time must be later than start time.");
        }

        $newDays = json_decode($days_json, true);
        if (!is_array($newDays) || empty($newDays)) {
            respond("error", "Invalid days selection for {$type}.");
        }

        $candSql = "
            SELECT cs.*, o.section_id, sec.section_name
            FROM tbl_class_schedule cs
            JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
            JOIN tbl_sections sec ON sec.section_id = o.section_id
            WHERE o.ay_id = ?
              AND o.semester = ?
              AND cs.time_start < ?
              AND cs.time_end > ?
              AND cs.offering_id <> ?
        ";
        $cStmt = $conn->prepare($candSql);
        $cStmt->bind_param(
            "iissi",
            $ctx['ay_id'],
            $ctx['semester'],
            $time_end,
            $time_start,
            $offering_id
        );
        $cStmt->execute();
        $cRes = $cStmt->get_result();

        while ($x = $cRes->fetch_assoc()) {

            $xDays = json_decode($x['days_json'], true);
            if (!days_overlap($newDays, $xDays)) continue;

            $when = days_fmt($xDays) . " " .
                    time_fmt($x['time_start']) . " - " .
                    time_fmt($x['time_end']);

            // ROOM CONFLICT
            if ($x['room_id'] == $room_id) {
                respond(
                    "conflict",
                    "<b>Room Conflict ({$type})</b><br>
                     Room is already used by Section <b>{$x['section_name']}</b><br>
                     {$when}"
                );
            }

            // SECTION CONFLICT
            if ($x['section_id'] == $ctx['section_id']) {
                respond(
                    "conflict",
                    "<b>Section Conflict ({$type})</b><br>
                     Section already has a class<br>
                     {$when}"
                );
            }
        }
    }

    /* -------------------------------------------------
       DELETE OLD LEC/LAB (EDIT MODE SAFE)
    ------------------------------------------------- */
    $del = $conn->prepare("
        DELETE FROM tbl_class_schedule
        WHERE offering_id = ?
          AND schedule_type IN ('LEC','LAB')
    ");
    $del->bind_param("i", $offering_id);
    $del->execute();

    /* -------------------------------------------------
       INSERT NEW LEC + LAB
    ------------------------------------------------- */
    $group_id = time(); // grouping identifier

    foreach ($schedules as $sched) {

        $sql = "
            INSERT INTO tbl_class_schedule
            (offering_id, schedule_type, schedule_group_id,
             room_id, days_json, time_start, time_end, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ississsi",
            $offering_id,
            $sched['type'],
            $group_id,
            $sched['room_id'],
            $sched['days_json'],
            $sched['time_start'],
            $sched['time_end'],
            $_SESSION['user_id']
        );
        $stmt->execute();
    }

    respond("ok", "Lecture and Laboratory schedules saved.");
}

