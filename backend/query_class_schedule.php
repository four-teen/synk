<?php
// backend/query_class_schedule.php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';

header('Content-Type: application/json');

define('SYNK_SCHEDULE_DAY_START', '07:30:00');
define('SYNK_SCHEDULE_DAY_END', '17:30:00');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond("error", "Invalid request method.");
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    respond("error", "Unauthorized access.");
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    respond("error", "CSRF validation failed.");
}

$college_id = (int)($_SESSION['college_id'] ?? 0);
if ($college_id <= 0) {
    respond("error", "Missing college context.");
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

function normalize_time_input($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $value)) {
        return null;
    }

    return strlen($value) === 5 ? $value . ':00' : $value;
}

function validate_time_window($timeStart, $timeEnd, $label) {
    if ($timeStart < SYNK_SCHEDULE_DAY_START || $timeEnd > SYNK_SCHEDULE_DAY_END) {
        respond(
            "error",
            "{$label} must stay within the supported scheduling window of 7:30 AM to 5:30 PM."
        );
    }
}

function mark_offering_scheduled($conn, $offering_id) {
    $upd = $conn->prepare("
        UPDATE tbl_prospectus_offering
        SET status = 'active'
        WHERE offering_id = ?
          AND (status IS NULL OR status != 'locked')
    ");
    $upd->bind_param("i", $offering_id);
    $ok = $upd->execute();
    $upd->close();
    return $ok;
}

/* =====================================================
   LOAD OFFERING CONTEXT
===================================================== */
function load_context_any($conn, $offering_id, $college_id) {
    $sql = "
        SELECT o.section_id, o.ay_id, o.semester, sec.section_name
        FROM tbl_prospectus_offering o
        LEFT JOIN tbl_sections sec ON sec.section_id = o.section_id
        JOIN tbl_program p ON p.program_id = o.program_id
        WHERE o.offering_id = ?
          AND p.college_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offering_id, $college_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function load_context_live($conn, $offering_id, $college_id) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT o.section_id, o.ay_id, o.semester, sec.section_name
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        JOIN tbl_program p ON p.program_id = o.program_id
        WHERE o.offering_id = ?
          AND p.college_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offering_id, $college_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function room_access_table_exists($conn) {
    static $hasAccessTable = null;

    if ($hasAccessTable !== null) {
        return $hasAccessTable;
    }

    $q = $conn->query("SHOW TABLES LIKE 'tbl_room_college_access'");
    $hasAccessTable = $q && $q->num_rows > 0;
    return $hasAccessTable;
}

function room_is_accessible_in_term($conn, $room_id, $college_id, $ay_id, $semester) {
    return load_room_access_in_term($conn, $room_id, $college_id, $ay_id, $semester) !== null;
}

function normalize_room_type($type) {
    $value = strtolower(trim((string)$type));
    return in_array($value, ['lecture', 'laboratory', 'lec_lab'], true) ? $value : 'lecture';
}

function load_room_access_in_term($conn, $room_id, $college_id, $ay_id, $semester) {
    if (!room_access_table_exists($conn)) {
        return null;
    }

    $sql = "
        SELECT
            r.room_id,
            r.room_code,
            r.room_name,
            LOWER(COALESCE(r.room_type, 'lecture')) AS room_type,
            acc.access_type
        FROM tbl_room_college_access acc
        INNER JOIN tbl_rooms r ON r.room_id = acc.room_id
        WHERE acc.room_id = ?
          AND acc.college_id = ?
          AND acc.ay_id = ?
          AND acc.semester = ?
          AND r.status = 'active'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $room_id, $college_id, $ay_id, $semester);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['room_type'] = normalize_room_type($row['room_type'] ?? 'lecture');
    $row['access_type'] = strtolower((string)($row['access_type'] ?? 'owner'));
    return $row;
}

function room_type_allows_schedule($room_type, $schedule_type) {
    $roomType = normalize_room_type($room_type);
    $scheduleType = strtoupper(trim((string)$schedule_type));

    if ($scheduleType === 'LAB') {
        return in_array($roomType, ['laboratory', 'lec_lab'], true);
    }

    return in_array($roomType, ['lecture', 'lec_lab'], true);
}

function validate_room_for_schedule($conn, $room_id, $college_id, $ay_id, $semester, $schedule_type) {
    $room = load_room_access_in_term($conn, $room_id, $college_id, $ay_id, $semester);
    if (!$room) {
        respond("error", "Selected room is not available for this Academic Year and Semester.");
    }

    if (!room_type_allows_schedule($room['room_type'], $schedule_type)) {
        $roomLabel = trim((string)($room['room_code'] ?: $room['room_name'] ?: 'Selected room'));
        $typeLabel = strtoupper(trim((string)$schedule_type)) === 'LAB'
            ? 'laboratory'
            : 'lecture';
        respond("error", "{$roomLabel} is not compatible with {$typeLabel} scheduling.");
    }

    return $room;
}

/* =====================================================
   STANDARD CONFLICT CHECK (SECTION + ROOM)
   - ignores same offering_id
   - only checks live synced offerings
===================================================== */
function check_conflict($conn, $ctx, $offering_id, $room_id, $time_start, $time_end, $days, $label) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

    $sql = "
        SELECT cs.room_id, cs.days_json, cs.time_start, cs.time_end,
               o.section_id, sec.section_name
        FROM tbl_class_schedule cs
        JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
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
        if (!days_overlap($days, $xDays)) {
            continue;
        }

        $when = days_fmt($xDays) . " " .
                time_fmt($x['time_start']) . " - " .
                time_fmt($x['time_end']);

        if ($x['section_id'] == $ctx['section_id']) {
            respond(
                "conflict",
                "<b>Section Conflict ({$label})</b><br>
                 Section <b>{$ctx['section_name']}</b> already has a class<br>
                 {$when}"
            );
        }

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
    if (!$offering_id) {
        respond("error", "Missing offering reference.");
    }

    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT schedule_type, schedule_group_id,
               room_id, days_json, time_start, time_end
        FROM tbl_class_schedule cs
        JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        JOIN tbl_program p ON p.program_id = o.program_id
        WHERE cs.offering_id = ?
          AND p.college_id = ?
        ORDER BY schedule_type
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offering_id, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $data = ["group_id" => null, "LEC" => null, "LAB" => null];
    while ($r = $res->fetch_assoc()) {
        $data['group_id'] = $r['schedule_group_id'];
        $data[$r['schedule_type']] = [
            "room_id"    => $r['room_id'],
            "time_start" => $r['time_start'],
            "time_end"   => $r['time_end'],
            "days"       => json_decode($r['days_json'], true)
        ];
    }

    respond("ok", "Loaded", $data);
}

