<?php
require_once "db.php";

// ------------------------------------------------------------
// LOAD ACCOUNTS
// ------------------------------------------------------------
if (isset($_POST['load_accounts'])) {

    $sql = "
        SELECT u.*, c.college_code, c.college_name
        FROM tbl_useraccount u
        LEFT JOIN tbl_college c ON u.college_id = c.college_id
        ORDER BY u.user_id DESC
    ";

    $res = $conn->query($sql);
    $i = 1;

    while ($row = $res->fetch_assoc()) {

        // Role label
        $roleLabel = strtoupper($row['role']);

        // College label
        $collegeLabel = "";
        if (!empty($row['college_id']) && !empty($row['college_name'])) {
            $collegeLabel = $row['college_code'] . " - " . $row['college_name'];
        } else {
            $collegeLabel = "<span class='text-muted'>N/A</span>";
        }

        // Status badge
        $badge = ($row['status'] === 'active')
            ? "<span class='badge bg-success'>ACTIVE</span>"
            : "<span class='badge bg-secondary'>INACTIVE</span>";

        echo "
        <tr>
          <td>{$i}</td>
          <td>" . htmlspecialchars($row['username']) . "</td>
          <td>" . htmlspecialchars($row['email']) . "</td>
          <td>" . htmlspecialchars($roleLabel) . "</td>
          <td>" . (is_string($collegeLabel) ? $collegeLabel : htmlspecialchars($collegeLabel)) . "</td>
          <td>{$badge}</td>
          <td class='text-end text-nowrap'>
            <button class='btn btn-sm btn-warning btnEditAccount'
                data-id='{$row['user_id']}'
                data-username=\"" . htmlspecialchars($row['username'], ENT_QUOTES) . "\"
                data-email=\"" . htmlspecialchars($row['email'], ENT_QUOTES) . "\"
                data-role='{$row['role']}'
                data-college='" . ($row['college_id'] ?? "") . "'
                data-status='{$row['status']}'>
              <i class='bx bx-edit-alt'></i>
            </button>

            <button class='btn btn-sm btn-danger btnDeleteAccount'
                data-id='{$row['user_id']}'>
              <i class='bx bx-trash'></i>
            </button>
          </td>
        </tr>
        ";

        $i++;
    }

    exit;
}


// ------------------------------------------------------------
// SAVE NEW ACCOUNT
// ------------------------------------------------------------
if (isset($_POST['save_account'])) {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? '';
    $status   = $_POST['status'] ?? 'active';
    $college_id = $_POST['college_id'] ?? '';

    if ($username === '' || $email === '' || $password === '' || $role === '') {
        echo "missing";
        exit;
    }

    if ($role === 'scheduler' && $college_id === '') {
        echo "need_college";
        exit;
    }

    // Check duplicates
    $q1 = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE username = ?");
    $q1->bind_param("s", $username);
    $q1->execute();
    $q1->store_result();
    if ($q1->num_rows > 0) {
        echo "dup_username";
        $q1->close();
        exit;
    }
    $q1->close();

    $q2 = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE email = ?");
    $q2->bind_param("s", $email);
    $q2->execute();
    $q2->store_result();
    if ($q2->num_rows > 0) {
        echo "dup_email";
        $q2->close();
        exit;
    }
    $q2->close();

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    if ($college_id === '') {
        $college_id = null;
    }

    $stmt = $conn->prepare("
        INSERT INTO tbl_useraccount (username, email, password, role, college_id, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssis",
        $username,
        $email,
        $hashed,
        $role,
        $college_id,
        $status
    );
    $stmt->execute();
    $stmt->close();

    echo "success";
    exit;
}


// ------------------------------------------------------------
// UPDATE ACCOUNT
// ------------------------------------------------------------
if (isset($_POST['update_account'])) {

    $user_id  = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? '';
    $status   = $_POST['status'] ?? 'active';
    $college_id = $_POST['college_id'] ?? '';

    if ($username === '' || $email === '' || $role === '') {
        echo "missing";
        exit;
    }

    if ($role === 'scheduler' && $college_id === '') {
        echo "need_college";
        exit;
    }

    // Check duplicate username
    $q1 = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE username = ? AND user_id <> ?");
    $q1->bind_param("si", $username, $user_id);
    $q1->execute();
    $q1->store_result();
    if ($q1->num_rows > 0) {
        echo "dup_username";
        $q1->close();
        exit;
    }
    $q1->close();

    // Check duplicate email
    $q2 = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE email = ? AND user_id <> ?");
    $q2->bind_param("si", $email, $user_id);
    $q2->execute();
    $q2->store_result();
    if ($q2->num_rows > 0) {
        echo "dup_email";
        $q2->close();
        exit;
    }
    $q2->close();

    if ($college_id === '') {
        $college_id = null;
    }

    // Build update query
    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE tbl_useraccount
            SET username=?, email=?, password=?, role=?, college_id=?, status=?
            WHERE user_id=?
        ");
        $stmt->bind_param(
            "ssssisi",
            $username,
            $email,
            $hashed,
            $role,
            $college_id,
            $status,
            $user_id
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE tbl_useraccount
            SET username=?, email=?, role=?, college_id=?, status=?
            WHERE user_id=?
        ");
        $stmt->bind_param(
            "sssisi",
            $username,
            $email,
            $role,
            $college_id,
            $status,
            $user_id
        );
    }

    $stmt->execute();
    $stmt->close();

    echo "success";
    exit;
}


// ------------------------------------------------------------
// DELETE ACCOUNT
// ------------------------------------------------------------
if (isset($_POST['delete_account'])) {

    $user_id = (int)($_POST['user_id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM tbl_useraccount WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    echo "deleted";
    exit;
}

?>
