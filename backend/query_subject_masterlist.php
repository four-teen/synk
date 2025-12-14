<?php
include '../backend/db.php';


// ==========================================================
// LOAD SUBJECT MASTERLIST
// ==========================================================
if (isset($_POST['load_subjects'])) {

    $sql = "
        SELECT sub_id, sub_code, sub_description, status
        FROM tbl_subject_masterlist
        ORDER BY sub_code ASC
    ";

    $result = $conn->query($sql);
    $output = "";
    $i = 1;

    while ($row = $result->fetch_assoc()) {

        $badge = ($row['status'] == 'active')
            ? "<span class='badge bg-success'>ACTIVE</span>"
            : "<span class='badge bg-secondary'>INACTIVE</span>";

        $output .= "
        <tr>
            <td>{$i}</td>
            <td>{$row['sub_code']}</td>
            <td>{$row['sub_description']}</td>
            <td>{$badge}</td>

            <td class='text-end text-nowrap'>

                <button class='btn btn-sm btn-warning btnEdit'
                    data-id='{$row['sub_id']}'
                    data-code=\"{$row['sub_code']}\"
                    data-desc=\"{$row['sub_description']}\"
                    data-status='{$row['status']}'>
                    <i class='bx bx-edit-alt'></i>
                </button>

                <button class='btn btn-sm btn-danger btnDelete'
                    data-id='{$row['sub_id']}'>
                    <i class='bx bx-trash'></i>
                </button>

            </td>
        </tr>
        ";

        $i++;
    }

    echo $output;
    exit;
}



// ==========================================================
// SAVE NEW SUBJECT
// ==========================================================
if (isset($_POST['save_subject'])) {

    $code = $conn->real_escape_string(strtoupper($_POST['sub_code']));
    $desc = $conn->real_escape_string(strtoupper($_POST['sub_description']));

    // prevent duplicates (optional but good practice)
    $dupCheck = $conn->query("SELECT sub_id FROM tbl_subject_masterlist WHERE sub_code='$code' AND sub_description='$desc'");
    if ($dupCheck->num_rows > 0) {
        echo "duplicate";
        exit;
    }

    $sql = "
        INSERT INTO tbl_subject_masterlist (sub_code, sub_description)
        VALUES ('$code', '$desc')
    ";

    $conn->query($sql);

    echo "success";
    exit;
}



// ==========================================================
// UPDATE SUBJECT
// ==========================================================
if (isset($_POST['update_subject'])) {

    $id    = $_POST['sub_id'];
    $code  = $conn->real_escape_string(strtoupper($_POST['sub_code']));
    $desc  = $conn->real_escape_string(strtoupper($_POST['sub_description']));
    $status = $_POST['status'];

    $sql = "
        UPDATE tbl_subject_masterlist
        SET 
            sub_code = '$code',
            sub_description = '$desc',
            status = '$status'
        WHERE sub_id = '$id'
    ";

    $conn->query($sql);

    echo "updated";
    exit;
}



// ==========================================================
// DELETE SUBJECT
// ==========================================================
if (isset($_POST['delete_subject'])) {

    $id = $_POST['sub_id'];

    $sql = "DELETE FROM tbl_subject_masterlist WHERE sub_id = '$id'";
    $conn->query($sql);

    echo "deleted";
    exit;
}

?>
