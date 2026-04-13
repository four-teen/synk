<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schema_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'scheduler') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.', 'faculty' => []]);
    exit;
}

$collegeId = (int)($_SESSION['college_id'] ?? 0);
$ayId = (int)($_POST['ay_id'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);
$facultyIds = $_POST['faculty_ids'] ?? [];

if (!is_array($facultyIds)) {
    $facultyIds = [$facultyIds];
}

$facultyIds = array_values(array_unique(array_filter(array_map('intval', $facultyIds), static function ($value) {
    return $value > 0;
})));

if ($collegeId <= 0 || $ayId <= 0 || $semester <= 0 || empty($facultyIds)) {
    echo json_encode(['status' => 'ok', 'faculty' => []]);
    exit;
}

function workload_overview_title_case(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = strtolower($value);
    $value = preg_replace_callback('/(^|[\s,\/-])([a-z])/', static function ($matches) {
        return $matches[1] . strtoupper($matches[2]);
    }, $value);

    return (string)$value;
}

function workload_overview_context_key(int $groupId, int $scheduleId, int $offeringId): string
{
    if ($groupId > 0) {
        return 'group:' . $groupId;
    }

    if ($scheduleId > 0) {
        return 'schedule:' . $scheduleId;
    }

    return 'offering:' . $offeringId;
}

$liveOfferingJoins = synk_section_curriculum_live_offering_join_sql('o', 'sec', 'sc', 'ps', 'pys', 'ph');
$classScheduleHasGroupId = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_group_id');
$classScheduleHasType = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_type');
$facultyHasDesignationId = synk_table_has_column($conn, 'tbl_faculty', 'designation_id');
$designationTableExists = synk_table_exists($conn, 'tbl_designation');
$designationHasStatus = $designationTableExists && synk_table_has_column($conn, 'tbl_designation', 'status');
$facultyIdList = implode(',', array_map('intval', $facultyIds));

$summaries = [];
foreach ($facultyIds as $facultyId) {
    $summaries[$facultyId] = [
        'faculty_id' => $facultyId,
        'designation_name' => '',
        'designation_label' => '',
        'designation_units' => 0.0,
        'workload_load' => 0.0,
        'total_load' => 0.0,
        'total_preparations' => 0
    ];
}

if ($facultyHasDesignationId && $designationTableExists) {
    $designationSql = "
        SELECT
            f.faculty_id,
            d.designation_name,
            d.designation_units
        FROM tbl_faculty f
        LEFT JOIN tbl_designation d
            ON d.designation_id = f.designation_id
           " . ($designationHasStatus ? "AND d.status = 'active'" : '') . "
        WHERE f.faculty_id IN ({$facultyIdList})
    ";

    $designationRes = $conn->query($designationSql);
    if ($designationRes instanceof mysqli_result) {
        while ($designationRow = $designationRes->fetch_assoc()) {
            $facultyId = (int)($designationRow['faculty_id'] ?? 0);
            if (!isset($summaries[$facultyId])) {
                continue;
            }

            $designationName = trim((string)($designationRow['designation_name'] ?? ''));
            $designationUnits = (float)($designationRow['designation_units'] ?? 0);
            $summaries[$facultyId]['designation_name'] = $designationName;
            $summaries[$facultyId]['designation_label'] = workload_overview_title_case($designationName);
            $summaries[$facultyId]['designation_units'] = round($designationUnits, 2);
        }
        $designationRes->free();
    }
}

$selectParts = [
    'fw.faculty_id',
    'cs.schedule_id',
    'o.offering_id',
    $classScheduleHasGroupId ? 'cs.schedule_group_id AS group_id' : 'NULL AS group_id',
    $classScheduleHasType ? 'cs.schedule_type AS schedule_type' : "'LEC' AS schedule_type",
    'sm.sub_code',
    'ps.lec_units',
    'ps.lab_units',
    'ps.total_units'
];

$orderParts = [
    'fw.faculty_id',
    'sec.section_name',
    'sm.sub_code',
    $classScheduleHasGroupId ? 'COALESCE(cs.schedule_group_id, cs.schedule_id)' : 'cs.schedule_id'
];

if ($classScheduleHasType) {
    $orderParts[] = "FIELD(cs.schedule_type, 'LEC', 'LAB')";
}

$rawSql = "
    SELECT
        " . implode(",\n        ", $selectParts) . "
    FROM tbl_faculty_workload_sched fw
    INNER JOIN tbl_class_schedule cs
        ON cs.schedule_id = fw.schedule_id
    INNER JOIN tbl_prospectus_offering o
        ON o.offering_id = cs.offering_id
    {$liveOfferingJoins}
    INNER JOIN tbl_program p
        ON p.program_id = o.program_id
    INNER JOIN tbl_subject_masterlist sm
        ON sm.sub_id = ps.sub_id
    WHERE fw.ay_id = ?
      AND fw.semester = ?
      AND p.college_id = ?
      AND fw.faculty_id IN ({$facultyIdList})
    ORDER BY " . implode(",\n             ", $orderParts) . "
";

$rawStmt = $conn->prepare($rawSql);
$rawStmt->bind_param("iii", $ayId, $semester, $collegeId);
$rawStmt->execute();
$rawRes = $rawStmt->get_result();

$rowsByFacultyContext = [];
$preparationMap = [];
$offeringIds = [];

while ($row = $rawRes->fetch_assoc()) {
    $facultyId = (int)($row['faculty_id'] ?? 0);
    if (!isset($summaries[$facultyId])) {
        continue;
    }

    $offeringId = (int)($row['offering_id'] ?? 0);
    $contextKey = workload_overview_context_key(
        (int)($row['group_id'] ?? 0),
        (int)($row['schedule_id'] ?? 0),
        $offeringId
    );

    if (!isset($rowsByFacultyContext[$facultyId])) {
        $rowsByFacultyContext[$facultyId] = [];
    }

    if (!isset($rowsByFacultyContext[$facultyId][$contextKey])) {
        $rowsByFacultyContext[$facultyId][$contextKey] = [];
    }

    $rowsByFacultyContext[$facultyId][$contextKey][] = [
        'schedule_type' => (string)($row['schedule_type'] ?? 'LEC'),
        'lec_units' => (float)($row['lec_units'] ?? 0),
        'lab_units' => (float)($row['lab_units'] ?? 0),
        'total_units' => (float)($row['total_units'] ?? 0)
    ];

    if ($offeringId > 0) {
        $offeringIds[$offeringId] = true;
    }

    $preparationKey = trim((string)($row['sub_code'] ?? ''));
    if ($preparationKey !== '') {
        if (!isset($preparationMap[$facultyId])) {
            $preparationMap[$facultyId] = [];
        }
        $preparationMap[$facultyId][$preparationKey] = true;
    }
}

$rawStmt->close();

$contextTotals = [];
if (!empty($offeringIds)) {
    $offeringIdList = implode(',', array_map('intval', array_keys($offeringIds)));
    $contextSelectParts = [
        'cs.schedule_id',
        'cs.offering_id',
        $classScheduleHasGroupId ? 'cs.schedule_group_id AS group_id' : 'NULL AS group_id',
        $classScheduleHasType ? 'cs.schedule_type AS schedule_type' : "'LEC' AS schedule_type"
    ];

    $contextSql = "
        SELECT
            " . implode(",\n            ", $contextSelectParts) . "
        FROM tbl_class_schedule cs
        WHERE cs.offering_id IN ({$offeringIdList})
    ";

    $contextRes = $conn->query($contextSql);
    if ($contextRes instanceof mysqli_result) {
        while ($contextRow = $contextRes->fetch_assoc()) {
            $contextKey = workload_overview_context_key(
                (int)($contextRow['group_id'] ?? 0),
                (int)($contextRow['schedule_id'] ?? 0),
                (int)($contextRow['offering_id'] ?? 0)
            );

            if (!isset($contextTotals[$contextKey])) {
                $contextTotals[$contextKey] = [
                    'total_count' => 0,
                    'lec_count' => 0,
                    'lab_count' => 0
                ];
            }

            $contextTotals[$contextKey]['total_count']++;
            if (strtoupper(trim((string)($contextRow['schedule_type'] ?? 'LEC'))) === 'LAB') {
                $contextTotals[$contextKey]['lab_count']++;
                continue;
            }

            $contextTotals[$contextKey]['lec_count']++;
        }
        $contextRes->free();
    }
}

foreach ($summaries as $facultyId => &$summary) {
    $contextRows = $rowsByFacultyContext[$facultyId] ?? [];
    $totalLoad = 0.0;

    foreach ($contextRows as $contextKey => $rows) {
        $metrics = synk_schedule_sum_display_metrics($rows, $contextTotals[$contextKey] ?? []);
        $totalLoad += (float)($metrics['faculty_load'] ?? 0);
    }

    $summary['workload_load'] = round($totalLoad, 2);
    $summary['total_preparations'] = count($preparationMap[$facultyId] ?? []);
    $summary['total_load'] = round($summary['workload_load'] + (float)$summary['designation_units'], 2);
}
unset($summary);

echo json_encode([
    'status' => 'ok',
    'faculty' => array_values($summaries)
]);
exit;
?>
