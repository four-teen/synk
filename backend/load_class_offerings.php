<?php
session_start();
include 'db.php';

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
$ay_id         = $_POST['ay_id'] ?? '';
$semester      = $_POST['semester'] ?? '';

if ($prospectus_id === '' || $ay_id === '' || $semester === '') {
    echo "<div class='text-center text-danger py-4'>Missing filters.</div>";
    exit;
}

$college_id = (int)($_SESSION['college_id'] ?? 0);
if ($college_id <= 0) {
    echo "<div class='text-center text-danger py-4'>Missing college context.</div>";
    exit;
}

$sql = "
SELECT
    o.offering_id,
    sec.section_name,
    sec.year_level,
    sm.sub_code,
    sm.sub_description,
    ps.lab_units,
    cs.schedule_id,
    cs.room_id,
    cs.days_json,
    cs.time_start,
    cs.time_end,
    r.room_name
FROM tbl_prospectus_offering o
INNER JOIN tbl_program p ON p.program_id = o.program_id
INNER JOIN tbl_sections sec ON sec.section_id = o.section_id
INNER JOIN tbl_prospectus_subjects ps ON ps.ps_id = o.ps_id
INNER JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_class_schedule cs
    ON cs.offering_id = o.offering_id
   AND cs.schedule_type = 'LEC'
LEFT JOIN tbl_rooms r ON r.room_id = cs.room_id
WHERE o.prospectus_id = ?
  AND o.ay_id = ?
  AND o.semester = ?
  AND p.college_id = ?
ORDER BY sec.year_level ASC, sec.section_name ASC, sm.sub_code ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $prospectus_id, $ay_id, $semester, $college_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<div class='text-center text-muted py-4'>No class offerings found.</div>";
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

$grouped = [];
while ($row = $res->fetch_assoc()) {
    $grouped[(int)$row['year_level']][] = $row;
}
ksort($grouped);

foreach ($grouped as $yearLevel => $rows) {
    echo "<div class='schedule-group-card mb-3'>";
    echo "  <div class='schedule-group-header px-3 py-2 d-flex justify-content-between align-items-center'>";
    echo "    <span>" . htmlspecialchars(year_label($yearLevel)) . "</span>";
    echo "    <span class='badge bg-label-primary'>" . count($rows) . " class(es)</span>";
    echo "  </div>";
    echo "  <div class='table-responsive'>";
    echo "    <table class='table table-bordered table-hover mb-0'>";
    echo "      <thead>";
    echo "        <tr>";
    echo "          <th>Section</th>";
    echo "          <th>Subject</th>";
    echo "          <th>Description</th>";
    echo "          <th>Days</th>";
    echo "          <th>Time</th>";
    echo "          <th>Room</th>";
    echo "          <th>Status</th>";
    echo "          <th>Action</th>";
    echo "        </tr>";
    echo "      </thead>";
    echo "      <tbody>";

    foreach ($rows as $row) {
        $isScheduled = !empty($row['schedule_id']);
        $statusBadge = $isScheduled
            ? "<span class='badge bg-success'>Scheduled</span>"
            : "<span class='badge bg-secondary'>Not Scheduled</span>";

        $daysText = "-";
        if (!empty($row['days_json'])) {
            $d = json_decode($row['days_json'], true);
            if (is_array($d) && !empty($d)) {
                $daysText = implode('', $d);
            }
        }

        $timeText = "<span class='text-muted'>-</span>";
        if (!empty($row['time_start']) && !empty($row['time_end'])) {
            $startLabel = htmlspecialchars(date('h:i A', strtotime($row['time_start'])));
            $endLabel = htmlspecialchars(date('h:i A', strtotime($row['time_end'])));
            $timeText = "
                <span class='d-block text-nowrap'><i class='bx bx-play-circle text-success me-1'></i>{$startLabel}</span>
                <span class='d-block text-nowrap'><i class='bx bx-stop-circle text-danger me-1'></i>{$endLabel}</span>
            ";
        }

        $roomText = !empty($row['room_name']) ? $row['room_name'] : '-';

        $btnLabel = $isScheduled ? 'Edit' : 'Schedule';
        $btnClass = $isScheduled ? 'btn-warning' : 'btn-primary';

        echo "<tr>";
        echo "  <td>" . htmlspecialchars((string)$row['section_name']) . "</td>";
        echo "  <td class='text-nowrap'>" . htmlspecialchars(strtoupper((string)$row['sub_code'])) . "</td>";
        echo "  <td>" . htmlspecialchars(strtoupper((string)$row['sub_description'])) . "</td>";
        echo "  <td class='text-center'>" . htmlspecialchars($daysText) . "</td>";
        echo "  <td class='text-center'>{$timeText}</td>";
        echo "  <td class='text-center'>" . htmlspecialchars($roomText) . "</td>";
        echo "  <td class='text-center'>{$statusBadge}</td>";
        echo "  <td class='text-center'>";
        echo "    <button class='btn {$btnClass} btn-sm btn-schedule'";
        echo "      data-offering-id='" . (int)$row['offering_id'] . "'";
        echo "      data-sub-code='" . htmlspecialchars((string)$row['sub_code'], ENT_QUOTES) . "'";
        echo "      data-sub-desc='" . htmlspecialchars((string)$row['sub_description'], ENT_QUOTES) . "'";
        echo "      data-section='" . htmlspecialchars((string)$row['section_name'], ENT_QUOTES) . "'";
        echo "      data-lab-units='" . (int)$row['lab_units'] . "'";
        echo "      data-room-id='" . (int)($row['room_id'] ?? 0) . "'";
        echo "      data-time-start='" . htmlspecialchars((string)($row['time_start'] ?? ''), ENT_QUOTES) . "'";
        echo "      data-time-end='" . htmlspecialchars((string)($row['time_end'] ?? ''), ENT_QUOTES) . "'";
        echo "      data-days-json='" . htmlspecialchars((string)($row['days_json'] ?? ''), ENT_QUOTES) . "'>";
        echo "      {$btnLabel}";
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
