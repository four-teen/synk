<?php
session_start();
ob_start();
include 'db.php';

// Ensure scheduler or admin is allowed (optional)
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
//     echo json_encode(["status" => "unauthorized"]);
//     exit;
// }

// ======================================================================
// LOAD FACULTY ASSIGNED IN A COLLEGE
// ======================================================================
if (isset($_POST['load_college_faculty'])) {

    $college_id = intval($_POST['college_id'] ?? 0);
    $response = [];

    if ($college_id <= 0) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT 
            cf.college_faculty_id,
            f.faculty_id,
            f.last_name,
            f.first_name,
            f.middle_name,
            f.ext_name,
            cf.status,
            cf.date_created
        FROM tbl_college_faculty cf
        INNER JOIN tbl_faculty f ON cf.faculty_id = f.faculty_id
        WHERE cf.college_id = ?
          AND cf.status = 'active'
        ORDER BY f.last_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response[] = $row;
    }

    echo json_encode($response);
    exit;
}



// ======================================================================
// LOAD FACULTY LIST FOR SELECT2
// ======================================================================
if (isset($_POST['load_faculty_dropdown'])) {

    $data = [];

    $sql = "
        SELECT faculty_id, last_name, first_name, middle_name, ext_name
        FROM tbl_faculty
        WHERE status = 'active'
        ORDER BY last_name ASC
    ";
    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {

        $full = $row['last_name'] . ', ' . $row['first_name'];

        if (!empty($row['middle_name'])) {
            $full .= ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.';
        }

        if (!empty($row['ext_name'])) {
            $full .= ', ' . $row['ext_name'];
        }

        $data[] = [
            "id" => $row['faculty_id'],
            "text" => $full
        ];
    }

    echo json_encode($data);
    exit;
}



// ======================================================================
// SAVE NEW ASSIGNMENT (COLLEGE → FACULTY)
// ======================================================================
if (isset($_POST['save_assignment'])) {

    $college_id = intval($_POST['college_id'] ?? 0);
    $faculty_id = intval($_POST['faculty_id'] ?? 0);

    if ($college_id <= 0 || $faculty_id <= 0) {
        echo json_encode(["status" => "invalid"]);
        exit;
    }

    // Duplicate check
    $check = $conn->prepare("
        SELECT 1 FROM tbl_college_faculty 
        WHERE college_id = ? AND faculty_id = ?
    ");
    $check->bind_param("ii", $college_id, $faculty_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(["status" => "duplicate"]);
        exit;
    }

    // Insert
    $sql = "
        INSERT INTO tbl_college_faculty (college_id, faculty_id, status)
        VALUES (?, ?, 'active')
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $college_id, $faculty_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "inserted"]);
    } else {
        echo json_encode(["status" => "error"]);
    }

    exit;
}



// ======================================================================
// REMOVE ASSIGNMENT (SET INACTIVE)
// ======================================================================
if (isset($_POST['remove_assignment'])) {

    $college_faculty_id = intval($_POST['college_faculty_id'] ?? 0);

    if ($college_faculty_id <= 0) {
        echo json_encode(["status" => "invalid"]);
        exit;
    }

    $sql = "
        UPDATE tbl_college_faculty
        SET status = 'inactive'
        WHERE college_faculty_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $college_faculty_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "removed"]);
    } else {
        echo json_encode(["status" => "error"]);
    }

    exit;
}
?>
