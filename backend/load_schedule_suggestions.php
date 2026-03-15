<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';
require_once __DIR__ . '/academic_schedule_policy_helper.php';

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
$target_type = strtoupper(trim((string)($_POST['target_type'] ?? 'LEC')));
$target_key = trim((string)($_POST['target_key'] ?? ''));

if ($college_id <= 0 || $offering_id <= 0 || !in_array($target_type, ['LEC', 'LAB'], true)) {
    respond('error', 'Missing scheduling context.');
}

$schedulePolicy = synk_fetch_effective_schedule_policy($conn, $college_id);

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
        if ($normalized !== '') {
            $seen[$normalized] = true;
        }
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
    return in_array($value, ['lecture', 'laboratory', 'lec_lab'], true) ? $value : 'lecture';
}

function room_type_allows_schedule($roomType, $scheduleType) {
    $roomType = normalize_room_type($roomType);
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
        $label .= $ownerCode !== '' ? " (Shared from {$ownerCode})" : ' (Shared)';
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
            o.section_id
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

    $normalized = [];
    $rawList = $draftsRaw;

    $isAssoc = array_keys($draftsRaw) !== range(0, count($draftsRaw) - 1);
    if ($isAssoc && (isset($draftsRaw['LEC']) || isset($draftsRaw['LAB']))) {
        $rawList = [];
        foreach (['LEC', 'LAB'] as $legacyType) {
            if (isset($draftsRaw[$legacyType]) && is_array($draftsRaw[$legacyType])) {
                $rawList[] = array_merge($draftsRaw[$legacyType], ['type' => $legacyType]);
            }
        }
    }

    foreach ($rawList as $index => $raw) {
        if (!is_array($raw)) {
            continue;
        }

        $type = strtoupper(trim((string)($raw['type'] ?? '')));
        if (!in_array($type, ['LEC', 'LAB'], true)) {
            continue;
        }

        $days = [];
        if (isset($raw['days']) && is_array($raw['days'])) {
            $days = normalize_days_array($raw['days']);
        } elseif (!empty($raw['days_json'])) {
            $days = normalize_days_array(json_decode((string)$raw['days_json'], true));
        }

        $normalized[] = [
            'block_key' => trim((string)($raw['block_key'] ?? $raw['schedule_id'] ?? "{$type}-{$index}")),
            'schedule_id' => (int)($raw['schedule_id'] ?? 0),
            'type' => $type,
            'room_id' => (int)($raw['room_id'] ?? 0),
            'time_start' => trim((string)($raw['time_start'] ?? '')),
            'time_end' => trim((string)($raw['time_end'] ?? '')),
            'days' => $days
        ];
    }

    return $normalized;
}

function is_complete_draft($draft) {
    return is_array($draft)
        && !empty($draft['type'])
        && !empty($draft['time_start'])
        && !empty($draft['time_end'])
        && !empty($draft['days'])
        && is_array($draft['days'])
        && $draft['time_end'] > $draft['time_start'];
}

function desired_weekly_minutes($targetType, $targetDraft, $allDrafts, $context) {
    if (is_complete_draft($targetDraft)) {
        return synk_schedule_weekly_minutes($targetDraft['days'], $targetDraft['time_start'], $targetDraft['time_end']);
    }

    $required = synk_required_minutes_by_type(
        (float)$context['lec_units'],
        (float)$context['lab_units'],
        (float)$context['total_units']
    );

    $scheduledSameType = 0;
    foreach ($allDrafts as $draft) {
        if (!is_complete_draft($draft)) {
            continue;
        }
        if (($draft['block_key'] ?? '') === ($targetDraft['block_key'] ?? '')) {
            continue;
        }
        if (($draft['type'] ?? '') !== $targetType) {
            continue;
        }

        $scheduledSameType += synk_schedule_weekly_minutes($draft['days'], $draft['time_start'], $draft['time_end']);
    }

    $remaining = max(60, (int)($required[$targetType] ?? 0) - $scheduledSameType);
    return ceil_to_slot($remaining, 30);
}

function add_pattern(&$patterns, $days, $weeklyMinutes, $weight, $label) {
    $days = normalize_days_array($days);
    $meetingCount = count($days);
    if ($meetingCount <= 0) {
        return;
    }

    $perMeetingMinutes = ceil_to_slot($weeklyMinutes / $meetingCount, 30);
    if ($perMeetingMinutes <= 0) {
        return;
    }

    $key = implode('-', $days) . '|' . $perMeetingMinutes;
    if (isset($patterns[$key])) {
        if ($weight > $patterns[$key]['weight']) {
            $patterns[$key]['weight'] = $weight;
            $patterns[$key]['label'] = $label;
        }
        return;
    }

    $patterns[$key] = [
        'days' => $days,
        'meeting_minutes' => $perMeetingMinutes,
        'weight' => $weight,
        'label' => $label
    ];
}

