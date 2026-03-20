<?php
session_start();
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    echo "<div class='text-center text-danger py-4'>Unauthorized access.</div>";
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo "<div class='text-center text-danger py-4'>CSRF validation failed.</div>";
    exit;
}

$prospectus_id = $_POST['prospectus_id'] ?? '';
$ay_id = $_POST['ay_id'] ?? '';
$semester = $_POST['semester'] ?? '';
$sortBy = trim((string)($_POST['sort_by'] ?? 'year_level'));
$sortBy = in_array($sortBy, ['year_level', 'subject'], true) ? $sortBy : 'year_level';

if ($prospectus_id === '' || $ay_id === '' || $semester === '') {
    echo "<div class='text-center text-danger py-4'>Missing filters.</div>";
    exit;
}

$college_id = (int)($_SESSION['college_id'] ?? 0);
if ($college_id <= 0) {
    echo "<div class='text-center text-danger py-4'>Missing college context.</div>";
    exit;
}

function year_label($year) {
    $year = (string)$year;
    if ($year === '1') return '1st Year';
    if ($year === '2') return '2nd Year';
    if ($year === '3') return '3rd Year';
    if ($year === '4') return '4th Year';
    if ($year === '5') return '5th Year';
    if ($year === '6') return '6th Year';
    return 'Year ' . $year;
}

function schedule_group_count_label(string $sortBy): string
{
    return $sortBy === 'subject' ? 'section(s)' : 'class(es)';
}

function schedule_group_title(string $sortBy, array $row): string
{
    if ($sortBy === 'subject') {
        return strtoupper(trim((string)$row['sub_code'])) . ' - ' . strtoupper(trim((string)$row['sub_description']));
    }

    return year_label((string)($row['year_level'] ?? ''));
}

