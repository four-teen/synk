<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';

header('Content-Type: application/json');

function respond($status, $message = '', $extra = []) {
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Invalid request method.');
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'scheduler') {
    respond('error', 'Unauthorized access.');
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    respond('error', 'CSRF validation failed.');
}

$college_id = (int)($_SESSION['college_id'] ?? 0);
$offering_id = (int)($_POST['offering_id'] ?? 0);

if ($college_id <= 0 || $offering_id <= 0) {
    respond('error', 'Missing scheduling context.');
}

function day_order() {
    return ['M', 'T', 'W', 'Th', 'F', 'S'];
}

function normalize_day_token($day) {
    $token = strtoupper(trim((string)$day));

    if ($token === 'TH') {
        return 'Th';
    }

    return in_array($token, day_order(), true) ? $token : '';
}

function normalize_days_array($days) {
    if (!is_array($days)) {
        return [];
    }

    $seen = [];
    foreach ($days as $day) {
        $normalized = normalize_day_token($day);
        if ($normalized === '') {
            continue;
        }
        $seen[$normalized] = true;
    }

    $result = [];
    foreach (day_order() as $day) {
        if (isset($seen[$day])) {
            $result[] = $day;
        }
    }

    return $result;
}

function days_overlap($a, $b) {
    return is_array($a) && is_array($b) && count(array_intersect($a, $b)) > 0;
}

function time_overlap($startA, $endA, $startB, $endB) {
    $startA = is_numeric($startA) ? (int)$startA : time_to_minutes($startA);
    $endA = is_numeric($endA) ? (int)$endA : time_to_minutes($endA);
    $startB = is_numeric($startB) ? (int)$startB : time_to_minutes($startB);
    $endB = is_numeric($endB) ? (int)$endB : time_to_minutes($endB);

    if ($startA === null || $endA === null || $startB === null || $endB === null) {
        return false;
    }

    return ($startA < $endB) && ($endA > $startB);
}

function time_to_minutes($time) {
    $time = trim((string)$time);
    if ($time === '') {
        return null;
    }

    $parts = explode(':', $time);
    if (count($parts) < 2) {
        return null;
    }

    return ((int)$parts[0] * 60) + (int)$parts[1];
}

function minutes_to_time($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d:00', $hours, $mins);
}

