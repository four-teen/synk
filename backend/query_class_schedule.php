<?php
// backend/query_class_schedule.php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';
require_once __DIR__ . '/academic_schedule_policy_helper.php';
require_once __DIR__ . '/schedule_merge_helper.php';
require_once __DIR__ . '/schema_helper.php';

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
$schedulePolicy = synk_fetch_effective_schedule_policy($conn, $college_id);

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

function validate_time_window($timeStart, $timeEnd, $label, array $policy) {
    if (!synk_schedule_policy_is_within_window($timeStart, $timeEnd, $policy)) {
        respond(
            "error",
            "{$label} must stay within the supported scheduling window of " . $policy['window_label'] . "."
        );
    }
}

function validate_schedule_policy($days, $timeStart, $timeEnd, $label, array $policy) {
    $disallowedDays = synk_schedule_policy_disallowed_days((array)$days, $policy);
    if (!empty($disallowedDays)) {
        respond(
            "error",
            "{$label} uses blocked day(s): " . implode(', ', $disallowedDays) . "."
        );
    }

    validate_time_window($timeStart, $timeEnd, $label, $policy);

    $blockedTime = synk_schedule_policy_blocked_time_overlap($timeStart, $timeEnd, $policy);
    if ($blockedTime !== null) {
        respond(
            "error",
            "{$label} overlaps the blocked time range of " .
            synk_schedule_policy_window_label(
                (string)($blockedTime['start'] ?? ''),
                (string)($blockedTime['end'] ?? '')
            ) . "."
        );
    }
}

function schedule_policy_issue_message($days, $timeStart, $timeEnd, $label, array $policy) {
    $disallowedDays = synk_schedule_policy_disallowed_days((array)$days, $policy);
    if (!empty($disallowedDays)) {
        return "{$label} uses blocked day(s): " . implode(', ', $disallowedDays) . ".";
    }

    if (!synk_schedule_policy_is_within_window($timeStart, $timeEnd, $policy)) {
        return "{$label} must stay within the supported scheduling window of " . $policy['window_label'] . ".";
    }

    $blockedTime = synk_schedule_policy_blocked_time_overlap($timeStart, $timeEnd, $policy);
    if ($blockedTime !== null) {
        return "{$label} overlaps the blocked time range of " .
            synk_schedule_policy_window_label(
                (string)($blockedTime['start'] ?? ''),
                (string)($blockedTime['end'] ?? '')
            ) . ".";
    }

    return null;
}

function normalize_days_array($days) {
    if (!is_array($days)) {
        return [];
    }

    $out = [];
    foreach ($days as $day) {
        $value = trim((string)$day);
        if ($value !== '') {
            $out[$value] = true;
        }
    }

    return array_keys($out);
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
        SELECT
            o.section_id,
            o.ay_id,
            o.semester,
            o.status AS offering_status,
            sec.section_name,
            sec.full_section
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
        SELECT
            o.offering_id,
            o.program_id,
            o.section_id,
            o.ay_id,
            o.semester,
            o.status AS offering_status,
            sec.section_name,
            sec.full_section,
            sec.year_level,
            p.program_code,
            ps.sub_id,
            o.ps_id,
            sm.sub_code,
            sm.sub_description,
            ps.lec_units,
            ps.lab_units,
            ps.total_units
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        JOIN tbl_program p ON p.program_id = o.program_id
        JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
        WHERE o.offering_id = ?
          AND p.college_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offering_id, $college_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function load_scoped_offerings_for_term($conn, $prospectus_id, $ay_id, $semester, $college_id) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            o.offering_id,
            o.ay_id,
            o.semester,
            o.status AS offering_status,
            sec.section_name,
            sm.sub_code,
            sm.sub_description
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
        ORDER BY sec.section_name ASC, sm.sub_code ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iiii", $prospectus_id, $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'offering_id' => (int)$row['offering_id'],
            'ay_id' => (int)$row['ay_id'],
            'semester' => (int)$row['semester'],
            'offering_status' => strtolower(trim((string)($row['offering_status'] ?? 'pending'))),
            'section_name' => (string)($row['section_name'] ?? ''),
            'sub_code' => (string)($row['sub_code'] ?? ''),
            'sub_description' => (string)($row['sub_description'] ?? '')
        ];
    }

    $stmt->close();
    return $rows;
}

function load_scoped_offerings_for_college_term($conn, $ay_id, $semester, $college_id) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            o.offering_id,
            o.prospectus_id,
            o.ay_id,
            o.semester,
            o.status AS offering_status,
            sec.section_name,
            sm.sub_code,
            sm.sub_description
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
        ORDER BY
            p.program_code ASC,
            sec.section_name ASC,
            sm.sub_code ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iii", $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'offering_id' => (int)$row['offering_id'],
            'prospectus_id' => (int)($row['prospectus_id'] ?? 0),
            'ay_id' => (int)$row['ay_id'],
            'semester' => (int)$row['semester'],
            'offering_status' => strtolower(trim((string)($row['offering_status'] ?? 'pending'))),
            'section_name' => (string)($row['section_name'] ?? ''),
            'sub_code' => (string)($row['sub_code'] ?? ''),
            'sub_description' => (string)($row['sub_description'] ?? '')
        ];
    }

    $stmt->close();
    return $rows;
}

function saved_schedule_tables_exist($conn) {
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    foreach (['tbl_class_schedule_set', 'tbl_class_schedule_set_row'] as $table) {
        $q = $conn->query("SHOW TABLES LIKE '{$table}'");
        if (!($q && $q->num_rows > 0)) {
            $exists = false;
            return $exists;
        }
    }

    $exists = true;
    return $exists;
}

function ensure_saved_schedule_tables_exist($conn) {
    if (!saved_schedule_tables_exist($conn)) {
        respond("error", "Saved schedule set tables are not available yet.");
    }
}

function load_saved_schedule_sets_for_scope($conn, $college_id, $prospectus_id, $ay_id, $semester) {
    $sql = "
        SELECT
            s.schedule_set_id,
            s.set_name,
            s.remarks,
            s.date_created,
            s.date_updated,
            COUNT(r.schedule_set_row_id) AS row_count,
            COUNT(DISTINCT r.offering_id) AS offering_count
        FROM tbl_class_schedule_set s
        LEFT JOIN tbl_class_schedule_set_row r
            ON r.schedule_set_id = s.schedule_set_id
        WHERE s.college_id = ?
          AND s.prospectus_id = ?
          AND s.ay_id = ?
          AND s.semester = ?
        GROUP BY
            s.schedule_set_id,
            s.set_name,
            s.remarks,
            s.date_created,
            s.date_updated
        ORDER BY s.date_updated DESC, s.schedule_set_id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iiii", $college_id, $prospectus_id, $ay_id, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'schedule_set_id' => (int)($row['schedule_set_id'] ?? 0),
            'set_name' => (string)($row['set_name'] ?? ''),
            'remarks' => (string)($row['remarks'] ?? ''),
            'date_created' => (string)($row['date_created'] ?? ''),
            'date_updated' => (string)($row['date_updated'] ?? ''),
            'row_count' => (int)($row['row_count'] ?? 0),
            'offering_count' => (int)($row['offering_count'] ?? 0)
        ];
    }

    $stmt->close();
    return $rows;
}

function load_saved_schedule_set_record($conn, $schedule_set_id, $college_id) {
    $sql = "
        SELECT
            schedule_set_id,
            college_id,
            prospectus_id,
            ay_id,
            semester,
            set_name,
            remarks,
            date_created,
            date_updated
        FROM tbl_class_schedule_set
        WHERE schedule_set_id = ?
          AND college_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ii", $schedule_set_id, $college_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function load_saved_schedule_set_by_name($conn, $college_id, $prospectus_id, $ay_id, $semester, $set_name) {
    $sql = "
        SELECT
            schedule_set_id,
            set_name,
            remarks,
            date_created,
            date_updated
        FROM tbl_class_schedule_set
        WHERE college_id = ?
          AND prospectus_id = ?
          AND ay_id = ?
          AND semester = ?
          AND set_name = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("iiiis", $college_id, $prospectus_id, $ay_id, $semester, $set_name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function load_saved_schedule_set_rows($conn, $schedule_set_id, $college_id) {
    $sql = "
        SELECT
            r.schedule_set_row_id,
            r.schedule_set_id,
            r.offering_id,
            r.program_id,
            r.ps_id,
            r.section_id,
            r.schedule_type,
            r.block_order,
            r.room_id,
            r.days_json,
            r.time_start,
            r.time_end,
            r.subject_code_snapshot,
            r.subject_description_snapshot,
            r.section_name_snapshot,
            r.room_label_snapshot
        FROM tbl_class_schedule_set_row r
        INNER JOIN tbl_class_schedule_set s
            ON s.schedule_set_id = r.schedule_set_id
        WHERE r.schedule_set_id = ?
          AND s.college_id = ?
        ORDER BY
            r.offering_id ASC,
            r.block_order ASC,
            FIELD(r.schedule_type, 'LEC', 'LAB'),
            r.schedule_set_row_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("ii", $schedule_set_id, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function load_live_schedule_snapshot_rows_for_scope($conn, $prospectus_id, $ay_id, $semester, $college_id) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            o.offering_id,
            o.program_id,
            o.ps_id,
            o.section_id,
            cs.schedule_type,
            cs.room_id,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            sm.sub_code,
            sm.sub_description,
            sec.section_name,
            COALESCE(NULLIF(TRIM(r.room_code), ''), NULLIF(TRIM(r.room_name), ''), '') AS room_label
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
          AND cs.schedule_type IN ('LEC', 'LAB')
        ORDER BY
            sec.section_name ASC,
            sm.sub_code ASC,
            o.offering_id ASC,
            FIELD(cs.schedule_type, 'LEC', 'LAB'),
            cs.time_start ASC,
            cs.schedule_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iiii", $prospectus_id, $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['schedule_type'] = synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC'));
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function load_live_schedule_snapshot_rows_for_college_term($conn, $ay_id, $semester, $college_id) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            o.offering_id,
            o.program_id,
            o.ps_id,
            o.section_id,
            cs.schedule_type,
            cs.room_id,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            sm.sub_code,
            sm.sub_description,
            sec.section_name,
            COALESCE(NULLIF(TRIM(r.room_code), ''), NULLIF(TRIM(r.room_name), ''), '') AS room_label
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
          AND cs.schedule_type IN ('LEC', 'LAB')
        ORDER BY
            p.program_code ASC,
            sec.section_name ASC,
            sm.sub_code ASC,
            o.offering_id ASC,
            FIELD(cs.schedule_type, 'LEC', 'LAB'),
            cs.time_start ASC,
            cs.schedule_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iii", $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['schedule_type'] = synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC'));
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function load_live_scheduled_offering_ids_for_scope($conn, $prospectus_id, $ay_id, $semester, $college_id) {
    $sql = "
        SELECT DISTINCT cs.offering_id
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iiii", $prospectus_id, $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($offeringId > 0) {
            $ids[] = $offeringId;
        }
    }

    $stmt->close();
    return $ids;
}

function load_live_scheduled_offering_ids_for_college_term($conn, $ay_id, $semester, $college_id) {
    $sql = "
        SELECT DISTINCT cs.offering_id
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iii", $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($offeringId > 0) {
            $ids[] = $offeringId;
        }
    }

    $stmt->close();
    return $ids;
}

function schedule_set_offering_label($subCode, $sectionName, $fallback = 'Offering') {
    $parts = [];

    $subCode = trim((string)$subCode);
    if ($subCode !== '') {
        $parts[] = $subCode;
    }

    $sectionName = trim((string)$sectionName);
    if ($sectionName !== '') {
        $parts[] = $sectionName;
    }

    $label = implode(' ', $parts);
    return $label !== '' ? $label : $fallback;
}

function schedule_set_row_label($row) {
    $type = synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC'));
    $typeLabel = $type === 'LAB' ? 'LAB' : 'LEC';
    $subject = trim((string)($row['subject_code_snapshot'] ?? ''));
    $section = trim((string)($row['section_name_snapshot'] ?? ''));

    $parts = [];
    if ($subject !== '') {
        $parts[] = $subject;
    }
    if ($section !== '') {
        $parts[] = $section;
    }
    $parts[] = $typeLabel;

    return implode(' / ', $parts);
}

function validate_saved_schedule_set_internal_conflicts($rows) {
    $count = count($rows);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $left = $rows[$i];
            $right = $rows[$j];

            if (!days_overlap($left['days'], $right['days'])) {
                continue;
            }

            if (!time_overlap($left['time_start'], $left['time_end'], $right['time_start'], $right['time_end'])) {
                continue;
            }

            $leftLabel = htmlspecialchars((string)($left['label'] ?? 'Saved schedule'), ENT_QUOTES, 'UTF-8');
            $rightLabel = htmlspecialchars((string)($right['label'] ?? 'Saved schedule'), ENT_QUOTES, 'UTF-8');

            if ((int)$left['offering_id'] === (int)$right['offering_id']) {
                return "<b>Saved Set Conflict</b><br><b>{$leftLabel}</b> overlaps with <b>{$rightLabel}</b> for the same offering.";
            }

            if ((int)$left['section_id'] > 0 && (int)$left['section_id'] === (int)$right['section_id']) {
                $sectionLabel = htmlspecialchars((string)($left['section_name'] ?? $right['section_name'] ?? 'Selected section'), ENT_QUOTES, 'UTF-8');
                return "<b>Saved Set Conflict</b><br>Section <b>{$sectionLabel}</b> has overlapping rows: <b>{$leftLabel}</b> and <b>{$rightLabel}</b>.";
            }

            if ((int)$left['room_id'] > 0 && (int)$left['room_id'] === (int)$right['room_id']) {
                $roomLabel = htmlspecialchars((string)($left['room_label'] ?? $right['room_label'] ?? 'Selected room'), ENT_QUOTES, 'UTF-8');
                return "<b>Saved Set Conflict</b><br>Room <b>{$roomLabel}</b> is double-booked by <b>{$leftLabel}</b> and <b>{$rightLabel}</b>.";
            }
        }
    }

    return null;
}

function find_conflict_excluding_offerings($conn, $ay_id, $semester, $excludedOfferingIds, $section_id, $section_name, $room_id, $days, $time_start, $time_end, $label) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $excludedOfferingIds = array_values(array_unique(array_map('intval', (array)$excludedOfferingIds)));

    $sql = "
        SELECT
            cs.room_id,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            o.section_id,
            sec.section_name
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND cs.time_start < ?
          AND cs.time_end > ?
    ";

    if (!empty($excludedOfferingIds)) {
        $sql .= " AND cs.offering_id NOT IN (" . implode(',', $excludedOfferingIds) . ")";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return "Unable to validate saved set conflicts.";
    }

    $stmt->bind_param("iiss", $ay_id, $semester, $time_end, $time_start);
    $stmt->execute();
    $res = $stmt->get_result();

    $safeLabel = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
    $safeSectionName = htmlspecialchars((string)$section_name, ENT_QUOTES, 'UTF-8');

    while ($row = $res->fetch_assoc()) {
        $existingDays = normalize_days_array(json_decode((string)($row['days_json'] ?? '[]'), true));
        if (!days_overlap($days, $existingDays)) {
            continue;
        }

        $when = htmlspecialchars(
            days_fmt($existingDays) . ' ' . time_fmt((string)$row['time_start']) . ' - ' . time_fmt((string)$row['time_end']),
            ENT_QUOTES,
            'UTF-8'
        );

        if ((int)($row['section_id'] ?? 0) > 0 && (int)$row['section_id'] === (int)$section_id) {
            $stmt->close();
            return "<b>Section Conflict ({$safeLabel})</b><br>Section <b>{$safeSectionName}</b> already has a class<br>{$when}";
        }

        if ((int)($row['room_id'] ?? 0) > 0 && (int)$row['room_id'] === (int)$room_id) {
            $existingSection = htmlspecialchars((string)($row['section_name'] ?? 'another section'), ENT_QUOTES, 'UTF-8');
            $stmt->close();
            return "<b>Room Conflict ({$safeLabel})</b><br>Room is already used by Section <b>{$existingSection}</b><br>{$when}";
        }
    }

    $stmt->close();
    return null;
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