function schedule_section_cell_html(array $row, string $sortBy): string
{
    $section = htmlspecialchars((string)($row['section_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($sortBy !== 'subject') {
        return "<span class='schedule-section-name'>{$section}</span>";
    }

    $yearMeta = htmlspecialchars(year_label((string)($row['year_level'] ?? '')), ENT_QUOTES, 'UTF-8');
    return
        "<span class='schedule-section-name'>{$section}</span>" .
        "<span class='schedule-section-meta'>{$yearMeta}</span>";
}

function block_badge_html($type, $sequence) {
    $type = synk_normalize_schedule_type((string)$type);
    $badgeClass = $type === 'LAB'
        ? 'bg-label-success text-success'
        : 'bg-label-primary text-primary';
    $prefix = $type === 'LAB' ? 'LAB' : 'LEC';
    return "<span class='badge {$badgeClass} me-1'>{$prefix} {$sequence}</span>";
}

function contact_hours_value($hours) {
    $value = (float)$hours;
    return floor($value) == $value
        ? number_format($value, 0)
        : number_format($value, 1);
}

function normalize_prospectus_contact_hours(float $lecHours, float $labValue, ?float $storedTotalUnits = null): array
{
    $safeLecHours = max(0.0, $lecHours);
    $safeLabValue = max(0.0, $labValue);
    $safeTotalUnits = (float)($storedTotalUnits ?? 0.0);
    $labContactHours = synk_lab_contact_hours($safeLecHours, $safeLabValue, $safeTotalUnits);

    return [
        'lec_units' => round($safeLecHours, 2),
        'lab_units' => round($labContactHours, 2),
        'total_units' => round(synk_subject_units_total($safeLecHours, $labContactHours, 0.0), 2)
    ];
}

function schedule_stack_item_html($badgeHtml, $value, $valueClass = '') {
    $className = 'schedule-stack-value';
    if ($valueClass !== '') {
        $className .= ' ' . trim($valueClass);
    }

    return
        "<div class='schedule-stack-item'>" .
            "<div class='schedule-stack-badge'>{$badgeHtml}</div>" .
            "<div class='{$className}'>" . htmlspecialchars((string)$value) . "</div>" .
        "</div>";
}

function schedule_room_stack_item_html($badgeHtml, $value, $roomId, $scheduleType, $offeringId) {
    $safeRoomId = (int)$roomId;
    $safeType = htmlspecialchars((string)$scheduleType, ENT_QUOTES);
    $safeOfferingId = (int)$offeringId;

    return
        "<div class='schedule-stack-item schedule-room-entry' data-room-id='{$safeRoomId}' data-schedule-type='{$safeType}' data-offering-id='{$safeOfferingId}'>" .
            "<div class='schedule-stack-badge'>{$badgeHtml}</div>" .
            "<div class='schedule-stack-value is-room'>" . htmlspecialchars((string)$value) . "</div>" .
        "</div>";
}

function schedule_stack_empty_html() {
    return "<div class='schedule-stack-item empty'><div class='schedule-stack-value text-muted'>-</div></div>";
}

function offering_schedule_status_badge(array $row): array
{
    $required = synk_required_minutes_by_type(
        (float)$row['lec_units'],
        (float)$row['lab_units'],
        (float)$row['total_units']
    );
    $scheduled = synk_sum_scheduled_minutes_by_type($row['entries']);
    $requiredTypes = ((float)$row['lab_units'] > 0) ? ['LEC', 'LAB'] : ['LEC'];
    $hasAny = false;
    $isComplete = true;

    foreach ($requiredTypes as $type) {
        $requiredMinutes = (int)($required[$type] ?? 0);
        $scheduledMinutes = (int)($scheduled[$type] ?? 0);

        if ($scheduledMinutes > 0) {
            $hasAny = true;
        }

        if ($requiredMinutes > 0 && $scheduledMinutes < $requiredMinutes) {
            $isComplete = false;
        }
    }

    if (!$hasAny) {
        return [
            'badge' => "<span class='badge bg-secondary'>Not Scheduled</span>",
            'button_label' => 'Schedule',
            'button_class' => 'btn-primary'
        ];
    }

    if ($isComplete) {
        return [
            'badge' => "<span class='badge bg-success'>Scheduled</span>",
            'button_label' => 'Edit',
            'button_class' => 'btn-warning'
        ];
    }

    return [
        'badge' => "<span class='badge bg-warning text-dark'>Incomplete</span>",
        'button_label' => 'Edit',
        'button_class' => 'btn-warning'
    ];
}

$liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
$orderByClause = $sortBy === 'subject'
    ? "
ORDER BY
    sm.sub_code ASC,
    sm.sub_description ASC,
    sec.year_level ASC,
    sec.section_name ASC,
    FIELD(cs.schedule_type, 'LEC', 'LAB'),
    cs.schedule_id ASC
"
    : "
ORDER BY
    sec.year_level ASC,
    sec.section_name ASC,
    sm.sub_code ASC,
    FIELD(cs.schedule_type, 'LEC', 'LAB'),
    cs.schedule_id ASC
";

$sql = "
SELECT
    o.offering_id,
    sec.section_name,
    sec.year_level,
    sm.sub_code,
    sm.sub_description,
    ps.lab_units,
    ps.lec_units,
    ps.total_units,
    cs.schedule_id,
    cs.schedule_type,
    cs.room_id,
    cs.days_json,
    cs.time_start,
    cs.time_end,
    r.room_name,
    r.room_code
FROM tbl_prospectus_offering o
{$liveOfferingJoins}
INNER JOIN tbl_program p ON p.program_id = o.program_id
INNER JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_class_schedule cs
    ON cs.offering_id = o.offering_id
   AND cs.schedule_type IN ('LEC', 'LAB')
LEFT JOIN tbl_rooms r ON r.room_id = cs.room_id
WHERE o.prospectus_id = ?
  AND o.ay_id = ?
  AND o.semester = ?
  AND p.college_id = ?
" . $orderByClause . "
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $prospectus_id, $ay_id, $semester, $college_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<div class='text-center text-muted py-4'>No class offerings found.</div>";
    exit;
}

$offeringsById = [];
$offeringOrder = [];
while ($row = $res->fetch_assoc()) {
    $offeringId = (int)$row['offering_id'];

    if (!isset($offeringsById[$offeringId])) {
        $displayHours = normalize_prospectus_contact_hours(
            (float)$row['lec_units'],
            (float)$row['lab_units'],
            isset($row['total_units']) ? (float)$row['total_units'] : null
        );

        $offeringsById[$offeringId] = [
            'offering_id' => $offeringId,
            'year_level' => (int)$row['year_level'],
            'section_name' => (string)$row['section_name'],
            'sub_code' => (string)$row['sub_code'],
            'sub_description' => (string)$row['sub_description'],
            'lab_units' => (float)$row['lab_units'],
            'lec_units' => (float)$row['lec_units'],
            'total_units' => (float)$row['total_units'],
            'display_lab_units' => (float)$displayHours['lab_units'],
            'display_lec_units' => (float)$displayHours['lec_units'],
            'entries' => []
        ];
        $offeringOrder[] = $offeringId;
    }

    $scheduleType = strtoupper(trim((string)($row['schedule_type'] ?? '')));
    if (!empty($row['schedule_id']) && in_array($scheduleType, ['LEC', 'LAB'], true)) {
        $roomLabel = trim((string)($row['room_code'] ?? ''));
        if ($roomLabel === '') {
            $roomLabel = trim((string)($row['room_name'] ?? ''));
        }

        $days = synk_normalize_schedule_days(json_decode((string)($row['days_json'] ?? ''), true));

        $offeringsById[$offeringId]['entries'][] = [
            'schedule_id' => (int)$row['schedule_id'],
            'schedule_type' => synk_normalize_schedule_type($scheduleType),
            'room_id' => (int)($row['room_id'] ?? 0),
            'days_json' => json_encode($days),
            'days' => $days,
            'time_start' => (string)($row['time_start'] ?? ''),
            'time_end' => (string)($row['time_end'] ?? ''),
            'room_label' => $roomLabel
        ];
    }
}

$grouped = [];
foreach ($offeringOrder as $offeringId) {
    $offering = $offeringsById[$offeringId];
    $groupKey = $sortBy === 'subject'
        ? strtoupper((string)$offering['sub_code']) . '|' . strtoupper((string)$offering['sub_description'])
        : (string)(int)$offering['year_level'];

    if (!isset($grouped[$groupKey])) {
        $grouped[$groupKey] = [];
    }

    $grouped[$groupKey][] = $offering;
}

foreach ($grouped as $groupKey => $offerings) {
    $groupTitle = schedule_group_title($sortBy, $offerings[0]);
    $countLabel = schedule_group_count_label($sortBy);

    echo "<div class='schedule-group-card mb-3'>";
    echo "  <div class='schedule-group-header px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2'>";
    echo "    <span>" . htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "    <span class='badge bg-label-primary schedule-group-count' data-total-count='" . count($offerings) . "' data-count-label='" . htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') . "'>" . count($offerings) . " " . htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "  </div>";
    echo "  <div class='table-responsive schedule-pan-shell'>";
    echo "    <table class='table table-bordered table-hover mb-0 schedule-offerings-table'>";
    echo "      <thead>";
    echo "        <tr>";
    echo "          <th class='schedule-section-col'>Section</th>";
    echo "          <th class='schedule-subject-col'>Subject</th>";
    echo "          <th class='schedule-description-col'>Description</th>";
    echo "          <th class='text-center text-nowrap schedule-hours-col'>LEC Hrs</th>";
    echo "          <th class='text-center text-nowrap schedule-hours-col'>LAB Hrs</th>";
    echo "          <th class='text-center schedule-days-col'>Days</th>";
    echo "          <th class='text-center schedule-time-col'>Time</th>";
    echo "          <th class='text-center schedule-room-col'>Room</th>";
    echo "          <th class='text-center schedule-status-col'>Status</th>";
    echo "          <th class='text-center schedule-action-col'>Action</th>";
    echo "        </tr>";
    echo "      </thead>";
    echo "      <tbody>";

    foreach ($offerings as $row) {
        $status = offering_schedule_status_badge($row);
        $daysParts = [];
        $timeParts = [];
        $roomParts = [];
        $sequenceByType = ['LEC' => 0, 'LAB' => 0];

        foreach ($row['entries'] as $entry) {
            $type = synk_normalize_schedule_type((string)$entry['schedule_type']);
            $sequenceByType[$type]++;
            $label = block_badge_html($type, $sequenceByType[$type]);
            $daysText = !empty($entry['days']) ? implode('', $entry['days']) : '-';
            $timeText = ($entry['time_start'] !== '' && $entry['time_end'] !== '')
                ? date('h:i A', strtotime($entry['time_start'])) . " - " . date('h:i A', strtotime($entry['time_end']))
                : '-';
            $roomText = $entry['room_label'] !== ''
                ? $entry['room_label']
                : '-';

            $daysParts[] = schedule_stack_item_html($label, $daysText, 'is-days');
            $timeParts[] = schedule_stack_item_html($label, $timeText, 'is-time');
            $roomParts[] = schedule_room_stack_item_html(
                $label,
                $roomText,
                (int)$entry['room_id'],
                $type,
                (int)$row['offering_id']
            );
        }

        if (empty($daysParts)) {
            $daysParts[] = schedule_stack_empty_html();
            $timeParts[] = schedule_stack_empty_html();
            $roomParts[] = schedule_stack_empty_html();
        }

        $searchText = strtolower(trim(
            (string)$row['section_name'] . ' ' .
            (string)$row['sub_code'] . ' ' .
            (string)$row['sub_description'] . ' ' .
            year_label((string)$row['year_level'])
        ));

        echo "<tr class='schedule-offering-row' data-search-text='" . htmlspecialchars($searchText, ENT_QUOTES) . "'>";
        echo "  <td class='schedule-section-col'>" . schedule_section_cell_html($row, $sortBy) . "</td>";
        echo "  <td class='text-nowrap schedule-subject-col'>" . htmlspecialchars(strtoupper((string)$row['sub_code'])) . "</td>";
        echo "  <td class='schedule-description-col'>" . htmlspecialchars(strtoupper((string)$row['sub_description'])) . "</td>";
        echo "  <td class='text-center fw-semibold schedule-hours-col'>" . htmlspecialchars(contact_hours_value($row['display_lec_units'])) . "</td>";
        echo "  <td class='text-center fw-semibold schedule-hours-col'>" . htmlspecialchars(contact_hours_value($row['display_lab_units'])) . "</td>";
        echo "  <td class='text-center schedule-days-col'>" . implode('', $daysParts) . "</td>";
        echo "  <td class='text-center schedule-time-col'>" . implode('', $timeParts) . "</td>";
        echo "  <td class='text-center schedule-room-col'>" . implode('', $roomParts) . "</td>";
        echo "  <td class='text-center schedule-status-col'>{$status['badge']}</td>";
        echo "  <td class='text-center schedule-action-col'>";
        echo "    <button type='button' class='btn {$status['button_class']} btn-sm btn-schedule'";
        echo "      data-offering-id='" . (int)$row['offering_id'] . "'";
        echo "      data-sub-code='" . htmlspecialchars((string)$row['sub_code'], ENT_QUOTES) . "'";
        echo "      data-sub-desc='" . htmlspecialchars((string)$row['sub_description'], ENT_QUOTES) . "'";
        echo "      data-section='" . htmlspecialchars((string)$row['section_name'], ENT_QUOTES) . "'";
        echo "      data-lab-units='" . (float)$row['lab_units'] . "'";
        echo "      data-lec-units='" . (float)$row['lec_units'] . "'";
        echo "      data-total-units='" . (float)$row['total_units'] . "'>";
        echo "      {$status['button_label']}";
        echo "    </button>";
        echo "  </td>";
        echo "</tr>";
    }

    echo "      </tbody>";
    echo "    </table>";
    echo "  </div>";
    echo "</div>";
}
?>
