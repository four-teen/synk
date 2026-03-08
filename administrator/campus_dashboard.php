<?php
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';
require_once '../backend/offering_scope_helper.php';
require_once '../backend/schema_helper.php';

function campus_dashboard_day_token(string $day): string
{
    $token = strtoupper(trim($day));
    return $token === 'TH' ? 'Th' : $token;
}

function campus_dashboard_day_label(string $day): string
{
    $labels = [
        'M' => 'Mon',
        'T' => 'Tue',
        'W' => 'Wed',
        'Th' => 'Thu',
        'F' => 'Fri',
        'S' => 'Sat',
    ];

    return $labels[$day] ?? $day;
}

function campus_dashboard_decode_days(string $daysJson): array
{
    $decoded = json_decode($daysJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $unique = [];
    foreach ($decoded as $day) {
        $token = campus_dashboard_day_token((string)$day);
        if ($token !== '') {
            $unique[$token] = true;
        }
    }

    return array_keys($unique);
}

function campus_dashboard_time_to_minutes(string $time): int
{
    $time = trim($time);
    if ($time === '') {
        return 0;
    }

    $parts = explode(':', $time);
    if (count($parts) < 2) {
        return 0;
    }

    $hours = (int)$parts[0];
    $minutes = (int)$parts[1];
    return ($hours * 60) + $minutes;
}

function campus_dashboard_minutes_to_label(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    $suffix = $hours >= 12 ? 'PM' : 'AM';
    $displayHour = $hours % 12;
    if ($displayHour === 0) {
        $displayHour = 12;
    }

    return sprintf('%d:%02d %s', $displayHour, $mins, $suffix);
}

function campus_dashboard_overlap_hours(int $startMinutes, int $endMinutes, int $windowStartMinutes, int $windowEndMinutes): float
{
    $effectiveStart = max($startMinutes, $windowStartMinutes);
    $effectiveEnd = min($endMinutes, $windowEndMinutes);

    if ($effectiveEnd <= $effectiveStart) {
        return 0.0;
    }

    return ($effectiveEnd - $effectiveStart) / 60;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

$currentTerm = synk_fetch_current_academic_term($conn);
$currentAyId = (int)($currentTerm['ay_id'] ?? 0);
$currentSem = (int)($currentTerm['semester'] ?? 0);
$academicTermText = trim((string)($currentTerm['term_text'] ?? 'Current academic term'));
$academicTermTextEscaped = htmlspecialchars($academicTermText, ENT_QUOTES, 'UTF-8');

// -----------------------------------------
// GET CAMPUS ID
// -----------------------------------------
$campusIdParam = $_GET['campus_id'] ?? null;

if ($campusIdParam === null) {
    // No campus id – go back to main dashboard
    header("Location: index.php");
    exit;
}

$isUniversitySummary = ($campusIdParam === 'all');

// -----------------------------------------
// OPTIONAL: GET COLLEGE ID (FILTER MODE)
// -----------------------------------------
$collegeIdParam = $_GET['college_id'] ?? null;
$selectedCollegeId = null;
$selectedCollegeName = null;

if (!$isUniversitySummary && $collegeIdParam !== null && $collegeIdParam !== '') {
    $selectedCollegeId = (int)$collegeIdParam;
}


// -----------------------------------------
// LOAD CAMPUS INFO
// -----------------------------------------
$campusName = "University Summary";
$campusCode = "SKSU";

if (!$isUniversitySummary) {
    $campusId = (int)$campusIdParam;

    $stmt = $conn->prepare("
        SELECT campus_id, campus_code, campus_name
        FROM tbl_campus
        WHERE campus_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("i", $campusId);
    $stmt->execute();
    $resCampus = $stmt->get_result();

    if ($resCampus->num_rows === 0) {
        // Invalid campus – redirect back
        header("Location: index.php");
        exit;
    }

    $campusRow  = $resCampus->fetch_assoc();
    $campusName = $campusRow['campus_name'];
    $campusCode = $campusRow['campus_code'];
    $stmt->close();
}

// -----------------------------------------
// VALIDATE SELECTED COLLEGE UNDER THIS CAMPUS
// -----------------------------------------
if (!$isUniversitySummary && $selectedCollegeId) {

    $stmt = $conn->prepare("
        SELECT college_id, college_name
        FROM tbl_college
        WHERE college_id = ?
          AND campus_id  = ?
          AND status     = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $selectedCollegeId, $campusId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        // invalid college for this campus → reset filter safely
        $selectedCollegeId = null;
        $selectedCollegeName = null;
    } else {
        $row = $res->fetch_assoc();
        $selectedCollegeName = $row['college_name'];
    }
    $stmt->close();
}



// -----------------------------------------
// LOAD COLLEGES UNDER SELECTED CAMPUS
// -----------------------------------------
$colleges = [];

if (!$isUniversitySummary) {
    $stmt = $conn->prepare("
        SELECT college_id, college_code, college_name
        FROM tbl_college
        WHERE campus_id = ?
          AND status = 'active'
        ORDER BY college_name ASC
    ");
    $stmt->bind_param("i", $campusId);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $colleges[] = $row;
    }
    $stmt->close();
}


// -----------------------------------------
// ROOMS & ROOM-BY-COLLEGE DATA
// -----------------------------------------
$rooms          = [];
$roomCount      = 0;
$collegeLabels  = [];
$collegeRoomCnt = [];

if ($isUniversitySummary) {
    // All campuses – aggregate per campus
    $roomQuery = "
        SELECT 
            c.campus_name AS label,
            COUNT(r.room_id) AS room_count
        FROM tbl_rooms r
        INNER JOIN tbl_college col ON col.college_id = r.college_id
        INNER JOIN tbl_campus c ON c.campus_id = col.campus_id
        GROUP BY c.campus_id
        ORDER BY c.campus_name ASC
    ";

    $rs = $conn->query($roomQuery);
    while ($row = $rs->fetch_assoc()) {
        $collegeLabels[]  = $row['label'];
        $collegeRoomCnt[] = (int)$row['room_count'];
        $roomCount       += (int)$row['room_count'];
    }

    // Load list of rooms (all campuses) – simple list
    $roomListQuery = "
        SELECT 
            r.room_id,
            r.room_name,
            r.room_code,
            col.college_name,
            c.campus_name
        FROM tbl_rooms r
        INNER JOIN tbl_college col ON col.college_id = r.college_id
        INNER JOIN tbl_campus c   ON c.campus_id = col.campus_id
        ORDER BY c.campus_name, col.college_name, r.room_name
    ";
} else {
    // Single campus – chart per college (or rooms for a selected college)
    if ($selectedCollegeId) {

        // Chart becomes: one line entry for the selected college only
        $stmt = $conn->prepare("
            SELECT 
                col.college_name AS label,
                COUNT(r.room_id) AS room_count
            FROM tbl_rooms r
            INNER JOIN tbl_college col ON col.college_id = r.college_id
            WHERE col.college_id = ?
            GROUP BY col.college_id
            ORDER BY col.college_name ASC
        ");
        $stmt->bind_param("i", $selectedCollegeId);

    } else {

        $stmt = $conn->prepare("
            SELECT 
                col.college_name AS label,
                COUNT(r.room_id) AS room_count
            FROM tbl_rooms r
            INNER JOIN tbl_college col ON col.college_id = r.college_id
            WHERE col.campus_id = ?
            GROUP BY col.college_id
            ORDER BY col.college_name ASC
        ");
        $stmt->bind_param("i", $campusId);
    }

    $stmt->execute();
    $rs = $stmt->get_result();

    $collegeLabels  = [];
    $collegeRoomCnt = [];
    $roomCount      = 0;

    while ($row = $rs->fetch_assoc()) {
        $collegeLabels[]  = $row['label'];
        $collegeRoomCnt[] = (int)$row['room_count'];
        $roomCount       += (int)$row['room_count'];
    }
    $stmt->close();

    if ($selectedCollegeId) {
        $roomListQuery = "
            SELECT 
                r.room_id,
                r.room_name,
                r.room_code,
                col.college_name
            FROM tbl_rooms r
            INNER JOIN tbl_college col ON col.college_id = r.college_id
            WHERE col.college_id = {$selectedCollegeId}
            ORDER BY col.college_name, r.room_name
        ";
    } else {
        $roomListQuery = "
            SELECT 
                r.room_id,
                r.room_name,
                r.room_code,
                col.college_name
            FROM tbl_rooms r
            INNER JOIN tbl_college col ON col.college_id = r.college_id
            WHERE col.campus_id = {$campusId}
            ORDER BY col.college_name, r.room_name
        ";
    }

}

// Execute the room list query (for table)
$rsRooms = $conn->query($roomListQuery);
while ($row = $rsRooms->fetch_assoc()) {
    $rooms[] = $row;
}

// -----------------------------------------
// CURRENT TERM ANALYTICS
// -----------------------------------------

$analyticsReady = ($currentAyId > 0 && $currentSem > 0);
$heatmapSlotLabels = [];
$heatmapSeries = [];
$heatmapPeakLabel = 'No occupied room slots';
$scheduleSummary = [
    'scheduled_classes' => 0,
    'assigned_classes' => 0,
    'unassigned_classes' => 0,
];
$utilizationRows = [];
$utilizationChartLabels = [];
$utilizationChartUsed = [];
$utilizationChartAvailable = [];
$utilizationMetricLabel = $isUniversitySummary ? 'Campus' : ($selectedCollegeId ? 'Room' : 'College');
$utilizationHeading = $isUniversitySummary
    ? 'Room Utilization by Campus'
    : ($selectedCollegeId ? 'Accessible Room Utilization' : 'Room Utilization by College');
$heatmapSubtext = $isUniversitySummary
    ? 'Each cell shows the percentage of rooms occupied across all campuses in the current-term weekday window.'
    : ($selectedCollegeId
        ? 'Each cell shows the percentage of this college\'s accessible rooms occupied in the current-term weekday window.'
        : 'Each cell shows the percentage of campus rooms occupied in the current-term weekday window.');
$utilizationSubtext = $isUniversitySummary
    ? 'Measures how busy each campus room pool is during the current term.'
    : ($selectedCollegeId
        ? 'Measures occupancy across rooms this college can use for the current term.'
        : 'Measures how busy each college room pool is during the current term.');
$resourceCapacityNote = 'Room capacity is measured as 40 room-hours per week per room (Monday to Friday, 7:00 AM to 3:00 PM).';
$assignmentCoveragePercent = 0.0;
$highestUtilizationLabel = 'No room data';
$highestUtilizationPercent = 0.0;
$heatmapPeakOccupiedRooms = 0;
$heatmapPeakPercent = 0.0;
$heatmapTotalRooms = 0;

if ($analyticsReady) {
    $daysOrder = ['M', 'T', 'W', 'Th', 'F'];
    $dayWindowStartMinutes = 7 * 60;
    $dayWindowEndMinutes = $dayWindowStartMinutes + (8 * 60);
    $slotDefinitions = [];
    for ($slotMinutes = $dayWindowStartMinutes; $slotMinutes < $dayWindowEndMinutes; $slotMinutes += 60) {
        $slotLabel = campus_dashboard_minutes_to_label($slotMinutes);
        $slotDefinitions[] = [
            'label' => $slotLabel,
            'start' => $slotMinutes,
            'end' => $slotMinutes + 60,
        ];
        $heatmapSlotLabels[] = $slotLabel;
    }

    $heatmapMatrix = [];
    foreach ($daysOrder as $dayKey) {
        $heatmapMatrix[$dayKey] = [];
        foreach ($heatmapSlotLabels as $slotLabel) {
            $heatmapMatrix[$dayKey][$slotLabel] = [];
        }
    }

    $scheduleScopeSql = '';
    $scheduleScopeTypes = '';
    $scheduleScopeParams = [];

    if ($selectedCollegeId) {
        $scheduleScopeSql = ' AND col.college_id = ?';
        $scheduleScopeTypes = 'i';
        $scheduleScopeParams[] = $selectedCollegeId;
    } elseif (!$isUniversitySummary) {
        $scheduleScopeSql = ' AND camp.campus_id = ?';
        $scheduleScopeTypes = 'i';
        $scheduleScopeParams[] = $campusId;
    }

    $liveOfferingJoins = synk_live_offering_join_sql('po', 'sec', 'ps', 'pys', 'ph');
    $scheduleSql = "
        SELECT
            cs.schedule_id,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            COALESCE(fa.has_faculty, 0) AS has_faculty
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = sec.program_id
        INNER JOIN tbl_college col
            ON col.college_id = p.college_id
        INNER JOIN tbl_campus camp
            ON camp.campus_id = col.campus_id
        LEFT JOIN (
            SELECT DISTINCT schedule_id, 1 AS has_faculty
            FROM tbl_faculty_workload_sched
            WHERE ay_id = ?
              AND semester = ?
        ) fa
            ON fa.schedule_id = cs.schedule_id
        WHERE po.ay_id = ?
          AND po.semester = ?
          {$scheduleScopeSql}
        ORDER BY cs.time_start ASC, cs.schedule_id ASC
    ";

    $stmt = $conn->prepare($scheduleSql);
    $scheduleParams = [$currentAyId, $currentSem, $currentAyId, $currentSem];
    foreach ($scheduleScopeParams as $scopeParam) {
        $scheduleParams[] = $scopeParam;
    }
    $scheduleTypes = 'iiii' . $scheduleScopeTypes;

    if ($stmt && synk_bind_dynamic_params($stmt, $scheduleTypes, $scheduleParams)) {
        $stmt->execute();
        $scheduleResult = $stmt->get_result();

        while ($scheduleRow = $scheduleResult->fetch_assoc()) {
            $scheduleSummary['scheduled_classes']++;
            if ((int)$scheduleRow['has_faculty'] === 1) {
                $scheduleSummary['assigned_classes']++;
            } else {
                $scheduleSummary['unassigned_classes']++;
            }
        }

        $stmt->close();
    } elseif ($stmt) {
        $stmt->close();
    }
    unset($stmt);

    $roomCapacityPerWeek = 40.0;
    $roomPool = [];
    $roomPoolResult = null;

    if ($selectedCollegeId) {
        if (synk_table_exists($conn, 'tbl_room_college_access')) {
            $roomPoolSql = "
                SELECT DISTINCT
                    r.room_id,
                    r.room_code,
                    r.room_name,
                    acc.access_type,
                    owner.college_code AS owner_code
                FROM tbl_room_college_access acc
                INNER JOIN tbl_rooms r
                    ON r.room_id = acc.room_id
                INNER JOIN tbl_college owner
                    ON owner.college_id = r.college_id
                WHERE acc.college_id = ?
                  AND acc.ay_id = ?
                  AND acc.semester = ?
                  AND r.status = 'active'
                ORDER BY r.room_name ASC, r.room_code ASC
            ";

            $stmt = $conn->prepare($roomPoolSql);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('iii', $selectedCollegeId, $currentAyId, $currentSem);
            }
        } else {
            $roomPoolSql = "
                SELECT
                    r.room_id,
                    r.room_code,
                    r.room_name
                FROM tbl_rooms r
                WHERE r.college_id = ?
                  AND r.status = 'active'
                ORDER BY r.room_name ASC, r.room_code ASC
            ";

            $stmt = $conn->prepare($roomPoolSql);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $selectedCollegeId);
            }
        }

        if ($stmt instanceof mysqli_stmt) {
            $stmt->execute();
            $roomPoolResult = $stmt->get_result();

            while ($roomRow = $roomPoolResult->fetch_assoc()) {
                $roomId = (int)$roomRow['room_id'];
                $roomCode = trim((string)($roomRow['room_code'] ?? ''));
                $roomName = trim((string)($roomRow['room_name'] ?? ''));
                $roomLabel = $roomName !== '' ? $roomName : $roomCode;
                if ($roomCode !== '' && $roomName !== '' && strcasecmp($roomCode, $roomName) !== 0) {
                    $roomLabel = $roomCode . ' - ' . $roomName;
                }

                if (strtolower(trim((string)($roomRow['access_type'] ?? 'owner'))) === 'shared') {
                    $ownerCode = trim((string)($roomRow['owner_code'] ?? ''));
                    if ($ownerCode !== '') {
                        $roomLabel .= ' (Shared from ' . $ownerCode . ')';
                    }
                }

                $roomPool[$roomId] = [
                    'category_key' => (string)$roomId,
                    'category_label' => $roomLabel,
                    'used_hours' => 0.0,
                ];
            }

            $stmt->close();
        }
        unset($stmt);
    } else {
        if ($isUniversitySummary) {
            $roomPoolSql = "
                SELECT
                    r.room_id,
                    camp.campus_id AS category_key,
                    camp.campus_name AS category_label
                FROM tbl_rooms r
                INNER JOIN tbl_college col
                    ON col.college_id = r.college_id
                INNER JOIN tbl_campus camp
                    ON camp.campus_id = col.campus_id
                WHERE r.status = 'active'
                ORDER BY camp.campus_name ASC, r.room_name ASC, r.room_code ASC
            ";

            $roomPoolResult = $conn->query($roomPoolSql);
        } else {
            $stmt = $conn->prepare("
                SELECT
                    r.room_id,
                    col.college_id AS category_key,
                    col.college_name AS category_label
                FROM tbl_rooms r
                INNER JOIN tbl_college col
                    ON col.college_id = r.college_id
                WHERE col.campus_id = ?
                  AND r.status = 'active'
                ORDER BY col.college_name ASC, r.room_name ASC, r.room_code ASC
            ");
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $campusId);
                $stmt->execute();
                $roomPoolResult = $stmt->get_result();
            }
        }

        if ($roomPoolResult instanceof mysqli_result) {
            while ($roomRow = $roomPoolResult->fetch_assoc()) {
                $roomId = (int)$roomRow['room_id'];
                $categoryKey = (string)$roomRow['category_key'];
                $roomPool[$roomId] = [
                    'category_key' => $categoryKey,
                    'category_label' => (string)$roomRow['category_label'],
                    'used_hours' => 0.0,
                ];
            }
        }

        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
            unset($stmt);
        }
    }

    if (!empty($roomPool)) {
        $roomIds = array_keys($roomPool);
        $roomIdList = implode(',', array_map('intval', $roomIds));
        $roomUsageSql = "
            SELECT
                cs.room_id,
                cs.time_start,
                cs.time_end,
                cs.days_json
            FROM tbl_class_schedule cs
            INNER JOIN tbl_prospectus_offering po
                ON po.offering_id = cs.offering_id
            WHERE po.ay_id = ?
              AND po.semester = ?
              AND cs.room_id IN ({$roomIdList})
        ";

        $stmt = $conn->prepare($roomUsageSql);
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param('ii', $currentAyId, $currentSem);
            $stmt->execute();
            $roomUsageResult = $stmt->get_result();
            $heatmapTotalRooms = count($roomPool);

            while ($usageRow = $roomUsageResult->fetch_assoc()) {
                $roomId = (int)$usageRow['room_id'];
                if (!isset($roomPool[$roomId])) {
                    continue;
                }

                $meetingDays = array_values(array_intersect(
                    campus_dashboard_decode_days((string)($usageRow['days_json'] ?? '')),
                    $daysOrder
                ));
                $startMinutes = campus_dashboard_time_to_minutes((string)($usageRow['time_start'] ?? ''));
                $endMinutes = campus_dashboard_time_to_minutes((string)($usageRow['time_end'] ?? ''));
                $meetingHours = campus_dashboard_overlap_hours(
                    $startMinutes,
                    $endMinutes,
                    $dayWindowStartMinutes,
                    $dayWindowEndMinutes
                );

                if (count($meetingDays) === 0 || $meetingHours <= 0) {
                    continue;
                }

                $roomPool[$roomId]['used_hours'] += ($meetingHours * count($meetingDays));

                foreach ($meetingDays as $dayKey) {
                    foreach ($slotDefinitions as $slotDefinition) {
                        if ($slotDefinition['start'] < $endMinutes && $slotDefinition['end'] > $startMinutes) {
                            $heatmapMatrix[$dayKey][$slotDefinition['label']][$roomId] = true;
                        }
                    }
                }
            }

            $stmt->close();
        }
        unset($stmt);

        foreach ($daysOrder as $dayKey) {
            $seriesData = [];
            foreach ($heatmapSlotLabels as $slotLabel) {
                $occupiedRooms = count($heatmapMatrix[$dayKey][$slotLabel]);
                $occupancyPercent = $heatmapTotalRooms > 0
                    ? round(($occupiedRooms / $heatmapTotalRooms) * 100, 1)
                    : 0.0;

                $seriesData[] = [
                    'x' => $slotLabel,
                    'y' => $occupancyPercent,
                ];

                if ($occupancyPercent > $heatmapPeakPercent) {
                    $heatmapPeakPercent = $occupancyPercent;
                    $heatmapPeakOccupiedRooms = $occupiedRooms;
                    $heatmapPeakLabel = campus_dashboard_day_label($dayKey) . ' - ' . $slotLabel;
                }
            }

            $heatmapSeries[] = [
                'name' => campus_dashboard_day_label($dayKey),
                'data' => $seriesData,
            ];
        }

        $utilizationCategoryIndex = [];
        foreach ($roomPool as $roomMeta) {
            $categoryKey = $roomMeta['category_key'];
            if (!isset($utilizationCategoryIndex[$categoryKey])) {
                $utilizationCategoryIndex[$categoryKey] = [
                    'label' => $roomMeta['category_label'],
                    'room_count' => 0,
                    'used_hours' => 0.0,
                ];
            }

            $utilizationCategoryIndex[$categoryKey]['room_count']++;
            $utilizationCategoryIndex[$categoryKey]['used_hours'] += $roomMeta['used_hours'];
        }

        foreach ($utilizationCategoryIndex as $categoryMeta) {
            $capacityHours = $categoryMeta['room_count'] * $roomCapacityPerWeek;
            $usedHours = round($categoryMeta['used_hours'], 1);
            $availableHours = round(max($capacityHours - $categoryMeta['used_hours'], 0), 1);
            $utilizationPercent = $capacityHours > 0
                ? round(($categoryMeta['used_hours'] / $capacityHours) * 100, 1)
                : 0.0;

            $utilizationRows[] = [
                'label' => $categoryMeta['label'],
                'room_count' => (int)$categoryMeta['room_count'],
                'used_hours' => $usedHours,
                'available_hours' => $availableHours,
                'capacity_hours' => round($capacityHours, 1),
                'utilization_percent' => $utilizationPercent,
            ];
        }

        usort($utilizationRows, static function (array $a, array $b): int {
            if ($a['utilization_percent'] === $b['utilization_percent']) {
                return strcasecmp($a['label'], $b['label']);
            }

            return $b['utilization_percent'] <=> $a['utilization_percent'];
        });

        foreach ($utilizationRows as $utilizationRow) {
            $utilizationChartLabels[] = $utilizationRow['label'];
            $utilizationChartUsed[] = $utilizationRow['used_hours'];
            $utilizationChartAvailable[] = $utilizationRow['available_hours'];
        }

        if (!empty($utilizationRows)) {
            $highestUtilizationLabel = (string)$utilizationRows[0]['label'];
            $highestUtilizationPercent = (float)$utilizationRows[0]['utilization_percent'];
        }
    }

    if (empty($heatmapSeries)) {
        foreach ($daysOrder as $dayKey) {
            $seriesData = [];
            foreach ($heatmapSlotLabels as $slotLabel) {
                $seriesData[] = [
                    'x' => $slotLabel,
                    'y' => 0,
                ];
            }

            $heatmapSeries[] = [
                'name' => campus_dashboard_day_label($dayKey),
                'data' => $seriesData,
            ];
        }
    }

    if ($scheduleSummary['scheduled_classes'] > 0) {
        $assignmentCoveragePercent = round(
            ($scheduleSummary['assigned_classes'] / $scheduleSummary['scheduled_classes']) * 100,
            1
        );
    }
}


