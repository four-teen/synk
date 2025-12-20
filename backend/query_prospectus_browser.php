<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

if (isset($_POST['load_prospectus_by_program'])) {

    $programId = intval($_POST['program_id'] ?? 0);
    if ($programId <= 0) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT
            h.prospectus_id,
            h.cmo_no,
            h.effective_sy,
            COUNT(DISTINCT pys.pys_id) AS yearsem_count,
            COUNT(ps.ps_id) AS subject_count,
            SUM(ps.total_units) AS total_units
        FROM tbl_prospectus_header h
        LEFT JOIN tbl_prospectus_year_sem pys
            ON pys.prospectus_id = h.prospectus_id
        LEFT JOIN tbl_prospectus_subjects ps
            ON ps.pys_id = pys.pys_id
        WHERE h.program_id = ?
        GROUP BY h.prospectus_id
        ORDER BY h.prospectus_id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $programId);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = $r;
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
