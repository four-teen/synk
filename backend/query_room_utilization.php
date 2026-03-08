<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schema_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    echo json_encode([]);
    exit;
}

$college_id = (int)$_SESSION['college_id'];

/* ==========================================================
   MAP SEMESTER LABEL -> DB VALUE
========================================================== */
function mapSemester($s) {
    if ($s === '1st') return 1;
    if ($s === '2nd') return 2;
    if ($s === 'Midyear') return 3;
    return 0;
}

function room_access_table_exists($conn) {
    static $hasAccessTable = null;

    if ($hasAccessTable !== null) {
        return $hasAccessTable;
    }

    $result = $conn->query("SHOW TABLES LIKE 'tbl_room_college_access'");
    $hasAccessTable = $result && $result->num_rows > 0;
    return $hasAccessTable;
}

function build_room_label($row) {
    $code = trim((string)($row['room_code'] ?? ''));
    $name = trim((string)($row['room_name'] ?? ''));
    $label = $name !== '' ? $name : $code;

    if ($name !== '' && $code !== '' && strcasecmp($name, $code) !== 0) {
        $label = $code . ' - ' . $name;
    }

    $accessType = strtolower(trim((string)($row['access_type'] ?? 'owner')));
    $ownerCode = trim((string)($row['owner_code'] ?? ''));
    if ($accessType === 'shared') {
        $label .= $ownerCode !== ''
            ? " (Shared from {$ownerCode})"
            : " (Shared)";
    }

    return $label;
}

function load_accessible_rooms_for_term($conn, $college_id, $ayLabel, $semester) {
    $rooms = [];

    if (room_access_table_exists($conn)) {
        $sql = "
            SELECT DISTINCT
                r.room_id,
                r.room_code,
                r.room_name,
                acc.access_type,
                owner.college_code AS owner_code
            FROM tbl_room_college_access acc
            INNER JOIN tbl_rooms r
                ON r.room_id = acc.room_id
            INNER JOIN tbl_college owner
                ON owner.college_id = r.college_id
            INNER JOIN tbl_academic_years ay
                ON ay.ay_id = acc.ay_id
            WHERE acc.college_id = ?
              AND ay.ay = ?
              AND acc.semester = ?
              AND r.status = 'active'
            ORDER BY r.room_name ASC, r.room_code ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $college_id, $ayLabel, $semester);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $roomId = (int)$row['room_id'];
            $rooms[$roomId] = [
                "room_id" => $roomId,
                "room_code" => (string)$row['room_code'],
                "room_name" => (string)($row['room_name'] ?? ''),
                "room_label" => build_room_label($row),
                "groups" => []
            ];
        }

        $stmt->close();
        return $rooms;
    }

    $sql = "
        SELECT room_id, room_code, room_name
        FROM tbl_rooms
        WHERE college_id = ?
          AND status = 'active'
        ORDER BY room_name ASC, room_code ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $roomId = (int)$row['room_id'];
        $rooms[$roomId] = [
            "room_id" => $roomId,
            "room_code" => (string)$row['room_code'],
            "room_name" => (string)($row['room_name'] ?? ''),
            "room_label" => build_room_label($row),
            "groups" => []
        ];
    }

    $stmt->close();
    return $rooms;
}

function normalize_day_token($day) {
    $token = strtoupper(trim((string)$day));
    return $token === 'TH' ? 'Th' : $token;
}

function normalize_day_key($days) {
    $order = [
        'M' => 1,
        'T' => 2,
        'W' => 3,
        'Th' => 4,
        'F' => 5,
        'S' => 6
    ];

    if (!is_array($days)) {
        return '';
    }

    $normalized = [];
    foreach ($days as $day) {
        $token = normalize_day_token($day);
        if ($token !== '') {
            $normalized[$token] = true;
        }
    }

    $tokens = array_keys($normalized);
    usort($tokens, function ($a, $b) use ($order) {
        return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
    });

    return implode('', $tokens);
}

function format_workload_days($daysJson) {
    $days = json_decode((string)$daysJson, true);
    if (!is_array($days) || empty($days)) {
        return '';
    }

    return implode(', ', array_map(function ($day) {
        return normalize_day_token($day);
    }, $days));
}

