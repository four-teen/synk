<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

if (!isset($_POST['generate_offerings'])) {
    echo json_encode(["status"=>"error","message"=>"Invalid request"]);
    exit;
}

$prospectus_id = intval($_POST['prospectus_id'] ?? 0);
$ay_id         = intval($_POST['ay_id'] ?? 0);
$semester      = intval($_POST['semester'] ?? 0);

if (!$prospectus_id || !$ay_id || !$semester) {
    echo json_encode(["status"=>"error","message"=>"Missing parameters"]);
    exit;
}

$conn->begin_transaction();

try {

    /* ============================
       GET PROGRAM ID
    ============================ */
    $stmt = $conn->prepare("
        SELECT program_id
        FROM tbl_prospectus_header
        WHERE prospectus_id = ?
    ");
    $stmt->bind_param("i", $prospectus_id);
    $stmt->execute();
    $program_id = $stmt->get_result()->fetch_assoc()['program_id'] ?? 0;
    $stmt->close();

    if (!$program_id) {
        throw new Exception("Invalid prospectus");
    }

    /* ============================
       LOAD SUBJECTS (WITH YEAR LEVEL)
    ============================ */
    $sub = $conn->prepare("
        SELECT ps.ps_id, pys.year_level
        FROM tbl_prospectus_year_sem pys
        JOIN tbl_prospectus_subjects ps ON ps.pys_id = pys.pys_id
        WHERE pys.prospectus_id = ?
          AND pys.semester = ?
    ");
    $sub->bind_param("ii", $prospectus_id, $semester);
    $sub->execute();
    $subjects = $sub->get_result();

    if ($subjects->num_rows === 0) {
        throw new Exception("No subjects found for this semester");
    }

    /* ============================
       PREPARE STATEMENTS
    ============================ */

    // Active sections by year level
    $sec = $conn->prepare("
        SELECT section_id
        FROM tbl_sections
        WHERE program_id = ?
          AND year_level = ?
          AND status = 'active'
    ");

    // Check existing offering
    $chk = $conn->prepare("
        SELECT offering_id
        FROM tbl_prospectus_offering
        WHERE prospectus_id = ?
          AND ay_id = ?
          AND semester = ?
          AND ps_id = ?
          AND section_id = ?
        LIMIT 1
    ");

    // Insert offering
    $ins = $conn->prepare("
        INSERT INTO tbl_prospectus_offering
        (program_id, prospectus_id, ps_id, year_level, semester, ay_id, section_id, status, date_created)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    $inserted = 0;

    /* ============================
       ADD MISSING OFFERINGS
    ============================ */
    while ($s = $subjects->fetch_assoc()) {

        $yearLevel = (int)$s['year_level'];

        $sec->bind_param("ii", $program_id, $yearLevel);
        $sec->execute();
        $sections = $sec->get_result();

        if ($sections->num_rows === 0) continue;

        while ($secRow = $sections->fetch_assoc()) {

            // Check if offering exists
            $chk->bind_param(
                "iiiii",
                $prospectus_id,
                $ay_id,
                $semester,
                $s['ps_id'],
                $secRow['section_id']
            );
            $chk->execute();
            $exists = $chk->get_result()->num_rows > 0;

            if ($exists) continue;

            // Insert new offering
            $ins->bind_param(
                "iiiiiii",
                $program_id,
                $prospectus_id,
                $s['ps_id'],
                $yearLevel,
                $semester,
                $ay_id,
                $secRow['section_id']
            );
            $ins->execute();
            $inserted++;
        }
    }

    /* ============================
       CLEAN UP INACTIVE SECTIONS
    ============================ */

    // Get offerings tied to inactive sections (excluding locked)
    $old = $conn->prepare("
        SELECT o.offering_id
        FROM tbl_prospectus_offering o
        JOIN tbl_sections s ON s.section_id = o.section_id
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND s.status = 'inactive'
          AND (o.status IS NULL OR o.status != 'locked')
    ");
    $old->bind_param("iii", $prospectus_id, $ay_id, $semester);
    $old->execute();
    $oldRes = $old->get_result();

    $deleted_offerings = 0;
    $deleted_schedules = 0;

    if ($oldRes->num_rows > 0) {

        // Delete schedules first
        $delSched = $conn->prepare("
            DELETE FROM tbl_class_schedule
            WHERE offering_id = ?
        ");

        // Delete offering
        $delOff = $conn->prepare("
            DELETE FROM tbl_prospectus_offering
            WHERE offering_id = ?
        ");

        while ($row = $oldRes->fetch_assoc()) {

            $oid = (int)$row['offering_id'];

            $delSched->bind_param("i", $oid);
            $delSched->execute();
            $deleted_schedules += $delSched->affected_rows;

            $delOff->bind_param("i", $oid);
            $delOff->execute();
            $deleted_offerings++;
        }
    }

    $conn->commit();

    echo json_encode([
        "status"             => "ok",
        "inserted"           => $inserted,
        "deleted_offerings"  => $deleted_offerings,
        "deleted_schedules"  => $deleted_schedules
    ]);

} catch (Exception $e) {

    $conn->rollback();
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
