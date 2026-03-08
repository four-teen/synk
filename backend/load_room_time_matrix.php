<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    echo "<div class='text-danger text-center'>Unauthorized access.</div>";
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo "<div class='text-danger text-center'>CSRF validation failed.</div>";
    exit;
}

$college_id = (int)($_SESSION['college_id'] ?? 0);
$ay_id = (int)($_POST['ay_id'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);

if ($college_id <= 0 || $ay_id <= 0 || !in_array($semester, [1, 2, 3], true)) {
    echo "<div class='text-danger text-center'>Invalid scheduling context.</div>";
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function time_compact_label($time) {
    return date("H:i", strtotime($time));
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

$timeSlots = [];
$cursor = strtotime("07:30");
$end = strtotime("17:30");

while ($cursor < $end) {
    $timeSlots[] = [
        'start' => date("H:i:s", $cursor),
        'end' => date("H:i:s", strtotime("+30 minutes", $cursor))
    ];
    $cursor = strtotime("+30 minutes", $cursor);
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
        cs.schedule_id,
        cs.room_id,
        cs.schedule_type,
        cs.time_start,
        cs.time_end,
        cs.days_json,
        sm.sub_code,
        sec.section_name,
        c.college_code
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

while ($row = $schedRes->fetch_assoc()) {
    $decodedDays = json_decode((string)$row['days_json'], true);
    if (!is_array($decodedDays) || empty($decodedDays)) {
        continue;
    }

    $item = [
        'schedule_id' => (int)$row['schedule_id'],
        'schedule_type' => strtoupper(trim((string)($row['schedule_type'] ?? 'LEC'))),
        'time_start' => (string)$row['time_start'],
        'time_end' => (string)$row['time_end'],
        'sub_code' => (string)$row['sub_code'],
        'section_name' => (string)$row['section_name'],
        'college_code' => (string)($row['college_code'] ?? ''),
        'subject_class' => subject_color_class($row['sub_code'] ?? '')
    ];

    foreach ($decodedDays as $dayToken) {
        $dayKey = normalize_day_token($dayToken);
        if (!in_array($dayKey, $daysOrder, true)) {
            continue;
        }

        $schedulesByRoomDay[(int)$row['room_id']][$dayKey][] = $item;
    }
}
$schedStmt->close();

echo "<div class='mb-3 small text-muted matrix-meta-note'>";
echo "Each column is a 30-minute time block. The same subject keeps the same color across the matrix. Shared rooms include schedules from every college using that room.";
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
    echo "<th class='text-center matrix-slot-header'><div class='matrix-time-slot'><span>" . h(time_compact_label($slot['start'])) . "</span><span>" . h(time_compact_label($slot['end'])) . "</span></div></th>";
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

                $entriesHtml[] = "
                    <div class='matrix-entry " . h($item['subject_class']) . "'>
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
