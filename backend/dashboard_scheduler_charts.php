<?php
session_start();
include 'db.php';
require_once __DIR__ . '/academic_term_helper.php';
require_once __DIR__ . '/offering_scope_helper.php';

header('Content-Type: application/json');

function empty_dashboard_chart_payload() {
    return [
        'program_progress' => [],
        'weekly_pressure' => []
    ];
}

function dashboard_day_order() {
    return ['M', 'T', 'W', 'Th', 'F', 'S'];
}

function dashboard_day_label($token) {
    $labels = [
        'M' => 'Mon',
        'T' => 'Tue',
        'W' => 'Wed',
        'Th' => 'Thu',
        'F' => 'Fri',
        'S' => 'Sat'
    ];

    return $labels[$token] ?? $token;
}

function normalize_dashboard_day_token($day) {
    $token = strtoupper(trim((string)$day));

    if ($token === 'TH') {
        return 'Th';
    }

    return in_array($token, ['M', 'T', 'W', 'F', 'S'], true) ? $token : '';
}

if (!isset($_SESSION['college_id'])) {
    echo json_encode(empty_dashboard_chart_payload());
    exit;
}

$college_id = (int)$_SESSION['college_id'];
$currentTerm = synk_fetch_current_academic_term($conn);
$currentAyId = (int)($currentTerm['ay_id'] ?? 0);
$currentSemester = (int)($currentTerm['semester'] ?? 0);

if ($college_id <= 0 || $currentAyId <= 0 || $currentSemester <= 0) {
    echo json_encode(empty_dashboard_chart_payload());
    exit;
}

$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

$programProgress = [];
$programSql = "
    SELECT
        p.program_id,
        COALESCE(
            NULLIF(TRIM(p.program_code), ''),
            NULLIF(TRIM(p.program_name), ''),
            CONCAT('Program ', p.program_id)
        ) AS program_label,
        COUNT(DISTINCT o.offering_id) AS total_offerings,
        COUNT(DISTINCT cs.offering_id) AS scheduled_offerings
    FROM tbl_prospectus_offering o
    {$liveOfferingJoins}
    INNER JOIN tbl_program p
        ON p.program_id = o.program_id
    LEFT JOIN tbl_class_schedule cs
        ON cs.offering_id = o.offering_id
    WHERE p.college_id = ?
      AND p.status = 'active'
      AND o.ay_id = ?
      AND o.semester = ?
    GROUP BY p.program_id, p.program_code, p.program_name
    HAVING COUNT(DISTINCT o.offering_id) > 0
    ORDER BY total_offerings DESC, program_label ASC
";

$programStmt = $conn->prepare($programSql);
$programStmt->bind_param("iii", $college_id, $currentAyId, $currentSemester);
$programStmt->execute();
$programRes = $programStmt->get_result();

while ($row = $programRes->fetch_assoc()) {
    $total = (int)($row['total_offerings'] ?? 0);
    $scheduled = (int)($row['scheduled_offerings'] ?? 0);

    $programProgress[] = [
        'program' => $row['program_label'],
        'total' => $total,
        'scheduled' => $scheduled,
        'pending' => max(0, $total - $scheduled)
    ];
}

$programStmt->close();

$weeklyDaySets = [];
foreach (dashboard_day_order() as $token) {
    $weeklyDaySets[$token] = [
        'meetings' => [],
        'rooms' => [],
        'faculty' => []
    ];
}

$weeklySql = "
    SELECT
        cs.schedule_id,
        cs.room_id,
        cs.days_json,
        fw.faculty_id
    FROM tbl_class_schedule cs
    INNER JOIN tbl_prospectus_offering o
        ON o.offering_id = cs.offering_id
    {$liveOfferingJoins}
    INNER JOIN tbl_program p
        ON p.program_id = o.program_id
    LEFT JOIN tbl_faculty_workload_sched fw
        ON fw.schedule_id = cs.schedule_id
       AND fw.ay_id = ?
       AND fw.semester = ?
    WHERE p.college_id = ?
      AND p.status = 'active'
      AND o.ay_id = ?
      AND o.semester = ?
";

$weeklyStmt = $conn->prepare($weeklySql);
$weeklyStmt->bind_param("iiiii", $currentAyId, $currentSemester, $college_id, $currentAyId, $currentSemester);
$weeklyStmt->execute();
$weeklyRes = $weeklyStmt->get_result();

while ($row = $weeklyRes->fetch_assoc()) {
    $days = json_decode((string)($row['days_json'] ?? ''), true);

    if (!is_array($days) || empty($days)) {
        continue;
    }

    $scheduleId = (int)($row['schedule_id'] ?? 0);
    $roomId = (int)($row['room_id'] ?? 0);
    $facultyId = (int)($row['faculty_id'] ?? 0);

    foreach ($days as $day) {
        $token = normalize_dashboard_day_token($day);

        if ($token === '' || !isset($weeklyDaySets[$token]) || $scheduleId <= 0) {
            continue;
        }

        $weeklyDaySets[$token]['meetings'][$scheduleId] = true;

        if ($roomId > 0) {
            $weeklyDaySets[$token]['rooms'][$roomId] = true;
        }

        if ($facultyId > 0) {
            $weeklyDaySets[$token]['faculty'][$facultyId] = true;
        }
    }
}

$weeklyStmt->close();

$weeklyPressure = [];
foreach (dashboard_day_order() as $token) {
    $weeklyPressure[] = [
        'day' => dashboard_day_label($token),
        'meetings' => count($weeklyDaySets[$token]['meetings']),
        'rooms' => count($weeklyDaySets[$token]['rooms']),
        'faculty' => count($weeklyDaySets[$token]['faculty'])
    ];
}

echo json_encode([
    'program_progress' => $programProgress,
    'weekly_pressure' => $weeklyPressure
]);
exit;
?>
