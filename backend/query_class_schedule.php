<?php
session_start();
ob_start();
include 'db.php';

header('Content-Type: application/json');

// ======================================================================
// A. GENERATE PROSPECTUS OFFERINGS  (Option A: delete then regenerate)
// ======================================================================
if (isset($_POST['generate_offerings'])) {

    $prospectus_id = intval($_POST['prospectus_id'] ?? 0);
    $ay            = trim($_POST['ay'] ?? '');
    $semester      = trim($_POST['semester'] ?? '');

    if ($prospectus_id <= 0 || $ay === '' || $semester === '') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing prospectus, AY, or semester.'
        ]);
        exit;
    }

    // 1) Get program_id from prospectus header
    $sql  = "SELECT program_id FROM tbl_prospectus_header WHERE prospectus_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prospectus_id);
    $stmt->execute();
    $stmt->bind_result($program_id);

    if (!$stmt->fetch()) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Prospectus not found.'
        ]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // 2) OPTION A - delete existing offerings for this program + AY + semester
    $del = $conn->prepare("
        DELETE FROM tbl_prospectus_offering
        WHERE program_id = ? AND ay = ? AND semester = ?
    ");
    $del->bind_param("iss", $program_id, $ay, $semester);
    $del->execute();
    $deleted = $del->affected_rows;
    $del->close();

    // 3) Insert fresh offerings
    $sql = "
        INSERT INTO tbl_prospectus_offering
            (program_id, prospectus_id, ps_id, section_id, ay, semester, status)
        SELECT
            h.program_id,
            h.prospectus_id,
            s.ps_id,
            sec.section_id,
            ?, ?,                     -- ay, semester
            'open'
        FROM tbl_prospectus_header h
        JOIN tbl_prospectus_year_sem pys
              ON pys.prospectus_id = h.prospectus_id
        JOIN tbl_prospectus_subjects s
              ON s.pys_id = pys.pys_id
        JOIN tbl_sections sec
              ON sec.program_id = h.program_id
             AND sec.year_level = pys.year_level
        WHERE h.prospectus_id = ?
          AND pys.semester = ?
          AND sec.status = 'active'
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssis", $ay, $semester, $prospectus_id, $semester);
    $stmt->execute();
    $inserted = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'status'   => 'ok',
        'deleted'  => $deleted,
        'inserted' => $inserted
    ]);
    exit;
}

// ======================================================================
// B. SAVE / UPDATE CLASS SCHEDULE (Modal)
// ======================================================================
if (isset($_POST['save_schedule'])) {

    $offering_id = intval($_POST['offering_id'] ?? 0);
    $faculty_id  = intval($_POST['faculty_id'] ?? 0);
    $room_id     = intval($_POST['room_id'] ?? 0);
    $time_start  = $_POST['time_start'] ?? '';
    $time_end    = $_POST['time_end'] ?? '';
    $days_json   = $_POST['days_json'] ?? '[]';

    if ($offering_id <= 0 || $faculty_id <= 0 || $room_id <= 0 ||
        $time_start === '' || $time_end === '' || $days_json === '[]') {

        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing schedule data.'
        ]);
        exit;
    }

    if ($time_end <= $time_start) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'End time must be later than start time.'
        ]);
        exit;
    }

    // 1) Fetch offering info & subject_id
    $sql = "
        SELECT 
            o.program_id,
            o.section_id,
            o.ay,
            o.semester,
            ps.sub_id
        FROM tbl_prospectus_offering o
        JOIN tbl_prospectus_subjects ps ON ps.ps_id = o.ps_id
        WHERE o.offering_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $offering_id);
    $stmt->execute();
    $stmt->bind_result($program_id, $section_id, $ay, $semester, $subject_id);

    if (!$stmt->fetch()) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Offering not found.'
        ]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // decode selected days
    $newDays = json_decode($days_json, true);
    if (!is_array($newDays) || count($newDays) === 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid day selection.'
        ]);
        exit;
    }

    // 2) Load existing schedules for conflict checking
    $sql = "
        SELECT 
            workload_id,
            faculty_id,
            room_id,
            section_id,
            subject_id,
            days_json,
            time_start,
            time_end
        FROM tbl_class_schedule
        WHERE ay = ?
          AND semester = ?
          AND (
                faculty_id = ? 
             OR room_id    = ?
             OR section_id = ?
          )
          AND NOT (
                program_id = ?
            AND section_id = ?
            AND subject_id = ?
          )
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssiiiii",
        $ay,
        $semester,
        $faculty_id,
        $room_id,
        $section_id,
        $program_id,
        $section_id,
        $subject_id
    );
    $stmt->execute();
    $res = $stmt->get_result();

    $conflicts = [];

    while ($row = $res->fetch_assoc()) {
        $existDays = json_decode($row['days_json'], true) ?: [];
        $commonDays = array_intersect($newDays, $existDays);
        if (empty($commonDays)) {
            continue;
        }

        // time overlap check
        if ($time_start < $row['time_end'] && $time_end > $row['time_start']) {

            if ($row['faculty_id'] == $faculty_id) {
                $conflicts[] = 'Faculty has another class at this time.';
            }
            if ($row['room_id'] == $room_id) {
                $conflicts[] = 'Room is already occupied at this time.';
            }
            if ($row['section_id'] == $section_id) {
                $conflicts[] = 'Section already has another subject at this time.';
            }
        }
    }
    $stmt->close();

    if (!empty($conflicts)) {
        $conflicts = array_unique($conflicts);
        echo json_encode([
            'status'  => 'conflict',
            'message' => implode('<br>', $conflicts)
        ]);
        exit;
    }

    // 3) No conflict – delete old schedule for this offering (if any)
    $del = $conn->prepare("
        DELETE FROM tbl_class_schedule
        WHERE program_id = ?
          AND section_id = ?
          AND subject_id = ?
          AND ay = ?
          AND semester = ?
    ");
    $del->bind_param("iiiss", $program_id, $section_id, $subject_id, $ay, $semester);
    $del->execute();
    $del->close();

    // 4) Insert new schedule
    $ins = $conn->prepare("
        INSERT INTO tbl_class_schedule
            (faculty_id, program_id, section_id, year_level,
             subject_id, ay, semester, days_json,
             time_start, time_end, room_id)
        VALUES
            (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)
    ");
    $year_level_dummy = 0; // you may update this later if you want exact year_level
    $ins->bind_param(
        "iiiisssssii",
        $faculty_id,
        $program_id,
        $section_id,
        $subject_id,
        $ay,
        $semester,
        $days_json,
        $time_start,
        $time_end,
        $room_id
    );
    $ins->execute();
    $ins->close();

    // 5) Mark offering as 'scheduled'
    $up = $conn->prepare("
        UPDATE tbl_prospectus_offering
        SET status = 'scheduled'
        WHERE offering_id = ?
    ");
    $up->bind_param("i", $offering_id);
    $up->execute();
    $up->close();

    echo json_encode([
        'status'  => 'ok',
        'message' => 'Schedule saved.'
    ]);
    exit;
}

// ======================================================================
// Fallback
// ======================================================================
echo json_encode([
    'status'  => 'error',
    'message' => 'No valid action specified.'
]);
exit;
