<?php
session_start();
ob_start();
include 'db.php';

$pid      = intval($_POST['prospectus_id'] ?? 0);
$ay_id    = intval($_POST['ay'] ?? 0);          // ay_id (number)
$semester = trim($_POST['semester'] ?? '');     // 1,2,3

if (!$pid || !$ay_id || $semester === '') {
    echo "<tr><td colspan='10' class='text-center text-muted'>Missing filters.</td></tr>";
    exit;
}

/**
 * Get actual AY string (e.g., 2024-2025) from ay_id
 * so we can show it later if needed.
 */
$ay = '';
$ayStmt = $conn->prepare("SELECT ay FROM tbl_academic_years WHERE ay_id = ?");
$ayStmt->bind_param("i", $ay_id);
$ayStmt->execute();
$ayStmt->bind_result($ay);
$ayStmt->fetch();
$ayStmt->close();

/**
 * Main query:
 *  - tbl_prospectus_offering      = o
 *  - tbl_prospectus_subjects      = ps   (has total_units)
 *  - tbl_subject_masterlist       = sm   (sub_code, sub_description)
 *  - tbl_sections                 = sec  (section_name)
 *  - tbl_faculty                  = f    (faculty name, optional)
 *  - tbl_rooms                    = r    (room name, optional)
 */
$sql = "
    SELECT 
        o.offering_id,
        o.section_id,
        o.status,

        sm.sub_code,
        sm.sub_description,
        ps.total_units,

        NULL AS faculty_id,
        NULL AS room_id,
        NULL AS days_json,
        NULL AS time_start,
        NULL AS time_end,

        sec.section_name
    FROM tbl_prospectus_offering o
    LEFT JOIN tbl_prospectus_subjects ps 
           ON ps.ps_id = o.ps_id
    LEFT JOIN tbl_subject_masterlist sm 
           ON sm.sub_id = ps.sub_id
    LEFT JOIN tbl_sections sec 
           ON sec.section_id = o.section_id
    WHERE o.prospectus_id = ?
      AND o.ay = ?
      AND o.semester = ?
    ORDER BY sec.section_name, sm.sub_code
";


$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $pid, $ay_id, $semester);   // ay stored as ay_id (number)
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo "<tr><td colspan='10' class='text-center text-muted'>No offerings found.</td></tr>";
    exit;
}

while ($row = $result->fetch_assoc()) {

    // ---------- display helpers ----------
    // faculty
    if (!empty($row['faculty_id'])) {
        $faculty = htmlspecialchars($row['faculty_name']);
    } else {
        $faculty = "<span class='text-muted'>Unassigned</span>";
    }

    // days
    if (!empty($row['days_json'])) {
        $daysArr = json_decode($row['days_json'], true);
        if (is_array($daysArr) && count($daysArr) > 0) {
            $days = htmlspecialchars(implode(', ', $daysArr));
        } else {
            $days = "<span class='text-muted'>—</span>";
        }
    } else {
        $days = "<span class='text-muted'>—</span>";
    }

    // time
    if (!empty($row['time_start']) && !empty($row['time_end'])) {
        $time = date('h:i A', strtotime($row['time_start'])) .
                ' - ' .
                date('h:i A', strtotime($row['time_end']));
    } else {
        $time = "<span class='text-muted'>—</span>";
    }

    // room
    if (!empty($row['room_id'])) {
        $room = htmlspecialchars($row['room_name']);
    } else {
        $room = "<span class='text-muted'>Unassigned</span>";
    }

    // status badge
    $status = $row['status'] ?? 'planned';
    if ($status === 'scheduled') {
        $statusBadge = "<span class='badge bg-label-success badge-status'>Scheduled</span>";
    } else {
        $statusBadge = "<span class='badge bg-label-warning badge-status'>Planned</span>";
    }

    // ---------- row output ----------
    echo "
    <tr>
        <td>" . htmlspecialchars($row['section_name']) . "</td>
        <td>" . htmlspecialchars($row['sub_code']) . "</td>
        <td>" . htmlspecialchars($row['sub_description']) . "</td>
        <td class='text-center'>" . (int)$row['total_units'] . "</td>
        <td>$faculty</td>
        <td>$days</td>
        <td>$time</td>
        <td>$room</td>
        <td>$statusBadge</td>
        <td>
            <button 
                class='btn btn-sm btn-outline-primary btn-schedule'
                data-offering-id='" . $row['offering_id'] . "'
                data-section='" . htmlspecialchars($row['section_name'], ENT_QUOTES) . "'
                data-sub-code='" . htmlspecialchars($row['sub_code'], ENT_QUOTES) . "'
                data-sub-desc='" . htmlspecialchars($row['sub_description'], ENT_QUOTES) . "'
                data-faculty-id='" . ($row['faculty_id'] ?? '') . "'
                data-room-id='" . ($row['room_id'] ?? '') . "'
                data-days-json='" . htmlspecialchars($row['days_json'] ?? '', ENT_QUOTES) . "'
                data-time-start='" . ($row['time_start'] ?? '') . "'
                data-time-end='" . ($row['time_end'] ?? '') . "'
            >
                <i class='bx bx-calendar-edit me-1'></i>Schedule
            </button>
        </td>
    </tr>";
}

$stmt->close();