function build_schedule_patterns($scheduleType, $weeklyMinutes) {
    $scheduleType = strtoupper(trim((string)$scheduleType));
    $weeklyMinutes = max(60, ceil_to_slot($weeklyMinutes, 30));
    $patterns = [];

    if ($scheduleType === 'LAB') {
        add_pattern($patterns, ['T', 'Th'], $weeklyMinutes, 28, 'TTh lab split');
        add_pattern($patterns, ['M', 'W'], $weeklyMinutes, 26, 'MW lab split');
        add_pattern($patterns, ['F', 'S'], $weeklyMinutes, 18, 'FS lab split');
        add_pattern($patterns, ['S'], $weeklyMinutes, 14, 'Saturday lab block');
        add_pattern($patterns, ['F'], $weeklyMinutes, 12, 'Friday lab block');
        add_pattern($patterns, ['T'], $weeklyMinutes, 10, 'Tuesday lab block');
        add_pattern($patterns, ['Th'], $weeklyMinutes, 10, 'Thursday lab block');
        return array_values($patterns);
    }

    add_pattern($patterns, ['M', 'W'], $weeklyMinutes, 30, 'MW lecture split');
    add_pattern($patterns, ['T', 'Th'], $weeklyMinutes, 30, 'TTh lecture split');
    add_pattern($patterns, ['M', 'W', 'F'], $weeklyMinutes, 24, 'MWF lecture spread');
    add_pattern($patterns, ['T', 'Th', 'S'], $weeklyMinutes, 18, 'TThS lecture spread');
    add_pattern($patterns, ['M'], $weeklyMinutes, 14, 'Monday lecture block');
    add_pattern($patterns, ['T'], $weeklyMinutes, 14, 'Tuesday lecture block');
    add_pattern($patterns, ['W'], $weeklyMinutes, 14, 'Wednesday lecture block');
    add_pattern($patterns, ['Th'], $weeklyMinutes, 14, 'Thursday lecture block');
    add_pattern($patterns, ['F'], $weeklyMinutes, 12, 'Friday lecture block');
    add_pattern($patterns, ['S'], $weeklyMinutes, 8, 'Saturday lecture block');

    return array_values($patterns);
}

