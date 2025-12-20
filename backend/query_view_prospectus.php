<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$pid = intval($_GET['prospectus_id'] ?? 0);
if ($pid <= 0) {
    echo json_encode(["error" => "Invalid prospectus id"]);
    exit;
}

/* ===========================
   1) HEADER INFO
=========================== */
$hsql = "
    SELECT h.prospectus_id, h.cmo_no, h.effective_sy,
           p.program_name, p.program_code, p.major
    FROM tbl_prospectus_header h
    LEFT JOIN tbl_program p ON p.program_id = h.program_id
    WHERE h.prospectus_id = ?
    LIMIT 1
";
$hstmt = $conn->prepare($hsql);
$hstmt->bind_param("i", $pid);
$hstmt->execute();
$header = $hstmt->get_result()->fetch_assoc();
$hstmt->close();

if (!$header) {
    echo json_encode(["error" => "Prospectus not found"]);
    exit;
}

/* ===========================
   2) YEAR/SEM STRUCTURE (even if no subjects)
=========================== */
$structure = []; // [year][sem] = []
$ysSql = "
    SELECT pys_id, year_level, semester
    FROM tbl_prospectus_year_sem
    WHERE prospectus_id = ?
    ORDER BY year_level ASC, semester ASC
";
$ysStmt = $conn->prepare($ysSql);
$ysStmt->bind_param("i", $pid);
$ysStmt->execute();
$ysRes = $ysStmt->get_result();

$pysMap = []; // pys_id -> [year, sem]
while ($row = $ysRes->fetch_assoc()) {
    $year = (string)$row['year_level'];
    $sem  = (string)$row['semester'];

    if (!isset($structure[$year])) $structure[$year] = [];
    if (!isset($structure[$year][$sem])) $structure[$year][$sem] = [];

    $pysMap[(int)$row['pys_id']] = ["year" => $year, "sem" => $sem];
}
$ysStmt->close();

/* ===========================
   3) SUBJECTS (LEFT JOIN-safe via mapping)
=========================== */
$subjects = []; // [year][sem] = [rows...]

if (!empty($pysMap)) {

    // pull all subjects for these pys ids
    $pysIds = array_keys($pysMap);
    $placeholders = implode(',', array_fill(0, count($pysIds), '?'));
    $types = str_repeat('i', count($pysIds));

    $subSql = "
        SELECT ps.pys_id,
               s.sub_code, s.sub_description,
               ps.lec_units, ps.lab_units, ps.total_units,
               ps.prerequisites, ps.sort_order
        FROM tbl_prospectus_subjects ps
        JOIN tbl_subject_masterlist s ON s.sub_id = ps.sub_id
        WHERE ps.pys_id IN ($placeholders)
        ORDER BY ps.pys_id ASC, ps.sort_order ASC, s.sub_code ASC
    ";

    $subStmt = $conn->prepare($subSql);
    $subStmt->bind_param($types, ...$pysIds);
    $subStmt->execute();
    $subRes = $subStmt->get_result();

    while ($s = $subRes->fetch_assoc()) {
        $pys_id = (int)$s['pys_id'];

        if (!isset($pysMap[$pys_id])) continue;

        $year = $pysMap[$pys_id]["year"];
        $sem  = $pysMap[$pys_id]["sem"];

        if (!isset($subjects[$year])) $subjects[$year] = [];
        if (!isset($subjects[$year][$sem])) $subjects[$year][$sem] = [];

        // normalize prereq display
        $s['prerequisites'] = trim($s['prerequisites'] ?? '');
        if ($s['prerequisites'] === '') $s['prerequisites'] = 'None';

        // ensure total_units
        $lec = (int)$s['lec_units'];
        $lab = (int)$s['lab_units'];
        $s['total_units'] = isset($s['total_units']) && $s['total_units'] !== null
            ? (int)$s['total_units']
            : ($lec + $lab);

        $subjects[$year][$sem][] = $s;
    }

    $subStmt->close();
}

echo json_encode([
    "header"    => $header,
    "structure" => $structure,
    "subjects"  => $subjects
]);
exit;