// -----------------------------------------
// CAMPUS METRICS (FACULTY, PROGRAMS, SECTIONS, ROOMS)
// -----------------------------------------

$facultyCount = 0;
$programCount = 0;
$sectionCount = 0;

if ($isUniversitySummary) {

    // ALL FACULTY
    $facultyCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_faculty
        WHERE status='active'
    ")->fetch_assoc()['cnt'];

    // ALL PROGRAMS
    $programCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_program
        WHERE status='active'
    ")->fetch_assoc()['cnt'];

    // ALL SECTIONS
    $sectionCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_sections
        WHERE status='active'
    ")->fetch_assoc()['cnt'];

} else {

    // ----------------------------
    // FACULTY count (campus or college)
    // ----------------------------
    if ($selectedCollegeId) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM tbl_faculty f
            INNER JOIN tbl_college_faculty cf ON cf.faculty_id = f.faculty_id
            WHERE cf.college_id = ?
              AND f.status = 'active'
              AND cf.status = 'active'
        ");
        $stmt->bind_param("i", $selectedCollegeId);
        $stmt->execute();
        $facultyCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    } else {
        $facultyCount = (int)$conn->query("
            SELECT COUNT(*) AS cnt
            FROM tbl_faculty f
            INNER JOIN tbl_college_faculty cf ON cf.faculty_id = f.faculty_id
            INNER JOIN tbl_college c ON c.college_id = cf.college_id
            WHERE c.campus_id = {$campusId}
              AND f.status = 'active'
              AND cf.status = 'active'
        ")->fetch_assoc()['cnt'];
    }

    // ----------------------------
    // PROGRAM count (campus or college)
    // ----------------------------
    if ($selectedCollegeId) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM tbl_program p
            WHERE p.college_id = ?
              AND p.status = 'active'
        ");
        $stmt->bind_param("i", $selectedCollegeId);
        $stmt->execute();
        $programCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    } else {
        $programCount = (int)$conn->query("
            SELECT COUNT(*) AS cnt
            FROM tbl_program p
            INNER JOIN tbl_college c ON c.college_id = p.college_id
            WHERE c.campus_id = {$campusId}
              AND p.status = 'active'
        ")->fetch_assoc()['cnt'];
    }

    // ----------------------------
    // SECTION count (campus or college)
    // ----------------------------
    if ($selectedCollegeId) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM tbl_sections s
            INNER JOIN tbl_program p ON p.program_id = s.program_id
            WHERE p.college_id = ?
              AND s.status = 'active'
        ");
        $stmt->bind_param("i", $selectedCollegeId);
        $stmt->execute();
        $sectionCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    } else {
        $sectionCount = (int)$conn->query("
            SELECT COUNT(*) AS cnt
            FROM tbl_sections s
            INNER JOIN tbl_program p ON p.program_id = s.program_id
            INNER JOIN tbl_college c ON c.college_id = p.college_id
            WHERE c.campus_id = {$campusId}
              AND s.status = 'active'
        ")->fetch_assoc()['cnt'];
    }
}