function required_schedule_types_for_context($ctx) {
    return ((float)($ctx['lab_units'] ?? 0) > 0) ? ['LEC', 'LAB'] : ['LEC'];
}

function load_existing_schedule_rows($conn, $offering_id) {
    $sql = "
        SELECT
            cs.schedule_id,
            cs.schedule_type,
            cs.schedule_group_id,
            cs.room_id,
            cs.days_json,
            cs.time_start,
            cs.time_end
        FROM tbl_class_schedule cs
        WHERE cs.offering_id = ?
        ORDER BY FIELD(cs.schedule_type, 'LEC', 'LAB'), cs.schedule_id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $offering_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $type = strtoupper(trim((string)($row['schedule_type'] ?? '')));
        if (!in_array($type, ['LEC', 'LAB'], true)) {
            continue;
        }

        if (isset($rows[$type])) {
            respond("error", "Offering has multiple {$type} schedule rows. Resolve the existing schedule data first.");
        }

        $rows[$type] = $row;
    }

    $stmt->close();
    return $rows;
}

function load_schedule_block_rows($conn, $offering_id) {
    $sql = "
        SELECT
            cs.schedule_id,
            cs.schedule_type,
            cs.schedule_group_id,
            cs.room_id,
            cs.days_json,
            cs.time_start,
            cs.time_end
        FROM tbl_class_schedule cs
        WHERE cs.offering_id = ?
          AND cs.schedule_type IN ('LEC', 'LAB')
        ORDER BY FIELD(cs.schedule_type, 'LEC', 'LAB'), cs.schedule_id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $offering_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['schedule_id'] = (int)$row['schedule_id'];
        $row['schedule_group_id'] = isset($row['schedule_group_id']) ? (int)$row['schedule_group_id'] : 0;
        $row['room_id'] = (int)$row['room_id'];
        $row['schedule_type'] = synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC'));
        $row['days'] = normalize_days_array(json_decode((string)$row['days_json'], true));
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function mark_offering_schedule_state($conn, $offering_id, $state) {
    $allowed = ['active', 'pending'];
    $state = in_array($state, $allowed, true) ? $state : 'pending';

    $upd = $conn->prepare("
        UPDATE tbl_prospectus_offering
        SET status = ?
        WHERE offering_id = ?
          AND (status IS NULL OR status != 'locked')
    ");
    $upd->bind_param("si", $state, $offering_id);
    $ok = $upd->execute();
    $upd->close();
    return $ok;
}

function build_schedule_coverage_summary($ctx, $rows) {
    $required = synk_required_minutes_by_type(
        (float)($ctx['lec_units'] ?? 0),
        (float)($ctx['lab_units'] ?? 0),
        (float)($ctx['total_units'] ?? 0)
    );
    $scheduled = synk_sum_scheduled_minutes_by_type($rows);
    $requiredTypes = required_schedule_types_for_context($ctx);

    $hasAny = false;
    $isComplete = true;
    foreach ($requiredTypes as $type) {
        $requiredMinutes = (int)($required[$type] ?? 0);
        $scheduledMinutes = (int)($scheduled[$type] ?? 0);

        if ($scheduledMinutes > 0) {
            $hasAny = true;
        }

        if ($requiredMinutes > 0 && $scheduledMinutes < $requiredMinutes) {
            $isComplete = false;
        }
    }

    if (!$hasAny) {
        $status = 'empty';
    } elseif ($isComplete) {
        $status = 'complete';
    } else {
        $status = 'partial';
    }

    return [
        'status' => $status,
        'required_minutes' => $required,
        'scheduled_minutes' => $scheduled
    ];
}

function load_workload_faculties_for_offering($conn, $offering_id, $ay_id, $semester) {
    $sql = "
        SELECT
            fw.faculty_id,
            CONCAT(
                f.last_name,
                ', ',
                f.first_name,
                CASE
                    WHEN COALESCE(f.ext_name, '') <> '' THEN CONCAT(' ', f.ext_name)
                    ELSE ''
                END
            ) AS faculty_name,
            COUNT(*) AS assigned_rows
        FROM tbl_faculty_workload_sched fw
        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fw.schedule_id
        INNER JOIN tbl_faculty f
            ON f.faculty_id = fw.faculty_id
        WHERE cs.offering_id = ?
          AND fw.ay_id = ?
          AND fw.semester = ?
        GROUP BY fw.faculty_id, faculty_name
        ORDER BY faculty_name
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $offering_id, $ay_id, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    $faculties = [];
    while ($row = $res->fetch_assoc()) {
        $faculties[] = [
            'faculty_id' => (int)$row['faculty_id'],
            'faculty_name' => (string)$row['faculty_name'],
            'assigned_rows' => (int)$row['assigned_rows']
        ];
    }

    $stmt->close();
    return $faculties;
}

function load_workload_faculty_map_for_offerings($conn, array $offering_ids, $ay_id, $semester) {
    $map = [];
    if (empty($offering_ids)) {
        return $map;
    }

    $safeIds = array_map('intval', array_values(array_unique($offering_ids)));
    $sql = "
        SELECT
            cs.offering_id,
            CONCAT(
                f.last_name,
                ', ',
                f.first_name,
                CASE
                    WHEN COALESCE(f.ext_name, '') <> '' THEN CONCAT(' ', f.ext_name)
                    ELSE ''
                END
            ) AS faculty_name
        FROM tbl_faculty_workload_sched fw
        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fw.schedule_id
        INNER JOIN tbl_faculty f
            ON f.faculty_id = fw.faculty_id
        WHERE cs.offering_id IN (" . implode(',', $safeIds) . ")
          AND fw.ay_id = ?
          AND fw.semester = ?
        GROUP BY cs.offering_id, fw.faculty_id, faculty_name
        ORDER BY faculty_name ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $map;
    }

    $stmt->bind_param("ii", $ay_id, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $offeringId = (int)$row['offering_id'];
        if (!isset($map[$offeringId])) {
            $map[$offeringId] = [];
        }

        $map[$offeringId][] = (string)$row['faculty_name'];
    }

    $stmt->close();
    return $map;
}

function format_faculty_display_name(array $row) {
    $lastName = trim((string)($row['last_name'] ?? ''));
    $firstName = trim((string)($row['first_name'] ?? ''));
    $extName = trim((string)($row['ext_name'] ?? ''));

    $name = $lastName;
    if ($firstName !== '') {
        $name .= ($name !== '' ? ', ' : '') . $firstName;
    }

    if ($extName !== '') {
        $name .= ($name !== '' ? ' ' : '') . $extName;
    }

    $name = trim($name);
    if ($name !== '') {
        return $name;
    }

    $facultyId = (int)($row['faculty_id'] ?? 0);
    return $facultyId > 0 ? 'Faculty #' . $facultyId : 'Faculty';
}

function load_college_term_faculty_rows($conn, $college_id, $ay_id, $semester) {
    $assignmentHasAyId = synk_table_has_column($conn, 'tbl_college_faculty', 'ay_id');
    $assignmentHasSemester = synk_table_has_column($conn, 'tbl_college_faculty', 'semester');
    $assignmentHasStatus = synk_table_has_column($conn, 'tbl_college_faculty', 'status');
    $facultyHasExtName = synk_table_has_column($conn, 'tbl_faculty', 'ext_name');

    $where = ['cf.college_id = ?'];
    $types = 'i';
    $params = [$college_id];

    if ($assignmentHasStatus) {
        $where[] = "LOWER(TRIM(COALESCE(cf.status, 'active'))) = 'active'";
    }

    if ($assignmentHasAyId) {
        $where[] = 'cf.ay_id = ?';
        $types .= 'i';
        $params[] = $ay_id;
    }

    if ($assignmentHasSemester) {
        $where[] = 'cf.semester = ?';
        $types .= 'i';
        $params[] = $semester;
    }

    $sql = "
        SELECT DISTINCT
            f.faculty_id,
            f.last_name,
            f.first_name,
            " . ($facultyHasExtName ? "COALESCE(f.ext_name, '') AS ext_name" : "'' AS ext_name") . "
        FROM tbl_college_faculty cf
        INNER JOIN tbl_faculty f
            ON f.faculty_id = cf.faculty_id
        WHERE " . implode("\n          AND ", $where) . "
        ORDER BY f.last_name ASC, f.first_name ASC, f.faculty_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    synk_bind_dynamic_params($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'faculty_id' => (int)($row['faculty_id'] ?? 0),
            'last_name' => (string)($row['last_name'] ?? ''),
            'first_name' => (string)($row['first_name'] ?? ''),
            'ext_name' => (string)($row['ext_name'] ?? ''),
            'faculty_name' => format_faculty_display_name($row)
        ];
    }

    $stmt->close();
    return $rows;
}

function load_faculty_schedule_counts_by_faculty($conn, array $facultyIds, $ay_id, $semester, $college_id) {
    $counts = [];
    $safeFacultyIds = array_map('intval', array_values(array_unique($facultyIds)));
    $safeFacultyIds = array_values(array_filter($safeFacultyIds, static function ($value) {
        return $value > 0;
    }));

    if (empty($safeFacultyIds)) {
        return $counts;
    }

    $classScheduleHasGroupId = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_group_id');
    $contextExpr = $classScheduleHasGroupId
        ? "CASE
                WHEN cs.schedule_group_id IS NOT NULL AND cs.schedule_group_id > 0
                    THEN CONCAT('group:', cs.schedule_group_id)
                ELSE CONCAT('offering:', cs.offering_id)
           END"
        : "CONCAT('offering:', cs.offering_id)";

    $sql = "
        SELECT
            fw.faculty_id,
            COUNT(DISTINCT fw.schedule_id) AS scheduled_block_count,
            COUNT(DISTINCT {$contextExpr}) AS scheduled_class_count
        FROM tbl_faculty_workload_sched fw
        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fw.schedule_id
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        WHERE fw.faculty_id IN (" . implode(',', $safeFacultyIds) . ")
          AND fw.ay_id = ?
          AND fw.semester = ?
          AND p.college_id = ?
        GROUP BY fw.faculty_id
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $counts;
    }

    $stmt->bind_param("iii", $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $facultyId = (int)($row['faculty_id'] ?? 0);
        $counts[$facultyId] = [
            'scheduled_block_count' => (int)($row['scheduled_block_count'] ?? 0),
            'scheduled_class_count' => (int)($row['scheduled_class_count'] ?? 0)
        ];
    }

    $stmt->close();
    return $counts;
}

function load_college_term_faculty_record($conn, $college_id, $ay_id, $semester, $faculty_id) {
    $rows = load_college_term_faculty_rows($conn, $college_id, $ay_id, $semester);
    foreach ($rows as $row) {
        if ((int)($row['faculty_id'] ?? 0) === (int)$faculty_id) {
            return $row;
        }
    }

    return null;
}

function load_faculty_schedule_entries_for_term($conn, $faculty_id, $ay_id, $semester, $college_id, $currentOfferingId = 0) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $classScheduleHasGroupId = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_group_id');
    $classScheduleHasType = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_type');

    $sql = "
        SELECT
            cs.schedule_id,
            cs.offering_id,
            " . ($classScheduleHasGroupId ? 'cs.schedule_group_id AS group_id' : 'NULL AS group_id') . ",
            " . ($classScheduleHasType ? "cs.schedule_type AS schedule_type" : "'LEC' AS schedule_type") . ",
            sm.sub_code,
            sm.sub_description,
            sec.section_name,
            sec.full_section,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            COALESCE(NULLIF(TRIM(r.room_code), ''), NULLIF(TRIM(r.room_name), ''), 'TBA') AS room_label
        FROM tbl_faculty_workload_sched fw
        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fw.schedule_id
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        WHERE fw.faculty_id = ?
          AND fw.ay_id = ?
          AND fw.semester = ?
          AND p.college_id = ?
        ORDER BY
            cs.time_start ASC,
            sec.section_name ASC,
            sm.sub_code ASC,
            " . ($classScheduleHasType ? "FIELD(cs.schedule_type, 'LEC', 'LAB')," : "") . "
            cs.schedule_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iiii", $faculty_id, $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $entries = [];
    while ($row = $res->fetch_assoc()) {
        $days = normalize_days_array(json_decode((string)($row['days_json'] ?? ''), true));
        $sectionLabel = trim((string)($row['full_section'] ?? ''));
        if ($sectionLabel === '') {
            $sectionLabel = trim((string)($row['section_name'] ?? ''));
        }

        $entries[] = [
            'schedule_id' => (int)($row['schedule_id'] ?? 0),
            'offering_id' => (int)($row['offering_id'] ?? 0),
            'group_id' => (int)($row['group_id'] ?? 0),
            'schedule_type' => synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC')),
            'subject_code' => (string)($row['sub_code'] ?? ''),
            'subject_description' => (string)($row['sub_description'] ?? ''),
            'section_label' => $sectionLabel,
            'room_label' => (string)($row['room_label'] ?? 'TBA'),
            'days' => $days,
            'time_start' => (string)($row['time_start'] ?? ''),
            'time_end' => (string)($row['time_end'] ?? ''),
            'is_current_offering' => (int)($row['offering_id'] ?? 0) === (int)$currentOfferingId
        ];
    }

    $stmt->close();
    return $entries;
}

function load_other_faculty_schedule_entries_for_term($conn, $selected_faculty_id, $ay_id, $semester, $college_id, $currentOfferingId = 0) {
    if ($ay_id <= 0 || $semester <= 0 || $college_id <= 0) {
        return [];
    }

    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $classScheduleHasGroupId = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_group_id');
    $classScheduleHasType = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_type');

    $sql = "
        SELECT
            cs.schedule_id,
            cs.offering_id,
            " . ($classScheduleHasGroupId ? 'cs.schedule_group_id AS group_id' : 'NULL AS group_id') . ",
            " . ($classScheduleHasType ? "cs.schedule_type AS schedule_type" : "'LEC' AS schedule_type") . ",
            sm.sub_code,
            sm.sub_description,
            sec.section_name,
            sec.full_section,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            COALESCE(NULLIF(TRIM(r.room_code), ''), NULLIF(TRIM(r.room_name), ''), 'TBA') AS room_label,
            fw.faculty_id,
            CONCAT(
                f.last_name,
                ', ',
                f.first_name,
                CASE
                    WHEN COALESCE(f.ext_name, '') <> '' THEN CONCAT(' ', f.ext_name)
                    ELSE ''
                END
            ) AS faculty_name
        FROM tbl_faculty_workload_sched fw
        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fw.schedule_id
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        INNER JOIN tbl_faculty f
            ON f.faculty_id = fw.faculty_id
        WHERE fw.ay_id = ?
          AND fw.semester = ?
          AND p.college_id = ?
        ORDER BY
            cs.time_start ASC,
            sec.section_name ASC,
            sm.sub_code ASC,
            " . ($classScheduleHasType ? "FIELD(cs.schedule_type, 'LEC', 'LAB')," : "") . "
            cs.schedule_id ASC,
            faculty_name ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iii", $ay_id, $semester, $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $entriesBySchedule = [];
    while ($row = $res->fetch_assoc()) {
        $scheduleId = (int)($row['schedule_id'] ?? 0);
        if ($scheduleId <= 0) {
            continue;
        }

        if (!isset($entriesBySchedule[$scheduleId])) {
            $days = normalize_days_array(json_decode((string)($row['days_json'] ?? ''), true));
            $sectionLabel = trim((string)($row['full_section'] ?? ''));
            if ($sectionLabel === '') {
                $sectionLabel = trim((string)($row['section_name'] ?? ''));
            }

            $entriesBySchedule[$scheduleId] = [
                'schedule_id' => $scheduleId,
                'offering_id' => (int)($row['offering_id'] ?? 0),
                'group_id' => (int)($row['group_id'] ?? 0),
                'schedule_type' => synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC')),
                'subject_code' => (string)($row['sub_code'] ?? ''),
                'subject_description' => (string)($row['sub_description'] ?? ''),
                'section_label' => $sectionLabel,
                'room_label' => (string)($row['room_label'] ?? 'TBA'),
                'days' => $days,
                'time_start' => (string)($row['time_start'] ?? ''),
                'time_end' => (string)($row['time_end'] ?? ''),
                'is_current_offering' => (int)($row['offering_id'] ?? 0) === (int)$currentOfferingId,
                'is_other_faculty_assignment' => true,
                'owner_faculty_names' => [],
                '_owner_faculty_ids' => []
            ];
        }

        $facultyId = (int)($row['faculty_id'] ?? 0);
        $facultyName = trim((string)($row['faculty_name'] ?? ''));
        if ($facultyId > 0 && !in_array($facultyId, $entriesBySchedule[$scheduleId]['_owner_faculty_ids'], true)) {
            $entriesBySchedule[$scheduleId]['_owner_faculty_ids'][] = $facultyId;
        }
        if ($facultyName !== '' && !in_array($facultyName, $entriesBySchedule[$scheduleId]['owner_faculty_names'], true)) {
            $entriesBySchedule[$scheduleId]['owner_faculty_names'][] = $facultyName;
        }
    }

    $stmt->close();

    $entries = [];
    foreach ($entriesBySchedule as $entry) {
        if (in_array((int)$selected_faculty_id, $entry['_owner_faculty_ids'], true)) {
            continue;
        }

        unset($entry['_owner_faculty_ids']);
        if (!empty($entry['owner_faculty_names'])) {
            $entries[] = $entry;
        }
    }

    return $entries;
}