function minutes_to_input_time($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

function minutes_to_label($minutes) {
    return date('h:i A', strtotime(minutes_to_time($minutes)));
}

function days_to_label($days) {
    return implode('', normalize_days_array($days));
}

function ceil_to_slot($minutes, $slot = 30) {
    $minutes = (int)ceil((float)$minutes);
    return (int)(ceil(max($slot, $minutes) / $slot) * $slot);
}

function room_access_table_exists($conn) {
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $q = $conn->query("SHOW TABLES LIKE 'tbl_room_college_access'");
    $exists = $q && $q->num_rows > 0;
    return $exists;
}

function normalize_room_type($type) {
    $value = strtolower(trim((string)$type));
    return in_array($value, ['lecture', 'laboratory', 'lec_lab'], true)
        ? $value
        : 'lecture';
}

function room_type_allows_schedule($roomType, $scheduleType) {
    $roomType = normalize_room_type($roomType);
    $scheduleType = strtoupper(trim((string)$scheduleType));

    if ($scheduleType === 'LAB') {
        return in_array($roomType, ['laboratory', 'lec_lab'], true);
    }

    return in_array($roomType, ['lecture', 'lec_lab'], true);
}

function build_room_label(array $row) {
    $code = trim((string)($row['room_code'] ?? ''));
    $name = trim((string)($row['room_name'] ?? ''));
    $label = $code !== '' ? $code : $name;

    if ($code !== '' && $name !== '' && strcasecmp($code, $name) !== 0) {
        $label = $code . ' - ' . $name;
    } elseif ($label === '') {
        $label = 'Room';
    }

    $accessType = strtolower(trim((string)($row['access_type'] ?? 'owner')));
    if ($accessType === 'shared') {
        $ownerCode = trim((string)($row['owner_code'] ?? ''));
        $label .= $ownerCode !== ''
            ? " (Shared from {$ownerCode})"
            : ' (Shared)';
    }

    return $label;
}

function load_offering_context($conn, $offeringId, $collegeId) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            o.offering_id,
            o.section_id,
            o.ay_id,
            o.semester,
            o.year_level,
            sec.section_name,
            sm.sub_code,
            sm.sub_description,
            ps.lec_units,
            ps.lab_units,
            ps.total_units
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        INNER JOIN tbl_program p ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
        WHERE o.offering_id = ?
          AND p.college_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $offeringId, $collegeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function load_accessible_rooms($conn, $collegeId, $ayId, $semester) {
    if (!room_access_table_exists($conn)) {
        return [];
    }

    $sql = "
        SELECT DISTINCT
            r.room_id,
            r.room_code,
            r.room_name,
            LOWER(COALESCE(r.room_type, 'lecture')) AS room_type,
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
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iii', $collegeId, $ayId, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    $rooms = [];
    while ($row = $res->fetch_assoc()) {
        $row['room_id'] = (int)$row['room_id'];
        $row['room_type'] = normalize_room_type($row['room_type'] ?? 'lecture');
        $row['access_type'] = strtolower((string)($row['access_type'] ?? 'owner'));
        $row['label'] = build_room_label($row);
        $rooms[] = $row;
    }

    $stmt->close();
    return $rooms;
}

function load_term_schedule_snapshot_by_day($conn, $ayId, $semester, $excludeOfferingId) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
    $sql = "
        SELECT
            cs.schedule_id,
            cs.offering_id,
            cs.room_id,
            cs.schedule_type,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            o.section_id,
            sec.section_name
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
        {$liveOfferingJoins}
        WHERE o.ay_id = ?
          AND o.semester = ?
          AND cs.offering_id <> ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iii', $ayId, $semester, $excludeOfferingId);
    $stmt->execute();
    $res = $stmt->get_result();

    $byDay = [];
    foreach (day_order() as $day) {
        $byDay[$day] = [];
    }

    while ($row = $res->fetch_assoc()) {
        $days = normalize_days_array(json_decode((string)$row['days_json'], true));
        if (empty($days)) {
            continue;
        }

        $item = [
            'schedule_id' => (int)$row['schedule_id'],
            'offering_id' => (int)$row['offering_id'],
            'room_id' => (int)$row['room_id'],
            'schedule_type' => strtoupper(trim((string)($row['schedule_type'] ?? 'LEC'))),
            'time_start' => (string)$row['time_start'],
            'time_end' => (string)$row['time_end'],
            'section_id' => (int)$row['section_id'],
            'section_name' => (string)($row['section_name'] ?? ''),
            'days' => $days
        ];

        foreach ($days as $day) {
            $byDay[$day][] = $item;
        }
    }

    $stmt->close();
    return $byDay;
}

function normalize_draft_schedules($draftsRaw) {
    if (is_string($draftsRaw) && trim($draftsRaw) !== '') {
        $decoded = json_decode($draftsRaw, true);
        $draftsRaw = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($draftsRaw)) {
        return [];
    }

    $result = [];
    foreach (['LEC', 'LAB'] as $type) {
        if (!isset($draftsRaw[$type]) || !is_array($draftsRaw[$type])) {
            continue;
        }

        $raw = $draftsRaw[$type];
        $days = [];

        if (isset($raw['days']) && is_array($raw['days'])) {
            $days = normalize_days_array($raw['days']);
        } elseif (!empty($raw['days_json'])) {
            $decodedDays = json_decode((string)$raw['days_json'], true);
            $days = normalize_days_array($decodedDays);
        }

        $timeStart = trim((string)($raw['time_start'] ?? ''));
        $timeEnd = trim((string)($raw['time_end'] ?? ''));

        $result[$type] = [
            'room_id' => (int)($raw['room_id'] ?? 0),
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'days' => $days
        ];
    }

    return $result;
}

function is_complete_draft($draft) {
    return is_array($draft)
        && !empty($draft['time_start'])
        && !empty($draft['time_end'])
        && !empty($draft['days'])
        && is_array($draft['days'])
        && $draft['time_end'] > $draft['time_start'];
}

