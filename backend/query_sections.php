<?php
    session_start();
    ob_start();
    include '../backend/db.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
        echo "unauthorized";
        exit;
    }


// -------------------------------------------------
// DELETE SINGLE SECTION
// -------------------------------------------------
if (isset($_POST['delete_section'])) {

    $section_id = intval($_POST['section_id']);

    // Validate section belongs to scheduler's college
    $college_id = $_SESSION['college_id'];

    $check = mysqli_query($conn, "
        SELECT s.section_id 
        FROM tbl_sections s
        JOIN tbl_program p ON s.program_id = p.program_id
        WHERE s.section_id = '$section_id'
        AND p.college_id = '$college_id'
    ");

    if (mysqli_num_rows($check) == 0) {
        echo "forbidden";
        exit;
    }

    $delete = mysqli_query($conn, "
        DELETE FROM tbl_sections 
        WHERE section_id = '$section_id'
    ");

    echo $delete ? "success" : "error";
    exit;
}


// -------------------------------------------------
// LOAD GROUPED SECTIONS (FOR DISPLAY IN CARDS)
// -------------------------------------------------
if (isset($_POST['load_grouped_sections'])) {

    $college_id = $_SESSION['college_id'];

    $sql = "
        SELECT s.*, p.program_code, p.program_name
        FROM tbl_sections s
        JOIN tbl_program p ON s.program_id = p.program_id
        WHERE p.college_id = '$college_id'
        ORDER BY p.program_code, s.year_level, s.section_name
    ";

    $run = mysqli_query($conn, $sql);

    $grouped = [];

    while ($r = mysqli_fetch_assoc($run)) {

        $program_title = $r['program_code'] . " — " . $r['program_name'];

        if (!isset($grouped[$program_title])) {
            $grouped[$program_title] = [];
        }

        $grouped[$program_title][] = [
            "section_id"   => $r['section_id'],   // ← add this
            "year_level"   => $r['year_level'],
            "section_name" => $r['section_name'],
            "full_section" => $r['full_section'],
            "status"       => $r['status']
        ];
    }

    echo json_encode($grouped);
    exit;
}


// -------------------------------------------------
// LOAD EXISTING SECTIONS LIST
// -------------------------------------------------
if (isset($_POST['load_sections'])) {

    $college_id = $_SESSION['college_id'];

    $sql = "
        SELECT s.*, p.program_code 
        FROM tbl_sections s
        JOIN tbl_program p ON s.program_id = p.program_id
        WHERE p.college_id = '$college_id'
        ORDER BY p.program_code, s.year_level, s.section_name
    ";

    $run = mysqli_query($conn, $sql);
    $i = 1;
    $rows = "";

    while ($r = mysqli_fetch_assoc($run)) {

        $rows .= "
            <tr>
                <td>{$i}</td>
                <td>{$r['program_code']}</td>
                <td>{$r['year_level']}</td>
                <td>{$r['section_name']}</td>
                <td>{$r['full_section']}</td>
                <td><span class='badge bg-success'>{$r['status']}</span></td>
            </tr>
        ";
        $i++;
    }

    echo $rows;
    exit;
}


// -------------------------------------------------
// SAVE SECTIONS
// -------------------------------------------------
if (isset($_POST['save_sections'])) {

    $program_id   = intval($_POST['program_id']);
    $program_code = mysqli_real_escape_string($conn, $_POST['program_code']);
    $year         = intval($_POST['year_level']);
    $count        = intval($_POST['count']);
    $startIndex   = intval($_POST['start_index'] ?? 0);

    $letters = range('A','Z');

    for ($i = 0; $i < $count; $i++) {

        $idx = $startIndex + $i;

        if (!isset($letters[$idx])) break;

        $section_name = $year . $letters[$idx];       // 1D, 1E
        $full_section = $program_code . " " . $section_name;

        $check = mysqli_query($conn,
            "SELECT section_id FROM tbl_sections
             WHERE program_id='$program_id'
             AND year_level='$year'
             AND section_name='$section_name'"
        );

        if (mysqli_num_rows($check) == 0) {

            mysqli_query($conn,
                "INSERT INTO tbl_sections (program_id, year_level, section_name, full_section)
                 VALUES ('$program_id', '$year', '$section_name', '$full_section')"
            );
        }
    }

    echo "success";
    exit;
}


// add near bottom of query_sections.php

if (isset($_POST['load_sections_by_prog_year'])) {

    $program_id = intval($_POST['program_id']);
    $year_level = intval($_POST['year_level']);

    $out = "<option value=''>Select Section</option>";

    $sql = "SELECT section_id, section_name 
            FROM tbl_sections 
            WHERE program_id='$program_id' AND year_level='$year_level' 
            ORDER BY section_name";

    $run = mysqli_query($conn, $sql);
    while ($r = mysqli_fetch_assoc($run)) {
        $out .= "<option value='{$r['section_id']}'>{$r['section_name']}</option>";
    }

    echo $out;
    exit;
}

// -------------------------------------------------
// GET NEXT AVAILABLE SECTION LETTER (ACTIVE ONLY)
// -------------------------------------------------
if (isset($_POST['get_next_section_index'])) {

    $program_id = intval($_POST['program_id']);
    $year_level = intval($_POST['year_level']);

    $sql = "
        SELECT MAX(ASCII(RIGHT(section_name, 1))) AS max_letter
        FROM tbl_sections
        WHERE program_id = ?
          AND year_level = ?
          AND status = 'active'
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $program_id, $year_level);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    // If no existing sections, start at A (index 0)
    $nextIndex = 0;

    if (!empty($res['max_letter'])) {
        $nextIndex = ($res['max_letter'] - ord('A')) + 1;
    }

    echo json_encode([
        "next_index" => $nextIndex
    ]);
    exit;
}



?>