function load_schedule_row_counts_for_offerings($conn, array $offering_ids) {
    $counts = [];
    if (empty($offering_ids)) {
        return $counts;
    }

    $safeIds = array_map('intval', array_values(array_unique($offering_ids)));
    $sql = "
        SELECT offering_id, COUNT(*) AS row_count
        FROM tbl_class_schedule
        WHERE offering_id IN (" . implode(',', $safeIds) . ")
        GROUP BY offering_id
    ";

    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) {
        return $counts;
    }

    while ($row = $res->fetch_assoc()) {
        $counts[(int)$row['offering_id']] = (int)$row['row_count'];
    }

    return $counts;
}

function load_merge_context_rows_for_offerings($conn, array $offeringIds, $college_id) {
    $normalizedIds = synk_schedule_merge_normalize_offering_ids($offeringIds);
    if (empty($normalizedIds)) {
        return [];
    }

    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $idList = implode(',', array_map('intval', $normalizedIds));
    $sql = "
        SELECT
            o.offering_id,
            o.program_id,
            o.ps_id,
            o.section_id,
            o.ay_id,
            o.semester,
            o.status AS offering_status,
            ps.sub_id,
            sm.sub_code,
            sm.sub_description,
            sec.section_name,
            sec.full_section,
            COALESCE(NULLIF(TRIM(p.program_code), ''), '') AS program_code,
            ps.lec_units,
            ps.lab_units,
            ps.total_units
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        WHERE o.offering_id IN ({$idList})
          AND p.college_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($offeringId <= 0) {
            continue;
        }

        $rows[$offeringId] = [
            'offering_id' => $offeringId,
            'program_id' => (int)($row['program_id'] ?? 0),
            'ps_id' => (int)($row['ps_id'] ?? 0),
            'section_id' => (int)($row['section_id'] ?? 0),
            'ay_id' => (int)($row['ay_id'] ?? 0),
            'semester' => (int)($row['semester'] ?? 0),
            'offering_status' => strtolower(trim((string)($row['offering_status'] ?? 'pending'))),
            'sub_id' => (int)($row['sub_id'] ?? 0),
            'sub_code' => (string)($row['sub_code'] ?? ''),
            'sub_description' => (string)($row['sub_description'] ?? ''),
            'section_name' => (string)($row['section_name'] ?? ''),
            'full_section' => (string)($row['full_section'] ?? ''),
            'program_code' => (string)($row['program_code'] ?? ''),
            'lec_units' => (float)($row['lec_units'] ?? 0),
            'lab_units' => (float)($row['lab_units'] ?? 0),
            'total_units' => (float)($row['total_units'] ?? 0)
        ];
    }

    $stmt->close();
    return $rows;
}

function load_peer_section_rows_for_subject($conn, array $ctx, int $college_id): array
{
    $subjectId = (int)($ctx['sub_id'] ?? 0);
    $ayId = (int)($ctx['ay_id'] ?? 0);
    $semester = (int)($ctx['semester'] ?? 0);
    $programId = (int)($ctx['program_id'] ?? 0);
    $yearLevel = (int)($ctx['year_level'] ?? 0);

    if ($subjectId <= 0 || $ayId <= 0 || $semester <= 0 || $college_id <= 0 || $programId <= 0 || $yearLevel <= 0) {
        return [];
    }

    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            o.offering_id,
            o.section_id,
            sec.section_name,
            sec.full_section,
            sec.year_level,
            p.program_code
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
          AND o.program_id = ?
          AND ps.sub_id = ?
          AND sec.year_level = ?
        ORDER BY
            sec.section_name ASC,
            o.offering_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("iiiiii", $ayId, $semester, $college_id, $programId, $subjectId, $yearLevel);
    $stmt->execute();
    $res = $stmt->get_result();

    $sections = [];
    while ($row = $res->fetch_assoc()) {
        $sectionId = (int)($row['section_id'] ?? 0);
        if ($sectionId <= 0 || isset($sections[$sectionId])) {
            continue;
        }

        $sections[$sectionId] = [
            'section_id' => $sectionId,
            'offering_id' => (int)($row['offering_id'] ?? 0),
            'section_name' => (string)($row['section_name'] ?? ''),
            'full_section' => (string)($row['full_section'] ?? ''),
            'year_level' => (int)($row['year_level'] ?? 0),
            'program_code' => (string)($row['program_code'] ?? '')
        ];
    }

    $stmt->close();
    return array_values($sections);
}

function load_term_offering_ids_by_section($conn, array $sectionIds, int $ayId, int $semester): array
{
    $rowsBySection = [];
    $safeSectionIds = array_values(array_unique(array_filter(array_map('intval', $sectionIds), static function ($value) {
        return $value > 0;
    })));

    foreach ($safeSectionIds as $sectionId) {
        $rowsBySection[$sectionId] = [];
    }

    if (empty($safeSectionIds) || $ayId <= 0 || $semester <= 0) {
        return $rowsBySection;
    }

    $sql = "
        SELECT section_id, offering_id
        FROM tbl_prospectus_offering
        WHERE ay_id = ?
          AND semester = ?
          AND section_id IN (" . implode(',', $safeSectionIds) . ")
        ORDER BY section_id ASC, offering_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rowsBySection;
    }

    $stmt->bind_param("ii", $ayId, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($res && ($row = $res->fetch_assoc())) {
        $sectionId = (int)($row['section_id'] ?? 0);
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($sectionId > 0 && $offeringId > 0) {
            $rowsBySection[$sectionId][] = $offeringId;
        }
    }

    $stmt->close();
    return $rowsBySection;
}

function load_schedule_matrix_rows_by_offering($conn, array $offeringIds, int $ayId, int $semester): array
{
    $rowsByOffering = [];
    $safeOfferingIds = synk_schedule_merge_normalize_offering_ids($offeringIds);
    if (empty($safeOfferingIds) || $ayId <= 0 || $semester <= 0) {
        return $rowsByOffering;
    }

    $liveOfferingJoins = synk_live_offering_join_sql('po', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            cs.offering_id,
            cs.schedule_id,
            cs.schedule_type,
            cs.time_start,
            cs.time_end,
            cs.days_json,
            sm.sub_code,
            sm.sub_description,
            COALESCE(
                NULLIF(TRIM(r.room_code), ''),
                NULLIF(TRIM(r.room_name), ''),
                'TBA'
            ) AS room_label
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        WHERE cs.offering_id IN (" . implode(',', array_map('intval', $safeOfferingIds)) . ")
          AND po.ay_id = ?
          AND po.semester = ?
          AND cs.schedule_type IN ('LEC', 'LAB')
        ORDER BY
            cs.offering_id ASC,
            cs.time_start ASC,
            sm.sub_code ASC,
            FIELD(cs.schedule_type, 'LEC', 'LAB'),
            cs.schedule_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rowsByOffering;
    }

    $stmt->bind_param("ii", $ayId, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($res && ($row = $res->fetch_assoc())) {
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($offeringId <= 0) {
            continue;
        }

        if (!isset($rowsByOffering[$offeringId])) {
            $rowsByOffering[$offeringId] = [];
        }

        $rowsByOffering[$offeringId][] = [
            'offering_id' => $offeringId,
            'schedule_id' => (int)($row['schedule_id'] ?? 0),
            'schedule_type' => synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC')),
            'time_start' => (string)($row['time_start'] ?? ''),
            'time_end' => (string)($row['time_end'] ?? ''),
            'days' => normalize_days_array(json_decode((string)($row['days_json'] ?? '[]'), true)),
            'subject_code' => (string)($row['sub_code'] ?? ''),
            'subject_description' => (string)($row['sub_description'] ?? ''),
            'room_label' => (string)($row['room_label'] ?? 'TBA')
        ];
    }

    $stmt->close();
    return $rowsByOffering;
}

function build_peer_section_schedule_matrix_payload($conn, array $ctx, int $college_id): array
{
    $peerSections = load_peer_section_rows_for_subject($conn, $ctx, $college_id);
    if (empty($peerSections)) {
        return [];
    }

    $sectionIds = array_map(static function (array $row): int {
        return (int)($row['section_id'] ?? 0);
    }, $peerSections);

    $sectionOfferingIds = load_term_offering_ids_by_section(
        $conn,
        $sectionIds,
        (int)($ctx['ay_id'] ?? 0),
        (int)($ctx['semester'] ?? 0)
    );

    $allOfferingIds = [];
    foreach ($sectionOfferingIds as $offeringIds) {
        $allOfferingIds = array_merge($allOfferingIds, $offeringIds);
    }

    $allOfferingIds = synk_schedule_merge_normalize_offering_ids($allOfferingIds);
    $mergeContext = empty($allOfferingIds)
        ? []
        : synk_schedule_merge_load_display_context($conn, $allOfferingIds);

    $effectiveBySection = [];
    foreach ($sectionOfferingIds as $sectionId => $offeringIds) {
        $effectiveIds = [];
        foreach ($offeringIds as $offeringId) {
            $mergeInfo = $mergeContext[$offeringId] ?? null;
            $effectiveIds[] = (int)($mergeInfo['owner_offering_id'] ?? $offeringId);
        }

        $effectiveBySection[$sectionId] = synk_schedule_merge_normalize_offering_ids($effectiveIds);
    }

    $allEffectiveIds = [];
    foreach ($effectiveBySection as $effectiveIds) {
        $allEffectiveIds = array_merge($allEffectiveIds, $effectiveIds);
    }
    $allEffectiveIds = synk_schedule_merge_normalize_offering_ids($allEffectiveIds);

    $rowsByOffering = load_schedule_matrix_rows_by_offering(
        $conn,
        $allEffectiveIds,
        (int)($ctx['ay_id'] ?? 0),
        (int)($ctx['semester'] ?? 0)
    );

    $sections = [];
    foreach ($peerSections as $sectionRow) {
        $sectionId = (int)($sectionRow['section_id'] ?? 0);
        $entries = [];

        foreach ($effectiveBySection[$sectionId] ?? [] as $effectiveOfferingId) {
            foreach ($rowsByOffering[$effectiveOfferingId] ?? [] as $entry) {
                $entries[] = $entry;
            }
        }

        usort($entries, static function (array $left, array $right): int {
            if ((string)($left['time_start'] ?? '') !== (string)($right['time_start'] ?? '')) {
                return strcmp((string)($left['time_start'] ?? ''), (string)($right['time_start'] ?? ''));
            }

            if ((string)($left['subject_code'] ?? '') !== (string)($right['subject_code'] ?? '')) {
                return strcmp((string)($left['subject_code'] ?? ''), (string)($right['subject_code'] ?? ''));
            }

            if ((string)($left['schedule_type'] ?? '') !== (string)($right['schedule_type'] ?? '')) {
                return strcmp((string)($left['schedule_type'] ?? ''), (string)($right['schedule_type'] ?? ''));
            }

            return (int)($left['schedule_id'] ?? 0) <=> (int)($right['schedule_id'] ?? 0);
        });

        $label = trim((string)($sectionRow['full_section'] ?? ''));
        if ($label === '') {
            $label = trim(
                (string)($sectionRow['program_code'] ?? '') . ' ' .
                (string)($sectionRow['section_name'] ?? '')
            );
        }

        $sections[] = [
            'section_id' => $sectionId,
            'offering_id' => (int)($sectionRow['offering_id'] ?? 0),
            'label' => $label,
            'full_section' => (string)($sectionRow['full_section'] ?? ''),
            'program_code' => (string)($sectionRow['program_code'] ?? ''),
            'section_name' => (string)($sectionRow['section_name'] ?? ''),
            'is_current' => $sectionId > 0 && $sectionId === (int)($ctx['section_id'] ?? 0),
            'entry_count' => count($entries),
            'entries' => $entries
        ];
    }

    return $sections;
}

function merge_required_minutes_signature(array $ctx): string {
    $required = synk_required_minutes_by_type(
        (float)($ctx['lec_units'] ?? 0),
        (float)($ctx['lab_units'] ?? 0),
        (float)($ctx['total_units'] ?? 0)
    );

    return (int)($required['LEC'] ?? 0) . '|' . (int)($required['LAB'] ?? 0);
}

function ensure_offering_not_merged_member($conn, $offering_id, $college_id) {
    $memberToOwner = synk_schedule_merge_load_member_to_owner_map($conn, [(int)$offering_id]);
    $ownerId = (int)($memberToOwner[(int)$offering_id] ?? 0);
    if ($ownerId <= 0) {
        return;
    }

    $ownerCtx = load_context_any($conn, $ownerId, $college_id);
    $ownerLabel = trim((string)($ownerCtx['full_section'] ?? ''));
    if ($ownerLabel === '') {
        $ownerLabel = trim((string)($ownerCtx['section_name'] ?? 'this schedule owner'));
    }

    respond(
        "error",
        "This offering inherits schedule from <b>" . htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8') . "</b>. Unmerge it first before editing or clearing its schedule."
    );
}

function load_other_faculty_workload_rows($conn, $faculty_id, $ay_id, $semester, $offering_id) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            cs.schedule_id,
            cs.schedule_type,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            sm.sub_code,
            sec.section_name
        FROM tbl_faculty_workload_sched fw
        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fw.schedule_id
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        WHERE fw.faculty_id = ?
          AND fw.ay_id = ?
          AND fw.semester = ?
          AND cs.offering_id <> ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $faculty_id, $ay_id, $semester, $offering_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['days'] = normalize_days_array(json_decode((string)$row['days_json'], true));
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function preview_room_validation_result($conn, $room_id, $college_id, $ay_id, $semester, $schedule_type) {
    $room = load_room_access_in_term($conn, $room_id, $college_id, $ay_id, $semester);
    if (!$room) {
        return [
            'room' => null,
            'message' => "Selected room is not available for this Academic Year and Semester."
        ];
    }

    if (!room_type_allows_schedule($room['room_type'], $schedule_type)) {
        $roomLabel = trim((string)($room['room_code'] ?: $room['room_name'] ?: 'Selected room'));
        $typeLabel = strtoupper(trim((string)$schedule_type)) === 'LAB'
            ? 'laboratory'
            : 'lecture';

        return [
            'room' => $room,
            'message' => "{$roomLabel} is not compatible with {$typeLabel} scheduling."
        ];
    }

    return [
        'room' => $room,
        'message' => null
    ];
}

function load_live_conflicts_for_schedule_block($conn, $ctx, $offering_id, $room_id, $days, $time_start, $time_end) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            cs.schedule_id,
            cs.room_id,
            cs.schedule_type,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            o.section_id,
            sec.section_name,
            sm.sub_code,
            COALESCE(NULLIF(TRIM(r.room_code), ''), NULLIF(TRIM(r.room_name), ''), 'Selected room') AS room_label
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND cs.offering_id <> ?
          AND cs.time_start < ?
          AND cs.time_end > ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'section_conflicts' => [],
            'room_conflicts' => []
        ];
    }

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

    $sectionConflicts = [];
    $roomConflicts = [];
    while ($row = $res->fetch_assoc()) {
        $existingDays = normalize_days_array(json_decode((string)($row['days_json'] ?? '[]'), true));
        if (!days_overlap($days, $existingDays)) {
            continue;
        }

        $entry = [
            'schedule_id' => (int)($row['schedule_id'] ?? 0),
            'section_name' => (string)($row['section_name'] ?? ''),
            'subject_code' => (string)($row['sub_code'] ?? ''),
            'schedule_type' => synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC')),
            'room_label' => (string)($row['room_label'] ?? 'Selected room'),
            'days' => $existingDays,
            'time_start' => (string)($row['time_start'] ?? ''),
            'time_end' => (string)($row['time_end'] ?? '')
        ];

        if ((int)($row['section_id'] ?? 0) > 0 && (int)$row['section_id'] === (int)($ctx['section_id'] ?? 0)) {
            $sectionConflicts[] = $entry;
        }

        if ((int)($row['room_id'] ?? 0) > 0 && (int)$row['room_id'] === (int)$room_id) {
            $roomConflicts[] = $entry;
        }
    }

    $stmt->close();

    return [
        'section_conflicts' => $sectionConflicts,
        'room_conflicts' => $roomConflicts
    ];
}