function add_pattern(&$patterns, $days, $minutes, $weight, $label) {
    $days = normalize_days_array($days);
    $minutes = ceil_to_slot($minutes, 30);

    if (empty($days) || $minutes <= 0) {
        return;
    }

    $key = implode('-', $days) . '|' . $minutes;
    if (isset($patterns[$key])) {
        if ($weight > $patterns[$key]['weight']) {
            $patterns[$key]['weight'] = $weight;
            $patterns[$key]['label'] = $label;
        }
        return;
    }

    $patterns[$key] = [
        'days' => $days,
        'minutes' => $minutes,
        'weight' => $weight,
        'label' => $label
    ];
}

function build_schedule_patterns($scheduleType, $lecUnits, $labUnits) {
    $scheduleType = strtoupper(trim((string)$scheduleType));
    $patterns = [];

    if ($scheduleType === 'LAB') {
        $weeklyMinutes = max(180, (int)round((float)$labUnits * 180));

        if ($weeklyMinutes <= 240) {
            foreach (['M', 'T', 'W', 'Th', 'F'] as $day) {
                add_pattern($patterns, [$day], $weeklyMinutes, 26, "{$day} laboratory block");
            }
            add_pattern($patterns, ['S'], $weeklyMinutes, 18, 'Saturday laboratory block');
        } else {
            $splitMinutes = max(180, ceil_to_slot($weeklyMinutes / 2, 30));
            add_pattern($patterns, ['M', 'W'], $splitMinutes, 24, 'MW split laboratory');
            add_pattern($patterns, ['T', 'Th'], $splitMinutes, 24, 'TTh split laboratory');
            add_pattern($patterns, ['F', 'S'], $splitMinutes, 15, 'FS split laboratory');
        }

        return array_values($patterns);
    }

    $weeklyMinutes = max(60, (int)round((float)$lecUnits * 60));

    if ($weeklyMinutes >= 120) {
        $pairMinutes = min(150, ceil_to_slot($weeklyMinutes / 2, 30));
        add_pattern($patterns, ['M', 'W'], $pairMinutes, 30, 'MW lecture pair');
        add_pattern($patterns, ['T', 'Th'], $pairMinutes, 30, 'TTh lecture pair');
    }

    if ($weeklyMinutes >= 180) {
        $mwfMinutes = min(90, max(60, ceil_to_slot($weeklyMinutes / 3, 30)));
        add_pattern($patterns, ['M', 'W', 'F'], $mwfMinutes, 24, 'MWF lecture spread');
    }

    if ($weeklyMinutes <= 180) {
        $singleMinutes = max(60, ceil_to_slot($weeklyMinutes, 30));
        foreach (['M', 'T', 'W', 'Th'] as $day) {
            add_pattern($patterns, [$day], $singleMinutes, 14, "{$day} lecture block");
        }
        add_pattern($patterns, ['F'], $singleMinutes, 12, 'Friday lecture block');
        add_pattern($patterns, ['S'], $singleMinutes, 8, 'Saturday lecture block');
    }

    if (empty($patterns)) {
        add_pattern($patterns, ['M', 'W'], 90, 18, 'MW lecture pair');
        add_pattern($patterns, ['T', 'Th'], 90, 18, 'TTh lecture pair');
    }

    return array_values($patterns);
}

function build_section_day_intervals($snapshotByDay, $sectionId, $drafts, $excludeType) {
    $intervals = [];
    foreach (day_order() as $day) {
        $intervals[$day] = [];

        foreach ($snapshotByDay[$day] ?? [] as $item) {
            if ((int)$item['section_id'] !== (int)$sectionId) {
                continue;
            }

            $intervals[$day][] = [
                'start' => time_to_minutes($item['time_start']),
                'end' => time_to_minutes($item['time_end'])
            ];
        }
    }

    foreach ($drafts as $type => $draft) {
        if ($type === $excludeType || !is_complete_draft($draft)) {
            continue;
        }

        $start = time_to_minutes($draft['time_start']);
        $end = time_to_minutes($draft['time_end']);
        if ($start === null || $end === null) {
            continue;
        }

        foreach ($draft['days'] as $day) {
            $intervals[$day][] = [
                'start' => $start,
                'end' => $end
            ];
        }
    }

    foreach ($intervals as $day => $list) {
        usort($list, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });
        $intervals[$day] = $list;
    }

    return $intervals;
}

