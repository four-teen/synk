<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schema_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['rows' => [], 'meta' => []]);
    exit;
}

$faculty_id = (int)($_POST['faculty_id'] ?? 0);
$ay_id = (int)($_POST['ay_id'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);
$college_id = (int)($_SESSION['college_id'] ?? 0);

if ($faculty_id <= 0 || $ay_id <= 0 || $semester <= 0) {
    echo json_encode(['rows' => [], 'meta' => []]);
    exit;
}

function workload_title_case(string $value): string
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

function workload_context_key_from_values(int $groupId, int $scheduleId, int $offeringId): string
{
    if ($groupId > 0) {
        return 'group:' . $groupId;
    }

    if ($scheduleId > 0) {
        return 'schedule:' . $scheduleId;
    }

    return 'offering:' . $offeringId;
}

function workload_build_share_metrics(
    float $subjectUnits,
    float $lecUnits,
    float $labHoursTotal,
    array $contextTotals,
    array $ownedTotals
): array {
    $totalCount = (int)($contextTotals['total_count'] ?? 0);
    $totalLecCount = max(0, (int)($contextTotals['lec_count'] ?? 0));
    $totalLabCount = max(0, (int)($contextTotals['lab_count'] ?? 0));
    $ownedCount = (int)($ownedTotals['total_count'] ?? 0);
    $ownedLecCount = max(0, (int)($ownedTotals['lec_count'] ?? 0));
    $ownedLabCount = max(0, (int)($ownedTotals['lab_count'] ?? 0));

    $ownsAllRows = $totalCount > 0 && $ownedCount >= $totalCount;
    $lectureUnitsPerRow = $totalLecCount > 0 ? ($lecUnits / $totalLecCount) : 0.0;
    $labHoursPerRow = $totalLabCount > 0 ? ($labHoursTotal / $totalLabCount) : 0.0;

    if ($ownsAllRows) {
        $displayLec = $lecUnits;
        $displayLab = $labHoursTotal;
    } elseif ($ownedLecCount > 0 && $ownedLabCount === 0) {
        $displayLec = $lectureUnitsPerRow * $ownedLecCount;
        $displayLab = 0.0;
    } elseif ($ownedLecCount === 0 && $ownedLabCount > 0) {
        $displayLab = $labHoursPerRow * $ownedLabCount;
        $displayLec = max(0.0, $subjectUnits - $displayLab);
    } else {
        $displayLec = $lectureUnitsPerRow * $ownedLecCount;
        $displayLab = $labHoursPerRow * $ownedLabCount;
    }

    return [
        'units' => round($subjectUnits, 2),
        'lec' => round($displayLec, 2),
        'lab' => round($displayLab, 2),
        'faculty_load' => round($displayLec + ($displayLab * SYNK_LAB_LOAD_MULTIPLIER), 2)
    ];
}

$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
$classScheduleHasGroupId = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_group_id');
$classScheduleHasType = synk_table_has_column($conn, 'tbl_class_schedule', 'schedule_type');
$facultyHasDesignationId = synk_table_has_column($conn, 'tbl_faculty', 'designation_id');
$designationTableExists = synk_table_exists($conn, 'tbl_designation');
$designationHasStatus = $designationTableExists && synk_table_has_column($conn, 'tbl_designation', 'status');
$assignmentHasAyId = synk_table_has_column($conn, 'tbl_college_faculty', 'ay_id');
$assignmentHasSemester = synk_table_has_column($conn, 'tbl_college_faculty', 'semester');

$selectParts = [
    'fw.workload_id',
    'cs.schedule_id',
    'o.offering_id',
    $classScheduleHasGroupId ? 'cs.schedule_group_id AS group_id' : 'NULL AS group_id',
    $classScheduleHasType ? 'cs.schedule_type AS type' : "'LEC' AS type",
    'sm.sub_code',
    'sm.sub_description AS `desc`',
    'sec.section_name AS section',
    'sec.full_section AS full_section',
    'cs.days_json',
    'cs.time_start',
    'cs.time_end',
    'r.room_code AS room',
    'ps.lec_units',
    'ps.lab_units',
    'ps.total_units'
];

$orderParts = [
    'sec.section_name',
    'sm.sub_code',
    $classScheduleHasGroupId ? 'COALESCE(cs.schedule_group_id, cs.schedule_id)' : 'cs.schedule_id'
];

if ($classScheduleHasType) {
    $orderParts[] = "FIELD(cs.schedule_type, 'LEC', 'LAB')";
}

$orderParts[] = 'cs.time_start';

$sql = "
SELECT
    " . implode(",\n    ", $selectParts) . "
FROM tbl_faculty_workload_sched fw
JOIN tbl_class_schedule cs       ON cs.schedule_id = fw.schedule_id
JOIN tbl_prospectus_offering o   ON o.offering_id = cs.offering_id
{$liveOfferingJoins}
JOIN tbl_program p               ON p.program_id = o.program_id
JOIN tbl_subject_masterlist sm   ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_rooms r            ON r.room_id = cs.room_id
WHERE
    fw.faculty_id = ?
AND fw.ay_id      = ?
AND fw.semester   = ?
AND p.college_id  = ?
ORDER BY
    " . implode(",\n    ", $orderParts) . "
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $faculty_id, $ay_id, $semester, $college_id);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$rawRows = [];
$preparations = [];
$offeringIds = [];

