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
    $p = $conn->prepare("
        SELECT program_id
        FROM tbl_prospectus_header
        WHERE prospectus_id = ?
    ");
    $p->bind_param("i", $prospectus_id);
    $p->execute();
    $program_id = $p->get_result()->fetch_assoc()['program_id'] ?? 0;

    if (!$program_id) {
        throw new Exception("Invalid prospectus → program not found");
    }

    /* ============================
       DELETE CLASS SCHEDULES FIRST
       (Prevent orphan schedules)
    ============================ */
    $delSched = $conn->prepare("
        DELETE cs
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o
            ON o.offering_id = cs.offering_id
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
    ");

    $delSched->bind_param(
        "iii",
        $prospectus_id,
        $ay_id,
        $semester
    );

    $delSched->execute();
    $deletedSchedules = $delSched->affected_rows;
    $delSched->close();

    /* ============================
       DELETE OLD OFFERINGS
    ============================ */
    $del = $conn->prepare("
        DELETE FROM tbl_prospectus_offering
        WHERE prospectus_id = ?
          AND ay_id = ?
          AND semester = ?
    ");
    $del->bind_param("iii", $prospectus_id, $ay_id, $semester);
    $del->execute();
    $deleted = $del->affected_rows;

    /* ============================
       GET SUBJECTS
    ============================ */
    $sub = $conn->prepare("
        SELECT ps.ps_id, pys.year_level
        FROM tbl_prospectus_year_sem pys
        INNER JOIN tbl_prospectus_subjects ps
            ON ps.pys_id = pys.pys_id
        WHERE pys.prospectus_id = ?
          AND pys.semester = ?
    ");
    $sub->bind_param("ii", $prospectus_id, $semester);
    $sub->execute();
    $subjects = $sub->get_result();

    if ($subjects->num_rows === 0) {
        throw new Exception("No subjects found");
    }

    /* ============================
       PREPARE SECTIONS (FILTERED BY YEAR LEVEL)
       NOTE: This assumes tbl_sections has year_level column
    ============================ */
    $sec = $conn->prepare("
        SELECT section_id
        FROM tbl_sections
        WHERE program_id = ?
          AND year_level = ?
          AND status = 'active'
    ");

    /* ============================
       INSERT OFFERINGS
    ============================ */
    $ins = $conn->prepare("
        INSERT INTO tbl_prospectus_offering
        (program_id, prospectus_id, ps_id, year_level, semester, ay_id, section_id, status, date_created)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

$inserted = 0;

while ($s = $subjects->fetch_assoc()) {

    $yearLevel = (int)$s['year_level'];

    // get ONLY sections that match this subject's year level
    $sec->bind_param("ii", $program_id, $yearLevel);
    $sec->execute();
    $sections = $sec->get_result();

    // if no sections for that year, skip
    if ($sections->num_rows === 0) {
        continue;
    }

    while ($secRow = $sections->fetch_assoc()) {

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


    $conn->commit();

    echo json_encode([
        "status"   => "ok",
        "inserted" => $inserted,
        "deleted"  => $deleted
    ]);

} catch (Exception $e) {

    $conn->rollback();
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
