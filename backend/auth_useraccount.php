<?php

require_once __DIR__ . '/scheduler_access_helper.php';

function synk_useraccount_columns(mysqli $conn): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM tbl_useraccount");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
        $result->close();
    }

    $cache = $columns;
    return $cache;
}

function synk_useraccount_has_column(mysqli $conn, string $column): bool
{
    $columns = synk_useraccount_columns($conn);
    return isset($columns[$column]);
}

function synk_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function synk_is_allowed_email_domain(string $email, string $allowedDomain): bool
{
    $email = synk_normalize_email($email);
    $allowedDomain = strtolower(trim($allowedDomain));

    if ($email === '' || $allowedDomain === '') {
        return false;
    }

    return preg_match('/^[^@\s]+@' . preg_quote($allowedDomain, '/') . '$/i', $email) === 1;
}

function synk_supported_module_roles(): array
{
    return ['admin', 'scheduler'];
}

function synk_role_redirect_path(string $role): ?string
{
    if ($role === 'admin') {
        return 'administrator/';
    }

    if ($role === 'scheduler') {
        return 'scheduler/';
    }

    return null;
}

function synk_find_useraccount_by_email(mysqli $conn, string $email): ?array
{
    $fields = [
        'u.user_id',
        'u.username',
        'u.email',
        'u.password',
        'u.role',
        'u.status',
        'u.college_id',
        'c.college_name',
    ];

    if (synk_useraccount_has_column($conn, 'auth_provider')) {
        $fields[] = 'u.auth_provider';
    }
    if (synk_useraccount_has_column($conn, 'google_sub')) {
        $fields[] = 'u.google_sub';
    }
    if (synk_useraccount_has_column($conn, 'google_email_verified')) {
        $fields[] = 'u.google_email_verified';
    }
    if (synk_useraccount_has_column($conn, 'last_google_name')) {
        $fields[] = 'u.last_google_name';
    }
    if (synk_useraccount_has_column($conn, 'last_login_at')) {
        $fields[] = 'u.last_login_at';
    }

    $sql = "
        SELECT " . implode(",\n               ", $fields) . "
        FROM tbl_useraccount u
        LEFT JOIN tbl_college c ON c.college_id = u.college_id
        WHERE LOWER(u.email) = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $normalizedEmail = synk_normalize_email($email);
    $stmt->bind_param("s", $normalizedEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function synk_complete_user_login(array $row, ?mysqli $conn = null): string
{
    session_regenerate_id(true);

    if ((string)($row['role'] ?? '') === 'scheduler' && $conn instanceof mysqli) {
        $accessRows = synk_resolve_scheduler_access_rows(
            $conn,
            (int)($row['user_id'] ?? 0),
            (int)($row['college_id'] ?? 0)
        );
        synk_scheduler_store_session_scope($row, $accessRows, (int)($row['college_id'] ?? 0));
    } else {
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['college_id'] = $row['college_id'];
        $_SESSION['college_name'] = $row['college_name'];
    }

    return (string)$row['role'];
}

function synk_logout_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_unset();
    session_destroy();
}

function synk_build_placeholder_password(): string
{
    try {
        $randomValue = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $randomValue = uniqid('synk-google-', true);
    }

    return password_hash($randomValue, PASSWORD_DEFAULT);
}

function synk_stmt_bind_params(mysqli_stmt $stmt, string $types, array $values): void
{
    $refs = [];
    $refs[] = $types;

    foreach ($values as $index => $value) {
        $values[$index] = $value;
        $refs[] = &$values[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function synk_record_google_login(mysqli $conn, int $userId, string $googleSub, bool $emailVerified, string $googleName): void
{
    $assignments = [];
    $types = '';
    $values = [];

    if (synk_useraccount_has_column($conn, 'google_sub')) {
        $assignments[] = 'google_sub = ?';
        $types .= 's';
        $values[] = $googleSub;
    }

    if (synk_useraccount_has_column($conn, 'auth_provider')) {
        $assignments[] = 'auth_provider = ?';
        $types .= 's';
        $values[] = 'google';
    }

    if (synk_useraccount_has_column($conn, 'google_email_verified')) {
        $assignments[] = 'google_email_verified = ?';
        $types .= 'i';
        $values[] = $emailVerified ? 1 : 0;
    }

    if (synk_useraccount_has_column($conn, 'last_google_name')) {
        $assignments[] = 'last_google_name = ?';
        $types .= 's';
        $values[] = $googleName;
    }

    if (synk_useraccount_has_column($conn, 'last_login_at')) {
        $assignments[] = 'last_login_at = NOW()';
    }

    if (empty($assignments)) {
        return;
    }

    $sql = "UPDATE tbl_useraccount SET " . implode(', ', $assignments) . " WHERE user_id = ?";
    $stmt = $conn->prepare($sql);

    $types .= 'i';
    $values[] = $userId;
    synk_stmt_bind_params($stmt, $types, $values);
    $stmt->execute();
    $stmt->close();
}
