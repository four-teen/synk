<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/academic_schedule_policy_helper.php';
require_once __DIR__ . '/schedule_merge_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "<div class='text-danger text-center'>Unauthorized access.</div>";
    exit;
}

$role = (string)($_SESSION['role'] ?? '');
if (!in_array($role, ['scheduler', 'admin'], true)) {
    echo "<div class='text-danger text-center'>Unauthorized access.</div>";
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo "<div class='text-danger text-center'>CSRF validation failed.</div>";
    exit;
}

$college_id = 0;
if ($role === 'scheduler') {
    $college_id = (int)($_SESSION['college_id'] ?? 0);
} elseif ($role === 'admin') {
    $college_id = (int)($_POST['college_id'] ?? 0);
}
$ay_id = (int)($_POST['ay_id'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);

if ($college_id <= 0 || $ay_id <= 0 || !in_array($semester, [1, 2, 3], true)) {
    echo "<div class='text-danger text-center'>Invalid scheduling context.</div>";
    exit;
}

$schedulePolicy = synk_fetch_effective_schedule_policy($conn, $college_id);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function time_policy_label($time) {
    return synk_schedule_policy_time_label((string)$time);
}

function build_policy_time_slots(array $schedulePolicy, int $slotMinutes = 30): array {
    $slotMinutes = max(5, $slotMinutes);
    $dayStart = synk_schedule_policy_normalize_time((string)($schedulePolicy['day_start'] ?? ''), '07:30:00');
    $dayEnd = synk_schedule_policy_normalize_time((string)($schedulePolicy['day_end'] ?? ''), '17:30:00');
    $cursor = strtotime($dayStart);
    $end = strtotime($dayEnd);

    if ($cursor === false || $end === false || $cursor >= $end) {
        return [];
    }

    $slots = [];
    while ($cursor < $end) {
        $slotEnd = min(strtotime("+{$slotMinutes} minutes", $cursor), $end);
        if ($slotEnd === false || $slotEnd <= $cursor) {
            break;
        }

        $slots[] = [
            'start' => date("H:i:s", $cursor),
            'end' => date("H:i:s", $slotEnd)
        ];
        $cursor = $slotEnd;
    }

    return $slots;
}

function normalize_day_token($day) {
    $token = strtoupper(trim((string)$day));
    return $token === 'TH' ? 'Th' : $token;
}

function day_short_label($day) {
    $labels = [
        'M' => 'Mon',
        'T' => 'Tue',
        'W' => 'Wed',
        'Th' => 'Thu',
        'F' => 'Fri',
        'S' => 'Sat'
    ];

    return $labels[$day] ?? $day;
}

function subject_color_class($subjectCode) {
    $subject = strtoupper(trim((string)$subjectCode));
    if ($subject === '') {
        return 'sub-0';
    }

    $paletteSize = 12;
    $hash = (int)(sprintf('%u', crc32($subject)) % $paletteSize);
    return 'sub-' . $hash;
}

function overlaps($slotStart, $slotEnd, $itemStart, $itemEnd) {
    return ($slotStart < $itemEnd) && ($slotEnd > $itemStart);
}

$hasAccessTable = $conn->query("SHOW TABLES LIKE 'tbl_room_college_access'");
if (!$hasAccessTable || $hasAccessTable->num_rows === 0) {
    echo "<div class='text-muted text-center'>Room access table is missing.</div>";
    exit;
}

// Build the matrix columns directly from the effective schedule policy window.
$timeSlots = build_policy_time_slots($schedulePolicy, 30);
if (empty($timeSlots)) {
    echo "<div class='text-danger text-center'>Invalid scheduling window for the selected policy.</div>";
    exit;
}

$roomsStmt = $conn->prepare("
    SELECT DISTINCT
        r.room_id,
        r.room_code,
        r.room_name,
        acc.access_type,
        owner.college_code AS owner_code
    FROM tbl_room_college_access acc
    INNER JOIN tbl_rooms r ON r.room_id = acc.room_id
    INNER JOIN tbl_college owner ON owner.college_id = r.college_id
    WHERE acc.college_id = ?
      AND acc.ay_id = ?
      AND acc.semester = ?
      AND r.status = 'active'
    ORDER BY r.room_name ASC, r.room_code ASC
");
$roomsStmt->bind_param("iii", $college_id, $ay_id, $semester);
$roomsStmt->execute();
$roomsRes = $roomsStmt->get_result();

$rooms = [];
while ($row = $roomsRes->fetch_assoc()) {
    $roomId = (int)$row['room_id'];
    $label = trim((string)($row['room_code'] ?? ''));
    $roomName = trim((string)($row['room_name'] ?? ''));

    if ($roomName !== '' && strcasecmp($label, $roomName) !== 0) {
        $label = $label !== '' ? ($label . " - " . $roomName) : $roomName;
    } elseif ($label === '') {
        $label = $roomName;
    }

    $accessType = strtolower(trim((string)($row['access_type'] ?? 'owner')));
    if ($accessType === 'shared') {
        $ownerCode = trim((string)($row['owner_code'] ?? ''));
        $label .= $ownerCode !== '' ? " (Shared from {$ownerCode})" : " (Shared)";
    }

    $rooms[$roomId] = $label;
}
$roomsStmt->close();

if (empty($rooms)) {
    echo "<div class='text-muted text-center'>No active rooms found for the selected term.</div>";
    exit;
}

$roomIds = array_keys($rooms);
$roomIdList = implode(',', array_map('intval', $roomIds));
$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

$schedSql = "
    SELECT
        o.offering_id,
        cs.schedule_id,
        cs.room_id,
        cs.schedule_type,
        cs.time_start,
        cs.time_end,
        cs.days_json,
        sm.sub_code,
        sec.section_name,
        c.college_code,
        p.college_id AS offering_college_id
    FROM tbl_class_schedule cs
    INNER JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
    {$liveOfferingJoins}
    INNER JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
    INNER JOIN tbl_program p ON p.program_id = o.program_id
    INNER JOIN tbl_college c ON c.college_id = p.college_id
    WHERE cs.room_id IN ({$roomIdList})
      AND o.ay_id = ?
      AND o.semester = ?
    ORDER BY cs.room_id, cs.time_start, cs.schedule_id
";

$schedStmt = $conn->prepare($schedSql);
if (!$schedStmt) {
    error_log('load_room_time_matrix prepare failed: ' . $conn->error);
    echo "<div class='text-danger text-center'>Unable to load the room-time matrix right now.</div>";
    exit;
}

$schedStmt->bind_param("ii", $ay_id, $semester);
$schedStmt->execute();
$schedRes = $schedStmt->get_result();

$daysOrder = ['M', 'T', 'W', 'Th', 'F', 'S'];
$schedulesByRoomDay = [];
$rawScheduleRows = [];
$scheduleOfferingIds = [];

while ($row = $schedRes->fetch_assoc()) {
    $rawScheduleRows[] = $row;
    $offeringId = (int)($row['offering_id'] ?? 0);
    if ($offeringId > 0) {
        $scheduleOfferingIds[] = $offeringId;
    }
}
$schedStmt->close();

$mergeContext = synk_schedule_merge_load_display_context($conn, $scheduleOfferingIds);

foreach ($rawScheduleRows as $row) {
    $decodedDays = json_decode((string)$row['days_json'], true);
    if (!is_array($decodedDays) || empty($decodedDays)) {
        continue;
    }

    $offeringId = (int)($row['offering_id'] ?? 0);
    $mergeInfo = $mergeContext[$offeringId] ?? null;
    $sectionLabel = (string)($row['section_name'] ?? '');
    if (is_array($mergeInfo) && (int)($mergeInfo['group_size'] ?? 1) > 1) {
        $groupLabel = trim((string)($mergeInfo['group_course_label'] ?? ''));
        if ($groupLabel !== '') {
            $sectionLabel = $groupLabel;
        }
    }

    $item = [
        'offering_id' => $offeringId,
        'schedule_id' => (int)$row['schedule_id'],
        'schedule_type' => strtoupper(trim((string)($row['schedule_type'] ?? 'LEC'))),
        'time_start' => (string)$row['time_start'],
        'time_end' => (string)$row['time_end'],
        'sub_code' => (string)$row['sub_code'],
        'section_name' => $sectionLabel,
        'college_code' => (string)($row['college_code'] ?? ''),
        'subject_class' => subject_color_class($row['sub_code'] ?? ''),
        'can_remove' => $role === 'scheduler'
            && (int)($row['offering_college_id'] ?? 0) === $college_id
            && !(is_array($mergeInfo) && !empty($mergeInfo['has_merged_members']))
    ];

    foreach ($decodedDays as $dayToken) {
        $dayKey = normalize_day_token($dayToken);
        if (!in_array($dayKey, $daysOrder, true)) {
            continue;
        }

        $schedulesByRoomDay[(int)$row['room_id']][$dayKey][] = $item;
    }
}

$policySourceLabel = trim((string)($schedulePolicy['source_label'] ?? 'Scheduling policy'));
$policyWindowLabel = trim((string)($schedulePolicy['window_label'] ?? ''));
if ($policyWindowLabel === '') {
    $policyWindowLabel = synk_schedule_policy_window_label(
        (string)($schedulePolicy['day_start'] ?? '07:30:00'),
        (string)($schedulePolicy['day_end'] ?? '17:30:00')
    );
}

echo "<div class='mb-3 small text-muted matrix-meta-note'>";
echo "This matrix follows the " . h($policySourceLabel) . " window of " . h($policyWindowLabel) . ".";
echo " Time labels use 12-hour format, and each column is a 30-minute time block.";
echo " The same subject keeps the same color across the matrix. Shared rooms include schedules from every college using that room.";
if ($role === 'scheduler') {
    echo " Click a colored schedule block to remove the entire offering schedule, including paired lecture and laboratory rows.";
}
if (!empty($schedulePolicy['blocked_days_label']) && $schedulePolicy['blocked_days_label'] !== 'None') {
    echo " Blocked days: " . h($schedulePolicy['blocked_days_label']) . ".";
}
if (!empty($schedulePolicy['blocked_times_label']) && $schedulePolicy['blocked_times_label'] !== 'None') {
    echo " Blocked times: " . h($schedulePolicy['blocked_times_label']) . ".";
}
echo "</div>";

echo "<div class='matrix-shell'>";
echo "<div class='table-responsive matrix-scroll-wrap'>";
echo "<table class='table table-bordered matrix-table'>";
echo "<colgroup>";
echo "<col class='matrix-room-col'>";
echo "<col class='matrix-day-col'>";
foreach ($timeSlots as $slot) {
    echo "<col class='matrix-slot-col'>";
}
echo "</colgroup>";
echo "<thead><tr>";
echo "<th class='matrix-room'>Room</th>";
echo "<th class='matrix-day'>Day</th>";

foreach ($timeSlots as $slot) {
    echo "<th class='text-center matrix-slot-header'><div class='matrix-time-slot'><span>" . h(time_policy_label($slot['start'])) . "</span><span>" . h(time_policy_label($slot['end'])) . "</span></div></th>";
}

echo "</tr></thead><tbody>";

foreach ($rooms as $roomId => $roomLabel) {
    $firstDayRow = true;

    foreach ($daysOrder as $dayKey) {
        $dayItems = $schedulesByRoomDay[$roomId][$dayKey] ?? [];
        $slotMap = array_fill(0, count($timeSlots), []);

        foreach ($dayItems as $item) {
            foreach ($timeSlots as $index => $slot) {
                if (!overlaps($slot['start'], $slot['end'], $item['time_start'], $item['time_end'])) {
                    continue;
                }

                $slotMap[$index][$item['schedule_id']] = $item;
            }
        }

        echo "<tr>";

        if ($firstDayRow) {
            echo "<td class='fw-bold matrix-room' rowspan='" . count($daysOrder) . "'>" . h($roomLabel) . "</td>";
            $firstDayRow = false;
        }

        echo "<td class='matrix-day text-center'>" . h(day_short_label($dayKey)) . "</td>";

        for ($slotIndex = 0; $slotIndex < count($timeSlots); ) {
            $currentItems = array_values($slotMap[$slotIndex]);

            if (empty($currentItems)) {
                echo "<td><div class='matrix-cell matrix-vacant'>-</div></td>";
                $slotIndex++;
                continue;
            }

            usort($currentItems, function ($a, $b) {
                return $a['schedule_id'] <=> $b['schedule_id'];
            });

            $signature = implode('|', array_map(function ($item) {
                return (string)$item['schedule_id'];
            }, $currentItems));

            $colspan = 1;
            for ($nextIndex = $slotIndex + 1; $nextIndex < count($timeSlots); $nextIndex++) {
                $nextItems = array_values($slotMap[$nextIndex]);
                usort($nextItems, function ($a, $b) {
                    return $a['schedule_id'] <=> $b['schedule_id'];
                });

                $nextSignature = implode('|', array_map(function ($item) {
                    return (string)$item['schedule_id'];
                }, $nextItems));

                if ($nextSignature !== $signature) {
                    break;
                }

                $colspan++;
            }

            $cellClass = count($currentItems) > 1 ? 'matrix-conflict' : 'matrix-occupied';
            $entriesHtml = [];

            foreach ($currentItems as $item) {
                $typeLabel = $item['schedule_type'] === 'LAB' ? 'LAB' : 'LEC';
                $collegeSuffix = $item['college_code'] !== '' ? " | " . $item['college_code'] : '';
                $entryClass = 'matrix-entry ' . $item['subject_class'] . ($item['can_remove'] ? ' matrix-entry-actionable' : ' matrix-entry-readonly');
                $entryTitle = trim($item['sub_code'] . ' - ' . $item['section_name']);

                $entriesHtml[] = "
                    <div
                        class='" . h($entryClass) . "'
                        data-offering-id='" . (int)$item['offering_id'] . "'
                        data-removable='" . ($item['can_remove'] ? "1" : "0") . "'
                        data-sub-code='" . h($item['sub_code']) . "'
                        data-section-name='" . h($item['section_name']) . "'
                        data-type-label='" . h($typeLabel) . "'
                        data-college-code='" . h($item['college_code']) . "'
                        title='" . h($item['can_remove'] ? "Click to remove this offering schedule" : "View-only entry") . "'
                        role='button'
                        tabindex='0'
                        aria-label='" . h($entryTitle . ' ' . $typeLabel) . "'>
                        <strong>" . h($item['sub_code']) . "</strong><br>
                        <small>" . h($item['section_name']) . " | " . h($typeLabel) . h($collegeSuffix) . "</small>
                    </div>
                ";
            }

            echo "<td colspan='{$colspan}'><div class='matrix-cell {$cellClass}'>" . implode('', $entriesHtml) . "</div></td>";
            $slotIndex += $colspan;
        }

        echo "</tr>";
    }
}

echo "</tbody></table>";
echo "</div>";
echo "</div>";