function candidate_has_conflict($candidate, $snapshotByDay, $context, $drafts, $scheduleType) {
    $seenScheduleIds = [];

    foreach ($candidate['days'] as $day) {
        foreach ($snapshotByDay[$day] ?? [] as $item) {
            $scheduleId = (int)$item['schedule_id'];
            if (isset($seenScheduleIds[$scheduleId])) {
                continue;
            }

            if (!time_overlap($candidate['time_start'], $candidate['time_end'], $item['time_start'], $item['time_end'])) {
                continue;
            }

            $seenScheduleIds[$scheduleId] = true;

            if ((int)$item['section_id'] === (int)$context['section_id']) {
                return true;
            }

            if ((int)$item['room_id'] === (int)$candidate['room_id']) {
                return true;
            }
        }
    }

    foreach ($drafts as $draftType => $draft) {
        if ($draftType === $scheduleType || !is_complete_draft($draft)) {
            continue;
        }

        if (
            days_overlap($candidate['days'], $draft['days']) &&
            time_overlap($candidate['time_start'], $candidate['time_end'], $draft['time_start'], $draft['time_end'])
        ) {
            return true;
        }
    }

    return false;
}

function room_fit_score($room, $scheduleType) {
    $score = 0;
    $roomType = normalize_room_type($room['room_type'] ?? 'lecture');
    $accessType = strtolower((string)($room['access_type'] ?? 'owner'));

    if ($scheduleType === 'LAB') {
        $score += $roomType === 'laboratory' ? 5 : 2;
    } else {
        $score += $roomType === 'lecture' ? 4 : 2;
    }

    $score += $accessType === 'owner' ? 4 : 2;
    return $score;
}

function candidate_balance_score($candidate, $sectionIntervalsByDay) {
    $score = 0;

    foreach ($candidate['days'] as $day) {
        $loadMinutes = 0;
        foreach ($sectionIntervalsByDay[$day] ?? [] as $interval) {
            $loadMinutes += max(0, ((int)$interval['end'] - (int)$interval['start']));
        }

        if ($loadMinutes === 0) {
            $score += 6;
        } elseif ($loadMinutes <= 120) {
            $score += 4;
        } elseif ($loadMinutes <= 240) {
            $score += 2;
        } else {
            $score -= 1;
        }
    }

    return $score;
}

function candidate_compactness_score($candidate, $sectionIntervalsByDay) {
    $score = 0;

    foreach ($candidate['days'] as $day) {
        $previousGap = null;
        $nextGap = null;

        foreach ($sectionIntervalsByDay[$day] ?? [] as $interval) {
            if ($interval['end'] <= $candidate['start_minutes']) {
                $gap = $candidate['start_minutes'] - (int)$interval['end'];
                $previousGap = ($previousGap === null) ? $gap : min($previousGap, $gap);
            } elseif ($interval['start'] >= $candidate['end_minutes']) {
                $gap = (int)$interval['start'] - $candidate['end_minutes'];
                $nextGap = ($nextGap === null) ? $gap : min($nextGap, $gap);
            }
        }

        foreach ([$previousGap, $nextGap] as $gap) {
            if ($gap === null) {
                continue;
            }

            if ($gap <= 30) {
                $score += 4;
            } elseif ($gap <= 60) {
                $score += 2;
            } elseif ($gap >= 180) {
                $score -= 2;
            }
        }

        if ($previousGap !== null && $nextGap !== null && $previousGap >= 90 && $nextGap >= 90) {
            $score -= 4;
        }
    }

    return $score;
}