function candidate_has_conflict($candidate, $snapshotByDay, $context, $drafts, $targetKey) {
    foreach ($candidate['days'] as $day) {
        foreach ($snapshotByDay[$day] ?? [] as $item) {
            if (!time_overlap($candidate['time_start'], $candidate['time_end'], $item['time_start'], $item['time_end'])) {
                continue;
            }

            if ((int)$item['section_id'] === (int)$context['section_id']) {
                return true;
            }

            if ((int)$item['room_id'] === (int)$candidate['room_id']) {
                return true;
            }
        }
    }

    foreach ($drafts as $draft) {
        if (!is_complete_draft($draft)) {
            continue;
        }

        if (($draft['block_key'] ?? '') === $targetKey) {
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
    $roomType = normalize_room_type($room['room_type'] ?? 'lecture');
    $score = 0;

    if ($scheduleType === 'LAB') {
        $score += $roomType === 'laboratory' ? 8 : 4;
    } else {
        $score += $roomType === 'lecture' ? 7 : 4;
    }

    $score += strtolower((string)($room['access_type'] ?? 'owner')) === 'owner' ? 3 : 1;
    return $score;
}

function companion_spacing_score($candidate, $drafts, $targetKey) {
    $score = 0;

    foreach ($drafts as $draft) {
        if (!is_complete_draft($draft) || ($draft['block_key'] ?? '') === $targetKey) {
            continue;
        }

        if (!days_overlap($candidate['days'], $draft['days'])) {
            $score += 2;
            continue;
        }

        if (time_overlap($candidate['time_start'], $candidate['time_end'], $draft['time_start'], $draft['time_end'])) {
            return -20;
        }

        $candidateStart = time_to_minutes($candidate['time_start']);
        $draftEnd = time_to_minutes($draft['time_end']);
        if ($candidateStart !== null && $draftEnd !== null) {
            $gap = abs($candidateStart - $draftEnd);
            if ($gap <= 60) {
                $score += 3;
            }
        }
    }

    return $score;
}

function time_score($candidate) {
    $start = time_to_minutes($candidate['time_start']);
    $end = time_to_minutes($candidate['time_end']);
    if ($start === null || $end === null) {
        return 0;
    }

    $score = 0;
    if ($start >= 450 && $start < 720) {
        $score += 2;
    }
    if ($end <= 1020) {
        $score += 2;
    }
    if (($start < 780) && ($end > 720)) {
        $score -= 6;
    }
    return $score;
}

function fit_bucket($score) {
    if ($score >= 44) {
        return ['label' => 'Best Fit', 'class' => 'best'];
    }
    if ($score >= 34) {
        return ['label' => 'Strong Fit', 'class' => 'strong'];
    }
    return ['label' => 'Valid Slot', 'class' => 'valid'];
}

function build_reasons($candidate, $room, $scheduleType) {
    $reasons = [
        'No room conflict',
        'No section conflict',
        $scheduleType === 'LAB' ? 'Laboratory-compatible room' : 'Lecture-compatible room'
    ];

    if (($room['access_type'] ?? 'owner') === 'shared') {
        $reasons[] = 'Shared room is available';
    } else {
        $reasons[] = 'Owned room is available';
    }

    return array_slice($reasons, 0, 4);
}

$context = load_offering_context($conn, $offering_id, $college_id);
if (!$context) {
    respond('error', 'Offering is out of sync. Re-run Generate Offerings first.');
}

$allowedTypes = ((float)$context['lab_units'] > 0) ? ['LEC', 'LAB'] : ['LEC'];
if (!in_array($target_type, $allowedTypes, true)) {
    respond('error', 'This subject does not allow the selected schedule type.');
}

if (!room_access_table_exists($conn)) {
    respond('error', 'Room access table is missing.');
}

$ay_id = (int)$context['ay_id'];
$semester = (int)$context['semester'];
$rooms = load_accessible_rooms($conn, $college_id, $ay_id, $semester);

if (empty($rooms)) {
    respond('ok', 'No accessible rooms found for this term.', [
        'target_type' => $target_type,
        'desired_minutes' => 0,
        'suggestions' => []
    ]);
}

$drafts = normalize_draft_schedules($_POST['drafts_json'] ?? '');
$snapshotByDay = load_term_schedule_snapshot_by_day($conn, $ay_id, $semester, $offering_id);
$targetDraft = [
    'block_key' => $target_key,
    'type' => $target_type
];

foreach ($drafts as $draft) {
    if (($draft['block_key'] ?? '') === $target_key) {
        $targetDraft = $draft;
        break;
    }
}

$desiredMinutes = desired_weekly_minutes($target_type, $targetDraft, $drafts, $context);
$patterns = build_schedule_patterns($target_type, $desiredMinutes);
$patterns = array_values(array_filter($patterns, static function (array $pattern) use ($schedulePolicy): bool {
    return empty(array_intersect($pattern['days'] ?? [], $schedulePolicy['blocked_days'] ?? []));
}));
$candidates = [];
$dayStart = time_to_minutes((string)($schedulePolicy['day_start'] ?? '07:30:00'));
$dayEnd = time_to_minutes((string)($schedulePolicy['day_end'] ?? '17:30:00'));

foreach ($patterns as $pattern) {
    $meetingMinutes = (int)($pattern['meeting_minutes'] ?? 0);
    if ($meetingMinutes <= 0 || ($dayStart + $meetingMinutes) > $dayEnd) {
        continue;
    }

    foreach ($rooms as $room) {
        if (!room_type_allows_schedule($room['room_type'] ?? 'lecture', $target_type)) {
            continue;
        }

        for ($start = $dayStart; ($start + $meetingMinutes) <= $dayEnd; $start += 30) {
            $end = $start + $meetingMinutes;
            $candidate = [
                'schedule_type' => $target_type,
                'room_id' => (int)$room['room_id'],
                'room_label' => (string)$room['label'],
                'days' => $pattern['days'],
                'days_label' => days_to_label($pattern['days']),
                'time_start' => minutes_to_time($start),
                'time_end' => minutes_to_time($end),
                'time_start_input' => minutes_to_input_time($start),
                'time_end_input' => minutes_to_input_time($end),
                'time_label' => minutes_to_label($start) . ' - ' . minutes_to_label($end),
                'pattern_label' => $pattern['label']
            ];

            if (synk_schedule_policy_blocked_time_overlap($candidate['time_start'], $candidate['time_end'], $schedulePolicy) !== null) {
                continue;
            }

            if (candidate_has_conflict($candidate, $snapshotByDay, $context, $drafts, $target_key)) {
                continue;
            }

            $score = 20
                + (int)($pattern['weight'] ?? 0)
                + room_fit_score($room, $target_type)
                + companion_spacing_score($candidate, $drafts, $target_key)
                + time_score($candidate);

            $fit = fit_bucket($score);
            $candidate['fit_label'] = $fit['label'];
            $candidate['fit_class'] = $fit['class'];
            $candidate['score'] = $score;
            $candidate['reasons'] = build_reasons($candidate, $room, $target_type);
            $candidates[] = $candidate;
        }
    }
}

usort($candidates, function ($a, $b) {
    if ($a['score'] === $b['score']) {
        if ($a['time_start'] === $b['time_start']) {
            return strcmp($a['room_label'], $b['room_label']);
        }
        return strcmp($a['time_start'], $b['time_start']);
    }
    return $b['score'] <=> $a['score'];
});

$selected = [];
$seen = [];
foreach ($candidates as $candidate) {
    $signature = implode('-', $candidate['days']) . '|' . $candidate['time_start'] . '|' . $candidate['room_id'];
    if (isset($seen[$signature])) {
        continue;
    }
    $seen[$signature] = true;
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

respond('ok', 'Suggestions loaded.', [
    'target_type' => $target_type,
    'target_key' => $target_key,
    'desired_minutes' => $desiredMinutes,
    'suggestions' => $selected
]);