?>
<!DOCTYPE html>
<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
<head>
  <meta charset="utf-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
  />

  <title>Campus Dashboard | Synk</title>

  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
    .kpi-box {
      padding: 10px;
    }
    .kpi-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 22px;
      color: #fff;
      margin: auto;
    }
    .kpi-value {
      font-size: 1.7rem;
      font-weight: 700;
    }

    .card-section-title {
      font-size: 0.9rem;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #888;
    }

    .table-sm td,
    .table-sm th {
      padding-top: 0.4rem !important;
      padding-bottom: 0.4rem !important;
    }

    .resource-stat-card {
      border: 1px solid #e7e7ef;
      border-radius: 14px;
      padding: 1rem;
      background: linear-gradient(180deg, #fff 0%, #fbfbff 100%);
      height: 100%;
    }

    .resource-stat {
      padding: 0.75rem 0;
      border-bottom: 1px solid #eef0f6;
    }

    .resource-stat:last-child {
      border-bottom: 0;
      padding-bottom: 0;
    }

    .resource-stat-label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #8592a3;
      margin-bottom: 0.25rem;
    }

    .resource-stat-value {
      font-size: 1.45rem;
      line-height: 1.2;
      font-weight: 700;
      color: #566a7f;
    }

    .resource-stat-note {
      font-size: 0.85rem;
      color: #8592a3;
    }

    .resource-note {
      font-size: 0.82rem;
      color: #8592a3;
    }

    .matrix-table th,
    .matrix-table td {
      min-width: 88px;
      vertical-align: middle;
    }

    .matrix-room {
      background: #f8f9fa;
      white-space: normal;
      word-break: break-word;
      line-height: 1.15;
    }

    .matrix-cell {
      border-radius: 6px;
      padding: 4px;
      text-align: center;
      font-size: 0.76rem;
      min-height: 34px;
    }

    .matrix-vacant {
      background: #eef2f6;
      color: #8b97a8;
    }

    .matrix-occupied {
      background: #f6f9fc;
      color: #17324d;
      border: 1px solid #d8e4f1;
      font-weight: 600;
    }

    .matrix-entry {
      border-radius: 5px;
      color: #fff;
      padding: 3px 5px;
      margin-bottom: 3px;
      line-height: 1.08;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
    }

    .matrix-entry:last-child {
      margin-bottom: 0;
    }

    .matrix-conflict .matrix-entry {
      color: #fff;
    }

    .matrix-entry strong {
      display: block;
      font-size: 0.68rem;
      letter-spacing: 0.01em;
    }

    .matrix-entry small {
      display: block;
      color: rgba(255, 255, 255, 0.92);
      font-size: 0.58rem;
    }

    .matrix-conflict {
      background: #fff5f5;
      border: 1px solid #ffc9c9;
    }

    .matrix-day {
      background: #f8f9fa;
      font-weight: 700;
      white-space: nowrap;
      min-width: 52px;
      width: 52px;
      max-width: 52px;
      letter-spacing: 0.02em;
    }

    .sub-0 { background: #2563eb; }
    .sub-1 { background: #059669; }
    .sub-2 { background: #d97706; }
    .sub-3 { background: #7c3aed; }
    .sub-4 { background: #0f766e; }
    .sub-5 { background: #dc2626; }
    .sub-6 { background: #db2777; }
    .sub-7 { background: #4f46e5; }
    .sub-8 { background: #0891b2; }
    .sub-9 { background: #65a30d; }
    .sub-10 { background: #b45309; }
    .sub-11 { background: #475569; }

    #matrixModal {
      --matrix-room-col-width: 118px;
      --matrix-day-col-width: 50px;
      --matrix-slot-col-width: 58px;
    }

    #matrixModal .matrix-table {
      font-size: 0.68rem;
    }

    #matrixModal .matrix-room-col {
      width: var(--matrix-room-col-width);
    }

    #matrixModal .matrix-day-col {
      width: var(--matrix-day-col-width);
    }

    #matrixModal .matrix-slot-col {
      width: var(--matrix-slot-col-width);
    }

    #matrixModal .matrix-table th {
      font-size: 0.62rem;
      line-height: 1.05;
      white-space: nowrap;
    }

    #matrixModal .matrix-table th,
    #matrixModal .matrix-table td {
      padding: 4px !important;
      min-width: var(--matrix-slot-col-width);
    }

    #matrixModal .matrix-room {
      min-width: var(--matrix-room-col-width);
      width: var(--matrix-room-col-width);
      max-width: var(--matrix-room-col-width);
      font-size: 0.69rem;
      padding: 7px 6px !important;
    }

    #matrixModal .matrix-day {
      min-width: var(--matrix-day-col-width);
      width: var(--matrix-day-col-width);
      max-width: var(--matrix-day-col-width);
      font-size: 0.64rem;
      padding: 4px 3px !important;
    }

    #matrixModal .matrix-cell {
      padding: 3px;
      min-height: 28px;
      font-size: 0.64rem;
      line-height: 1.02;
    }

    #matrixModal .matrix-cell strong {
      font-size: 0.64rem;
    }

    #matrixModal .matrix-cell small {
      font-size: 0.56rem;
      opacity: 0.94;
    }

    #matrixModal .matrix-time-slot {
      display: grid;
      gap: 1px;
      justify-items: center;
      line-height: 1.02;
      font-variant-numeric: tabular-nums;
    }

    #matrixModal .matrix-time-slot span {
      display: block;
    }

    #matrixModal .matrix-meta-note {
      line-height: 1.35;
    }

    #matrixModal .matrix-table thead th {
      position: sticky;
      top: 0;
      z-index: 6;
      background: #ffffff;
    }

    #matrixModal .matrix-room {
      position: sticky;
      left: 0;
      z-index: 5;
      box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
    }

    #matrixModal .matrix-day {
      position: sticky;
      left: var(--matrix-room-col-width);
      z-index: 4;
      box-shadow: 2px 0 4px rgba(0, 0, 0, 0.04);
    }

    #matrixModal .matrix-table thead .matrix-room {
      z-index: 9;
    }

    #matrixModal .matrix-table thead .matrix-day {
      z-index: 8;
    }

    #matrixModal .modal-body {
      padding: 0.5rem;
      overflow: hidden;
    }

    #matrixModal .matrix-shell {
      height: calc(95vh - 92px);
      display: flex;
      flex-direction: column;
      min-height: 0;
    }

    #matrixModal .matrix-scroll-wrap {
      flex: 1 1 auto;
      min-height: 0;
      overflow: auto;
      border: 1px solid #d6e0f0;
      border-radius: 14px;
      background: #fff;
    }

    #matrixModal .matrix-table {
      width: max-content;
      min-width: 100%;
    }

    #matrixModal .modal-dialog {
      max-width: min(99vw, 2200px) !important;
      width: min(99vw, 2200px);
      height: 95vh;
      margin: 0.75rem auto;
    }

    #matrixModal .modal-content {
      height: 100%;
    }

    @media (min-width: 1200px) {
      #matrixModal {
        --matrix-room-col-width: 104px;
        --matrix-day-col-width: 46px;
        --matrix-slot-col-width: 50px;
      }

      #matrixModal .matrix-table {
        width: 100%;
        table-layout: fixed;
      }
    }

    @media (min-width: 1600px) {
      #matrixModal {
        --matrix-room-col-width: 100px;
        --matrix-day-col-width: 44px;
        --matrix-slot-col-width: 48px;
      }
    }

    @media (max-width: 992px) {
      #matrixModal {
        --matrix-room-col-width: 126px;
        --matrix-day-col-width: 52px;
        --matrix-slot-col-width: 66px;
      }

      #matrixModal .modal-dialog {
        max-width: 100vw !important;
        width: 100vw;
        height: 100vh;
        margin: 0;
      }

      #matrixModal .matrix-shell {
        height: calc(100vh - 92px);
      }

      #matrixModal .matrix-scroll-wrap {
        border-radius: 0;
        border-left: 0;
        border-right: 0;
        border-bottom: 0;
      }
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- Sidebar -->
      <?php include 'sidebar.php'; ?>
      <!-- /Sidebar -->

      <div class="layout-page">

        <!-- Navbar -->
        <?php include 'navbar.php'; ?>
        <!-- /Navbar -->

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">

            <!-- BREADCRUMB / TITLE -->
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <small class="text-muted d-block">Campus Dashboard</small>
                <h4 class="fw-bold mb-0">
                  <?= htmlspecialchars($campusName); ?>
                  <?php if (!$isUniversitySummary): ?>
                    <span class="text-muted"> (<?= htmlspecialchars($campusCode); ?>)</span>
                  <?php endif; ?>
                </h4>
              </div>
              <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                  <i class="bx bx-arrow-back"></i> Back to Institutional View
                </a>
              </div>
            </div>

            <div class="row">
              <!-- LEFT COLUMN -->
              <div class="col-lg-8 mb-4 order-0">

                <!-- CAMPUS HERO CARD -->
                <div class="card shadow-sm mb-4">
                  <div class="row align-items-center g-0">
                    <div class="col-sm-8 px-4 py-4">
                      <p class="card-section-title mb-1">
                        <?= $isUniversitySummary ? 'System-wide Overview' : 'Campus Overview'; ?>
                      </p>
                      <h4 class="text-primary fw-bold mb-2">
                        <?= $isUniversitySummary ? 'SKSU University Summary' : $campusName . ' Campus'; ?>
                      </h4>
                      <p class="mb-3 text-muted">
                        Welcome back, <strong><?= $_SESSION['username'] ?? 'Administrator'; ?></strong>! 
                        This page shows an executive snapshot of
                        <?= $isUniversitySummary ? 'all SKSU campuses combined' : 'academic operations and resources for this campus'; ?>.
                      </p>
                      <div class="d-flex flex-wrap gap-2">
                        <a href="#" class="btn btn-sm btn-primary">
                          <i class="bx bx-file"></i>
                          <?= $isUniversitySummary ? 'Download University Report' : 'Download Campus Report'; ?>
                        </a>
                        <a href="../scheduler/" class="btn btn-sm btn-outline-primary">
                          <i class="bx bx-calendar"></i> View Room Utilization
                        </a>
                      </div>
                    </div>
                    <div class="col-sm-4 text-center">
                      <img
                        src="../assets/img/illustrations/man-with-laptop-light.png"
                        height="130"
                        alt="Overview"
                        class="img-fluid p-2"
                        data-app-dark-img="illustrations/man-with-laptop-dark.png"
                        data-app-light-img="illustrations/man-with-laptop-light.png"
                      />
                    </div>
                  </div>
                </div>