function load_faculty_conflicts_for_schedule_block($conn, $faculty_id, $ay_id, $semester, $offering_id, $days, $time_start, $time_end) {
    $conflicts = [];
    if ($faculty_id <= 0) {
        return $conflicts;
    }

    $otherSchedules = load_other_faculty_workload_rows($conn, $faculty_id, $ay_id, $semester, $offering_id);
    foreach ($otherSchedules as $other) {
        if (!days_overlap($days, $other['days'] ?? [])) {
            continue;
        }

        if (!time_overlap($time_start, $time_end, (string)($other['time_start'] ?? ''), (string)($other['time_end'] ?? ''))) {
            continue;
        }

        $conflicts[] = [
            'schedule_id' => (int)($other['schedule_id'] ?? 0),
            'subject_code' => (string)($other['sub_code'] ?? ''),
            'section_name' => (string)($other['section_name'] ?? ''),
            'schedule_type' => synk_normalize_schedule_type((string)($other['schedule_type'] ?? 'LEC')),
            'days' => normalize_days_array($other['days'] ?? []),
            'time_start' => (string)($other['time_start'] ?? ''),
            'time_end' => (string)($other['time_end'] ?? '')
        ];
    }

    return $conflicts;
}

function build_faculty_schedule_preview_payload($conn, array $ctx, $college_id, $offering_id, $faculty_id, $blocksRaw, array $policy) {
    $payload = [
        'draft_entries' => [],
        'preview_issues' => [],
        'draft_block_count' => 0,
        'draft_conflict_count' => 0,
        'draft_ready_count' => 0
    ];

    if (!is_array($blocksRaw) || empty($blocksRaw)) {
        return $payload;
    }

    $allowedTypes = required_schedule_types_for_context($ctx);
    $sequencePreview = ['LEC' => 0, 'LAB' => 0];
    $normalized = [];
    $sectionLabel = trim((string)($ctx['full_section'] ?? ''));
    if ($sectionLabel === '') {
        $sectionLabel = trim((string)($ctx['section_name'] ?? 'Section'));
    }

    foreach ($blocksRaw as $block) {
        if (!is_array($block)) {
            continue;
        }

        $rawType = strtoupper(trim((string)($block['type'] ?? '')));
        if (!in_array($rawType, ['LEC', 'LAB'], true)) {
            $payload['preview_issues'][] = "A draft block uses an invalid schedule type and was ignored.";
            continue;
        }

        $sequencePreview[$rawType]++;
        $label = block_label_for_response($rawType, $sequencePreview[$rawType]);

        if (!in_array($rawType, $allowedTypes, true)) {
            $payload['preview_issues'][] = "{$label} is not allowed for this subject.";
            continue;
        }

        $roomId = (int)($block['room_id'] ?? 0);
        $timeStart = normalize_time_input($block['time_start'] ?? '');
        $timeEnd = normalize_time_input($block['time_end'] ?? '');
        $days = normalize_days_array(json_decode((string)($block['days_json'] ?? ''), true));
        if (empty($days) && isset($block['days']) && is_array($block['days'])) {
            $days = normalize_days_array($block['days']);
        }

        if ($roomId <= 0 || !$timeStart || !$timeEnd || empty($days)) {
            $payload['preview_issues'][] = "{$label} is incomplete and was not included in the preview.";
            continue;
        }

        if ($timeEnd <= $timeStart) {
            $payload['preview_issues'][] = "{$label} must end later than it starts.";
            continue;
        }

        $policyIssue = schedule_policy_issue_message($days, $timeStart, $timeEnd, $label, $policy);
        if ($policyIssue !== null) {
            $payload['preview_issues'][] = $policyIssue;
            continue;
        }

        $roomValidation = preview_room_validation_result(
            $conn,
            $roomId,
            $college_id,
            (int)($ctx['ay_id'] ?? 0),
            (int)($ctx['semester'] ?? 0),
            $rawType
        );
        if ($roomValidation['message'] !== null) {
            $payload['preview_issues'][] = "{$label}: {$roomValidation['message']}";
            continue;
        }

        $room = $roomValidation['room'] ?? [];
        $roomLabel = trim((string)(($room['room_code'] ?? '') ?: ($room['room_name'] ?? '') ?: 'Selected room'));

        $normalized[] = [
            'schedule_id' => 0,
            'offering_id' => (int)$offering_id,
            'schedule_type' => $rawType,
            'room_id' => $roomId,
            'subject_code' => (string)($ctx['sub_code'] ?? ''),
            'subject_description' => (string)($ctx['sub_description'] ?? ''),
            'section_label' => $sectionLabel,
            'room_label' => $roomLabel,
            'days' => $days,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'is_preview_block' => true,
            'is_preview_conflict' => false,
            'preview_label' => $label,
            'preview_conflict_types' => [],
            'preview_conflict_details' => [],
            'preview_status_note' => 'Ready to save'
        ];
    }

    for ($i = 0; $i < count($normalized); $i++) {
        for ($j = $i + 1; $j < count($normalized); $j++) {
            if (!days_overlap($normalized[$i]['days'], $normalized[$j]['days'])) {
                continue;
            }

            if (!time_overlap($normalized[$i]['time_start'], $normalized[$i]['time_end'], $normalized[$j]['time_start'], $normalized[$j]['time_end'])) {
                continue;
            }

            $leftLabel = (string)($normalized[$i]['preview_label'] ?? 'Draft block');
            $rightLabel = (string)($normalized[$j]['preview_label'] ?? 'Draft block');
            $leftMessage = "Internal conflict with {$rightLabel}.";
            $rightMessage = "Internal conflict with {$leftLabel}.";

            if (!in_array('Internal Conflict', $normalized[$i]['preview_conflict_types'], true)) {
                $normalized[$i]['preview_conflict_types'][] = 'Internal Conflict';
            }
            if (!in_array($leftMessage, $normalized[$i]['preview_conflict_details'], true)) {
                $normalized[$i]['preview_conflict_details'][] = $leftMessage;
            }

            if (!in_array('Internal Conflict', $normalized[$j]['preview_conflict_types'], true)) {
                $normalized[$j]['preview_conflict_types'][] = 'Internal Conflict';
            }
            if (!in_array($rightMessage, $normalized[$j]['preview_conflict_details'], true)) {
                $normalized[$j]['preview_conflict_details'][] = $rightMessage;
            }
        }
    }

    foreach ($normalized as &$entry) {
        $sectionRoomConflicts = load_live_conflicts_for_schedule_block(
            $conn,
            $ctx,
            $offering_id,
            (int)($entry['room_id'] ?? 0),
            $entry['days'],
            (string)$entry['time_start'],
            (string)$entry['time_end']
        );

        if (!empty($sectionRoomConflicts['section_conflicts'])) {
            if (!in_array('Section Conflict', $entry['preview_conflict_types'], true)) {
                $entry['preview_conflict_types'][] = 'Section Conflict';
            }

            $sectionItem = $sectionRoomConflicts['section_conflicts'][0];
            $entry['preview_conflict_details'][] = "Section already has a class at " .
                days_fmt($sectionItem['days']) . " " .
                time_fmt($sectionItem['time_start']) . " - " .
                time_fmt($sectionItem['time_end']) . ".";
        }

        if (!empty($sectionRoomConflicts['room_conflicts'])) {
            if (!in_array('Room Conflict', $entry['preview_conflict_types'], true)) {
                $entry['preview_conflict_types'][] = 'Room Conflict';
            }

            $roomItem = $sectionRoomConflicts['room_conflicts'][0];
            $entry['preview_conflict_details'][] = trim((string)($entry['room_label'] ?? 'Selected room')) .
                " is already used by " .
                trim((string)($roomItem['section_name'] ?? 'another section')) .
                " at " .
                days_fmt($roomItem['days']) . " " .
                time_fmt($roomItem['time_start']) . " - " .
                time_fmt($roomItem['time_end']) . ".";
        }

        $facultyConflicts = load_faculty_conflicts_for_schedule_block(
            $conn,
            (int)$faculty_id,
            (int)($ctx['ay_id'] ?? 0),
            (int)($ctx['semester'] ?? 0),
            $offering_id,
            $entry['days'],
            (string)$entry['time_start'],
            (string)$entry['time_end']
        );
        if (!empty($facultyConflicts)) {
            if (!in_array('Faculty Conflict', $entry['preview_conflict_types'], true)) {
                $entry['preview_conflict_types'][] = 'Faculty Conflict';
            }

            $facultyItem = $facultyConflicts[0];
            $entry['preview_conflict_details'][] = "Faculty already handles " .
                trim((string)($facultyItem['subject_code'] ?? 'another class')) .
                " (" . trim((string)($facultyItem['section_name'] ?? 'Section')) .
                ", " . trim((string)($facultyItem['schedule_type'] ?? 'LEC')) .
                ") at " .
                days_fmt($facultyItem['days']) . " " .
                time_fmt($facultyItem['time_start']) . " - " .
                time_fmt($facultyItem['time_end']) . ".";
        }

        $entry['preview_conflict_details'] = array_values(array_unique($entry['preview_conflict_details']));
        $entry['is_preview_conflict'] = !empty($entry['preview_conflict_types']);
        $entry['preview_status_note'] = $entry['is_preview_conflict']
            ? 'Not ready to save'
            : 'Ready to save';
    }
    unset($entry);

    $payload['draft_entries'] = $normalized;
    $payload['draft_block_count'] = count($normalized);
    $payload['draft_conflict_count'] = count(array_filter($normalized, static function ($entry) {
        return !empty($entry['is_preview_conflict']);
    }));
    $payload['draft_ready_count'] = count($normalized) - $payload['draft_conflict_count'];

    return $payload;
}

function check_assigned_faculty_conflicts($conn, $ctx, $offering_id, $draftSchedules) {
    $assignedFaculties = load_workload_faculties_for_offering(
        $conn,
        $offering_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester']
    );

    if (empty($assignedFaculties)) {
        return;
    }

    foreach ($assignedFaculties as $faculty) {
        $otherSchedules = load_other_faculty_workload_rows(
            $conn,
            (int)$faculty['faculty_id'],
            (int)$ctx['ay_id'],
            (int)$ctx['semester'],
            $offering_id
        );

        $items = [];
        foreach ($draftSchedules as $draft) {
            foreach ($otherSchedules as $other) {
                if (!days_overlap($draft['days'], $other['days'])) {
                    continue;
                }

                if (!time_overlap($draft['start'], $draft['end'], $other['time_start'], $other['time_end'])) {
                    continue;
                }

                $draftWhen = days_fmt($draft['days']) . " " . time_fmt($draft['start']) . " - " . time_fmt($draft['end']);
                $otherWhen = days_fmt($other['days']) . " " . time_fmt($other['time_start']) . " - " . time_fmt($other['time_end']);

                $items[] = "<li><b>{$draft['type']}</b> {$draftWhen} conflicts with <b>" .
                    htmlspecialchars(strtoupper((string)$other['sub_code']), ENT_QUOTES, 'UTF-8') .
                    " (" . htmlspecialchars((string)$other['section_name'], ENT_QUOTES, 'UTF-8') .
                    ", " . htmlspecialchars((string)$other['schedule_type'], ENT_QUOTES, 'UTF-8') .
                    ")</b><br><small>{$otherWhen}</small></li>";

                if (count($items) >= 8) {
                    break 2;
                }
            }
        }

        if (!empty($items)) {
            $facultyName = htmlspecialchars((string)$faculty['faculty_name'], ENT_QUOTES, 'UTF-8');
            respond(
                "conflict",
                "<b>Faculty Conflict</b><br>This class is already assigned to <b>{$facultyName}</b> in Faculty Workload. Updating the schedule would overlap with the faculty's other assigned classes.<ul class='mb-0 mt-2'>" .
                implode('', $items) .
                "</ul>"
            );
        }
    }
}

function update_schedule_row($conn, $schedule_id, $schedule_type, $group_id, $room_id, $days_json, $time_start, $time_end) {
    if ($group_id === null) {
        $sql = "
            UPDATE tbl_class_schedule
            SET schedule_group_id = NULL,
                room_id = ?,
                days_json = ?,
                time_start = ?,
                time_end = ?
            WHERE schedule_id = ?
              AND schedule_type = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssis", $room_id, $days_json, $time_start, $time_end, $schedule_id, $schedule_type);
    } else {
        $sql = "
            UPDATE tbl_class_schedule
            SET schedule_group_id = ?,
                room_id = ?,
                days_json = ?,
                time_start = ?,
                time_end = ?
            WHERE schedule_id = ?
              AND schedule_type = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssis", $group_id, $room_id, $days_json, $time_start, $time_end, $schedule_id, $schedule_type);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        throw new RuntimeException("Failed to update {$schedule_type} schedule.");
    }
}

