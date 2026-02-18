<?php
/**
 * ======================================================================
 * Backend Handler : Sections (AJAX)
 * File Path       : synk/backend/query_sections.php
 * ======================================================================
 * HANDLES:
 * - load_grouped_sections (filtered by college + ay_id + semester)
 * - get_next_section_index (scope-aware)
 * - save_sections (scope-aware + duplicate-safe)
 * - delete_section (college-safe)
 * ======================================================================
 */

session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'scheduler') {
    exit('forbidden');
}

$college_id = (int)($_SESSION['college_id'] ?? 0);

/* =========================================================
   1) LOAD GROUPED SECTIONS (BY PROGRAM) — scope-aware
========================================================= */
if (isset($_POST['load_grouped_sections'])) {

    $ay_id    = isset($_POST['ay_id']) ? (int)$_POST['ay_id'] : 0;
    $semester = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;

    if ($ay_id <= 0 || $semester <= 0) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT
            s.section_id,
            s.year_level,
            s.section_name,
            s.full_section,
            s.status,
            p.program_code,
            p.program_name
        FROM tbl_sections s
        JOIN tbl_program p ON p.program_id = s.program_id
        WHERE p.college_id = ?
          AND s.ay_id = ?
          AND s.semester = ?
        ORDER BY p.program_code ASC, s.year_level ASC, s.section_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $college_id, $ay_id, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    $grouped = [];

    while ($row = $res->fetch_assoc()) {
        $programLabel = $row['program_code'] . " — " . $row['program_name'];

        if (!isset($grouped[$programLabel])) {
            $grouped[$programLabel] = [];
        }

        $grouped[$programLabel][] = [
            'section_id'    => (int)$row['section_id'],
            'year_level'    => $row['year_level'],
            'section_name'  => $row['section_name'],
            'full_section'  => $row['full_section'],
            'status'        => $row['status']
        ];
    }

    echo json_encode($grouped);
    exit;
}

