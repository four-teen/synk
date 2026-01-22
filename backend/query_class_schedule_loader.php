<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

/* =====================================================
   SECURITY
===================================================== */
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}
/* =====================================================
   COLLEGE CONTEXT (SCHEDULER SCOPE)
===================================================== */
if (!isset($_SESSION['college_id'])) {
    echo json_encode([]);
    exit;
}

$college_id = (int) $_SESSION['college_id'];



/* =====================================================
   INPUTS
===================================================== */
$faculty_id_raw = $_POST['faculty_id'] ?? '';
$ay_raw         = trim($_POST['ay'] ?? '');
$semester_raw   = trim($_POST['semester'] ?? '');

if ($ay_raw === '' || $semester_raw === '') {
    echo json_encode([]);
    exit;
}

/* =====================================================
   SEMESTER MAP (UI → DB)
===================================================== */
$semester_map = [
    '1st'     => 1,
    '2nd'     => 2,
    'Midyear' => 3
];

if (!isset($semester_map[$semester_raw])) {
    echo json_encode([]);
    exit;
}

$semester = $semester_map[$semester_raw];

/* =====================================================
   RESOLVE AY → ay_id
===================================================== */
$ayStmt = $conn->prepare("
    SELECT ay_id 
    FROM tbl_academic_years 
    WHERE ay = ? 
    LIMIT 1
");
$ayStmt->bind_param("s", $ay_raw);
$ayStmt->execute();
$ayRes = $ayStmt->get_result();

if ($ayRes->num_rows === 0) {
    echo json_encode([]);
    exit;
}

$ay_id = (int)$ayRes->fetch_assoc()['ay_id'];

/* =====================================================
   MAIN QUERY
   PURPOSE:
   - Load ALL scheduled classes
   - Faculty may be NULL
===================================================== */
$sql = "
SELECT
    cs.schedule_id,
    sm.sub_code,
    sm.sub_description,
    sec.section_name,
    cs.days_json,
    cs.time_start,
    cs.time_end,
    r.room_code,
    ps.total_units AS units,
    ps.lec_units   AS hours_lec,
    ps.lab_units   AS hours_lab
FROM tbl_class_schedule cs
JOIN tbl_prospectus_offering o 
    ON o.offering_id = cs.offering_id
JOIN tbl_program p
    ON p.program_id = o.program_id          -- 🔴 COLLEGE OWNER
JOIN tbl_sections sec 
    ON sec.section_id = o.section_id
JOIN tbl_prospectus_subjects ps 
    ON ps.ps_id = o.ps_id
JOIN tbl_subject_masterlist sm 
    ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_rooms r 
    ON r.room_id = cs.room_id
WHERE
    o.ay_id = ?
AND o.semester = ?
AND p.college_id = ?                         -- 🔴 COLLEGE FILTER
AND cs.schedule_id NOT IN (
    SELECT fw.schedule_id
    FROM tbl_faculty_workload_sched fw
    WHERE fw.ay_id = ?
      AND fw.semester = ?
)
ORDER BY
    sec.section_name,
    sm.sub_code,
    cs.time_start
";


$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iiiii",
    $ay_id,
    $semester,
    $college_id,
    $ay_id,
    $semester
);
$stmt->execute();
$res = $stmt->get_result();

/* =====================================================
   FORMAT OUTPUT
===================================================== */
$data = [];

while ($row = $res->fetch_assoc()) {

    $days_arr = json_decode($row['days_json'], true);
    if (!is_array($days_arr)) $days_arr = [];

    $data[] = [
        'schedule_id'         => (int)$row['schedule_id'],
        'subject_code'        => $row['sub_code'],
        'subject_description' => $row['sub_description'],
        'section_name'        => $row['section_name'],
        'days'                => implode(", ", $days_arr),
        'time'                => date("g:iA", strtotime($row['time_start'])) . "–" .
                                 date("g:iA", strtotime($row['time_end'])),
        'room_code'           => $row['room_code'] ?? '',
        'units'               => (int)$row['units'],
        'hours_lec'           => (int)$row['hours_lec'],
        'hours_lab'           => (int)$row['hours_lab']
    ];
}

echo json_encode($data);
exit;
