<?php
session_start();

require_once 'db.php';
require_once 'auth_config.php';
require_once 'auth_useraccount.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'unauthorized';
    exit;
}

$authSettings = synk_auth_settings();
$allowedDomain = (string)($authSettings['allowed_domain'] ?? 'sksu.edu.ph');

function query_accounts_provider_badge(?string $provider): string
{
    $provider = strtolower(trim((string)$provider));
    if ($provider === 'google') {
        return "<span class='badge bg-label-primary'>GOOGLE</span>";
    }

    return "<span class='badge bg-label-secondary'>LEGACY</span>";
}

function query_accounts_supported_role(string $role): bool
{
    return in_array($role, synk_supported_module_roles(), true);
}

function query_accounts_supported_roles_sql(mysqli $conn): string
{
    $supportedRoles = [];

    foreach (synk_supported_module_roles() as $role) {
        $supportedRoles[] = "'" . $conn->real_escape_string($role) . "'";
    }

    return implode(', ', $supportedRoles);
}

function query_accounts_role_label(string $role): string
{
    if ($role === 'admin') {
        return 'Administrator';
    }

    if ($role === 'scheduler') {
        return 'Scheduler';
    }

    return strtoupper($role);
}

function query_accounts_build_insert(mysqli $conn, array $payload): array
{
    $columns = ['username', 'email', 'password', 'role', 'status'];
    $placeholders = ['?', '?', '?', '?', '?'];
    $types = 'sssss';
    $values = [
        $payload['username'],
        $payload['email'],
        $payload['password'],
        $payload['role'],
        $payload['status'],
    ];

    $columns[] = 'college_id';
    if ($payload['college_id'] === null) {
        $placeholders[] = 'NULL';
    } else {
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $payload['college_id'];
    }

    if (synk_useraccount_has_column($conn, 'auth_provider')) {
        $columns[] = 'auth_provider';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = 'google';
    }

    if (synk_useraccount_has_column($conn, 'google_email_verified')) {
        $columns[] = 'google_email_verified';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = 0;
    }

    if (synk_useraccount_has_column($conn, 'last_google_name')) {
        $columns[] = 'last_google_name';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $payload['username'];
    }

    $sql = "INSERT INTO tbl_useraccount (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    return [$sql, $types, $values];
}

function query_accounts_build_update(mysqli $conn, array $payload): array
{
    $assignments = [
        'username = ?',
        'email = ?',
        'role = ?',
        'status = ?',
    ];
    $types = 'ssss';
    $values = [
        $payload['username'],
        $payload['email'],
        $payload['role'],
        $payload['status'],
    ];

    if ($payload['college_id'] === null) {
        $assignments[] = 'college_id = NULL';
    } else {
        $assignments[] = 'college_id = ?';
        $types .= 'i';
        $values[] = $payload['college_id'];
    }

    if (synk_useraccount_has_column($conn, 'auth_provider')) {
        $assignments[] = 'auth_provider = ?';
        $types .= 's';
        $values[] = 'google';
    }

    if (synk_useraccount_has_column($conn, 'google_sub')) {
        $assignments[] = 'google_sub = NULL';
    }

    if (synk_useraccount_has_column($conn, 'google_email_verified')) {
        $assignments[] = 'google_email_verified = ?';
        $types .= 'i';
        $values[] = 0;
    }

    if (synk_useraccount_has_column($conn, 'last_google_name')) {
        $assignments[] = 'last_google_name = ?';
        $types .= 's';
        $values[] = $payload['username'];
    }

    $types .= 'i';
    $values[] = $payload['user_id'];

    $sql = "UPDATE tbl_useraccount
            SET " . implode(', ', $assignments) . "
            WHERE user_id = ?";

    return [$sql, $types, $values];
}

