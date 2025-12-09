<?php
session_start();
ob_start();
include 'db.php';

header('Content-Type: application/json');

// ======================================================================
// GENERATE PROSPECTUS OFFERINGS  (Option A: delete then regenerate)
// ======================================================================
if (isset($_POST['generate_offerings'])) {

    $prospectus_id = intval($_POST['prospectus_id'] ?? 0);
    $ay            = trim($_POST['ay'] ?? '');        // ay_id (e.g., 2)
    $semester      = trim($_POST['semester'] ?? '');  // expects 1 / 2 / 3

    // Normalize semester strictly to '1','2','3'
    if (!in_array($semester, ['1', '2', '3'], true)) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid semester value.'
        ]);
        exit;
    }

    if ($prospectus_id <= 0 || $ay === '') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing prospectus or academic year.'
        ]);
        exit;
    }

    // 1) Get program_id from prospectus header
    $sql  = "SELECT program_id FROM tbl_prospectus_header WHERE prospectus_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Prepare failed (step 1): ' . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("i", $prospectus_id);
    $stmt->execute();
    $stmt->bind_result($program_id);

    if (!$stmt->fetch()) {
        $stmt->close();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Prospectus not found.'
        ]);
        exit;
    }
    $stmt->close();

    // 2) OPTION A - delete existing offerings for this program + AY + semester
    $del = $conn->prepare("
        DELETE FROM tbl_prospectus_offering
        WHERE program_id = ? AND ay = ? AND semester = ?
    ");
    if (!$del) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Prepare failed (delete): ' . $conn->error
        ]);
        exit;
    }

    $del->bind_param("iss", $program_id, $ay, $semester);
    $del->execute();
    $deleted = $del->affected_rows;
    $del->close();

    // 3) Insert fresh offerings
    //    - All subjects in this prospectus + semester
    //    - For every active section of the same program and year level
    $sql = "
        INSERT INTO tbl_prospectus_offering
            (program_id, prospectus_id, ps_id, section_id, ay, semester, status)
        SELECT
            h.program_id,
            h.prospectus_id,
            s.ps_id,
            sec.section_id,
            ?, ?,                    -- ay, semester
            'planned'
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
    if (!$stmt) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Prepare failed (insert): ' . $conn->error
        ]);
        exit;
    }

    // ay (string), semester (string), prospectus_id (int), pys.semester (string)
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
// Fallback if no action matched
// ======================================================================
echo json_encode([
    'status'  => 'error',
    'message' => 'No valid action specified.'
]);
exit;