<!-- COLLEGES UNDER THIS CAMPUS -->
<div class="card shadow-sm mb-4">
  <div class="card-header">
    <p class="card-section-title mb-1">Colleges</p>
    <h5 class="m-0">
      Academic Units Under
      <?= $isUniversitySummary ? 'All Campuses' : htmlspecialchars($campusName) . ' Campus'; ?>
    </h5>
  </div>

  <div class="card-body">
    <!--
      GUI PURPOSE:
      - Introduce colleges as the PRIMARY decision layer
      - This is GUI-only (no backend filtering yet)
      - Clicking a college will later refine KPIs, rooms, programs, and faculty
    -->

    <!-- RESET OPTION -->
    <div class="mb-3">
<a
  href="campus_dashboard.php?campus_id=<?= urlencode($campusIdParam); ?>"
  class="badge <?= !$selectedCollegeId ? 'bg-secondary text-white' : 'bg-label-secondary'; ?>"
  style="text-decoration:none;"
>
  All Colleges
</a>

    </div>

    <!-- COLLEGE BADGES (TEMPORARY GUI DATA) -->
<div class="d-flex flex-wrap gap-2">

  <?php if ($isUniversitySummary): ?>
    <span class="badge bg-secondary">
      Colleges are shown per campus
    </span>

  <?php elseif (count($colleges) === 0): ?>
    <span class="badge bg-secondary">
      No colleges recorded for this campus
    </span>

  <?php else: ?>
    <?php foreach ($colleges as $col): ?>
      <?php
      $isActive = ($selectedCollegeId && (int)$selectedCollegeId === (int)$col['college_id']);
      ?>

      <a
        href="campus_dashboard.php?campus_id=<?= urlencode($campusIdParam); ?>&college_id=<?= (int)$col['college_id']; ?>"
        class="badge <?= $isActive ? 'bg-primary text-white' : 'bg-label-primary'; ?>"
        style="cursor:pointer; text-decoration:none;"
        title="College Code: <?= htmlspecialchars($col['college_code']); ?>"
      >
        <i class="bx bx-buildings me-1"></i>
        <?= htmlspecialchars($col['college_name']); ?>
      </a>

    <?php endforeach; ?>
  <?php endif; ?>

