<?php
session_start();
ob_start();

include '../backend/db.php';

// --------------------------------------------
// SECURITY CHECK: only scheduler can access
// --------------------------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    echo "unauthorized";
    exit;
}

$college_id = $_SESSION['college_id']; // scheduler's assigned college

// --------------------------------------------
// LOAD ROOMS
// --------------------------------------------
if (isset($_POST['load_rooms'])) {

    $sql = "SELECT * FROM tbl_rooms 
            WHERE college_id = '$college_id'
            ORDER BY room_code ASC";

    $run = mysqli_query($conn, $sql);

    $rows = "";
    $i = 1;

    while ($r = mysqli_fetch_assoc($run)) {

        // Convert room type to readable format
        $type_label = [
            "lecture"   => "Lecture",
            "laboratory" => "Laboratory",
            "lec_lab"   => "Lecture-Laboratory"
        ][$r['room_type']];

        $rows .= "
            <tr>
                <td>{$i}</td>
                <td>".htmlspecialchars($r['room_code'])."</td>
                <td>".htmlspecialchars($r['room_name'])."</td>
                <td>{$type_label}</td>
                <td>{$r['capacity']}</td>

                <td class='text-end'>
                    <button class='btn btn-sm btn-info btnEdit'
                        data-id='{$r['room_id']}'
                        data-code='".htmlspecialchars($r['room_code'], ENT_QUOTES)."'
                        data-name='".htmlspecialchars($r['room_name'], ENT_QUOTES)."'
                        data-type='{$r['room_type']}'
                        data-capacity='{$r['capacity']}'>
                        <i class='bx bx-edit'></i>
                    </button>

                    <button class='btn btn-sm btn-danger btnDelete'
                        data-id='{$r['room_id']}'>
                        <i class='bx bx-trash'></i>
                    </button>
                </td>
            </tr>
        ";

        $i++;
    }

    echo $rows;
    exit;
}


// --------------------------------------------
// SAVE NEW ROOM
// --------------------------------------------
if (isset($_POST['save_room'])) {

    $room_code  = mysqli_real_escape_string($conn, strtoupper($_POST['room_code']));
    $room_name  = mysqli_real_escape_string($conn, strtoupper($_POST['room_name']));
    $room_type  = mysqli_real_escape_string($conn, strtoupper($_POST['room_type']));
    $capacity   = intval($_POST['capacity']);

    // Prevent duplicate rooms
    $check = mysqli_query($conn, "SELECT room_id FROM tbl_rooms 
                                  WHERE room_code='$room_code' AND college_id='$college_id'");

    if (mysqli_num_rows($check) > 0) {
        echo "duplicate";
        exit;
    }

    $sql = "INSERT INTO tbl_rooms (college_id, room_code, room_name, room_type, capacity) 
            VALUES ('$college_id', '$room_code', '$room_name', '$room_type', '$capacity')";

    echo mysqli_query($conn, $sql) ? "success" : "error";
    exit;
}


// --------------------------------------------
// UPDATE ROOM
// --------------------------------------------
if (isset($_POST['update_room'])) {

    $room_id    = intval($_POST['room_id']);
    $room_code  = mysqli_real_escape_string($conn, strtoupper($_POST['room_code']));
    $room_name  = mysqli_real_escape_string($conn, strtoupper($_POST['room_name']));
    $room_type  = mysqli_real_escape_string($conn, strtoupper($_POST['room_type']));
    $capacity   = intval($_POST['capacity']);

    // prevent duplicate room_code within same college
    $check = mysqli_query($conn, 
        "SELECT room_id FROM tbl_rooms 
         WHERE room_code='$room_code' AND college_id='$college_id' AND room_id <> '$room_id'"
    );

    if (mysqli_num_rows($check) > 0) {
        echo "duplicate";
        exit;
    }

    $sql = "UPDATE tbl_rooms 
            SET room_code='$room_code',
                room_name='$room_name',
                room_type='$room_type',
                capacity='$capacity'
            WHERE room_id='$room_id' AND college_id='$college_id'";

    echo mysqli_query($conn, $sql) ? "success" : "error";
    exit;
}


// --------------------------------------------
// DELETE ROOM
// --------------------------------------------
if (isset($_POST['delete_room'])) {

    $room_id = intval($_POST['room_id']);

    $sql = "DELETE FROM tbl_rooms 
            WHERE room_id='$room_id' 
              AND college_id='$college_id'";

    echo mysqli_query($conn, $sql) ? "success" : "error";
    exit;
}
?>