while ($row = $res->fetch_assoc()) {
    $days_arr = json_decode((string)$row['days_json'], true);
    if (!is_array($days_arr)) {
        $days_arr = [];
    }

    $rawRows[] = [
        'workload_id' => (int)$row['workload_id'],
        'schedule_id' => (int)($row['schedule_id'] ?? 0),
        'offering_id' => (int)$row['offering_id'],
        'group_id' => (int)($row['group_id'] ?? 0),
        'sub_code' => (string)$row['sub_code'],
        'desc' => (string)$row['desc'],
        'section' => (string)$row['section'],
        'full_section' => (string)($row['full_section'] ?? ''),
        'type' => strtoupper(trim((string)($row['type'] ?? 'LEC'))),
        'days_arr' => $days_arr,
        'time_start' => (string)$row['time_start'],
        'time_end' => (string)$row['time_end'],
        'room' => (string)($row['room'] ?? ''),
        'lec_units' => (float)($row['lec_units'] ?? 0),
        'lab_units' => (float)($row['lab_units'] ?? 0),
        'total_units' => (float)($row['total_units'] ?? 0)
    ];

    $offeringIds[(int)$row['offering_id']] = true;

    $preparationKey = trim((string)$row['sub_code']);
    if ($preparationKey !== '') {
        $preparations[$preparationKey] = true;
    }
}

$stmt->close();

$contextTotals = [];
if (!empty($offeringIds)) {
    $offeringIdList = implode(',', array_map('intval', array_keys($offeringIds)));
    $contextSelectParts = [
        'cs.schedule_id',
        'cs.offering_id',
        $classScheduleHasGroupId ? 'cs.schedule_group_id AS group_id' : 'NULL AS group_id',
        $classScheduleHasType ? 'cs.schedule_type AS type' : "'LEC' AS type"
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
            $contextKey = workload_context_key_from_values(
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
            $contextType = strtoupper(trim((string)($contextRow['type'] ?? 'LEC')));
            if ($contextType === 'LAB') {
                $contextTotals[$contextKey]['lab_count']++;
            } else {
                $contextTotals[$contextKey]['lec_count']++;
            }
        }
        $contextRes->free();
    }
}

$ownedTotals = [];
foreach ($rawRows as $rawRow) {
    $contextKey = workload_context_key_from_values(
        (int)($rawRow['group_id'] ?? 0),
        (int)($rawRow['schedule_id'] ?? 0),
        (int)($rawRow['offering_id'] ?? 0)
    );

    if (!isset($ownedTotals[$contextKey])) {
        $ownedTotals[$contextKey] = [
            'total_count' => 0,
            'lec_count' => 0,
            'lab_count' => 0
        ];
    }

    $ownedTotals[$contextKey]['total_count']++;
    if (($rawRow['type'] ?? 'LEC') === 'LAB') {
        $ownedTotals[$contextKey]['lab_count']++;
    } else {
        $ownedTotals[$contextKey]['lec_count']++;
    }
}