function workload_title_case($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $value = strtolower($value);
    $value = preg_replace_callback('/(^|[\s,\/-])([a-z])/', static function ($matches) {
        return $matches[1] . strtoupper($matches[2]);
    }, $value);

    return (string)$value;
}

if (isset($_POST['load_room_options'])) {
    $ay = trim((string)($_POST['ay'] ?? ''));
    $semester = mapSemester($_POST['semester'] ?? '');

    if ($ay === '' || $semester <= 0) {
        echo json_encode([
            'status' => 'ok',
            'rooms' => []
        ]);
        exit;
    }

    $rooms = array_values(array_map(function ($room) {
        return [
            'room_id' => (int)$room['room_id'],
            'label' => $room['room_label']
        ];
    }, load_accessible_rooms_for_term($conn, $college_id, $ay, $semester)));

    echo json_encode([
        'status' => 'ok',
        'rooms' => $rooms
    ]);
    exit;
}

/* ==========================================================
   SINGLE ROOM SCHEDULE
========================================================== */
if (isset($_POST['load_room_schedule'])) {
    $liveOfferingJoins = synk_live_offering_join_sql('po', 'sec', 'ps', 'pys', 'ph');

    $ay       = trim($_POST['ay']);
    $semester = mapSemester($_POST['semester']);
    $room_id  = (int)$_POST['room_id'];

    if (!$ay || !$semester || !$room_id) {
        echo json_encode([]);
        exit;
    }

    $accessibleRooms = load_accessible_rooms_for_term($conn, $college_id, $ay, $semester);
    if (!isset($accessibleRooms[$room_id])) {
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
            f.faculty_id,
            COALESCE(
                CONCAT(f.last_name, ', ', f.first_name),
                'TBA'
            ) AS faculty_name,
            r.capacity AS room_capacity
        FROM tbl_class_schedule cs
        INNER JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        INNER JOIN tbl_academic_years ay
            ON ay.ay_id = po.ay_id
        LEFT JOIN tbl_faculty_workload_sched fws
            ON fws.schedule_id = cs.schedule_id
        LEFT JOIN tbl_faculty f
            ON f.faculty_id = fws.faculty_id
        WHERE cs.room_id = ?
        AND ay.ay = ?
        AND po.semester = ?
        ORDER BY cs.time_start
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $room_id, $ay, $semester);
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
    $liveOfferingJoins = synk_live_offering_join_sql('po', 'sec', 'ps', 'pys', 'ph');

    $ay       = trim($_POST['ay']);
    $semester = mapSemester($_POST['semester']);

    if (!$ay || !$semester) {
        echo json_encode([]);
        exit;
    }

    $rooms = load_accessible_rooms_for_term($conn, $college_id, $ay, $semester);

    if (empty($rooms)) {
        echo json_encode([]);
        exit;
    }

    $roomIds = array_keys($rooms);
    $roomIdList = implode(',', array_map('intval', $roomIds));

    $schedSql = "
        SELECT
            cs.room_id,
            cs.time_start,
            cs.time_end,
            cs.days_json,
            sm.sub_code AS subject_code,
            sec.full_section AS section_name,
            f.faculty_id,
            COALESCE(
                CONCAT(f.last_name, ', ', f.first_name),
                'TBA'
            ) AS faculty_name
        FROM tbl_class_schedule cs
        INNER JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        INNER JOIN tbl_academic_years ay
            ON ay.ay_id = po.ay_id
        LEFT JOIN tbl_faculty_workload_sched fws
            ON fws.schedule_id = cs.schedule_id
        LEFT JOIN tbl_faculty f
            ON f.faculty_id = fws.faculty_id
        WHERE cs.room_id IN ({$roomIdList})
          AND r.status = 'active'
          AND ay.ay = ?
          AND po.semester = ?
        ORDER BY r.room_code, cs.time_start
    ";

    $schedStmt = $conn->prepare($schedSql);
    $schedStmt->bind_param("si", $ay, $semester);
    $schedStmt->execute();
    $schedRes = $schedStmt->get_result();

    while ($row = $schedRes->fetch_assoc()) {
        $roomId = (int)$row['room_id'];
        if (!isset($rooms[$roomId])) {
            continue;
        }

        $days = json_decode($row['days_json'], true) ?: [];
        $dayKey = normalize_day_key($days);
        if ($dayKey === '') {
            continue;
        }

        if (!isset($rooms[$roomId]['groups'][$dayKey])) {
            $rooms[$roomId]['groups'][$dayKey] = [];
        }

        $rooms[$roomId]['groups'][$dayKey][] = [
            "time_start"   => $row['time_start'],
            "time_end"     => $row['time_end'],
            "days_json"    => $row['days_json'],
            "subject_code" => $row['subject_code'],
            "section_name" => $row['section_name'],
            "faculty_id"   => (int)($row['faculty_id'] ?? 0),
            "faculty_name" => $row['faculty_name']
        ];
    }

    echo json_encode(array_values($rooms));
    exit;
}