function query_accounts_validate_payload(array $input, string $allowedDomain, bool $requireUserId = false): array
{
    $payload = [
        'user_id' => (int)($input['user_id'] ?? 0),
        'username' => trim((string)($input['username'] ?? '')),
        'email' => synk_normalize_email((string)($input['email'] ?? '')),
        'role' => trim((string)($input['role'] ?? '')),
        'status' => trim((string)($input['status'] ?? 'active')),
        'college_id' => ($input['college_id'] ?? '') === '' ? null : (int)$input['college_id'],
        'password' => synk_build_placeholder_password(),
    ];

    if (($requireUserId && $payload['user_id'] <= 0) || $payload['username'] === '' || $payload['email'] === '' || $payload['role'] === '') {
        return ['error' => 'missing'];
    }

    if (!query_accounts_supported_role($payload['role'])) {
        return ['error' => 'invalid_role'];
    }

    if (!in_array($payload['status'], ['active', 'inactive'], true)) {
        return ['error' => 'invalid_status'];
    }

    if (!synk_is_allowed_email_domain($payload['email'], $allowedDomain)) {
        return ['error' => 'invalid_domain'];
    }

    if ($payload['role'] === 'scheduler' && $payload['college_id'] === null) {
        return ['error' => 'need_college'];
    }

    return ['payload' => $payload];
}

if (isset($_POST['load_accounts'])) {
    $fields = ['u.*', 'c.college_code', 'c.college_name'];
    $sql = "
        SELECT " . implode(', ', $fields) . "
        FROM tbl_useraccount u
        LEFT JOIN tbl_college c ON u.college_id = c.college_id
        WHERE u.role IN (" . query_accounts_supported_roles_sql($conn) . ")
        ORDER BY u.user_id DESC
    ";

    $res = $conn->query($sql);
    $i = 1;

    while ($row = $res->fetch_assoc()) {
        $roleLabel = query_accounts_role_label($row['role']);
        $providerBadge = query_accounts_provider_badge($row['auth_provider'] ?? 'legacy');

        if (!empty($row['college_id']) && !empty($row['college_name'])) {
            $collegeLabel = htmlspecialchars($row['college_code'] . ' - ' . $row['college_name'], ENT_QUOTES, 'UTF-8');
        } else {
            $collegeLabel = "<span class='text-muted'>N/A</span>";
        }

        $badge = ($row['status'] === 'active')
            ? "<span class='badge bg-success'>ACTIVE</span>"
            : "<span class='badge bg-secondary'>INACTIVE</span>";

        echo "
        <tr>
          <td>{$i}</td>
          <td>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "</td>
          <td>" . htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . "</td>
          <td>{$providerBadge}</td>
          <td>" . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . "</td>
          <td>{$collegeLabel}</td>
          <td>{$badge}</td>
          <td class='text-end text-nowrap'>
            <button class='btn btn-sm btn-warning btnEditAccount'
                data-id='{$row['user_id']}'
                data-username=\"" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "\"
                data-email=\"" . htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . "\"
                data-role='" . htmlspecialchars($row['role'], ENT_QUOTES, 'UTF-8') . "'
                data-college='" . ($row['college_id'] ?? '') . "'
                data-status='" . htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') . "'>
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

if (isset($_POST['save_account'])) {
    $validation = query_accounts_validate_payload($_POST, $allowedDomain, false);
    if (isset($validation['error'])) {
        echo $validation['error'];
        exit;
    }

    $payload = $validation['payload'];

    $q = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE email = ? LIMIT 1");
    $q->bind_param("s", $payload['email']);
    $q->execute();
    $q->store_result();
    if ($q->num_rows > 0) {
        $q->close();
        echo 'dup_email';
        exit;
    }
    $q->close();

    [$sql, $types, $values] = query_accounts_build_insert($conn, $payload);
    $stmt = $conn->prepare($sql);
    synk_stmt_bind_params($stmt, $types, $values);
    $stmt->execute();
    $stmt->close();

    echo 'success';
    exit;
}

if (isset($_POST['update_account'])) {
    $validation = query_accounts_validate_payload($_POST, $allowedDomain, true);
    if (isset($validation['error'])) {
        echo $validation['error'];
        exit;
    }

    $payload = $validation['payload'];

    $q = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE email = ? AND user_id <> ? LIMIT 1");
    $q->bind_param("si", $payload['email'], $payload['user_id']);
    $q->execute();
    $q->store_result();
    if ($q->num_rows > 0) {
        $q->close();
        echo 'dup_email';
        exit;
    }
    $q->close();

    [$sql, $types, $values] = query_accounts_build_update($conn, $payload);
    $stmt = $conn->prepare($sql);
    synk_stmt_bind_params($stmt, $types, $values);
    $stmt->execute();
    $stmt->close();

    echo 'success';
    exit;
}

if (isset($_POST['delete_account'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        echo 'missing';
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM tbl_useraccount WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    echo 'deleted';
    exit;
}