/* =====================================================
   CLEAR SCHEDULE (LECTURE + LAB)
===================================================== */
if (isset($_POST['clear_schedule'])) {
    $offering_id = (int)($_POST['offering_id'] ?? 0);
    if (!$offering_id) {
        respond("error", "Missing offering reference.");
    }

    $ctx = load_context_any($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering not found.");
    }

    $deleted = 0;

    $conn->begin_transaction();
    try {
        $del = $conn->prepare("
            DELETE FROM tbl_class_schedule
            WHERE offering_id = ?
        ");
        $del->bind_param("i", $offering_id);
        if (!$del->execute()) {
            throw new RuntimeException("Failed to clear schedule rows.");
        }
        $deleted = max(0, (int)$del->affected_rows);
        $del->close();

        $upd = $conn->prepare("
            UPDATE tbl_prospectus_offering
            SET status = 'pending'
            WHERE offering_id = ?
              AND (status IS NULL OR status != 'locked')
        ");
        $upd->bind_param("i", $offering_id);
        if (!$upd->execute()) {
            throw new RuntimeException("Failed to update offering status.");
        }
        $upd->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond("error", "Failed to clear schedule.");
    }

    respond(
        "ok",
        $deleted > 0 ? "Schedule cleared." : "No existing schedule to clear.",
        ["deleted" => $deleted]
    );
}

/* =====================================================
   VALIDATE REQUEST
===================================================== */
if (!isset($_POST['save_schedule']) && !isset($_POST['save_dual_schedule']) && !isset($_POST['clear_schedule'])) {
    respond("error", "Invalid request.");
}

/* =====================================================
   SINGLE SCHEDULE (LECTURE ONLY)
===================================================== */
if (isset($_POST['save_schedule'])) {
    $offering_id = (int)$_POST['offering_id'];
    $room_id     = (int)$_POST['room_id'];
    $time_start  = normalize_time_input($_POST['time_start'] ?? '');
    $time_end    = normalize_time_input($_POST['time_end'] ?? '');
    $days        = json_decode($_POST['days_json'], true);

    if (!$offering_id || !$room_id || !$time_start || !$time_end || empty($days)) {
        respond("error", "Missing schedule fields.");
    }

    if ($time_end <= $time_start) {
        respond("error", "End time must be later than start time.");
    }

    validate_time_window($time_start, $time_end, 'Lecture schedule');

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    validate_room_for_schedule($conn, $room_id, $college_id, (int)$ctx['ay_id'], (int)$ctx['semester'], 'LEC');

    check_conflict($conn, $ctx, $offering_id, $room_id, $time_start, $time_end, $days, "LEC");

    $conn->begin_transaction();
    try {
        $del = $conn->prepare("
            DELETE FROM tbl_class_schedule
            WHERE offering_id = ? AND schedule_type = 'LEC'
        ");
        $del->bind_param("i", $offering_id);
        if (!$del->execute()) {
            throw new RuntimeException("Failed to clear previous lecture schedule.");
        }
        $del->close();

        $stmt = $conn->prepare("
            INSERT INTO tbl_class_schedule
            (offering_id, schedule_type, room_id, days_json, time_start, time_end, created_by)
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
        if (!$stmt->execute()) {
            throw new RuntimeException("Failed to save lecture schedule.");
        }
        $stmt->close();

        if (!mark_offering_scheduled($conn, $offering_id)) {
            throw new RuntimeException("Failed to update offering status.");
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond("error", "Failed to save lecture schedule.");
    }

    respond("ok", "Lecture schedule saved.");
}

/* =====================================================
   DUAL SCHEDULE (LECTURE + LAB)
===================================================== */
if (isset($_POST['save_dual_schedule'])) {
    $offering_id = (int)$_POST['offering_id'];
    $schedules   = $_POST['schedules'];

    if (!$offering_id || empty($schedules)) {
        respond("error", "Invalid dual schedule.");
    }

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $norm = [];
    foreach ($schedules as $s) {
        $type = strtoupper(trim((string)($s['type'] ?? '')));
        $roomId = (int)($s['room_id'] ?? 0);
        $timeStart = normalize_time_input($s['time_start'] ?? '');
        $timeEnd = normalize_time_input($s['time_end'] ?? '');
        $daysJson = (string)($s['days_json'] ?? '');
        $days = json_decode($daysJson, true);

        if (!in_array($type, ['LEC', 'LAB'], true)) {
            respond("error", "Invalid schedule type.");
        }

        if (!$roomId || !$timeStart || !$timeEnd || !is_array($days) || empty($days)) {
            respond("error", "{$type} schedule is incomplete.");
        }

        if ($timeEnd <= $timeStart) {
            respond("error", "{$type} end time must be later than start.");
        }

        validate_time_window(
            $timeStart,
            $timeEnd,
            $type === 'LAB' ? 'Laboratory schedule' : 'Lecture schedule'
        );

        $norm[] = [
            "type" => $type,
            "room" => $roomId,
            "start" => $timeStart,
            "end" => $timeEnd,
            "days" => $days,
            "days_json" => $daysJson
        ];
    }

    if (
        count($norm) == 2 &&
        days_overlap($norm[0]['days'], $norm[1]['days']) &&
        time_overlap($norm[0]['start'], $norm[0]['end'], $norm[1]['start'], $norm[1]['end'])
    ) {
        respond("conflict", "Lecture and Laboratory schedules overlap.");
    }

    foreach ($norm as $n) {
        validate_room_for_schedule(
            $conn,
            (int)$n['room'],
            $college_id,
            (int)$ctx['ay_id'],
            (int)$ctx['semester'],
            $n['type']
        );
    }

    foreach ($norm as $n) {
        check_conflict(
            $conn,
            $ctx,
            $offering_id,
            $n['room'],
            $n['start'],
            $n['end'],
            $n['days'],
            $n['type']
        );
    }

    $conn->begin_transaction();
    try {
        $del = $conn->prepare("
            DELETE FROM tbl_class_schedule
            WHERE offering_id = ? AND schedule_type IN ('LEC','LAB')
        ");
        $del->bind_param("i", $offering_id);
        if (!$del->execute()) {
            throw new RuntimeException("Failed to clear previous schedules.");
        }
        $del->close();

        $group_id = random_int(100000, 2147483647);
        $stmt = $conn->prepare("
            INSERT INTO tbl_class_schedule
            (offering_id, schedule_type, schedule_group_id,
             room_id, days_json, time_start, time_end, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($norm as $n) {
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
            if (!$stmt->execute()) {
                throw new RuntimeException("Failed to save schedule block.");
            }
        }
        $stmt->close();

        if (!mark_offering_scheduled($conn, $offering_id)) {
            throw new RuntimeException("Failed to update offering status.");
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond("error", "Failed to save lecture and laboratory schedules.");
    }

    respond("ok", "Lecture and Laboratory schedules saved.");
}