/* =========================================================
   2) GET NEXT SECTION INDEX (A-Z) — scope-aware
========================================================= */
if (isset($_POST['get_next_section_index'])) {

    $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;
    $year_level = isset($_POST['year_level']) ? (int)$_POST['year_level'] : 0;
    $ay_id      = isset($_POST['ay_id']) ? (int)$_POST['ay_id'] : 0;
    $semester   = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;

    if ($program_id <= 0 || $year_level <= 0 || $ay_id <= 0 || $semester <= 0) {
        echo json_encode(['next_index' => 0]);
        exit;
    }

    // SECURITY: ensure program belongs to this college
    $chk = $conn->prepare("SELECT 1 FROM tbl_program WHERE program_id=? AND college_id=? LIMIT 1");
    $chk->bind_param("ii", $program_id, $college_id);
    $chk->execute();
    $chkRes = $chk->get_result();
    if ($chkRes->num_rows === 0) {
        echo json_encode(['next_index' => 0]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT section_name
        FROM tbl_sections
        WHERE program_id = ?
          AND ay_id = ?
          AND semester = ?
          AND year_level = ?
    ");
    $stmt->bind_param("iiii", $program_id, $ay_id, $semester, $year_level);
    $stmt->execute();
    $res = $stmt->get_result();

    $maxIndex = 0; // 0 = start at A

    while ($row = $res->fetch_assoc()) {
        $name = (string)$row['section_name']; // e.g., 1A
        $letter = strtoupper(substr($name, -1)); // A

        if ($letter >= 'A' && $letter <= 'Z') {
            $idx = ord($letter) - ord('A') + 1; // A=>1, B=>2...
            if ($idx > $maxIndex) $maxIndex = $idx;
        }
    }

    // next index should be maxIndex (A already used => start from B)
    echo json_encode(['next_index' => $maxIndex]);
    exit;
}

/* =========================================================
   3) SAVE SECTIONS — scope-aware + duplicate-safe
========================================================= */
if (isset($_POST['save_sections'])) {

    $program_id   = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;
    $program_code = trim($_POST['program_code'] ?? '');
    $ay_id        = isset($_POST['ay_id']) ? (int)$_POST['ay_id'] : 0;
    $semester     = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;
    $year_level   = isset($_POST['year_level']) ? (int)$_POST['year_level'] : 0;
    $count        = isset($_POST['count']) ? (int)$_POST['count'] : 0;
    $start_index  = isset($_POST['start_index']) ? (int)$_POST['start_index'] : 0;

    if ($program_id <= 0 || $ay_id <= 0 || $semester <= 0 || $year_level <= 0 || $count <= 0) {
        exit("Missing required data.");
    }

    if ($semester < 1 || $semester > 3) {
        exit("Invalid semester value.");
    }

    // SECURITY: ensure program belongs to this college
    $chk = $conn->prepare("SELECT 1 FROM tbl_program WHERE program_id=? AND college_id=? LIMIT 1");
    $chk->bind_param("ii", $program_id, $college_id);
    $chk->execute();
    $chkRes = $chk->get_result();
    if ($chkRes->num_rows === 0) {
        exit("forbidden");
    }

    $letters = range('A', 'Z');

    // Build intended section_names
    $sectionNames = [];
    for ($i = 0; $i < $count; $i++) {
        $idx = $start_index + $i;
        if (!isset($letters[$idx])) break;
        $sectionNames[] = $year_level . $letters[$idx]; // e.g., 1A
    }

    if (count($sectionNames) === 0) {
        exit("No sections to save.");
    }

    // Pre-check duplicates within the same scope
    $placeholders = implode(',', array_fill(0, count($sectionNames), '?'));
    $types = str_repeat('s', count($sectionNames));

    $dupSql = "
        SELECT COUNT(*) AS cnt
        FROM tbl_sections
        WHERE program_id = ?
          AND ay_id = ?
          AND semester = ?
          AND year_level = ?
          AND section_name IN ($placeholders)
    ";

    $dupStmt = $conn->prepare($dupSql);

    // bind params dynamically
    $bindTypes = "iiii" . $types;
    $params = array_merge([$bindTypes, $program_id, $ay_id, $semester, $year_level], $sectionNames);

    // php 7+ dynamic bind
    $tmp = [];
    foreach ($params as $k => $v) $tmp[$k] = &$params[$k];
    call_user_func_array([$dupStmt, 'bind_param'], $tmp);

    $dupStmt->execute();
    $dupRes = $dupStmt->get_result();
    $dupRow = $dupRes->fetch_assoc();
    if ((int)$dupRow['cnt'] > 0) {
        exit("Duplicate detected: Some sections already exist in this Academic Year + Semester scope.");
    }

    // Insert all (transaction)
    $conn->begin_transaction();

    try {
        $ins = $conn->prepare("
            INSERT INTO tbl_sections
                (program_id, ay_id, semester, year_level, section_name, full_section, status)
            VALUES
                (?, ?, ?, ?, ?, ?, 'active')
        ");

        foreach ($sectionNames as $sn) {
            $full = $program_code . " " . $sn;

            $ins->bind_param("iiiiss", $program_id, $ay_id, $semester, $year_level, $sn, $full);
            $ins->execute();
        }

        $conn->commit();
        exit("success");

    } catch (Exception $e) {
        $conn->rollback();
        exit("Error saving sections: " . $e->getMessage());
    }
}

/* =========================================================
   4) DELETE SECTION — safe by college ownership
========================================================= */
if (isset($_POST['delete_section'])) {

    $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
    if ($section_id <= 0) exit("error");

    // Ensure section belongs to a program under this college
    $chk = $conn->prepare("
        SELECT 1
        FROM tbl_sections s
        JOIN tbl_program p ON p.program_id = s.program_id
        WHERE s.section_id = ?
          AND p.college_id = ?
        LIMIT 1
    ");
    $chk->bind_param("ii", $section_id, $college_id);
    $chk->execute();
    $chkRes = $chk->get_result();

    if ($chkRes->num_rows === 0) {
        exit("forbidden");
    }

    $del = $conn->prepare("DELETE FROM tbl_sections WHERE section_id = ? LIMIT 1");
    $del->bind_param("i", $section_id);

    if ($del->execute()) exit("success");
    exit("error");
}

exit("invalid");