if (isset($_POST['load_faculty_workload'])) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $classScheduleHasGroupId = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_group_id');
    $classScheduleHasType = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_type');
    $facultyHasDesignationId = synk_table_has_column($conn, 'tbl_faculty', 'designation_id');
    $designationTableExists = synk_table_exists($conn, 'tbl_designation');
    $designationHasStatus = $designationTableExists && synk_table_has_column($conn, 'tbl_designation', 'status');
    $assignmentHasAyId = synk_table_has_column($conn, 'tbl_college_faculty', 'ay_id');
    $assignmentHasSemester = synk_table_has_column($conn, 'tbl_college_faculty', 'semester');

    $facultyId = (int)($_POST['faculty_id'] ?? 0);
    $ay = trim((string)($_POST['ay'] ?? ''));
    $semester = mapSemester($_POST['semester'] ?? '');

    if ($facultyId <= 0 || $ay === '' || $semester <= 0) {
        echo json_encode([
            'rows' => [],
            'meta' => []
        ]);
        exit;
    }

    $selectParts = [
        'fw.workload_id',
        'o.offering_id',
        $classScheduleHasGroupId ? 'cs.schedule_group_id AS group_id' : 'NULL AS group_id',
        $classScheduleHasType ? 'cs.schedule_type AS type' : "'LEC' AS type",
        'sm.sub_code',
        'sm.sub_description AS subject_description',
        'sec.section_name',
        'sec.full_section',
        'cs.days_json',
        'cs.time_start',
        'cs.time_end',
        "COALESCE(r.room_code, '') AS room_code",
        "COALESCE(col.college_code, '') AS college_code",
        'ps.lec_units',
        'ps.lab_units',
        'ps.total_units',
        "COALESCE(CONCAT(f.last_name, ', ', f.first_name), 'TBA') AS faculty_name"
    ];

    $orderParts = [
        'sec.section_name',
        'sm.sub_code',
        $classScheduleHasGroupId ? 'COALESCE(cs.schedule_group_id, cs.schedule_id)' : 'cs.schedule_id'
    ];

    if ($classScheduleHasType) {
        $orderParts[] = "FIELD(cs.schedule_type, 'LEC', 'LAB')";
    }

    $orderParts[] = 'cs.time_start';

    $sql = "
        SELECT
            " . implode(",\n            ", $selectParts) . "
        FROM tbl_faculty_workload_sched fw
        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fw.schedule_id
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        LEFT JOIN tbl_program p
            ON p.program_id = o.program_id
        LEFT JOIN tbl_college col
            ON col.college_id = p.college_id
        LEFT JOIN tbl_faculty f
            ON f.faculty_id = fw.faculty_id
        INNER JOIN tbl_academic_years ay
            ON ay.ay_id = fw.ay_id
        WHERE fw.faculty_id = ?
          AND ay.ay = ?
          AND fw.semester = ?
        ORDER BY " . implode(",\n                 ", $orderParts) . "
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $facultyId, $ay, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    $preparations = [];
    $facultyName = '';

    while ($row = $res->fetch_assoc()) {
        $facultyName = $facultyName !== '' ? $facultyName : (string)($row['faculty_name'] ?? '');

        $type = strtoupper(trim((string)($row['type'] ?? 'LEC')));
        $lecUnits = (float)($row['lec_units'] ?? 0);
        $labValue = (float)($row['lab_units'] ?? 0);
        $totalUnits = (float)($row['total_units'] ?? 0);

        $labIsCredit = ($labValue > 0) && (abs(($lecUnits + $labValue) - $totalUnits) < 0.0001);
        $labHours = $labIsCredit ? ($labValue * 3.0) : $labValue;
        $subjectUnits = $totalUnits > 0
            ? $totalUnits
            : ($lecUnits + ($labIsCredit ? $labValue : 0));
        $subjectLoad = $lecUnits + ($labHours * 0.75);

        $fullSection = trim((string)($row['full_section'] ?? ''));
        if ($fullSection === '') {
            $fullSection = trim((string)($row['section_name'] ?? ''));
        }

        $subCode = trim((string)($row['sub_code'] ?? ''));
        if ($subCode !== '') {
            $preparations[$subCode] = true;
        }

        $rows[] = [
            'workload_id' => (int)($row['workload_id'] ?? 0),
            'offering_id' => (int)($row['offering_id'] ?? 0),
            'group_id' => (int)($row['group_id'] ?? 0),
            'type' => $type,
            'sub_code' => $subCode,
            'desc' => (string)($row['subject_description'] ?? ''),
            'course' => $fullSection,
            'section' => (string)($row['section_name'] ?? ''),
            'days' => format_workload_days($row['days_json'] ?? '[]'),
            'time' => date("g:iA", strtotime((string)$row['time_start'])) . "-" .
                      date("g:iA", strtotime((string)$row['time_end'])),
            'room' => (string)($row['room_code'] ?? ''),
            'college_code' => (string)($row['college_code'] ?? ''),
            'units' => round($subjectUnits, 2),
            'lec' => round($lecUnits, 2),
            'lab' => round($labHours, 2),
            'faculty_load' => round($subjectLoad, 2),
            'student_count' => 0
        ];
    }

    $stmt->close();

    $designationName = '';
    $designationUnits = 0.0;
    $designationCollegeName = '';

    if ($facultyHasDesignationId && $designationTableExists) {
        $designationWhere = [
            'cf.faculty_id = ?',
            "cf.status = 'active'"
        ];
        $designationTypes = 'i';
        $designationParams = [$facultyId];

        if ($assignmentHasAyId) {
            $designationWhere[] = 'cf.ay_id = ay.ay_id';
        }

        if ($assignmentHasSemester) {
            $designationWhere[] = 'cf.semester = ?';
            $designationTypes .= 'i';
            $designationParams[] = $semester;
        }

        $designationSql = "
            SELECT
                d.designation_name,
                d.designation_units,
                col.college_name,
                cf.college_id
            FROM tbl_college_faculty cf
            INNER JOIN tbl_faculty f
                ON f.faculty_id = cf.faculty_id
            LEFT JOIN tbl_designation d
                ON d.designation_id = f.designation_id
               " . ($designationHasStatus ? "AND d.status = 'active'" : '') . "
            LEFT JOIN tbl_college col
                ON col.college_id = cf.college_id
            INNER JOIN tbl_academic_years ay
                ON ay.ay = ?
            WHERE " . implode("\n              AND ", $designationWhere) . "
            ORDER BY CASE WHEN cf.college_id = ? THEN 0 ELSE 1 END,
                     cf.college_faculty_id DESC
            LIMIT 1
        ";

        $designationTypes = 's' . $designationTypes . 'i';
        array_unshift($designationParams, $ay);
        $designationParams[] = $college_id;

        $designationStmt = $conn->prepare($designationSql);
        synk_bind_dynamic_params($designationStmt, $designationTypes, $designationParams);
        $designationStmt->execute();
        $designationRes = $designationStmt->get_result();
        $designationRow = $designationRes->fetch_assoc();
        $designationStmt->close();

        if (is_array($designationRow)) {
            $designationName = trim((string)($designationRow['designation_name'] ?? ''));
            $designationUnits = (float)($designationRow['designation_units'] ?? 0);
            $designationCollegeName = trim((string)($designationRow['college_name'] ?? ''));
        }
    }

    echo json_encode([
        'rows' => $rows,
        'meta' => [
            'faculty_name' => $facultyName,
            'designation_name' => $designationName,
            'designation_label' => workload_title_case($designationName),
            'designation_units' => round($designationUnits, 2),
            'designation_college_name' => $designationCollegeName,
            'total_preparations' => count($preparations)
        ]
    ]);
    exit;
}

echo json_encode([]);
