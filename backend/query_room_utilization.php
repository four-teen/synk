<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    echo json_encode([]);
    exit;
}

$college_id = (int)$_SESSION['college_id'];

/* ==========================================================
   MAP SEMESTER LABEL -> DB VALUE
========================================================== */
function mapSemester($s) {
    if ($s === '1st') return '1';
    if ($s === '2nd') return '2';
    if ($s === 'Midyear') return '3';
    return '';
}

/* ==========================================================
   SINGLE ROOM SCHEDULE
========================================================== */
if (isset($_POST['load_room_schedule'])) {

    $ay       = trim($_POST['ay']);
    $semester = mapSemester($_POST['semester']);
    $room_id  = (int)$_POST['room_id'];

    if (!$ay || !$semester || !$room_id) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT
            cs.time_start,
            cs.time_end,
            cs.days_json,
            sm.sub_code AS subject_code,
            sec.full_section AS section_name,
            COALESCE(
                CONCAT(f.last_name, ', ', f.first_name),
                'TBA'
            ) AS faculty_name,
            r.capacity AS expected_students
        FROM tbl_class_schedule cs
        INNER JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        INNER JOIN tbl_prospectus_subjects ps
            ON ps.ps_id = po.ps_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        INNER JOIN tbl_sections sec
            ON sec.section_id = po.section_id
        INNER JOIN tbl_academic_years ay
            ON ay.ay_id = po.ay_id
        LEFT JOIN tbl_faculty_workload_sched fws
            ON fws.schedule_id = cs.schedule_id
        LEFT JOIN tbl_faculty f
            ON f.faculty_id = fws.faculty_id
        WHERE cs.room_id = ?
        AND ay.ay = ?
        AND po.semester = ?
        AND r.college_id = ?
        ORDER BY cs.time_start
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $room_id, $ay, $semester, $college_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];

    while ($row = $res->fetch_assoc()) {
        $row['days_raw'] = json_decode($row['days_json'], true) ?: [];
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

/* ==========================================================
   ALL ROOMS OVERVIEW
========================================================== */
if (isset($_POST['load_all_rooms'])) {

    $ay       = trim($_POST['ay']);
    $semester = mapSemester($_POST['semester']);

    if (!$ay || !$semester) {
        echo json_encode([]);
        exit;
    }

    $rooms = [];

    // Load all active rooms first so rooms without schedules still appear.
    $roomStmt = $conn->prepare("
        SELECT room_id, room_code
        FROM tbl_rooms
        WHERE college_id = ?
          AND status = 'active'
        ORDER BY room_code
    ");
    $roomStmt->bind_param("i", $college_id);
    $roomStmt->execute();
    $roomRes = $roomStmt->get_result();

    while ($r = $roomRes->fetch_assoc()) {
        $rooms[(int)$r['room_id']] = [
            "room_id"   => (int)$r['room_id'],
            "room_code" => $r['room_code'],
            "groups"    => []
        ];
    }

    if (empty($rooms)) {
        echo json_encode([]);
        exit;
    }

    // Single query for all rooms (replaces query-per-room).
    $schedSql = "
        SELECT
            cs.room_id,
            cs.time_start,
            cs.time_end,
            cs.days_json,
            sm.sub_code AS subject_code,
            sec.full_section AS section_name,
            COALESCE(
                CONCAT(f.last_name, ', ', f.first_name),
                'TBA'
            ) AS faculty_name
        FROM tbl_class_schedule cs
        INNER JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        INNER JOIN tbl_prospectus_subjects ps
            ON ps.ps_id = po.ps_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        INNER JOIN tbl_sections sec
            ON sec.section_id = po.section_id
        INNER JOIN tbl_academic_years ay
            ON ay.ay_id = po.ay_id
        LEFT JOIN tbl_faculty_workload_sched fws
            ON fws.schedule_id = cs.schedule_id
        LEFT JOIN tbl_faculty f
            ON f.faculty_id = fws.faculty_id
        WHERE r.college_id = ?
          AND r.status = 'active'
          AND ay.ay = ?
          AND po.semester = ?
        ORDER BY r.room_code, cs.time_start
    ";

    $schedStmt = $conn->prepare($schedSql);
    $schedStmt->bind_param("iss", $college_id, $ay, $semester);
    $schedStmt->execute();
    $schedRes = $schedStmt->get_result();

    while ($row = $schedRes->fetch_assoc()) {
        $roomId = (int)$row['room_id'];
        if (!isset($rooms[$roomId])) {
            continue;
        }

        $days = json_decode($row['days_json'], true) ?: [];
        $dayKey = implode('', $days);

        if (!isset($rooms[$roomId]['groups'][$dayKey])) {
            $rooms[$roomId]['groups'][$dayKey] = [];
        }

        $rooms[$roomId]['groups'][$dayKey][] = [
            "time_start"   => $row['time_start'],
            "time_end"     => $row['time_end'],
            "days_json"    => $row['days_json'],
            "subject_code" => $row['subject_code'],
            "section_name" => $row['section_name'],
            "faculty_name" => $row['faculty_name']
        ];
    }

    echo json_encode(array_values($rooms));
    exit;
}

echo json_encode([]);
