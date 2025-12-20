<?php
session_start();
include 'db.php';

if ($_SESSION['role'] !== 'admin') {
    echo "ERROR|Unauthorized access.";
    exit;
}

if (isset($_POST['load_programs'])) {

    $res = $conn->query("
        SELECT program_id, program_name, program_code
        FROM tbl_program
        WHERE status='active'
        ORDER BY program_name
    ");

    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = $r;
    }

    echo json_encode($data);
    exit;
}

if (isset($_POST['copy_prospectus'])) {

    $source = intval($_POST['source_prospectus_id'] ?? 0);
    $target = intval($_POST['target_program_id'] ?? 0);
    $cmo    = trim($_POST['cmo_no'] ?? '');
    $sy     = trim($_POST['effective_sy'] ?? '');

    if ($source <= 0 || $target <= 0 || $cmo === '' || $sy === '') {
        echo "ERROR|Missing required fields.";
        exit;
    }

    // BLOCK DUPLICATE
    $chk = $conn->prepare("
        SELECT prospectus_id
        FROM tbl_prospectus_header
        WHERE program_id = ? AND cmo_no = ? AND effective_sy = ?
        LIMIT 1
    ");
    $chk->bind_param("iss", $target, $cmo, $sy);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo "ERROR|Target program already has this CMO.";
        exit;
    }
    $chk->close();

    // CREATE NEW HEADER
    $stmt = $conn->prepare("
        INSERT INTO tbl_prospectus_header (program_id, cmo_no, effective_sy)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $target, $cmo, $sy);
    $stmt->execute();
    $newProspectusId = $stmt->insert_id;
    $stmt->close();

    // COPY YEAR / SEM
    $map = [];

    $ys = $conn->prepare("
        SELECT pys_id, year_level, semester
        FROM tbl_prospectus_year_sem
        WHERE prospectus_id = ?
    ");
    $ys->bind_param("i", $source);
    $ys->execute();
    $res = $ys->get_result();

    while ($r = $res->fetch_assoc()) {
        $ins = $conn->prepare("
            INSERT INTO tbl_prospectus_year_sem
            (prospectus_id, year_level, semester)
            VALUES (?, ?, ?)
        ");
        $ins->bind_param("iss", $newProspectusId, $r['year_level'], $r['semester']);
        $ins->execute();

        $map[$r['pys_id']] = $ins->insert_id;
        $ins->close();
    }
    $ys->close();

    // COPY SUBJECTS
    foreach ($map as $oldPys => $newPys) {

        $subs = $conn->prepare("
            SELECT sub_id, lec_units, lab_units, total_units,
                   prerequisites, prerequisite_sub_ids, sort_order
            FROM tbl_prospectus_subjects
            WHERE pys_id = ?
        ");
        $subs->bind_param("i", $oldPys);
        $subs->execute();
        $sres = $subs->get_result();

        while ($s = $sres->fetch_assoc()) {

            $ins = $conn->prepare("
                INSERT INTO tbl_prospectus_subjects
                (pys_id, sub_id, lec_units, lab_units, total_units,
                 prerequisites, prerequisite_sub_ids, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $ins->bind_param(
                "iiiiissi",
                $newPys,
                $s['sub_id'],
                $s['lec_units'],
                $s['lab_units'],
                $s['total_units'],
                $s['prerequisites'],
                $s['prerequisite_sub_ids'],
                $s['sort_order']
            );

            $ins->execute();
            $ins->close();
        }
        $subs->close();
    }

    echo "OK|{$newProspectusId}|Copied successfully.";
    exit;
}