foreach ($rawRows as $rawRow) {
    $lecUnits = (float)($rawRow['lec_units'] ?? 0);
    $labValue = (float)($rawRow['lab_units'] ?? 0);
    $totalUnits = (float)($rawRow['total_units'] ?? 0);
    $labIsCredit = ($labValue > 0) && (abs(($lecUnits + $labValue) - $totalUnits) < 0.0001);
    $labHours = $labIsCredit ? ($labValue * SYNK_LAB_CONTACT_HOURS_PER_UNIT) : $labValue;
    $subjectUnits = $totalUnits > 0
        ? $totalUnits
        : ($lecUnits + ($labIsCredit ? $labValue : 0));
    $contextKey = workload_context_key_from_values(
        (int)($rawRow['group_id'] ?? 0),
        (int)($rawRow['schedule_id'] ?? 0),
        (int)($rawRow['offering_id'] ?? 0)
    );
    $metrics = workload_build_share_metrics(
        $subjectUnits,
        $lecUnits,
        $labHours,
        $contextTotals[$contextKey] ?? [],
        $ownedTotals[$contextKey] ?? []
    );
    $fullSection = trim((string)($rawRow['full_section'] ?? ''));
    if ($fullSection === '') {
        $fullSection = trim((string)($rawRow['section'] ?? ''));
    }

    $rows[] = [
        'workload_id' => (int)$rawRow['workload_id'],
        'schedule_id' => (int)$rawRow['schedule_id'],
        'offering_id' => (int)$rawRow['offering_id'],
        'group_id' => (int)($rawRow['group_id'] ?? 0),
        'sub_code' => (string)$rawRow['sub_code'],
        'desc' => (string)$rawRow['desc'],
        'course' => $fullSection,
        'section' => (string)$rawRow['section'],
        'type' => (string)$rawRow['type'],
        'days' => implode(", ", $rawRow['days_arr'] ?? []),
        'time' => date("g:iA", strtotime((string)$rawRow['time_start'])) . "-" .
                  date("g:iA", strtotime((string)$rawRow['time_end'])),
        'room' => (string)($rawRow['room'] ?? ''),
        'lec_units' => round($lecUnits, 2),
        'lab_units' => round($labValue, 2),
        'subject_units' => round($subjectUnits, 2),
        'units' => $metrics['units'],
        'lec' => $metrics['lec'],
        'lab' => $metrics['lab'],
        'faculty_load' => $metrics['faculty_load'],
        'student_count' => 0
    ];
}

$designationName = '';
$designationUnits = 0.0;

if ($college_id > 0 && $facultyHasDesignationId && $designationTableExists) {
    $designationWhere = [
        'cf.college_id = ?',
        'cf.faculty_id = ?',
        "cf.status = 'active'"
    ];
    $designationTypes = 'ii';
    $designationParams = [$college_id, $faculty_id];

    if ($assignmentHasAyId) {
        $designationWhere[] = 'cf.ay_id = ?';
        $designationTypes .= 'i';
        $designationParams[] = $ay_id;
    }

    if ($assignmentHasSemester) {
        $designationWhere[] = 'cf.semester = ?';
        $designationTypes .= 'i';
        $designationParams[] = $semester;
    }

    $designationSql = "
        SELECT
            d.designation_name,
            d.designation_units
        FROM tbl_college_faculty cf
        INNER JOIN tbl_faculty f
            ON f.faculty_id = cf.faculty_id
        LEFT JOIN tbl_designation d
            ON d.designation_id = f.designation_id
           " . ($designationHasStatus ? "AND d.status = 'active'" : '') . "
        WHERE " . implode("\n          AND ", $designationWhere) . "
        ORDER BY cf.college_faculty_id DESC
        LIMIT 1
    ";

    $designationStmt = $conn->prepare($designationSql);
    synk_bind_dynamic_params($designationStmt, $designationTypes, $designationParams);
    $designationStmt->execute();
    $designationRes = $designationStmt->get_result();
    $designationRow = $designationRes->fetch_assoc();
    $designationStmt->close();

    if (is_array($designationRow)) {
        $designationName = trim((string)($designationRow['designation_name'] ?? ''));
        $designationUnits = (float)($designationRow['designation_units'] ?? 0);
    }
}

echo json_encode([
    'rows' => $rows,
    'meta' => [
        'designation_name' => $designationName,
        'designation_label' => workload_title_case($designationName),
        'designation_units' => round($designationUnits, 2),
        'total_preparations' => count($preparations)
    ]
]);
exit;