function candidate_companion_score($candidate, $drafts, $scheduleType) {
    foreach ($drafts as $draftType => $draft) {
        if ($draftType === $scheduleType || !is_complete_draft($draft)) {
            continue;
        }

        if (!days_overlap($candidate['days'], $draft['days'])) {
            return 3;
        }

        if (time_overlap($candidate['time_start'], $candidate['time_end'], $draft['time_start'], $draft['time_end'])) {
            return -10;
        }

        return -1;
    }

    return 0;
}

function candidate_time_score($candidate) {
    $score = 0;
    $lunchStart = 12 * 60;
    $lunchEnd = 13 * 60;

    if ($candidate['start_minutes'] < (8 * 60)) {
        $score -= 3;
    }

    if ($candidate['end_minutes'] > (17 * 60)) {
        $score -= 2;
    }

    if (($candidate['start_minutes'] < $lunchEnd) && ($candidate['end_minutes'] > $lunchStart)) {
        $score -= 7;
    } else {
        $score += 2;
    }

    return $score;
}

function candidate_fit_bucket($score) {
    if ($score >= 52) {
        return ['label' => 'Best Fit', 'class' => 'best'];
    }

    if ($score >= 42) {
        return ['label' => 'Strong Fit', 'class' => 'strong'];
    }

    return ['label' => 'Valid Slot', 'class' => 'valid'];
}

function build_candidate_reasons($candidate, $room, $balanceScore, $compactnessScore, $companionScore, $timeScore) {
    $reasons = [
        'No room conflict',
        'No section conflict',
        strtoupper($candidate['schedule_type']) === 'LAB'
            ? 'Laboratory room matched'
            : 'Lecture room matched'
    ];

    if (($room['access_type'] ?? 'owner') === 'shared') {
        $reasons[] = 'Shared room is available this term';
    } else {
        $reasons[] = 'Uses an owned room';
    }

    if ($balanceScore >= 6) {
        $reasons[] = 'Placed on a lighter section day';
    }

    if ($compactnessScore >= 4) {
        $reasons[] = 'Keeps the section timetable compact';
    }

    if ($companionScore > 0) {
        $reasons[] = 'Separated from the companion schedule';
    }

    if ($timeScore >= 2) {
        $reasons[] = 'Avoids the lunch window';
    }

    return array_slice(array_values(array_unique($reasons)), 0, 4);
}

