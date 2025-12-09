<?php
session_start();
ob_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    echo "unauthorized";
    exit;
}

$college_id = $_SESSION['college_id'];

// -----------------------------
// SAVE WORKLOAD ROW
// -----------------------------
if (isset($_POST['save_workload'])) {

    $faculty_id = intval($_POST['faculty_id']);
    $ay         = mysqli_real_escape_string($conn, $_POST['ay']);
    $semester   = mysqli_real_escape_string($conn, $_POST['semester']);

    $program_id = intval($_POST['program_id']);
    $year_level = intval($_POST['year_level']);
    $section_id = intval($_POST['section_id']);
    $subject_id = intval($_POST['subject_id']);
    $days_json  = mysqli_real_escape_string($conn, $_POST['days']);
    $time_start = mysqli_real_escape_string($conn, $_POST['time_start']);
    $time_end   = mysqli_real_escape_string($conn, $_POST['time_end']);
    $room_id    = intval($_POST['room_id']);

    $units      = intval($_POST['units']);
    $hours_lec  = intval($_POST['hours_lec']);
    $hours_lab  = intval($_POST['hours_lab']);
    $load_value = floatval($_POST['load_value']);

    // basic guard
    if (!$faculty_id || !$program_id || !$section_id || !$subject_id || !$ay) {
        echo "Invalid data.";
        exit;
    }

    $sql = "INSERT INTO tbl_faculty_workload 
            (faculty_id, program_id, section_id, year_level, subject_id, ay, semester,
             days_json, time_start, time_end, room_id, units, hours_lec, hours_lab, load_value)
            VALUES
            ('$faculty_id','$program_id','$section_id','$year_level','$subject_id','$ay','$semester',
             '$days_json','$time_start','$time_end','$room_id','$units','$hours_lec','$hours_lab','$load_value')";

    if (mysqli_query($conn, $sql)) {
        echo "success";
    } else {
        echo "DB Error: " . mysqli_error($conn);
    }
    exit;
}


// -----------------------------
// LOAD WORKLOAD FOR FACULTY+TERM
// returns JSON array of rows
// -----------------------------
if (isset($_POST['load_workload'])) {

    $faculty_id = intval($_POST['faculty_id']);
    $ay         = mysqli_real_escape_string($conn, $_POST['ay']);
    $semester   = mysqli_real_escape_string($conn, $_POST['semester']);

    $data = [];

    $sql = "
        SELECT fw.*, 
               s.sub_code, s.sub_description,
               sec.section_name,
               r.room_code
        FROM tbl_faculty_workload fw
        JOIN tbl_subject_masterlist s ON fw.subject_id = s.sub_id
        JOIN tbl_sections sec         ON fw.section_id = sec.section_id
        JOIN tbl_rooms r              ON fw.room_id = r.room_id
        WHERE fw.faculty_id = '$faculty_id'
          AND fw.ay = '$ay'
          AND fw.semester = '$semester'
        ORDER BY fw.year_level, sec.section_name, s.sub_code, fw.time_start
    ";

    $run = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($run)) {

        $days_arr = json_decode($row['days_json'], true);
        if (!is_array($days_arr)) $days_arr = [];

        $days_display = implode('', $days_arr);           // e.g. MWF, TTH
        $time_display = date('g:iA', strtotime($row['time_start'])) . '–' .
                        date('g:iA', strtotime($row['time_end']));

        $data[] = [
            'workload_id'  => $row['workload_id'],
            'subject_code' => $row['sub_code'],
            'subject_desc' => $row['sub_description'],
            'section_name' => $row['section_name'],
            'days_display' => $days_display,
            'time_display' => $time_display,
            'room_code'    => $row['room_code'],
            'units'        => $row['units'],
            'hours_lec'    => $row['hours_lec'],
            'hours_lab'    => $row['hours_lab'],
            'load_value'   => number_format($row['load_value'], 2)
        ];
    }

    echo json_encode($data);
    exit;
}


// -----------------------------
// DELETE WORKLOAD ROW
// -----------------------------
if (isset($_POST['delete_workload'])) {

    $id = intval($_POST['workload_id']);

    $sql = "DELETE FROM tbl_faculty_workload WHERE workload_id = '$id' LIMIT 1";

    if (mysqli_query($conn, $sql)) {
        echo "success";
    } else {
        echo "DB Error: " . mysqli_error($conn);
    }
    exit;
}

echo "no_action";
