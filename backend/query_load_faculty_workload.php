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
   INPUTS
===================================================== */
$faculty_id = intval($_POST['faculty_id'] ?? 0);
$ay_id      = intval($_POST['ay_id'] ?? 0);
$semester   = intval($_POST['semester'] ?? 0);

if (!$faculty_id || !$ay_id || !$semester) {
    echo json_encode([]);
    exit;
}

/* =====================================================
   WORKLOAD COMPUTATION CONFIG
   NOTE:
   - Some datasets store tbl_prospectus_subjects.lab_units as LAB credit units
   - Some datasets store tbl_prospectus_subjects.lab_units as LAB contact hours
   - Faculty Load Rule:
     LEC load = lec_units
      LAB load:
        if LAB is credit units => (lab_units × LAB_CONTACT_HOURS_PER_UNIT) × LAB_LOAD_MULTIPLIER
        else (already hours)    => lab_units × LAB_LOAD_MULTIPLIER
===================================================== */
define('LAB_LOAD_MULTIPLIER', 0.75);
define('LAB_CONTACT_HOURS_PER_UNIT', 3.0);

/* =====================================================
   MAIN QUERY
===================================================== */
$sql = "
SELECT
    fw.workload_id,
    o.offering_id,
    cs.schedule_group_id      AS group_id,
    cs.schedule_type          AS type,

    sm.sub_code,
    sm.sub_description        AS `desc`,
    sec.section_name          AS section,

    cs.days_json,
    cs.time_start,
    cs.time_end,

    r.room_code               AS room,

    ps.lec_units,
    ps.lab_units,
    ps.total_units
FROM tbl_faculty_workload_sched fw
JOIN tbl_class_schedule cs       ON cs.schedule_id = fw.schedule_id
JOIN tbl_prospectus_offering o   ON o.offering_id = cs.offering_id
JOIN tbl_sections sec            ON sec.section_id = o.section_id
JOIN tbl_prospectus_subjects ps  ON ps.ps_id = o.ps_id
JOIN tbl_subject_masterlist sm   ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_rooms r            ON r.room_id = cs.room_id
WHERE
    fw.faculty_id = ?
AND fw.ay_id      = ?
AND fw.semester   = ?
ORDER BY
    sec.section_name,
    sm.sub_code,
    COALESCE(cs.schedule_group_id, cs.schedule_id),
    FIELD(cs.schedule_type, 'LEC', 'LAB'),
    cs.time_start
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $faculty_id, $ay_id, $semester);
$stmt->execute();
$res = $stmt->get_result();

/* =====================================================
   BUILD RESPONSE
===================================================== */
$data = [];

while ($row = $res->fetch_assoc()) {

    /* ---------------------------
       Decode days
    --------------------------- */
    $days_arr = json_decode($row['days_json'], true);
    if (!is_array($days_arr)) {
        $days_arr = [];
    }
    $days = implode(", ", $days_arr);

    /* ---------------------------
       Format time
    --------------------------- */
    $time = date("g:iA", strtotime($row['time_start'])) .
            "–" .
            date("g:iA", strtotime($row['time_end']));

    /* ---------------------------
       Faculty Load Computation
       IMPORTANT:
       - dataset-aware handling for lab_units (credit vs hours)
    --------------------------- */
    $lec_units = (float) $row['lec_units'];
    $lab_value = (float) $row['lab_units'];
    $total_units = (float) $row['total_units'];

    // If lec + lab == total_units, lab is likely credit unit(s), not hours.
    $lab_is_credit = ($lab_value > 0) && (abs(($lec_units + $lab_value) - $total_units) < 0.0001);

    $lab_hours = $lab_is_credit
        ? ($lab_value * LAB_CONTACT_HOURS_PER_UNIT)
        : $lab_value;

    $lab_teaching_load = $lab_hours * LAB_LOAD_MULTIPLIER;
    $lec_load = $lec_units;
    $faculty_load = round($lec_load + $lab_teaching_load, 2);

    /* ---------------------------
       Push row
    --------------------------- */
    $data[] = [
        'workload_id'  => (int)$row['workload_id'],
        'offering_id'  => (int)$row['offering_id'],
        'group_id'     => $row['group_id'],
        'sub_code'     => $row['sub_code'],
        'desc'         => $row['desc'],
        'section'      => $row['section'],
        'type'         => $row['type'],
        'days'         => $days,
        'time'         => $time,
        'room'         => $row['room'] ?? '',
        'lec'          => $lec_units,
        'lab'          => round($lab_hours, 2),
        'lab_load'     => round($lab_teaching_load, 2),
        'units'        => (int)$row['total_units'],
        'faculty_load' => $faculty_load
    ];
}

echo json_encode($data);
exit;