function generate_suggestions_for_type($scheduleType, $context, $rooms, $snapshotByDay, $drafts) {
    $scheduleType = strtoupper(trim((string)$scheduleType));
    $patterns = build_schedule_patterns($scheduleType, (float)$context['lec_units'], (float)$context['lab_units']);
    $sectionIntervalsByDay = build_section_day_intervals($snapshotByDay, (int)$context['section_id'], $drafts, $scheduleType);

    $candidates = [];
    $dayStart = (7 * 60) + 30;
    $dayEnd = (17 * 60) + 30;

    foreach ($patterns as $pattern) {
        $duration = (int)$pattern['minutes'];
        if ($duration <= 0 || ($dayStart + $duration) > $dayEnd) {
            continue;
        }

        foreach ($rooms as $room) {
            if (!room_type_allows_schedule($room['room_type'] ?? 'lecture', $scheduleType)) {
                continue;
            }

            for ($start = $dayStart; ($start + $duration) <= $dayEnd; $start += 30) {
                $end = $start + $duration;
                $candidate = [
                    'schedule_type' => $scheduleType,
                    'room_id' => (int)$room['room_id'],
                    'room_label' => (string)$room['label'],
                    'days' => $pattern['days'],
                    'days_label' => days_to_label($pattern['days']),
                    'time_start' => minutes_to_time($start),
                    'time_end' => minutes_to_time($end),
                    'time_start_input' => minutes_to_input_time($start),
                    'time_end_input' => minutes_to_input_time($end),
                    'time_label' => minutes_to_label($start) . ' - ' . minutes_to_label($end),
                    'start_minutes' => $start,
                    'end_minutes' => $end,
                    'pattern_label' => $pattern['label']
                ];

                if (candidate_has_conflict($candidate, $snapshotByDay, $context, $drafts, $scheduleType)) {
                    continue;
                }

                $balanceScore = candidate_balance_score($candidate, $sectionIntervalsByDay);
                $compactnessScore = candidate_compactness_score($candidate, $sectionIntervalsByDay);
                $companionScore = candidate_companion_score($candidate, $drafts, $scheduleType);
                $timeScore = candidate_time_score($candidate);
                $roomScore = room_fit_score($room, $scheduleType);

                $score = 20
                    + (int)$pattern['weight']
                    + $roomScore
                    + $balanceScore
                    + $compactnessScore
                    + $companionScore
                    + $timeScore;

                $fit = candidate_fit_bucket($score);
                $candidate['fit_label'] = $fit['label'];
                $candidate['fit_class'] = $fit['class'];
                $candidate['score'] = $score;
                $candidate['reasons'] = build_candidate_reasons(
                    $candidate,
                    $room,
                    $balanceScore,
                    $compactnessScore,
                    $companionScore,
                    $timeScore
                );

                $candidates[] = $candidate;
            }
        }
    }

    usort($candidates, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            if ($a['start_minutes'] === $b['start_minutes']) {
                return strcmp($a['room_label'], $b['room_label']);
            }
            return $a['start_minutes'] <=> $b['start_minutes'];
        }

        return $b['score'] <=> $a['score'];
    });

    $selected = [];
    $patternUsage = [];

    foreach ($candidates as $candidate) {
        $patternKey = $candidate['days_label'] . '|' . $candidate['time_start_input'] . '|' . $candidate['time_end_input'];
        $patternUsage[$patternKey] = (int)($patternUsage[$patternKey] ?? 0);

        if ($patternUsage[$patternKey] >= 2) {
            continue;
        }

        $patternUsage[$patternKey]++;
        $selected[] = [
            'schedule_type' => $candidate['schedule_type'],
            'room_id' => $candidate['room_id'],
            'room_label' => $candidate['room_label'],
            'days' => $candidate['days'],
            'days_label' => $candidate['days_label'],
            'time_start' => $candidate['time_start_input'],
            'time_end' => $candidate['time_end_input'],
            'time_label' => $candidate['time_label'],
            'pattern_label' => $candidate['pattern_label'],
            'fit_label' => $candidate['fit_label'],
            'fit_class' => $candidate['fit_class'],
            'reasons' => $candidate['reasons']
        ];

        if (count($selected) >= 8) {
            break;
        }
    }

    return $selected;
}

$context = load_offering_context($conn, $offering_id, $college_id);
if (!$context) {
    respond('error', 'Offering is out of sync. Re-run Generate Offerings first.');
}

if (!room_access_table_exists($conn)) {
    respond('error', 'Room access table is missing.');
}

$ay_id = (int)$context['ay_id'];
$semester = (int)$context['semester'];
$rooms = load_accessible_rooms($conn, $college_id, $ay_id, $semester);

if (empty($rooms)) {
    respond('ok', 'No accessible rooms found for this term.', [
        'required_types' => ((float)$context['lab_units'] > 0) ? ['LEC', 'LAB'] : ['LEC'],
        'suggestions' => [
            'LEC' => [],
            'LAB' => []
        ]
    ]);
}

$drafts = normalize_draft_schedules($_POST['drafts_json'] ?? '');
$snapshotByDay = load_term_schedule_snapshot_by_day($conn, $ay_id, $semester, $offering_id);
$requiredTypes = ((float)$context['lab_units'] > 0) ? ['LEC', 'LAB'] : ['LEC'];

$suggestions = [
    'LEC' => [],
    'LAB' => []
];

foreach ($requiredTypes as $type) {
    $suggestions[$type] = generate_suggestions_for_type($type, $context, $rooms, $snapshotByDay, $drafts);
}

respond('ok', 'Suggestions loaded.', [
    'required_types' => $requiredTypes,
    'offering' => [
        'offering_id' => (int)$context['offering_id'],
        'subject_code' => (string)$context['sub_code'],
        'subject_description' => (string)$context['sub_description'],
        'section_name' => (string)$context['section_name'],
        'lec_units' => (float)$context['lec_units'],
        'lab_units' => (float)$context['lab_units']
    ],
    'suggestions' => $suggestions
]);