</div>


    <small class="text-muted d-block mt-3">
      Select a college to focus dashboard metrics, rooms, programs, and faculty assignments.
    </small>
  </div>
</div>


                <!-- KPI ROW -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center pb-2">
                    <div>
                      <p class="card-section-title mb-1">Key Indicators</p>
                      <h5 class="m-0">Academic Operations Overview</h5>
                    </div>
                    <span class="badge bg-label-primary">KPI Summary</span>
                  </div>
                  <div class="card-body px-4 py-3">
                    <div class="row gy-4">

                      <!-- Total Faculty (placeholder for now) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-primary">
                            <i class="bx bx-user-voice"></i>
                          </div>
                          <h3 class="kpi-value text-info mt-2"><?= $facultyCount; ?></h3>
                          <small class="text-muted">
                            <?= $isUniversitySummary ? 'Faculty (All Campuses)' : 'Campus Faculty'; ?>
                          </small>
                        </div>
                      </div>

                      <!-- Programs Offered (placeholder) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-info">
                            <i class="bx bx-book-open"></i>
                          </div>
                          <h3 class="kpi-value text-info mt-2"><?= (int)$programCount; ?></h3>
                          <small class="text-muted">Programs Offered</small>
                        </div>
                      </div>

                      <!-- Sections (placeholder) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-success">
                            <i class="bx bx-group"></i>
                          </div>
                          <h3 class="kpi-value text-success mt-2"><?= (int)$sectionCount; ?></h3>
                          <small class="text-muted">Active Sections</small>
                        </div>
                      </div>

                      <!-- Rooms (real count) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-warning">
                            <i class="bx bx-building-house"></i>
                          </div>
                          <h3 class="kpi-value text-warning mt-2">
                            <?= $roomCount; ?>
                          </h3>
                          <small class="text-muted">Total Rooms</small>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>

                <!-- SCHEDULING HEALTH ANALYTICS -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                      <p class="card-section-title mb-1">Scheduling Health</p>
                      <h5 class="m-0">Weekly Schedule Heatmap &amp; Room Utilization</h5>
                    </div>
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-2">
                      <?php if ($selectedCollegeId && $analyticsReady): ?>
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary"
                          id="openMatrixModal"
                        >
                          <i class="bx bx-grid-alt me-1"></i>
                          View Schedule Matrix
                        </button>
                      <?php elseif (!$selectedCollegeId): ?>
                        <span class="text-muted small">Select a college to open the scheduler matrix.</span>
                      <?php endif; ?>
                      <span class="badge bg-label-info"><?= $academicTermTextEscaped ?></span>
                    </div>
                  </div>
                  <div class="card-body">
                    <?php if (!$analyticsReady): ?>
                      <div class="alert alert-warning mb-0">
                        Configure the current academic term first to view scheduling heatmaps and room utilization.
                      </div>
                    <?php else: ?>
                      <div class="row g-4 align-items-stretch">
                        <div class="col-xl-8">
                          <div class="d-flex justify-content-between align-items-start flex-column flex-md-row gap-2 mb-3">
                            <div>
                              <h6 class="fw-bold mb-1">Weekly Schedule Heatmap</h6>
                              <small class="text-muted"><?= htmlspecialchars($heatmapSubtext); ?></small>
                            </div>
                            <span class="badge bg-label-primary">
                              Peak: <?= htmlspecialchars($heatmapPeakLabel); ?>
                              <?php if ($heatmapPeakOccupiedRooms > 0 && $heatmapTotalRooms > 0): ?>
                                (<?= (int)$heatmapPeakOccupiedRooms; ?>/<?= (int)$heatmapTotalRooms; ?> rooms, <?= number_format($heatmapPeakPercent, 1); ?>%)
                              <?php endif; ?>
                            </span>
                          </div>
                          <div id="scheduleHeatmapChart"></div>
                        </div>

                        <div class="col-xl-4">
                          <div class="resource-stat-card">
                            <h6 class="fw-bold mb-1">Scheduling Snapshot</h6>
                            <p class="resource-note mb-0">Use these figures to spot teaching pressure before opening deeper reports.</p>

                            <div class="resource-stat">
                              <div class="resource-stat-label">Scheduled Classes</div>
                              <div class="resource-stat-value"><?= (int)$scheduleSummary['scheduled_classes']; ?></div>
                              <div class="resource-stat-note">Classes included in this current-term scheduling view.</div>
                            </div>

                            <div class="resource-stat">
                              <div class="resource-stat-label">Faculty Coverage</div>
                              <div class="resource-stat-value"><?= number_format($assignmentCoveragePercent, 1); ?>%</div>
                              <div class="resource-stat-note"><?= (int)$scheduleSummary['assigned_classes']; ?> assigned, <?= (int)$scheduleSummary['unassigned_classes']; ?> unassigned.</div>
                            </div>

                            <div class="resource-stat">
                              <div class="resource-stat-label">Top <?= htmlspecialchars($utilizationMetricLabel); ?></div>
                              <div class="resource-stat-value"><?= htmlspecialchars($highestUtilizationLabel); ?></div>
                              <div class="resource-stat-note"><?= number_format($highestUtilizationPercent, 1); ?>% utilization in the busiest item for this view.</div>
                            </div>

                            <div class="resource-stat">
                              <div class="resource-stat-label">Interpretation</div>
                              <div class="resource-stat-note">
                                Darker heatmap cells mean a higher percentage of rooms are occupied in that hour. Higher utilization shows where space is getting tight first.
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <hr class="my-4" />

                      <div class="d-flex justify-content-between align-items-start flex-column flex-md-row gap-2 mb-3">
                        <div>
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($utilizationHeading); ?></h6>
                          <small class="text-muted"><?= htmlspecialchars($utilizationSubtext); ?></small>
                        </div>
                        <small class="resource-note"><?= htmlspecialchars($resourceCapacityNote); ?></small>
                      </div>

                      <div id="roomUtilizationChart"></div>

                      <div class="table-responsive mt-4">
                        <table class="table table-sm table-hover align-middle mb-0">
                          <thead>
                            <tr>
                              <th><?= htmlspecialchars($utilizationMetricLabel); ?></th>
                              <th class="text-center">Rooms</th>
                              <th class="text-end">Used Hours</th>
                              <th class="text-end">Capacity</th>
                              <th class="text-end">Utilization</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($utilizationRows)): ?>
                              <tr>
                                <td colspan="5" class="text-center text-muted">
                                  No current-term room utilization data is available for this view.
                                </td>
                              </tr>
                            <?php else: ?>
                              <?php foreach ($utilizationRows as $row): ?>
                                <tr>
                                  <td class="fw-semibold"><?= htmlspecialchars($row['label']); ?></td>
                                  <td class="text-center"><?= (int)$row['room_count']; ?></td>
                                  <td class="text-end"><?= number_format((float)$row['used_hours'], 1); ?></td>
                                  <td class="text-end"><?= number_format((float)$row['capacity_hours'], 1); ?></td>
                                  <td class="text-end">
                                    <span class="badge <?= $row['utilization_percent'] >= 80 ? 'bg-label-warning' : 'bg-label-success'; ?>">
                                      <?= number_format((float)$row['utilization_percent'], 1); ?>%
                                    </span>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            <?php endif; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>

                  </div>
                </div>

                <!-- PROGRAMS / PROSPECTUS PLACEHOLDER -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                      <p class="card-section-title mb-1">Programs & Prospectus</p>
                      <h5 class="m-0">Program Offerings Overview</h5>
                    </div>
                    <a href="manage-prospectus.php" class="btn btn-sm btn-outline-primary">
                      Manage Prospectus
                    </a>
                  </div>
                  <div class="card-body">
                    <p class="text-muted mb-2">
                      List all programs and prospectus versions for
                      <?= $isUniversitySummary ? 'the entire university.' : 'this campus.'; ?>
                    </p>
                    <div class="alert alert-info mb-0">
                      <i class="bx bx-info-circle me-1"></i>
                      <!-- Placeholder only for now. Hook this card to your existing Prospectus Builder queries when ready. -->
                    </div>
                  </div>
                </div>

              </div>
              <!-- /LEFT COLUMN -->

              <!-- RIGHT COLUMN -->
              <div class="col-lg-4 order-1 order-lg-0 mb-4">

                <!-- CAMPUS QUICK FACTS -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <p class="card-section-title mb-1">Quick Facts</p>
                    <h5 class="m-0">
                      <?= $isUniversitySummary ? 'University Snapshot' : 'Campus Snapshot'; ?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <ul class="list-unstyled mb-0">
                      <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Campus Code</span>
                        <strong><?= htmlspecialchars($campusCode); ?></strong>
                      </li>
                      <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Total Rooms</span>
                        <strong><?= $roomCount; ?></strong>
                      </li>
                       <li class="mb-2 d-flex justify-content-between">
                         <span class="text-muted">Faculty</span>
                         <strong><?= (int)$facultyCount; ?></strong>
                       </li>
                       <li class="mb-2 d-flex justify-content-between">
                         <span class="text-muted">Programs</span>
                         <strong><?= (int)$programCount; ?></strong>
                       </li>
                    </ul>
                  </div>
                </div>

                <!-- FACULTY DIRECTORY PLACEHOLDER -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                      <p class="card-section-title mb-1">People</p>
                      <h5 class="m-0">Faculty Directory</h5>
                    </div>
                    <a href="manage-faculty.php" class="btn btn-sm btn-outline-primary">
                      Manage Faculty
                    </a>
                  </div>
                  <div class="card-body">
                    <p class="text-muted mb-2">
                      Show top faculty for this campus (e.g., by workload or department).
                    </p>
                    <div class="alert alert-secondary mb-0">
                      <i class="bx bx-user me-1"></i>
                      Faculty assignments.
                    </div>
                  </div>
                </div>

                <!-- NAVIGATION TO OTHER CAMPUSES -->
                <div class="card shadow-sm">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0">Switch Campus</h5>
                    <span class="badge bg-label-primary">Navigation</span>
                  </div>
                  <div class="card-body">
                    <div class="list-group list-group-flush">
                      <a href="campus_dashboard.php?campus_id=all" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bx bx-globe me-2"></i>University Summary</span>
                      </a>
                      <?php
                      // Reuse campus list for quick switcher
                      $campSql = $conn->query("SELECT campus_id, campus_name FROM tbl_campus WHERE status='active' ORDER BY campus_name ASC");
                      while ($cRow = $campSql->fetch_assoc()):
                      ?>
                        <a
                          href="campus_dashboard.php?campus_id=<?= $cRow['campus_id']; ?>"
                          class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                          <?= (!$isUniversitySummary && (int)$campusId === (int)$cRow['campus_id']) ? 'active' : ''; ?>"
                        >
                          <span>
                            <i class="bx bx-buildings me-2"></i><?= htmlspecialchars($cRow['campus_name']); ?>
                          </span>
                          <?php if (!$isUniversitySummary && (int)$campusId === (int)$cRow['campus_id']): ?>
                            <i class="bx bx-check-circle"></i>
                          <?php endif; ?>
                        </a>
                      <?php endwhile; ?>
                    </div>
                  </div>
                </div>

              </div>
              <!-- /RIGHT COLUMN -->
            </div>

          </div>

          <!-- Footer -->
          <?php include '../footer.php'; ?>
          <!-- /Footer -->

          <div class="content-backdrop fade"></div>
        </div>
      </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <div class="modal fade" id="matrixModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title" id="matrixModalTitle">
            <i class="bx bx-grid-alt me-1"></i> Schedule Matrix
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div id="matrixContainer">
            <div class="text-center text-muted py-5">
              Loading schedule matrix...
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Core JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>

  <!-- Vendors JS -->
  <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>

  <!-- Main JS -->
  <script src="../assets/js/main.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      var heatmapSeries = <?= json_encode($heatmapSeries, JSON_NUMERIC_CHECK); ?>;
      var utilizationLabels = <?= json_encode($utilizationChartLabels); ?>;
      var utilizationUsed = <?= json_encode($utilizationChartUsed, JSON_NUMERIC_CHECK); ?>;
      var utilizationAvailable = <?= json_encode($utilizationChartAvailable, JSON_NUMERIC_CHECK); ?>;
      var analyticsReady = <?= $analyticsReady ? 'true' : 'false'; ?>;
      var currentAyId = <?= (int)$currentAyId; ?>;
      var currentSem = <?= (int)$currentSem; ?>;
      var selectedCollegeId = <?= (int)$selectedCollegeId; ?>;
      var selectedCollegeName = <?= json_encode(htmlspecialchars((string)($selectedCollegeName ?? 'Selected College'), ENT_QUOTES, 'UTF-8')); ?>;
      var academicTermText = <?= json_encode(htmlspecialchars($academicTermText, ENT_QUOTES, 'UTF-8')); ?>;
      var csrfToken = <?= json_encode($csrfToken); ?>;

      if (!analyticsReady) {
        return;
      }

      var hasApexCharts = typeof ApexCharts !== "undefined";
      var heatmapElement = document.querySelector("#scheduleHeatmapChart");
      if (heatmapElement && hasApexCharts) {
        var heatmapOptions = {
          series: heatmapSeries,
          chart: {
            type: "heatmap",
            height: 340,
            toolbar: { show: false }
          },
          dataLabels: {
            enabled: false
          },
          stroke: {
            width: 1,
            colors: ["#ffffff"]
          },
          plotOptions: {
            heatmap: {
              shadeIntensity: 0.6,
              enableShades: false,
              colorScale: {
                ranges: [
                  { from: 0, to: 0, name: "Idle", color: "#edf1f7" },
                  { from: 0.1, to: 25, name: "Light", color: "#8fc2ff" },
                  { from: 25.1, to: 50, name: "Moderate", color: "#4b97f2" },
                  { from: 50.1, to: 75, name: "Busy", color: "#1f6fd1" },
                  { from: 75.1, to: 100, name: "Peak", color: "#114aa3" }
                ]
              }
            }
          },
          xaxis: {
            labels: {
              rotate: -35,
              style: {
                fontSize: "11px",
                colors: "#777"
              }
            }
          },
          yaxis: {
            labels: {
              style: {
                colors: "#777"
              }
            }
          },
          legend: {
            position: "top",
            horizontalAlign: "left"
          },
          grid: {
            borderColor: "#eceef4",
            strokeDashArray: 4
          },
          tooltip: {
            y: {
              formatter: function (value) {
                return Number(value).toFixed(1) + "% occupied";
              }
            }
          }
        };

        new ApexCharts(heatmapElement, heatmapOptions).render();
      }

      if (!utilizationLabels.length) {
        utilizationLabels = ["No data"];
        utilizationUsed = [0];
        utilizationAvailable = [0];
      }

      var utilizationElement = document.querySelector("#roomUtilizationChart");
      if (utilizationElement && hasApexCharts) {
        var utilizationOptions = {
          series: [
            {
              name: "Used Room-Hours",
              data: utilizationUsed
            },
            {
              name: "Available Room-Hours",
              data: utilizationAvailable
            }
          ],
          chart: {
            type: "bar",
            height: Math.max(300, utilizationLabels.length * 56),
            stacked: true,
            toolbar: { show: false }
          },
          colors: ["#ffab00", "#ebeef5"],
          plotOptions: {
            bar: {
              horizontal: true,
              borderRadius: 6,
              barHeight: "58%"
            }
          },
          dataLabels: {
            enabled: false
          },
          stroke: {
            width: 1,
            colors: ["#ffffff"]
          },
          xaxis: {
            categories: utilizationLabels,
            title: {
              text: "Room-hours per week",
              style: { color: "#777" }
            },
            labels: {
              style: { colors: "#777" }
            }
          },
          yaxis: {
            labels: {
              style: { colors: "#566a7f" }
            }
          },
          legend: {
            position: "top",
            horizontalAlign: "left"
          },
          grid: {
            borderColor: "#eceef4",
            strokeDashArray: 4
          },
          tooltip: {
            shared: true,
            intersect: false,
            y: {
              formatter: function (value) {
                return Number(value).toFixed(1) + " hours";
              }
            }
          }
        };

        new ApexCharts(utilizationElement, utilizationOptions).render();
      }

      if (!hasApexCharts) {
        console.error("ApexCharts library failed to load.");
      }

      var openMatrixButton = document.getElementById("openMatrixModal");
      var matrixContainer = document.getElementById("matrixContainer");
      var matrixModalElement = document.getElementById("matrixModal");
      var matrixModalTitle = document.getElementById("matrixModalTitle");

      if (openMatrixButton && matrixContainer && matrixModalElement && selectedCollegeId > 0) {
        var matrixModal = new bootstrap.Modal(matrixModalElement);

        openMatrixButton.addEventListener("click", function () {
          matrixModalTitle.innerHTML = '<i class="bx bx-grid-alt me-1"></i> ' +
            selectedCollegeName + ' Schedule Matrix <small class="text-muted">(' + academicTermText + ')</small>';

          matrixContainer.innerHTML = `
            <div class="text-center text-muted py-5">
              Loading schedule matrix...
            </div>
          `;

          matrixModal.show();

          $.post(
            "../backend/load_room_time_matrix.php",
            {
              college_id: selectedCollegeId,
              ay_id: currentAyId,
              semester: currentSem,
              csrf_token: csrfToken
            }
          ).done(function (html) {
            matrixContainer.innerHTML = html;
          }).fail(function (xhr) {
            matrixContainer.innerHTML =
              "<div class='text-danger text-center py-4'>Failed to load schedule matrix.</div>";
            console.error(xhr.responseText);
          });
        });
      }
    });
  </script>

</body>
</html>