function insert_schedule_row($conn, $offering_id, $schedule_type, $group_id, $room_id, $days_json, $time_start, $time_end, $user_id) {
    if ($group_id === null) {
        $sql = "
            INSERT INTO tbl_class_schedule
            (offering_id, schedule_type, room_id, days_json, time_start, time_end, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isisssi", $offering_id, $schedule_type, $room_id, $days_json, $time_start, $time_end, $user_id);
    } else {
        $sql = "
            INSERT INTO tbl_class_schedule
            (offering_id, schedule_type, schedule_group_id, room_id, days_json, time_start, time_end, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isiisssi", $offering_id, $schedule_type, $group_id, $room_id, $days_json, $time_start, $time_end, $user_id);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        throw new RuntimeException("Failed to insert {$schedule_type} schedule.");
    }
}

function update_schedule_block_row($conn, $schedule_id, $schedule_type, $group_id, $room_id, $days_json, $time_start, $time_end) {
    if ($group_id === null) {
        $sql = "
            UPDATE tbl_class_schedule
            SET schedule_type = ?,
                schedule_group_id = NULL,
                room_id = ?,
                days_json = ?,
                time_start = ?,
                time_end = ?
            WHERE schedule_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssi", $schedule_type, $room_id, $days_json, $time_start, $time_end, $schedule_id);
    } else {
        $sql = "
            UPDATE tbl_class_schedule
            SET schedule_type = ?,
                schedule_group_id = ?,
                room_id = ?,
                days_json = ?,
                time_start = ?,
                time_end = ?
            WHERE schedule_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siisssi", $schedule_type, $group_id, $room_id, $days_json, $time_start, $time_end, $schedule_id);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        throw new RuntimeException("Failed to update schedule block.");
    }
}

function insert_schedule_block_row($conn, $offering_id, $schedule_type, $group_id, $room_id, $days_json, $time_start, $time_end, $user_id) {
    insert_schedule_row(
        $conn,
        $offering_id,
        $schedule_type,
        $group_id,
        $room_id,
        $days_json,
        $time_start,
        $time_end,
        $user_id
    );
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

function block_label_for_response($type, $sequence) {
    $base = strtoupper(trim((string)$type)) === 'LAB' ? 'Laboratory' : 'Lecture';
    return $base . ' ' . max(1, (int)$sequence);
}

/* =====================================================
   LOAD DYNAMIC SCHEDULE BLOCKS
===================================================== */
if (isset($_POST['load_schedule_blocks'])) {
    $offering_id = (int)($_POST['offering_id'] ?? 0);
    if (!$offering_id) {
        respond("error", "Missing offering reference.");
    }

    ensure_offering_not_merged_member($conn, $offering_id, $college_id);

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $rows = load_schedule_block_rows($conn, $offering_id);
    $coverage = build_schedule_coverage_summary($ctx, $rows);
    $sequenceByType = ['LEC' => 0, 'LAB' => 0];
    $blocks = [];

    foreach ($rows as $row) {
        $type = synk_normalize_schedule_type((string)$row['schedule_type']);
        $sequenceByType[$type]++;
        $metrics = synk_schedule_block_metrics_from_row([
            'schedule_type' => $type,
            'days' => $row['days'],
            'time_start' => (string)$row['time_start'],
            'time_end' => (string)$row['time_end'],
            'lec_units' => (float)($ctx['lec_units'] ?? 0),
            'lab_units' => (float)($ctx['lab_units'] ?? 0),
            'total_units' => (float)($ctx['total_units'] ?? 0)
        ]);

        $blocks[] = [
            'schedule_id' => (int)$row['schedule_id'],
            'group_id' => (int)($row['schedule_group_id'] ?? 0),
            'type' => $type,
            'label' => block_label_for_response($type, $sequenceByType[$type]),
            'room_id' => (int)$row['room_id'],
            'time_start' => (string)$row['time_start'],
            'time_end' => (string)$row['time_end'],
            'days' => $row['days'],
            'days_json' => json_encode($row['days']),
            'weekly_minutes' => $metrics['weekly_minutes'],
            'units' => $metrics['units'],
            'hours_lec' => $metrics['hours_lec'],
            'hours_lab' => $metrics['hours_lab'],
            'faculty_load' => $metrics['faculty_load']
        ];
    }

    respond("ok", "Loaded schedule blocks.", [
        'status_key' => $coverage['status'],
        'required_minutes' => $coverage['required_minutes'],
        'scheduled_minutes' => $coverage['scheduled_minutes'],
        'required_types' => required_schedule_types_for_context($ctx),
        'blocks' => $blocks
    ]);
}

/* =====================================================
   LOAD PEER SECTION SCHEDULE MATRIX
===================================================== */
if (isset($_POST['load_section_schedule_matrix'])) {
    $offering_id = (int)($_POST['offering_id'] ?? 0);
    if ($offering_id <= 0) {
        respond("error", "Missing offering reference.");
    }

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $sections = build_peer_section_schedule_matrix_payload($conn, $ctx, $college_id);
    if (empty($sections)) {
        respond("ok", "No peer sections were found for this subject.", [
            'current_section_id' => (int)($ctx['section_id'] ?? 0),
            'subject_code' => (string)($ctx['sub_code'] ?? ''),
            'subject_description' => (string)($ctx['sub_description'] ?? ''),
            'sections' => []
        ]);
    }

    respond("ok", "Loaded peer section matrix.", [
        'current_section_id' => (int)($ctx['section_id'] ?? 0),
        'subject_code' => (string)($ctx['sub_code'] ?? ''),
        'subject_description' => (string)($ctx['sub_description'] ?? ''),
        'sections' => $sections
    ]);
}

/* =====================================================
   LOAD FACULTY OPTIONS FOR BLOCK SCHEDULER
===================================================== */
if (isset($_POST['load_schedule_faculty_options'])) {
    $offering_id = (int)($_POST['offering_id'] ?? 0);
    if ($offering_id <= 0) {
        respond("error", "Missing offering reference.");
    }

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $facultyRows = load_college_term_faculty_rows(
        $conn,
        $college_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester']
    );

    $facultyIds = array_map(static function ($row) {
        return (int)($row['faculty_id'] ?? 0);
    }, $facultyRows);

    $countMap = load_faculty_schedule_counts_by_faculty(
        $conn,
        $facultyIds,
        (int)$ctx['ay_id'],
        (int)$ctx['semester'],
        $college_id
    );

    $assignedFaculties = load_workload_faculties_for_offering(
        $conn,
        $offering_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester']
    );
    $assignedSet = [];
    foreach ($assignedFaculties as $faculty) {
        $facultyId = (int)($faculty['faculty_id'] ?? 0);
        if ($facultyId > 0) {
            $assignedSet[$facultyId] = true;
        }
    }

    $faculty = [];
    foreach ($facultyRows as $row) {
        $facultyId = (int)($row['faculty_id'] ?? 0);
        $counts = $countMap[$facultyId] ?? [
            'scheduled_block_count' => 0,
            'scheduled_class_count' => 0
        ];

        $faculty[] = [
            'faculty_id' => $facultyId,
            'faculty_name' => (string)($row['faculty_name'] ?? format_faculty_display_name($row)),
            'scheduled_block_count' => (int)($counts['scheduled_block_count'] ?? 0),
            'scheduled_class_count' => (int)($counts['scheduled_class_count'] ?? 0),
            'is_assigned' => isset($assignedSet[$facultyId])
        ];
    }

    respond("ok", "Loaded faculty options.", [
        'assigned_faculty_ids' => array_values(array_map('intval', array_keys($assignedSet))),
        'faculty' => $faculty
    ]);
}

/* =====================================================
   LOAD FACULTY SCHEDULE OVERVIEW FOR BLOCK SCHEDULER
===================================================== */
if (isset($_POST['load_faculty_schedule_overview'])) {
    $offering_id = (int)($_POST['offering_id'] ?? 0);
    $faculty_id = (int)($_POST['faculty_id'] ?? 0);
    $blocksRaw = $_POST['blocks'] ?? [];

    if ($offering_id <= 0 || $faculty_id <= 0) {
        respond("error", "Missing faculty schedule context.");
    }

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $facultyRow = load_college_term_faculty_record(
        $conn,
        $college_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester'],
        $faculty_id
    );

    if (!$facultyRow) {
        respond("error", "Selected faculty is not available under this college term.");
    }

    $countMap = load_faculty_schedule_counts_by_faculty(
        $conn,
        [$faculty_id],
        (int)$ctx['ay_id'],
        (int)$ctx['semester'],
        $college_id
    );
    $counts = $countMap[$faculty_id] ?? [
        'scheduled_block_count' => 0,
        'scheduled_class_count' => 0
    ];

    $assignedFaculties = load_workload_faculties_for_offering(
        $conn,
        $offering_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester']
    );
    $isAssigned = false;
    foreach ($assignedFaculties as $faculty) {
        if ((int)($faculty['faculty_id'] ?? 0) === $faculty_id) {
            $isAssigned = true;
            break;
        }
    }

    $entries = load_faculty_schedule_entries_for_term(
        $conn,
        $faculty_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester'],
        $college_id,
        $offering_id
    );
    $otherAssignedEntries = load_other_faculty_schedule_entries_for_term(
        $conn,
        $faculty_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester'],
        $college_id,
        $offering_id
    );
    $previewPayload = build_faculty_schedule_preview_payload(
        $conn,
        $ctx,
        $college_id,
        $offering_id,
        $faculty_id,
        $blocksRaw,
        $schedulePolicy
    );
    respond("ok", "Loaded faculty schedule overview.", [
        'faculty' => [
            'faculty_id' => $faculty_id,
            'faculty_name' => (string)($facultyRow['faculty_name'] ?? format_faculty_display_name($facultyRow)),
            'scheduled_block_count' => (int)($counts['scheduled_block_count'] ?? 0),
            'scheduled_class_count' => (int)($counts['scheduled_class_count'] ?? 0),
            'is_assigned' => $isAssigned
        ],
        'entries' => $entries,
        'other_assigned_entries' => $otherAssignedEntries,
        'draft_entries' => $previewPayload['draft_entries'],
        'preview_issues' => $previewPayload['preview_issues'],
        'draft_block_count' => (int)($previewPayload['draft_block_count'] ?? 0),
        'draft_conflict_count' => (int)($previewPayload['draft_conflict_count'] ?? 0),
        'draft_ready_count' => (int)($previewPayload['draft_ready_count'] ?? 0)
    ]);
}

/* =====================================================
   SAVE DYNAMIC SCHEDULE BLOCKS
===================================================== */
if (isset($_POST['save_schedule_blocks'])) {
    $offering_id = (int)($_POST['offering_id'] ?? 0);
    $blocksRaw = $_POST['blocks'] ?? null;

    if ($offering_id <= 0 || !is_array($blocksRaw)) {
        respond("error", "Invalid schedule payload.");
    }

    ensure_offering_not_merged_member($conn, $offering_id, $college_id);

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $assignedFaculties = load_workload_faculties_for_offering(
        $conn,
        $offering_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester']
    );

    if (!empty($assignedFaculties)) {
        $names = array_map(static function ($faculty) {
            return htmlspecialchars((string)$faculty['faculty_name'], ENT_QUOTES, 'UTF-8');
        }, $assignedFaculties);

        respond(
            "error",
            "This schedule cannot be changed because it is already assigned in Faculty Workload to <b>" .
            implode('</b>, <b>', $names) .
            "</b>. Remove the workload assignment first."
        );
    }

    $existingRows = load_schedule_block_rows($conn, $offering_id);
    $existingById = [];
    $existingGroupId = null;

    foreach ($existingRows as $row) {
        $existingById[(int)$row['schedule_id']] = $row;
        $candidateGroupId = (int)($row['schedule_group_id'] ?? 0);
        if ($candidateGroupId > 0 && $existingGroupId === null) {
            $existingGroupId = $candidateGroupId;
        }
    }

    $allowedTypes = required_schedule_types_for_context($ctx);
    $normalized = [];
    $sequencePreview = ['LEC' => 0, 'LAB' => 0];

    foreach ($blocksRaw as $blockIndex => $block) {
        if (!is_array($block)) {
            continue;
        }

        $rawType = strtoupper(trim((string)($block['type'] ?? '')));
        if (!in_array($rawType, ['LEC', 'LAB'], true)) {
            respond("error", "Invalid schedule block type.");
        }

        if (!in_array($rawType, $allowedTypes, true)) {
            respond("error", "This subject does not allow {$rawType} schedule blocks.");
        }

        $scheduleId = (int)($block['schedule_id'] ?? 0);
        if ($scheduleId > 0 && !isset($existingById[$scheduleId])) {
            respond("error", "A schedule block could not be matched to this offering.");
        }

        $roomId = (int)($block['room_id'] ?? 0);
        $timeStart = normalize_time_input($block['time_start'] ?? '');
        $timeEnd = normalize_time_input($block['time_end'] ?? '');
        $days = normalize_days_array(json_decode((string)($block['days_json'] ?? ''), true));

        if (empty($days) && isset($block['days']) && is_array($block['days'])) {
            $days = normalize_days_array($block['days']);
        }

        $sequencePreview[$rawType]++;
        $label = block_label_for_response($rawType, $sequencePreview[$rawType]);

        if ($roomId <= 0 || !$timeStart || !$timeEnd || empty($days)) {
            respond("error", "{$label} is incomplete.");
        }

        if ($timeEnd <= $timeStart) {
            respond("error", "{$label} must end later than it starts.");
        }

        validate_schedule_policy($days, $timeStart, $timeEnd, $label, $schedulePolicy);
        validate_room_for_schedule($conn, $roomId, $college_id, (int)$ctx['ay_id'], (int)$ctx['semester'], $rawType);

        $normalized[] = [
            'schedule_id' => $scheduleId,
            'type' => $rawType,
            'label' => $label,
            'room_id' => $roomId,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'days' => $days,
            'days_json' => json_encode($days)
        ];
    }

    if (empty($normalized)) {
        respond("error", "Add at least one lecture or laboratory block.");
    }

    for ($i = 0; $i < count($normalized); $i++) {
        for ($j = $i + 1; $j < count($normalized); $j++) {
            $left = $normalized[$i];
            $right = $normalized[$j];

            if (!days_overlap($left['days'], $right['days'])) {
                continue;
            }

            if (!time_overlap($left['time_start'], $left['time_end'], $right['time_start'], $right['time_end'])) {
                continue;
            }

            respond(
                "conflict",
                "<b>Internal Schedule Conflict</b><br><b>{$left['label']}</b> overlaps with <b>{$right['label']}</b>."
            );
        }
    }

    foreach ($normalized as $block) {
        check_conflict(
            $conn,
            $ctx,
            $offering_id,
            (int)$block['room_id'],
            (string)$block['time_start'],
            (string)$block['time_end'],
            $block['days'],
            (string)$block['label']
        );
    }

    $group_id = count($normalized) > 1 ? ($existingGroupId ?: random_int(100000, 2147483647)) : null;
    $keepIds = [];

    $conn->begin_transaction();
    try {
        foreach ($normalized as $block) {
            if ((int)$block['schedule_id'] > 0) {
                $keepIds[(int)$block['schedule_id']] = true;
                update_schedule_block_row(
                    $conn,
                    (int)$block['schedule_id'],
                    (string)$block['type'],
                    $group_id,
                    (int)$block['room_id'],
                    (string)$block['days_json'],
                    (string)$block['time_start'],
                    (string)$block['time_end']
                );
                continue;
            }

            insert_schedule_block_row(
                $conn,
                $offering_id,
                (string)$block['type'],
                $group_id,
                (int)$block['room_id'],
                (string)$block['days_json'],
                (string)$block['time_start'],
                (string)$block['time_end'],
                (int)$_SESSION['user_id']
            );
        }

        $staleIds = [];
        foreach ($existingRows as $row) {
            $scheduleId = (int)$row['schedule_id'];
            if (!isset($keepIds[$scheduleId])) {
                $staleIds[] = $scheduleId;
            }
        }

        if (!empty($staleIds)) {
            $idList = implode(',', array_map('intval', $staleIds));
            $del = $conn->prepare("
                DELETE FROM tbl_class_schedule
                WHERE offering_id = ?
                  AND schedule_id IN ({$idList})
            ");
            $del->bind_param("i", $offering_id);
            if (!$del->execute()) {
                throw new RuntimeException("Failed to remove stale schedule blocks.");
            }
            $del->close();
        }

        $coverage = build_schedule_coverage_summary($ctx, $normalized);
        $offeringState = $coverage['status'] === 'complete' ? 'active' : 'pending';
        if (!mark_offering_schedule_state($conn, $offering_id, $offeringState)) {
            throw new RuntimeException("Failed to update offering status.");
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond("error", "Failed to save schedule blocks.");
    }

    respond("ok", "Schedule blocks saved.", [
        'status_key' => $coverage['status'],
        'required_minutes' => $coverage['required_minutes'],
        'scheduled_minutes' => $coverage['scheduled_minutes']
    ]);
}

/* =====================================================
   LOAD DUAL SCHEDULE (EDIT MODE)
===================================================== */
if (isset($_POST['load_dual_schedule'])) {
    $offering_id = (int)($_POST['offering_id'] ?? 0);
    if (!$offering_id) {
        respond("error", "Missing offering reference.");
    }

    ensure_offering_not_merged_member($conn, $offering_id, $college_id);

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    if (!in_array('LAB', required_schedule_types_for_context($ctx), true)) {
        respond("error", "This subject does not require a laboratory schedule.");
    }

    $rows = load_existing_schedule_rows($conn, $offering_id);
    if (empty($rows)) {
        respond("error", "No saved lecture/laboratory schedule found for this offering.");
    }

    $data = ["group_id" => null, "LEC" => null, "LAB" => null];
    foreach ($rows as $r) {
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
   CLEAR ALL SCHEDULES IN CURRENT COLLEGE SCOPE
===================================================== */
if (isset($_POST['clear_all_college_schedules'])) {
    $ay_id = (int)($_POST['ay_id'] ?? 0);
    $semester = (int)($_POST['semester'] ?? 0);

    if (!$ay_id || !in_array($semester, [1, 2, 3], true)) {
        respond("error", "Select Academic Year and Semester first.");
    }

    $offerings = load_scoped_offerings_for_college_term($conn, $ay_id, $semester, $college_id);
    if (empty($offerings)) {
        respond("ok", "No class offerings found for the selected college term.", [
            "scoped_offering_count" => 0,
            "clearable_offering_count" => 0,
            "cleared_offering_count" => 0,
            "deleted_schedule_row_count" => 0,
            "reset_offering_count" => 0,
            "skipped_count" => 0,
            "skipped" => []
        ]);
    }

    $offeringIds = array_map(static function ($offering) {
        return (int)$offering['offering_id'];
    }, $offerings);

    $workloadMap = load_workload_faculty_map_for_offerings($conn, $offeringIds, $ay_id, $semester);
    $scheduleCounts = load_schedule_row_counts_for_offerings($conn, $offeringIds);
    $mergeContext = synk_schedule_merge_load_display_context($conn, $offeringIds);
    $offeringsById = [];
    $groups = [];

    foreach ($offerings as $offering) {
        $offeringId = (int)$offering['offering_id'];
        $offeringsById[$offeringId] = $offering;
        $ownerId = (int)($mergeContext[$offeringId]['owner_offering_id'] ?? $offeringId);
        if (!isset($groups[$ownerId])) {
            $groups[$ownerId] = [];
        }
        $groups[$ownerId][] = $offeringId;
    }

    $clearableIds = [];
    $skipped = [];

    $deletedRows = 0;
    $resetOfferings = 0;
    $clearedOfferingCount = 0;

    foreach ($groups as $ownerId => $groupOfferingIds) {
        $groupOfferingIds = synk_schedule_merge_normalize_offering_ids($groupOfferingIds);
        $skipReason = "";

        foreach ($groupOfferingIds as $offeringId) {
            $offering = $offeringsById[$offeringId] ?? null;
            if (!$offering) {
                continue;
            }

            if (($offering['offering_status'] ?? '') === 'locked') {
                $skipReason = "This offering belongs to a merged group that includes a locked offering.";
                break;
            }
        }

        if ($skipReason === "") {
            $groupFacultyNames = [];
            foreach ($groupOfferingIds as $offeringId) {
                foreach (($workloadMap[$offeringId] ?? []) as $facultyName) {
                    $groupFacultyNames[$facultyName] = $facultyName;
                }
            }

            if (!empty($groupFacultyNames)) {
                $skipReason = "Assigned in Faculty Workload to " . implode(', ', array_values($groupFacultyNames)) . ".";
            }
        }

        if ($skipReason !== "") {
            foreach ($groupOfferingIds as $offeringId) {
                $offering = $offeringsById[$offeringId] ?? null;
                if (!$offering) {
                    continue;
                }

                $skipped[] = [
                    "offering_id" => $offeringId,
                    "sub_code" => (string)$offering['sub_code'],
                    "section_name" => (string)$offering['section_name'],
                    "reason" => $skipReason
                ];
            }
            continue;
        }

        foreach ($groupOfferingIds as $offeringId) {
            $clearableIds[] = $offeringId;
            $mergeInfo = $mergeContext[$offeringId] ?? null;
            if (($scheduleCounts[$offeringId] ?? 0) > 0 || (int)($mergeInfo['group_size'] ?? 1) > 1) {
                $clearedOfferingCount++;
            }
        }
    }

    if (!empty($clearableIds)) {
        $safeIds = array_map('intval', array_values(array_unique($clearableIds)));
        $idList = implode(',', $safeIds);

        $conn->begin_transaction();
        try {
            if (!$conn->query("
                DELETE FROM tbl_class_schedule
                WHERE offering_id IN ({$idList})
            ")) {
                throw new RuntimeException("Failed to clear college-term schedule rows.");
            }
            $deletedRows = max(0, (int)$conn->affected_rows);

            if (synk_schedule_merge_table_exists($conn) && !$conn->query("
                DELETE FROM `" . synk_schedule_merge_table_name() . "`
                WHERE owner_offering_id IN ({$idList})
                   OR member_offering_id IN ({$idList})
            ")) {
                throw new RuntimeException("Failed to clear college-term merge rows.");
            }

            if (!$conn->query("
                UPDATE tbl_prospectus_offering
                SET status = 'pending'
                WHERE offering_id IN ({$idList})
                  AND (status IS NULL OR status != 'locked')
            ")) {
                throw new RuntimeException("Failed to reset offering status.");
            }
            $resetOfferings = max(0, (int)$conn->affected_rows);

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            respond("error", "Failed to clear schedules for the selected college term.");
        }
    }

    $status = !empty($skipped) ? "partial" : "ok";
    $message = ($deletedRows > 0 || $resetOfferings > 0)
        ? "Schedules cleared for the selected college term."
        : "No existing schedules were found to clear for the selected college term.";

    respond($status, $message, [
        "scoped_offering_count" => count($offerings),
        "clearable_offering_count" => count($clearableIds),
        "cleared_offering_count" => $clearedOfferingCount,
        "deleted_schedule_row_count" => $deletedRows,
        "reset_offering_count" => $resetOfferings,
        "skipped_count" => count($skipped),
        "skipped" => $skipped
    ]);
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

    $memberToOwner = synk_schedule_merge_load_member_to_owner_map($conn, [$offering_id]);
    if (isset($memberToOwner[$offering_id])) {
        $ownerCtx = load_context_any($conn, (int)$memberToOwner[$offering_id], $college_id);
        $ownerLabel = trim((string)($ownerCtx['full_section'] ?? ''));
        if ($ownerLabel === '') {
            $ownerLabel = trim((string)($ownerCtx['section_name'] ?? 'this schedule owner'));
        }

        respond(
            "error",
            "This offering inherits schedule from <b>" . htmlspecialchars($ownerLabel, ENT_QUOTES, 'UTF-8') . "</b>. Unmerge it first before clearing."
        );
    }

    $ownerToMembers = synk_schedule_merge_load_owner_to_members_map($conn, [$offering_id]);
    $memberIds = $ownerToMembers[$offering_id] ?? [];
    if (!empty($memberIds)) {
        respond(
            "error",
            "This offering is the schedule owner of one or more merged offerings. Manage the merge group first before clearing the owner schedule."
        );
    }

    $assignedFaculties = load_workload_faculties_for_offering(
        $conn,
        $offering_id,
        (int)$ctx['ay_id'],
        (int)$ctx['semester']
    );

    if (!empty($assignedFaculties)) {
        $names = array_map(static function ($faculty) {
            return htmlspecialchars((string)$faculty['faculty_name'], ENT_QUOTES, 'UTF-8');
        }, $assignedFaculties);

        respond(
            "error",
            "Cannot clear this schedule because it is already assigned in Faculty Workload to <b>" .
            implode('</b>, <b>', $names) .
            "</b>. Remove the workload assignment first."
        );
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
   LOAD MERGE CANDIDATES FOR A SCHEDULE OWNER
===================================================== */
if (isset($_POST['load_schedule_merge_candidates'])) {
    $requestedOfferingId = (int)($_POST['offering_id'] ?? 0);
    if ($requestedOfferingId <= 0) {
        respond("error", "Missing offering reference.");
    }

    $memberToOwner = synk_schedule_merge_load_member_to_owner_map($conn, [$requestedOfferingId]);
    $ownerOfferingId = synk_schedule_merge_resolve_owner_id($requestedOfferingId, $memberToOwner);

    $ownerRows = load_merge_context_rows_for_offerings($conn, [$ownerOfferingId], $college_id);
    $owner = $ownerRows[$ownerOfferingId] ?? null;
    if (!$owner) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    $ownerMemberMap = synk_schedule_merge_load_owner_to_members_map($conn, [$ownerOfferingId]);
    $currentMemberIds = $ownerMemberMap[$ownerOfferingId] ?? [];

    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            o.offering_id
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
          AND ps.sub_id = ?
        ORDER BY p.program_code ASC, sec.full_section ASC, o.offering_id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond("error", "Unable to load merge candidates.");
    }

    $subjectId = (int)($owner['sub_id'] ?? 0);
    $stmt->bind_param("iiii", $owner['ay_id'], $owner['semester'], $college_id, $subjectId);
    $stmt->execute();
    $res = $stmt->get_result();

    $candidateIds = [];
    while ($row = $res->fetch_assoc()) {
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($offeringId > 0) {
            $candidateIds[] = $offeringId;
        }
    }
    $stmt->close();

    $candidateRows = load_merge_context_rows_for_offerings($conn, $candidateIds, $college_id);
    $mergeContext = synk_schedule_merge_load_display_context($conn, $candidateIds);
    $scheduleCounts = load_schedule_row_counts_for_offerings($conn, $candidateIds);
    $workloadMap = load_workload_faculty_map_for_offerings($conn, $candidateIds, (int)$owner['ay_id'], (int)$owner['semester']);
    $ownerMaps = synk_schedule_merge_load_owner_to_members_map($conn, $candidateIds);
    $ownerSignature = merge_required_minutes_signature($owner);
    $ownerHasSchedule = ((int)($scheduleCounts[$ownerOfferingId] ?? 0)) > 0;
    $ownerWorkloadNames = $workloadMap[$ownerOfferingId] ?? [];
    $ownerHasWorkload = !empty($ownerWorkloadNames);
    $groupHasLockedMembers = false;

    foreach ($currentMemberIds as $memberId) {
        $memberRow = $candidateRows[$memberId] ?? null;
        if ($memberRow && ($memberRow['offering_status'] ?? '') === 'locked') {
            $groupHasLockedMembers = true;
            break;
        }
    }

    $candidates = [];
    foreach ($candidateIds as $candidateId) {
        if ($candidateId === $ownerOfferingId) {
            continue;
        }

        $candidate = $candidateRows[$candidateId] ?? null;
        if (!$candidate) {
            continue;
        }

        $candidateMergeInfo = $mergeContext[$candidateId] ?? null;
        $candidateOwnerId = (int)($candidateMergeInfo['owner_offering_id'] ?? $candidateId);
        $isCurrentMember = in_array($candidateId, $currentMemberIds, true);
        $isMergedElsewhere = $candidateOwnerId > 0 && $candidateOwnerId !== $candidateId && $candidateOwnerId !== $ownerOfferingId;
        $ownsAnotherGroup = !empty($ownerMaps[$candidateId]);
        $hasWorkload = !empty($workloadMap[$candidateId]);
        $requiredSignature = merge_required_minutes_signature($candidate);
        $canSelect = true;
        $reason = "";

        if ($candidate['offering_status'] === 'locked' && !$isCurrentMember) {
            $canSelect = false;
            $reason = "Locked offerings cannot be merged.";
        } elseif ($requiredSignature !== $ownerSignature) {
            $canSelect = false;
            $reason = "Required lecture/laboratory hours do not match the owner schedule.";
        } elseif ($isMergedElsewhere) {
            $canSelect = false;
            $otherOwner = $candidateRows[$candidateOwnerId] ?? load_context_any($conn, $candidateOwnerId, $college_id);
            $otherOwnerLabel = trim((string)($otherOwner['full_section'] ?? ''));
            if ($otherOwnerLabel === '') {
                $otherOwnerLabel = trim((string)($otherOwner['section_name'] ?? 'another owner'));
            }
            $reason = "Already merged under {$otherOwnerLabel}.";
        } elseif ($ownsAnotherGroup && !$isCurrentMember) {
            $canSelect = false;
            $reason = "This offering already owns another merge group.";
        } elseif ($hasWorkload && !$isCurrentMember) {
            $canSelect = false;
            $reason = "Assigned in Faculty Workload and cannot be merged into another owner.";
        }

        $statusLabel = ((int)($scheduleCounts[$candidateId] ?? 0) > 0) ? "Scheduled" : "Not Scheduled";
        if ($isCurrentMember) {
            $statusLabel = "Merged Here";
        } elseif ($isMergedElsewhere) {
            $statusLabel = "Merged Elsewhere";
        } elseif ($ownsAnotherGroup) {
            $statusLabel = "Merge Owner";
        }

        $candidates[] = [
            'offering_id' => $candidateId,
            'section_name' => (string)$candidate['section_name'],
            'full_section' => (string)$candidate['full_section'],
            'program_code' => (string)$candidate['program_code'],
            'status_label' => $statusLabel,
            'has_schedule' => ((int)($scheduleCounts[$candidateId] ?? 0) > 0),
            'is_selected' => $isCurrentMember,
            'can_select' => $canSelect,
            'reason' => $reason
        ];
    }

    $ownerDisplayContext = synk_schedule_merge_load_display_context($conn, array_merge([$ownerOfferingId], $currentMemberIds));
    $ownerDisplay = $ownerDisplayContext[$ownerOfferingId] ?? [
        'group_course_label' => (string)($owner['full_section'] ?? ''),
        'group_size' => 1
    ];

    respond("ok", "Merge candidates loaded.", [
        'requested_offering_id' => $requestedOfferingId,
        'owner_offering_id' => $ownerOfferingId,
        'owner_has_schedule' => $ownerHasSchedule,
        'owner_has_workload' => $ownerHasWorkload,
        'owner_workload_names' => array_values($ownerWorkloadNames),
        'group_has_locked_members' => $groupHasLockedMembers,
        'owner' => [
            'offering_id' => $ownerOfferingId,
            'sub_code' => (string)$owner['sub_code'],
            'sub_description' => (string)$owner['sub_description'],
            'section_name' => (string)$owner['section_name'],
            'full_section' => (string)$owner['full_section'],
            'group_course_label' => (string)($ownerDisplay['group_course_label'] ?? ($owner['full_section'] ?? '')),
            'group_size' => (int)($ownerDisplay['group_size'] ?? 1)
        ],
        'selected_member_ids' => $currentMemberIds,
        'candidates' => $candidates
    ]);
}

/* =====================================================
   SAVE / UPDATE MERGE MEMBERS FOR A SCHEDULE OWNER
===================================================== */
if (isset($_POST['save_schedule_merge'])) {
    $ownerOfferingId = (int)($_POST['owner_offering_id'] ?? 0);
    $memberIdsRaw = $_POST['member_offering_ids_json'] ?? '[]';
    $memberIds = json_decode((string)$memberIdsRaw, true);

    if ($ownerOfferingId <= 0 || !is_array($memberIds)) {
        respond("error", "Invalid merge payload.");
    }

    $ownerRows = load_merge_context_rows_for_offerings($conn, [$ownerOfferingId], $college_id);
    $owner = $ownerRows[$ownerOfferingId] ?? null;
    if (!$owner) {
        respond("error", "Schedule owner is out of sync. Re-run Generate Offerings first.");
    }

    if ($owner['offering_status'] === 'locked') {
        respond("error", "Locked offerings cannot manage merge groups.");
    }

    ensure_offering_not_merged_member($conn, $ownerOfferingId, $college_id);

    $ownerScheduleCounts = load_schedule_row_counts_for_offerings($conn, [$ownerOfferingId]);
    if ((int)($ownerScheduleCounts[$ownerOfferingId] ?? 0) <= 0) {
        respond("error", "Define the owner schedule first before merging other offerings into it.");
    }

    $ownerWorkloadMap = load_workload_faculty_map_for_offerings(
        $conn,
        [$ownerOfferingId],
        (int)$owner['ay_id'],
        (int)$owner['semester']
    );
    if (!empty($ownerWorkloadMap[$ownerOfferingId])) {
        respond(
            "error",
            "This schedule owner is already assigned in Faculty Workload to <b>" .
            implode('</b>, <b>', array_map(static function ($name) {
                return htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
            }, $ownerWorkloadMap[$ownerOfferingId])) .
            "</b>. Remove the workload assignment first before changing the merge group."
        );
    }

    $requestedMemberIds = [];
    foreach ($memberIds as $memberId) {
        $value = (int)$memberId;
        if ($value > 0 && $value !== $ownerOfferingId) {
            $requestedMemberIds[$value] = $value;
        }
    }
    $requestedMemberIds = array_values($requestedMemberIds);

    $existingOwnerMap = synk_schedule_merge_load_owner_to_members_map($conn, [$ownerOfferingId]);
    $existingMemberIds = $existingOwnerMap[$ownerOfferingId] ?? [];
    $toAdd = array_values(array_diff($requestedMemberIds, $existingMemberIds));
    $toRemove = array_values(array_diff($existingMemberIds, $requestedMemberIds));

    $allTouchedIds = synk_schedule_merge_normalize_offering_ids(array_merge($requestedMemberIds, $existingMemberIds));
    $memberRows = load_merge_context_rows_for_offerings($conn, $allTouchedIds, $college_id);
    if (count($memberRows) !== count($allTouchedIds)) {
        respond("error", "One or more merge members are out of sync. Re-run Generate Offerings first.");
    }

    $requiredSignature = merge_required_minutes_signature($owner);
    $memberToOwner = synk_schedule_merge_load_member_to_owner_map($conn, $allTouchedIds);
    $ownerToMembers = synk_schedule_merge_load_owner_to_members_map($conn, $allTouchedIds);
    $workloadMap = load_workload_faculty_map_for_offerings($conn, $allTouchedIds, (int)$owner['ay_id'], (int)$owner['semester']);

    if (!empty($toAdd) || !empty($toRemove)) {
        foreach ($existingMemberIds as $existingMemberId) {
            $existingMember = $memberRows[$existingMemberId] ?? null;
            if ($existingMember && ($existingMember['offering_status'] ?? '') === 'locked') {
                respond(
                    "error",
                    htmlspecialchars((string)$existingMember['full_section'], ENT_QUOTES, 'UTF-8') .
                    " is locked. Remove the lock first before changing this merge group."
                );
            }
        }
    }

    foreach ($toAdd as $memberId) {
        $member = $memberRows[$memberId];

        if ($member['offering_status'] === 'locked') {
            respond("error", htmlspecialchars((string)$member['full_section'], ENT_QUOTES, 'UTF-8') . " is locked and cannot be merged.");
        }

        if ((int)$member['ay_id'] !== (int)$owner['ay_id'] || (int)$member['semester'] !== (int)$owner['semester']) {
            respond("error", "Merged offerings must belong to the same Academic Year and Semester.");
        }

        if ((int)$member['sub_id'] !== (int)$owner['sub_id']) {
            respond("error", "Only offerings with the same subject can be merged.");
        }

        if (merge_required_minutes_signature($member) !== $requiredSignature) {
            respond("error", "Required lecture/laboratory hours do not match the owner schedule.");
        }

        $existingOwnerId = (int)($memberToOwner[$memberId] ?? 0);
        if ($existingOwnerId > 0 && $existingOwnerId !== $ownerOfferingId) {
            respond("error", htmlspecialchars((string)$member['full_section'], ENT_QUOTES, 'UTF-8') . " is already merged into another owner.");
        }

        if (!empty($ownerToMembers[$memberId])) {
            respond("error", htmlspecialchars((string)$member['full_section'], ENT_QUOTES, 'UTF-8') . " already owns another merge group.");
        }

        if (!empty($workloadMap[$memberId])) {
            respond("error", htmlspecialchars((string)$member['full_section'], ENT_QUOTES, 'UTF-8') . " is already assigned in Faculty Workload and cannot be merged into another owner.");
        }
    }

    foreach ($toRemove as $memberId) {
        $member = $memberRows[$memberId] ?? null;
        if ($member && ($member['offering_status'] ?? '') === 'locked') {
            respond("error", htmlspecialchars((string)$member['full_section'], ENT_QUOTES, 'UTF-8') . " is locked and cannot be removed from the merge group.");
        }
    }

    $deleteScheduleIds = synk_schedule_merge_normalize_offering_ids($toAdd);

    $conn->begin_transaction();
    try {
        if (!empty($deleteScheduleIds)) {
            $deleteIdList = implode(',', array_map('intval', $deleteScheduleIds));
            if (!$conn->query("
                DELETE FROM tbl_class_schedule
                WHERE offering_id IN ({$deleteIdList})
            ")) {
                throw new RuntimeException("Failed to clear merged member schedules.");
            }
        }

        if (!empty($toRemove)) {
            $removeIdList = implode(',', array_map('intval', $toRemove));
            if (!$conn->query("
                DELETE FROM `" . synk_schedule_merge_table_name() . "`
                WHERE owner_offering_id = {$ownerOfferingId}
                  AND member_offering_id IN ({$removeIdList})
            ")) {
                throw new RuntimeException("Failed to remove merge members.");
            }
        }

        if (!empty($toAdd)) {
            $insertStmt = $conn->prepare("
                INSERT INTO `" . synk_schedule_merge_table_name() . "`
                    (owner_offering_id, member_offering_id, created_by)
                VALUES (?, ?, ?)
            ");
            if (!$insertStmt) {
                throw new RuntimeException("Failed to save merge rows.");
            }

            $createdBy = (int)($_SESSION['user_id'] ?? 0);
            foreach ($toAdd as $memberId) {
                $insertStmt->bind_param("iii", $ownerOfferingId, $memberId, $createdBy);
                if (!$insertStmt->execute()) {
                    $insertStmt->close();
                    throw new RuntimeException("Failed to save merge rows.");
                }
            }
            $insertStmt->close();
        }

        if (!empty($requestedMemberIds)) {
            $requestIdList = implode(',', array_map('intval', $requestedMemberIds));
            if (!$conn->query("
                UPDATE tbl_prospectus_offering
                SET status = 'active'
                WHERE offering_id IN ({$requestIdList})
                  AND (status IS NULL OR status != 'locked')
            ")) {
                throw new RuntimeException("Failed to update merged member status.");
            }
        }

        if (!empty($toRemove)) {
            $removeIdList = implode(',', array_map('intval', $toRemove));
            if (!$conn->query("
                UPDATE tbl_prospectus_offering
                SET status = 'pending'
                WHERE offering_id IN ({$removeIdList})
                  AND (status IS NULL OR status != 'locked')
            ")) {
                throw new RuntimeException("Failed to reset removed merge members.");
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond("error", "Failed to save the merge group.");
    }

    $displayContext = synk_schedule_merge_load_display_context($conn, array_merge([$ownerOfferingId], $requestedMemberIds));
    $ownerDisplay = $displayContext[$ownerOfferingId] ?? [
        'group_course_label' => (string)($owner['full_section'] ?? ''),
        'group_size' => 1
    ];

    respond("ok", empty($requestedMemberIds) ? "Merge group cleared." : "Merge group saved.", [
        'owner_offering_id' => $ownerOfferingId,
        'group_course_label' => (string)($ownerDisplay['group_course_label'] ?? ($owner['full_section'] ?? '')),
        'group_size' => (int)($ownerDisplay['group_size'] ?? 1),
        'member_count' => count($requestedMemberIds)
    ]);
}

/* =====================================================
   LOAD SAVED SCHEDULE SETS FOR CURRENT COLLEGE TERM
===================================================== */
if (isset($_POST['load_schedule_sets'])) {
    ensure_saved_schedule_tables_exist($conn);

    $ay_id = (int)($_POST['ay_id'] ?? 0);
    $semester = (int)($_POST['semester'] ?? 0);

    if (!$ay_id || !in_array($semester, [1, 2, 3], true)) {
        respond("error", "Select Academic Year and Semester first.");
    }

    $sets = load_saved_schedule_sets_for_scope($conn, $college_id, 0, $ay_id, $semester);
    respond("ok", "Loaded saved schedule sets.", [
        "sets" => $sets
    ]);
}

/* =====================================================
   SAVE CURRENT LIVE SCHEDULES AS A SET
===================================================== */
if (isset($_POST['save_schedule_set'])) {
    ensure_saved_schedule_tables_exist($conn);

    $ay_id = (int)($_POST['ay_id'] ?? 0);
    $semester = (int)($_POST['semester'] ?? 0);
    $set_name = trim((string)($_POST['set_name'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $overwriteExisting = (int)($_POST['overwrite_existing_set'] ?? 0) === 1;
    $scopeProspectusId = 0;

    $set_name = preg_replace('/\s+/', ' ', $set_name);
    $set_name = substr($set_name, 0, 120);
    $remarks = substr($remarks, 0, 255);

    if (!$ay_id || !in_array($semester, [1, 2, 3], true)) {
        respond("error", "Select Academic Year and Semester first.");
    }

    if ($set_name === '') {
        respond("error", "Provide a name for this saved schedule set.");
    }

    if (synk_schedule_merge_term_has_rows($conn, $college_id, $ay_id, $semester)) {
        respond("error", "Saved schedule sets are disabled while merged schedule groups exist in this college term. Unmerge the affected offerings first.");
    }

    $snapshotRows = load_live_schedule_snapshot_rows_for_college_term($conn, $ay_id, $semester, $college_id);
    if (empty($snapshotRows)) {
        respond("error", "No live schedules were found in the current college term to save.");
    }

    $existingSet = load_saved_schedule_set_by_name($conn, $college_id, $scopeProspectusId, $ay_id, $semester, $set_name);
    if ($existingSet && !$overwriteExisting) {
        respond("exists", "A saved schedule set with this name already exists.", [
            "existing_set_id" => (int)($existingSet['schedule_set_id'] ?? 0),
            "existing_set_name" => (string)($existingSet['set_name'] ?? $set_name)
        ]);
    }

    $remarksForDb = $remarks !== '' ? $remarks : null;
    $scheduleSetId = 0;
    $blockOrderByOffering = [];
    $offeringMap = [];

    $conn->begin_transaction();
    try {
        if ($existingSet) {
            $scheduleSetId = (int)($existingSet['schedule_set_id'] ?? 0);

            $updateSet = $conn->prepare("
                UPDATE tbl_class_schedule_set
                SET remarks = ?,
                    date_updated = CURRENT_TIMESTAMP
                WHERE schedule_set_id = ?
            ");
            if (!$updateSet) {
                throw new RuntimeException("Failed to update schedule set.");
            }

            $updateSet->bind_param("si", $remarksForDb, $scheduleSetId);
            if (!$updateSet->execute()) {
                $updateSet->close();
                throw new RuntimeException("Failed to update schedule set.");
            }
            $updateSet->close();

            $deleteRows = $conn->prepare("
                DELETE FROM tbl_class_schedule_set_row
                WHERE schedule_set_id = ?
            ");
            if (!$deleteRows) {
                throw new RuntimeException("Failed to refresh schedule set rows.");
            }

            $deleteRows->bind_param("i", $scheduleSetId);
            if (!$deleteRows->execute()) {
                $deleteRows->close();
                throw new RuntimeException("Failed to refresh schedule set rows.");
            }
            $deleteRows->close();
        } else {
            $insertSet = $conn->prepare("
                INSERT INTO tbl_class_schedule_set
                (college_id, prospectus_id, ay_id, semester, set_name, remarks, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$insertSet) {
                throw new RuntimeException("Failed to create schedule set.");
            }

            $createdBy = (int)($_SESSION['user_id'] ?? 0);
            $insertSet->bind_param(
                "iiiissi",
                $college_id,
                $scopeProspectusId,
                $ay_id,
                $semester,
                $set_name,
                $remarksForDb,
                $createdBy
            );
            if (!$insertSet->execute()) {
                $insertSet->close();
                throw new RuntimeException("Failed to create schedule set.");
            }
            $scheduleSetId = (int)$conn->insert_id;
            $insertSet->close();
        }

        $insertRow = $conn->prepare("
            INSERT INTO tbl_class_schedule_set_row
            (
                schedule_set_id,
                offering_id,
                program_id,
                ps_id,
                section_id,
                schedule_type,
                block_order,
                room_id,
                days_json,
                time_start,
                time_end,
                subject_code_snapshot,
                subject_description_snapshot,
                section_name_snapshot,
                room_label_snapshot
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$insertRow) {
            throw new RuntimeException("Failed to save schedule set rows.");
        }

        foreach ($snapshotRows as $row) {
            $offeringId = (int)($row['offering_id'] ?? 0);
            if ($offeringId <= 0) {
                continue;
            }

            $blockOrderByOffering[$offeringId] = (int)($blockOrderByOffering[$offeringId] ?? 0) + 1;
            $blockOrder = (int)$blockOrderByOffering[$offeringId];
            $programId = (int)($row['program_id'] ?? 0);
            $psId = (int)($row['ps_id'] ?? 0);
            $sectionId = (int)($row['section_id'] ?? 0);
            $scheduleType = synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC'));
            $roomId = (int)($row['room_id'] ?? 0);
            $daysJson = (string)($row['days_json'] ?? '[]');
            $timeStart = (string)($row['time_start'] ?? '');
            $timeEnd = (string)($row['time_end'] ?? '');
            $subjectCode = trim((string)($row['sub_code'] ?? ''));
            $subjectDescription = trim((string)($row['sub_description'] ?? ''));
            $sectionName = trim((string)($row['section_name'] ?? ''));
            $roomLabel = trim((string)($row['room_label'] ?? ''));

            $insertRow->bind_param(
                "iiiiisiisssssss",
                $scheduleSetId,
                $offeringId,
                $programId,
                $psId,
                $sectionId,
                $scheduleType,
                $blockOrder,
                $roomId,
                $daysJson,
                $timeStart,
                $timeEnd,
                $subjectCode,
                $subjectDescription,
                $sectionName,
                $roomLabel
            );

            if (!$insertRow->execute()) {
                $insertRow->close();
                throw new RuntimeException("Failed to save schedule set rows.");
            }

            $offeringMap[$offeringId] = true;
        }

        $insertRow->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond("error", "Failed to save the current live schedules as a set.");
    }

    respond("ok", $existingSet ? "Saved schedule set updated." : "Saved schedule set created.", [
        "schedule_set_id" => $scheduleSetId,
        "set_name" => $set_name,
        "row_count" => count($snapshotRows),
        "offering_count" => count($offeringMap)
    ]);
}

/* =====================================================
   LOAD SAVED SCHEDULE SET INTO LIVE SCHEDULES
===================================================== */
if (isset($_POST['load_schedule_set_into_live'])) {
    ensure_saved_schedule_tables_exist($conn);

    $schedule_set_id = (int)($_POST['schedule_set_id'] ?? 0);
    if ($schedule_set_id <= 0) {
        respond("error", "Select a saved schedule set first.");
    }

    $setRecord = load_saved_schedule_set_record($conn, $schedule_set_id, $college_id);
    if (!$setRecord) {
        respond("error", "Saved schedule set not found in your college scheduler.");
    }

    if (synk_schedule_merge_term_has_rows($conn, $college_id, (int)($setRecord['ay_id'] ?? 0), (int)($setRecord['semester'] ?? 0))) {
        respond("error", "Loading a saved schedule set is disabled while merged schedule groups exist in this college term. Clear or unmerge the active merge groups first.");
    }

    $setRows = load_saved_schedule_set_rows($conn, $schedule_set_id, $college_id);
    if (empty($setRows)) {
        respond("error", "This saved schedule set does not contain any rows.");
    }

    $scopeOfferings = load_scoped_offerings_for_college_term(
        $conn,
        (int)$setRecord['ay_id'],
        (int)$setRecord['semester'],
        $college_id
    );

    if (empty($scopeOfferings)) {
        respond("error", "The saved set college term is no longer available. Re-run Generate Offerings first.");
    }

    $scopeMap = [];
    foreach ($scopeOfferings as $offering) {
        $scopeMap[(int)$offering['offering_id']] = $offering;
    }

    $setLabelsByOffering = [];
    $targetOfferingMap = [];
    foreach ($setRows as $row) {
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($offeringId <= 0) {
            continue;
        }

        $targetOfferingMap[$offeringId] = true;
        if (!isset($setLabelsByOffering[$offeringId])) {
            $setLabelsByOffering[$offeringId] = schedule_set_offering_label(
                (string)($row['subject_code_snapshot'] ?? ''),
                (string)($row['section_name_snapshot'] ?? ''),
                'Offering #' . $offeringId
            );
        }
    }

    $currentLiveOfferingIds = load_live_scheduled_offering_ids_for_college_term(
        $conn,
        (int)$setRecord['ay_id'],
        (int)$setRecord['semester'],
        $college_id
    );

    $affectedOfferingMap = [];
    foreach ($currentLiveOfferingIds as $offeringId) {
        $affectedOfferingMap[(int)$offeringId] = true;
    }
    foreach (array_keys($targetOfferingMap) as $offeringId) {
        $affectedOfferingMap[(int)$offeringId] = true;
    }
    $affectedOfferingIds = array_map('intval', array_keys($affectedOfferingMap));

    if (empty($affectedOfferingIds)) {
        respond("error", "There are no affected live schedules to replace for this saved set.");
    }

    $outdatedLines = [];
    $lockedLines = [];
    foreach ($affectedOfferingIds as $offeringId) {
        $scopeOffering = $scopeMap[$offeringId] ?? null;
        $label = $scopeOffering
            ? schedule_set_offering_label($scopeOffering['sub_code'] ?? '', $scopeOffering['section_name'] ?? '', 'Offering #' . $offeringId)
            : ($setLabelsByOffering[$offeringId] ?? ('Offering #' . $offeringId));

        if (!$scopeOffering) {
            $outdatedLines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': offering no longer exists in this college term.';
            continue;
        }

        if (($scopeOffering['offering_status'] ?? '') === 'locked') {
            $lockedLines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': offering is locked.';
        }
    }

    if (!empty($outdatedLines)) {
        respond(
            "error",
            "This saved set references outdated offerings.<br><br>" . implode("<br>", array_slice($outdatedLines, 0, 8))
        );
    }

    if (!empty($lockedLines)) {
        respond(
            "error",
            "This saved set cannot be loaded because one or more affected offerings are locked.<br><br>" .
            implode("<br>", array_slice($lockedLines, 0, 8))
        );
    }

    $workloadMap = load_workload_faculty_map_for_offerings(
        $conn,
        $affectedOfferingIds,
        (int)$setRecord['ay_id'],
        (int)$setRecord['semester']
    );

    if (!empty($workloadMap)) {
        $workloadLines = [];
        foreach ($affectedOfferingIds as $offeringId) {
            if (empty($workloadMap[$offeringId])) {
                continue;
            }

            $scopeOffering = $scopeMap[$offeringId] ?? null;
            $label = $scopeOffering
                ? schedule_set_offering_label($scopeOffering['sub_code'] ?? '', $scopeOffering['section_name'] ?? '', 'Offering #' . $offeringId)
                : ($setLabelsByOffering[$offeringId] ?? ('Offering #' . $offeringId));

            $workloadLines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
                ': assigned in Faculty Workload to ' .
                htmlspecialchars(implode(', ', $workloadMap[$offeringId]), ENT_QUOTES, 'UTF-8') . '.';
        }

        if (!empty($workloadLines)) {
            respond(
                "error",
                "Remove the affected Faculty Workload assignments before loading this saved set.<br><br>" .
                implode("<br>", array_slice($workloadLines, 0, 8))
            );
        }
    }

    $contextCache = [];
    $normalizedRows = [];

    foreach ($setRows as $row) {
        $offeringId = (int)($row['offering_id'] ?? 0);
        if ($offeringId <= 0) {
            continue;
        }

        if (!isset($contextCache[$offeringId])) {
            $ctx = load_context_live($conn, $offeringId, $college_id);
            if (!$ctx) {
                respond("error", "One or more offerings in this saved set are out of sync. Re-run Generate Offerings first.");
            }
            $contextCache[$offeringId] = $ctx;
        }

        $type = synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC'));
        $roomId = (int)($row['room_id'] ?? 0);
        $timeStart = normalize_time_input((string)($row['time_start'] ?? ''));
        $timeEnd = normalize_time_input((string)($row['time_end'] ?? ''));
        $days = normalize_days_array(json_decode((string)($row['days_json'] ?? '[]'), true));
        $label = schedule_set_row_label($row);

        if ($roomId <= 0 || !$timeStart || !$timeEnd || empty($days)) {
            respond("error", "{$label} is incomplete inside the saved set.");
        }

        if ($timeEnd <= $timeStart) {
            respond("error", "{$label} has an invalid time range inside the saved set.");
        }

        validate_schedule_policy($days, $timeStart, $timeEnd, $label, $schedulePolicy);
        validate_room_for_schedule(
            $conn,
            $roomId,
            $college_id,
            (int)$setRecord['ay_id'],
            (int)$setRecord['semester'],
            $type
        );

        $normalizedRows[] = [
            'offering_id' => $offeringId,
            'section_id' => (int)($row['section_id'] ?? 0),
            'section_name' => (string)($row['section_name_snapshot'] ?? ($contextCache[$offeringId]['section_name'] ?? '')),
            'schedule_type' => $type,
            'room_id' => $roomId,
            'room_label' => (string)($row['room_label_snapshot'] ?? ''),
            'days' => $days,
            'days_json' => json_encode($days),
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'label' => $label
        ];
    }

    $internalConflict = validate_saved_schedule_set_internal_conflicts($normalizedRows);
    if ($internalConflict !== null) {
        respond("conflict", $internalConflict);
    }

    foreach ($normalizedRows as $row) {
        $ctx = $contextCache[(int)$row['offering_id']];
        $externalConflict = find_conflict_excluding_offerings(
            $conn,
            (int)$setRecord['ay_id'],
            (int)$setRecord['semester'],
            $affectedOfferingIds,
            (int)($ctx['section_id'] ?? 0),
            (string)($ctx['section_name'] ?? $row['section_name'] ?? ''),
            (int)$row['room_id'],
            $row['days'],
            (string)$row['time_start'],
            (string)$row['time_end'],
            (string)$row['label']
        );

        if ($externalConflict !== null) {
            respond("conflict", $externalConflict);
        }
    }

    $rowsByOffering = [];
    foreach ($normalizedRows as $row) {
        $rowsByOffering[(int)$row['offering_id']][] = $row;
    }

    $deletedRowCount = 0;
    $insertedRowCount = 0;
    $activatedOfferingCount = 0;
    $pendingOfferingCount = 0;

    $conn->begin_transaction();
    try {
        $idList = implode(',', array_map('intval', $affectedOfferingIds));

        if (!$conn->query("
            DELETE FROM tbl_class_schedule
            WHERE offering_id IN ({$idList})
        ")) {
            throw new RuntimeException("Failed to clear live schedule rows.");
        }
        $deletedRowCount = max(0, (int)$conn->affected_rows);

        if (!$conn->query("
            UPDATE tbl_prospectus_offering
            SET status = 'pending'
            WHERE offering_id IN ({$idList})
              AND (status IS NULL OR status != 'locked')
        ")) {
            throw new RuntimeException("Failed to reset offering status.");
        }

        foreach ($rowsByOffering as $offeringId => $offeringRows) {
            $groupId = count($offeringRows) > 1 ? random_int(100000, 2147483647) : null;

            foreach ($offeringRows as $row) {
                insert_schedule_row(
                    $conn,
                    (int)$offeringId,
                    (string)$row['schedule_type'],
                    $groupId,
                    (int)$row['room_id'],
                    (string)$row['days_json'],
                    (string)$row['time_start'],
                    (string)$row['time_end'],
                    (int)($_SESSION['user_id'] ?? 0)
                );
                $insertedRowCount++;
            }

            $ctx = $contextCache[(int)$offeringId];
            $coverage = build_schedule_coverage_summary($ctx, $offeringRows);
            $state = $coverage['status'] === 'complete' ? 'active' : 'pending';

            if (!mark_offering_schedule_state($conn, (int)$offeringId, $state)) {
                throw new RuntimeException("Failed to update offering schedule state.");
            }

            if ($state === 'active') {
                $activatedOfferingCount++;
            } else {
                $pendingOfferingCount++;
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respond("error", "Failed to load the saved schedule set into live schedules.");
    }

    respond("ok", "Saved schedule set loaded into live schedules.", [
        "schedule_set_id" => (int)$setRecord['schedule_set_id'],
        "set_name" => (string)($setRecord['set_name'] ?? ''),
        "affected_offering_count" => count($affectedOfferingIds),
        "cleared_live_offering_count" => count($currentLiveOfferingIds),
        "deleted_schedule_row_count" => $deletedRowCount,
        "loaded_offering_count" => count($rowsByOffering),
        "loaded_row_count" => $insertedRowCount,
        "activated_offering_count" => $activatedOfferingCount,
        "pending_offering_count" => $pendingOfferingCount
    ]);
}

/* =====================================================
   VALIDATE REQUEST
===================================================== */
if (
    !isset($_POST['load_schedule_sets']) &&
    !isset($_POST['save_schedule_set']) &&
    !isset($_POST['load_schedule_set_into_live']) &&
    !isset($_POST['load_schedule_merge_candidates']) &&
    !isset($_POST['save_schedule_merge']) &&
    !isset($_POST['load_schedule_blocks']) &&
    !isset($_POST['load_section_schedule_matrix']) &&
    !isset($_POST['load_schedule_faculty_options']) &&
    !isset($_POST['load_faculty_schedule_overview']) &&
    !isset($_POST['load_dual_schedule']) &&
    !isset($_POST['save_schedule']) &&
    !isset($_POST['save_dual_schedule']) &&
    !isset($_POST['save_schedule_blocks']) &&
    !isset($_POST['clear_schedule']) &&
    !isset($_POST['clear_all_college_schedules'])
) {
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
    $days        = normalize_days_array(json_decode($_POST['days_json'], true));

    if (!$offering_id || !$room_id || !$time_start || !$time_end || empty($days)) {
        respond("error", "Missing schedule fields.");
    }

    ensure_offering_not_merged_member($conn, $offering_id, $college_id);

    if ($time_end <= $time_start) {
        respond("error", "End time must be later than start time.");
    }

    validate_schedule_policy($days, $time_start, $time_end, 'Lecture schedule', $schedulePolicy);

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    if (in_array('LAB', required_schedule_types_for_context($ctx), true)) {
        respond("error", "This subject requires both lecture and laboratory schedules. Use the lecture + laboratory scheduler.");
    }

    validate_room_for_schedule($conn, $room_id, $college_id, (int)$ctx['ay_id'], (int)$ctx['semester'], 'LEC');

    check_conflict($conn, $ctx, $offering_id, $room_id, $time_start, $time_end, $days, "LEC");

    $existingRows = load_existing_schedule_rows($conn, $offering_id);
    if (isset($existingRows['LAB'])) {
        respond("error", "This lecture-only offering still has a laboratory schedule row. Resolve the existing schedule data first.");
    }

    check_assigned_faculty_conflicts(
        $conn,
        $ctx,
        $offering_id,
        [[
            'type' => 'LEC',
            'start' => $time_start,
            'end' => $time_end,
            'days' => $days
        ]]
    );

    $conn->begin_transaction();
    try {
        if (isset($existingRows['LEC'])) {
            update_schedule_row(
                $conn,
                (int)$existingRows['LEC']['schedule_id'],
                'LEC',
                null,
                $room_id,
                json_encode($days),
                $time_start,
                $time_end
            );
        } else {
            insert_schedule_row(
                $conn,
                $offering_id,
                'LEC',
                null,
                $room_id,
                json_encode($days),
                $time_start,
                $time_end,
                (int)$_SESSION['user_id']
            );
        }

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

    if (!$offering_id || empty($schedules) || !is_array($schedules)) {
        respond("error", "Invalid dual schedule.");
    }

    ensure_offering_not_merged_member($conn, $offering_id, $college_id);

    $ctx = load_context_live($conn, $offering_id, $college_id);
    if (!$ctx) {
        respond("error", "Offering is out of sync. Re-run Generate Offerings first.");
    }

    if (!in_array('LAB', required_schedule_types_for_context($ctx), true)) {
        respond("error", "This subject is lecture-only. Use the single schedule form.");
    }

    $norm = [];
    foreach ($schedules as $s) {
        $type = strtoupper(trim((string)($s['type'] ?? '')));
        $roomId = (int)($s['room_id'] ?? 0);
        $timeStart = normalize_time_input($s['time_start'] ?? '');
        $timeEnd = normalize_time_input($s['time_end'] ?? '');
        $daysJson = (string)($s['days_json'] ?? '');
        $days = normalize_days_array(json_decode($daysJson, true));

        if (!in_array($type, ['LEC', 'LAB'], true)) {
            respond("error", "Invalid schedule type.");
        }

        if (!$roomId || !$timeStart || !$timeEnd || !is_array($days) || empty($days)) {
            respond("error", "{$type} schedule is incomplete.");
        }

        if ($timeEnd <= $timeStart) {
            respond("error", "{$type} end time must be later than start.");
        }

        validate_schedule_policy(
            $days,
            $timeStart,
            $timeEnd,
            $type === 'LAB' ? 'Laboratory schedule' : 'Lecture schedule',
            $schedulePolicy
        );

        $norm[] = [
            "type" => $type,
            "room" => $roomId,
            "start" => $timeStart,
            "end" => $timeEnd,
            "days" => $days,
            "days_json" => json_encode($days)
        ];
    }

    if (count($norm) !== 2) {
        respond("error", "Lecture + laboratory scheduling requires exactly two schedule blocks.");
    }

    $typesSeen = array_column($norm, 'type');
    sort($typesSeen);
    if ($typesSeen !== ['LAB', 'LEC']) {
        respond("error", "Lecture + laboratory scheduling requires one LEC block and one LAB block.");
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

    check_assigned_faculty_conflicts($conn, $ctx, $offering_id, $norm);

    $existingRows = load_existing_schedule_rows($conn, $offering_id);
    $existingGroupId = null;
    foreach ($existingRows as $row) {
        $candidateGroupId = (int)($row['schedule_group_id'] ?? 0);
        if ($candidateGroupId > 0) {
            $existingGroupId = $candidateGroupId;
            break;
        }
    }
    $group_id = $existingGroupId ?: random_int(100000, 2147483647);

    $conn->begin_transaction();
    try {
        foreach ($norm as $n) {
            if (isset($existingRows[$n['type']])) {
                update_schedule_row(
                    $conn,
                    (int)$existingRows[$n['type']]['schedule_id'],
                    $n['type'],
                    $group_id,
                    (int)$n['room'],
                    (string)$n['days_json'],
                    (string)$n['start'],
                    (string)$n['end']
                );
            } else {
                insert_schedule_row(
                    $conn,
                    $offering_id,
                    $n['type'],
                    $group_id,
                    (int)$n['room'],
                    (string)$n['days_json'],
                    (string)$n['start'],
                    (string)$n['end'],
                    (int)$_SESSION['user_id']
                );
            }
        }

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
