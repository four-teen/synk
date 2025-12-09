<?php
require_once "db.php";

// ------------------------------------------------------------
// LOAD FACULTY LIST
// ------------------------------------------------------------
if (isset($_POST['load_faculty'])) {

    $qry = $conn->query("
        SELECT faculty_id, last_name, first_name, middle_name, ext_name, status
        FROM tbl_faculty
        ORDER BY last_name ASC, first_name ASC
    ");

    $count = 1;

    while ($row = $qry->fetch_assoc()) {

        // Build formatted full name
        $fullname = $row['last_name'] . ", " . $row['first_name'];
        if (!empty($row['middle_name'])) {
            $fullname .= " " . $row['middle_name'];
        }
        if (!empty($row['ext_name'])) {
            $fullname .= " " . $row['ext_name'];
        }

        // Status Badge
        $badge = ($row['status'] == "active") 
            ? "<span class='badge bg-success'>ACTIVE</span>"
            : "<span class='badge bg-secondary'>INACTIVE</span>";

        echo "
            <tr>
                <td>{$count}</td>
                <td>" . htmlspecialchars($fullname) . "</td>
                <td>{$badge}</td>
                <td class='text-end'>

                    <button class='btn btn-sm btn-warning btnEditFaculty'
                        data-id='{$row['faculty_id']}'
                        data-lname=\"" . htmlspecialchars($row['last_name']) . "\"
                        data-fname=\"" . htmlspecialchars($row['first_name']) . "\"
                        data-mname=\"" . htmlspecialchars($row['middle_name']) . "\"
                        data-ext=\"" . htmlspecialchars($row['ext_name']) . "\"
                        data-status='{$row['status']}'>
                        <i class='bx bx-edit'></i>
                    </button>

                    <button class='btn btn-sm btn-danger btnDeleteFaculty' 
                        data-id='{$row['faculty_id']}'>
                        <i class='bx bx-trash'></i>
                    </button>

                </td>
            </tr>
        ";

        $count++;
    }

    exit;
}


// ------------------------------------------------------------
// SAVE NEW FACULTY
// ------------------------------------------------------------
if (isset($_POST['save_faculty'])) {

    $lname = trim(strtoupper($_POST['last_name']));
    $fname = trim(strtoupper($_POST['first_name']));
    $mname = trim(strtoupper($_POST['middle_name']));
    $ext   = trim(strtoupper($_POST['ext_name']));
    $status = $_POST['status'];

    if ($lname == "" || $fname == "") {
        echo "missing";
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO tbl_faculty (last_name, first_name, middle_name, ext_name, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $lname, $fname, $mname, $ext, $status);
    $stmt->execute();
    $stmt->close();

    echo "success";
    exit;
}


// ------------------------------------------------------------
// UPDATE FACULTY
// ------------------------------------------------------------
if (isset($_POST['update_faculty'])) {

    $id    = $_POST['faculty_id'];
    $lname = trim(strtoupper($_POST['last_name']));
    $fname = trim(strtoupper($_POST['first_name']));
    $mname = trim(strtoupper($_POST['middle_name']));
    $ext   = trim(strtoupper($_POST['ext_name']));
    $status = $_POST['status'];

    if ($lname == "" || $fname == "") {
        echo "missing";
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE tbl_faculty 
        SET last_name=?, first_name=?, middle_name=?, ext_name=?, status=?
        WHERE faculty_id=?
    ");
    $stmt->bind_param("sssssi", $lname, $fname, $mname, $ext, $status, $id);
    $stmt->execute();
    $stmt->close();

    echo "success";
    exit;
}


// ------------------------------------------------------------
// DELETE FACULTY
// ------------------------------------------------------------
if (isset($_POST['delete_faculty'])) {

    $id = $_POST['faculty_id'];

    $stmt = $conn->prepare("DELETE FROM tbl_faculty WHERE faculty_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo "deleted";
    exit;
}

?>
