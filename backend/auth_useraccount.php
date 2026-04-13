<?php

require_once __DIR__ . '/scheduler_access_helper.php';
require_once __DIR__ . '/registrar_scope_helper.php';

function synk_useraccount_columns(mysqli $conn): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM tbl_useraccount");
    if ($result instanceof mysqli_result) {
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
    return ['admin', 'scheduler', 'professor', 'program_chair', 'registrar'];
}

function synk_role_label(string $role): string
{
    if ($role === 'admin') {
        return 'Administrator';
    }

    if ($role === 'scheduler') {
        return 'Scheduler';
    }

    if ($role === 'professor') {
        return 'Professor';
    }

    if ($role === 'program_chair') {
        return 'Program Chair';
    }

    if ($role === 'registrar') {
        return 'Registrar';
    }

    if ($role === 'student') {
        return 'Student';
    }

    return strtoupper($role);
}

function synk_role_redirect_path(string $role): ?string
{
    if ($role === 'admin') {
        return 'administrator/';
    }

    if ($role === 'scheduler') {
        return 'scheduler/';
    }

    if ($role === 'professor') {
        return 'professor/';
    }

    if ($role === 'program_chair') {
        return 'program-chair/';
    }

    if ($role === 'registrar') {
        return 'registrar/';
    }

    if ($role === 'student') {
        return 'student/';
    }

    return null;
}

function synk_useraccount_role_table_name(): string
{
    return 'tbl_useraccount_roles';
}

function synk_useraccount_role_table_exists(mysqli $conn): bool
{
    static $cache = null;

    if ($cache === true) {
        return true;
    }

    $tableName = synk_useraccount_role_table_name();
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->close();
    }

    if ($exists) {
        $cache = true;
    }

    return $exists;
}

function synk_ensure_useraccount_role_table(mysqli $conn): bool
{
    if (synk_useraccount_role_table_exists($conn)) {
        return true;
    }

    $tableName = synk_useraccount_role_table_name();
    $sql = "
        CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `account_role_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `role` VARCHAR(50) NOT NULL,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`account_role_id`),
            UNIQUE KEY `uniq_user_role` (`user_id`, `role`),
            KEY `idx_user_status_primary` (`user_id`, `status`, `is_primary`),
            KEY `idx_role_status` (`role`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($sql)) {
        return false;
    }

    return synk_useraccount_role_table_exists($conn);
}

function synk_normalize_supported_roles($roles): array
{
    if (!is_array($roles)) {
        if ($roles === null) {
            $roles = [];
        } else {
            $roles = [$roles];
        }
    }

    $supportedLookup = array_fill_keys(synk_supported_module_roles(), true);
    $normalized = [];

    foreach ($roles as $role) {
        $safeRole = strtolower(trim((string)$role));
        if ($safeRole === '' || !isset($supportedLookup[$safeRole])) {
            continue;
        }

        $normalized[$safeRole] = $safeRole;
    }

    return array_values($normalized);
}

function synk_sort_role_rows(array &$roleRows): void
{
    usort($roleRows, static function (array $left, array $right): int {
        $leftPrimary = !empty($left['is_primary']) ? 1 : 0;
        $rightPrimary = !empty($right['is_primary']) ? 1 : 0;

        if ($leftPrimary !== $rightPrimary) {
            return $rightPrimary <=> $leftPrimary;
        }

        return strcmp((string)($left['role'] ?? ''), (string)($right['role'] ?? ''));
    });
}

function synk_useraccount_role_rows_from_fallback(string $fallbackRole = ''): array
{
    $safeRoles = synk_normalize_supported_roles($fallbackRole !== '' ? [$fallbackRole] : []);
    if (empty($safeRoles)) {
        return [];
    }

    return [[
        'role' => $safeRoles[0],
        'is_primary' => true,
        'status' => 'active',
    ]];
}

function synk_fetch_useraccount_role_rows(mysqli $conn, int $userId, string $fallbackRole = ''): array
{
    if ($userId <= 0) {
        return synk_useraccount_role_rows_from_fallback($fallbackRole);
    }

    if (!synk_useraccount_role_table_exists($conn)) {
        return synk_useraccount_role_rows_from_fallback($fallbackRole);
    }

    $tableName = synk_useraccount_role_table_name();
    $stmt = $conn->prepare("
        SELECT role, is_primary, status
        FROM `{$tableName}`
        WHERE user_id = ?
          AND status = 'active'
        ORDER BY is_primary DESC, role ASC
    ");

    if (!$stmt) {
        return synk_useraccount_role_rows_from_fallback($fallbackRole);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $roleRows = [];

    while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
        $safeRole = strtolower(trim((string)($row['role'] ?? '')));
        if ($safeRole === '') {
            continue;
        }

        $roleRows[] = [
            'role' => $safeRole,
            'is_primary' => !empty($row['is_primary']),
            'status' => 'active',
        ];
    }

    if ($result instanceof mysqli_result) {
        $result->close();
    }
    $stmt->close();

    $normalizedRoles = synk_normalize_supported_roles(array_map(static function (array $row): string {
        return (string)($row['role'] ?? '');
    }, $roleRows));

    if (empty($normalizedRoles)) {
        return synk_useraccount_role_rows_from_fallback($fallbackRole);
    }

    $primaryRole = '';
    foreach ($roleRows as $row) {
        $role = (string)($row['role'] ?? '');
        if (!empty($row['is_primary']) && in_array($role, $normalizedRoles, true)) {
            $primaryRole = $role;
            break;
        }
    }

    if ($primaryRole === '') {
        $primaryRole = in_array($fallbackRole, $normalizedRoles, true) ? $fallbackRole : $normalizedRoles[0];
    }

    $rows = [];
    foreach ($normalizedRoles as $role) {
        $rows[] = [
            'role' => $role,
            'is_primary' => $role === $primaryRole,
            'status' => 'active',
        ];
    }

    synk_sort_role_rows($rows);
    return $rows;
}

function synk_fetch_useraccount_role_rows_bulk(mysqli $conn, array $fallbackRolesByUser): array
{
    $normalizedFallbacks = [];

    foreach ($fallbackRolesByUser as $userId => $fallbackRole) {
        $safeUserId = (int)$userId;
        if ($safeUserId <= 0) {
            continue;
        }

        $normalizedFallbacks[$safeUserId] = trim((string)$fallbackRole);
    }

    if (empty($normalizedFallbacks)) {
        return [];
    }

    $roleRowsByUser = [];
    foreach ($normalizedFallbacks as $userId => $fallbackRole) {
        $roleRowsByUser[$userId] = synk_useraccount_role_rows_from_fallback($fallbackRole);
    }

    if (!synk_useraccount_role_table_exists($conn)) {
        return $roleRowsByUser;
    }

    $tableName = synk_useraccount_role_table_name();
    $userIdList = implode(', ', array_map('intval', array_keys($normalizedFallbacks)));
    $result = $conn->query("
        SELECT user_id, role, is_primary
        FROM `{$tableName}`
        WHERE user_id IN ({$userIdList})
          AND status = 'active'
        ORDER BY user_id ASC, is_primary DESC, role ASC
    ");

    if (!($result instanceof mysqli_result)) {
        return $roleRowsByUser;
    }

    $rawRowsByUser = [];
    while ($row = $result->fetch_assoc()) {
        $userId = (int)($row['user_id'] ?? 0);
        $safeRole = strtolower(trim((string)($row['role'] ?? '')));

        if ($userId <= 0 || $safeRole === '') {
            continue;
        }

        if (!isset($rawRowsByUser[$userId])) {
            $rawRowsByUser[$userId] = [];
        }

        $rawRowsByUser[$userId][] = [
            'role' => $safeRole,
            'is_primary' => !empty($row['is_primary']),
            'status' => 'active',
        ];
    }

    $result->close();

    foreach ($normalizedFallbacks as $userId => $fallbackRole) {
        $rawRows = $rawRowsByUser[$userId] ?? [];
        if (empty($rawRows)) {
            continue;
        }

        $normalizedRoles = synk_normalize_supported_roles(array_map(static function (array $row): string {
            return (string)($row['role'] ?? '');
        }, $rawRows));

        if (empty($normalizedRoles)) {
            continue;
        }

        $primaryRole = '';
        foreach ($rawRows as $rawRow) {
            $role = (string)($rawRow['role'] ?? '');
            if (!empty($rawRow['is_primary']) && in_array($role, $normalizedRoles, true)) {
                $primaryRole = $role;
                break;
            }
        }

        if ($primaryRole === '') {
            $primaryRole = in_array($fallbackRole, $normalizedRoles, true) ? $fallbackRole : $normalizedRoles[0];
        }

        $rows = [];
        foreach ($normalizedRoles as $role) {
            $rows[] = [
                'role' => $role,
                'is_primary' => $role === $primaryRole,
                'status' => 'active',
            ];
        }

        synk_sort_role_rows($rows);
        $roleRowsByUser[$userId] = $rows;
    }

    return $roleRowsByUser;
}

function synk_useraccount_primary_role(array $roleRows, string $fallbackRole = ''): string
{
    foreach ($roleRows as $row) {
        $role = strtolower(trim((string)($row['role'] ?? '')));
        if (!empty($row['is_primary']) && $role !== '') {
            return $role;
        }
    }

    $safeFallbackRole = strtolower(trim($fallbackRole));
    if ($safeFallbackRole !== '') {
        foreach ($roleRows as $row) {
            if (strtolower(trim((string)($row['role'] ?? ''))) === $safeFallbackRole) {
                return $safeFallbackRole;
            }
        }
    }

    return isset($roleRows[0]['role']) ? strtolower(trim((string)$roleRows[0]['role'])) : '';
}

function synk_role_rows_to_payload(array $roleRows): array
{
    $payload = [];

    foreach ($roleRows as $row) {
        $role = strtolower(trim((string)($row['role'] ?? '')));
        if ($role === '') {
            continue;
        }

        $payload[] = [
            'role' => $role,
            'label' => synk_role_label($role),
            'is_primary' => !empty($row['is_primary']),
        ];
    }

    return array_values($payload);
}

function synk_filter_loginable_role_rows(mysqli $conn, array $row, array $roleRows): array
{
    $supportedLookup = array_fill_keys(synk_supported_module_roles(), true);
    $loginable = [];

    foreach ($roleRows as $roleRow) {
        $role = strtolower(trim((string)($roleRow['role'] ?? '')));
        if ($role === '' || !isset($supportedLookup[$role])) {
            continue;
        }

        if ($role === 'scheduler' && !synk_scheduler_account_has_access($conn, $row)) {
            continue;
        }

        if ($role === 'registrar' && !synk_registrar_account_has_access($conn, $row)) {
            continue;
        }

        $loginable[] = [
            'role' => $role,
            'is_primary' => !empty($roleRow['is_primary']),
            'status' => 'active',
        ];
    }

    synk_sort_role_rows($loginable);
    return $loginable;
}

function synk_persist_useraccount_roles(mysqli $conn, int $userId, array $roles, string $primaryRole): ?string
{
    if ($userId <= 0) {
        return 'missing';
    }

    $normalizedRoles = synk_normalize_supported_roles($roles);
    if (empty($normalizedRoles)) {
        return 'invalid_role';
    }

    $safePrimaryRole = strtolower(trim($primaryRole));
    if ($safePrimaryRole === '' || !in_array($safePrimaryRole, $normalizedRoles, true)) {
        $safePrimaryRole = $normalizedRoles[0];
    }

    if (!synk_ensure_useraccount_role_table($conn)) {
        return 'save_failed';
    }

    $tableName = synk_useraccount_role_table_name();
    $deleteStmt = $conn->prepare("DELETE FROM `{$tableName}` WHERE user_id = ?");
    if (!$deleteStmt) {
        return 'save_failed';
    }

    $deleteStmt->bind_param('i', $userId);
    if (!$deleteStmt->execute()) {
        $deleteStmt->close();
        return 'save_failed';
    }
    $deleteStmt->close();

    $insertStmt = $conn->prepare("
        INSERT INTO `{$tableName}` (user_id, role, is_primary, status)
        VALUES (?, ?, ?, 'active')
    ");

    if (!$insertStmt) {
        return 'save_failed';
    }

    foreach ($normalizedRoles as $role) {
        $isPrimary = $role === $safePrimaryRole ? 1 : 0;
        $insertStmt->bind_param('isi', $userId, $role, $isPrimary);
        if (!$insertStmt->execute()) {
            $insertStmt->close();
            return 'save_failed';
        }
    }

    $insertStmt->close();
    return null;
}

function synk_useraccount_select_fields(mysqli $conn): array
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

    return $fields;
}

function synk_find_useraccount_by_email(mysqli $conn, string $email): ?array
{
    $sql = "
        SELECT " . implode(",\n               ", synk_useraccount_select_fields($conn)) . "
        FROM tbl_useraccount u
        LEFT JOIN tbl_college c ON c.college_id = u.college_id
        WHERE LOWER(u.email) = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $normalizedEmail = synk_normalize_email($email);
    $stmt->bind_param('s', $normalizedEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

    if ($result instanceof mysqli_result) {
        $result->close();
    }
    $stmt->close();

    return $row ?: null;
}

function synk_find_useraccount_by_id(mysqli $conn, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $sql = "
        SELECT " . implode(",\n               ", synk_useraccount_select_fields($conn)) . "
        FROM tbl_useraccount u
        LEFT JOIN tbl_college c ON c.college_id = u.college_id
        WHERE u.user_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

    if ($result instanceof mysqli_result) {
        $result->close();
    }
    $stmt->close();

    return $row ?: null;
}

function synk_student_directory_table_exists(mysqli $conn): bool
{
    static $cache = null;

    if (is_bool($cache)) {
        return $cache;
    }

    $result = $conn->query("SHOW TABLES LIKE 'tbl_student_management'");
    if (!$result instanceof mysqli_result) {
        $cache = false;
        return $cache;
    }

    $cache = $result->num_rows > 0;
    $result->close();

    return $cache;
}

function synk_student_directory_email_exists(mysqli $conn, string $email): bool
{
    $normalizedEmail = synk_normalize_email($email);
    if ($normalizedEmail === '' || !synk_student_directory_table_exists($conn)) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT student_id
        FROM tbl_student_management
        WHERE email_address = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $normalizedEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->close();
    }
    $stmt->close();

    return $exists;
}

function synk_pending_role_login_key(): string
{
    return 'pending_role_login';
}

function synk_get_pending_role_login(): ?array
{
    $pending = $_SESSION[synk_pending_role_login_key()] ?? null;
    return is_array($pending) ? $pending : null;
}

function synk_clear_pending_role_login(): void
{
    unset($_SESSION[synk_pending_role_login_key()]);
}

function synk_store_pending_role_login(array $row, array $roleRows, array $context = []): void
{
    synk_clear_pending_role_login();

    $_SESSION[synk_pending_role_login_key()] = [
        'user_id' => (int)($row['user_id'] ?? 0),
        'username' => (string)($row['username'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'college_id' => isset($row['college_id']) ? (int)$row['college_id'] : 0,
        'college_name' => (string)($row['college_name'] ?? ''),
        'primary_role' => synk_useraccount_primary_role($roleRows, (string)($row['role'] ?? '')),
        'roles' => synk_role_rows_to_payload($roleRows),
        'avatar_url' => trim((string)($context['avatar_url'] ?? '')),
    ];
}

function synk_reset_authenticated_session_context(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['email'],
        $_SESSION['role'],
        $_SESSION['available_roles'],
        $_SESSION['primary_role'],
        $_SESSION['college_id'],
        $_SESSION['college_name'],
        $_SESSION['campus_id'],
        $_SESSION['campus_name'],
        $_SESSION['scheduler_college_access'],
        $_SESSION['default_college_id'],
        $_SESSION['default_campus_id'],
        $_SESSION['user_avatar_url'],
        $_SESSION['registrar_campus_access'],
        $_SESSION['student_google_sub'],
        $_SESSION['student_login_at']
    );
}

function synk_complete_user_login(
    array $row,
    ?mysqli $conn = null,
    ?string $selectedRole = null,
    ?array $availableRoleRows = null
): string {
    if ($availableRoleRows === null) {
        if ($conn instanceof mysqli) {
            $availableRoleRows = synk_filter_loginable_role_rows(
                $conn,
                $row,
                synk_fetch_useraccount_role_rows($conn, (int)($row['user_id'] ?? 0), (string)($row['role'] ?? ''))
            );
        } else {
            $availableRoleRows = synk_useraccount_role_rows_from_fallback((string)($row['role'] ?? ''));
        }
    }

    $activeRole = strtolower(trim((string)$selectedRole));
    $allowedRoles = array_map(static function (array $roleRow): string {
        return strtolower(trim((string)($roleRow['role'] ?? '')));
    }, $availableRoleRows);

    if ($activeRole === '' || !in_array($activeRole, $allowedRoles, true)) {
        $activeRole = synk_useraccount_primary_role($availableRoleRows, (string)($row['role'] ?? ''));
    }

    if ($activeRole === '') {
        $activeRole = strtolower(trim((string)($row['role'] ?? '')));
    }

    synk_clear_pending_role_login();
    session_regenerate_id(true);
    synk_reset_authenticated_session_context();

    $_SESSION['available_roles'] = array_values(array_unique(array_filter($allowedRoles)));
    $_SESSION['primary_role'] = synk_useraccount_primary_role($availableRoleRows, (string)($row['role'] ?? ''));

    if ($activeRole === 'scheduler' && $conn instanceof mysqli) {
        $schedulerRow = $row;
        $schedulerRow['role'] = 'scheduler';
        $accessRows = synk_resolve_scheduler_access_rows(
            $conn,
            (int)($schedulerRow['user_id'] ?? 0),
            (int)($schedulerRow['college_id'] ?? 0)
        );
        synk_scheduler_store_session_scope($schedulerRow, $accessRows, (int)($schedulerRow['college_id'] ?? 0));
    } elseif ($activeRole === 'registrar' && $conn instanceof mysqli) {
        $registrarRow = $row;
        $registrarRow['role'] = 'registrar';
        $scopeRows = synk_fetch_registrar_scope_rows($conn, (int)($registrarRow['user_id'] ?? 0));
        synk_registrar_store_session_scope($registrarRow, $scopeRows, (int)($_SESSION['campus_id'] ?? 0));
    } else {
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['role'] = $activeRole;
        $_SESSION['college_id'] = $row['college_id'];
        $_SESSION['college_name'] = $row['college_name'];
    }

    return $activeRole;
}

function synk_complete_student_login(string $email, string $displayName, string $googleSub): string
{
    synk_clear_pending_role_login();
    session_regenerate_id(true);
    synk_reset_authenticated_session_context();

    $normalizedEmail = synk_normalize_email($email);
    $safeGoogleSub = trim($googleSub);
    $fallbackName = preg_replace('/@.*$/', '', $normalizedEmail);
    $safeDisplayName = trim($displayName) !== '' ? trim($displayName) : (string)$fallbackName;

    $_SESSION['user_id'] = $safeGoogleSub !== '' ? 'student:' . $safeGoogleSub : 'student:' . md5($normalizedEmail);
    $_SESSION['username'] = $safeDisplayName !== '' ? $safeDisplayName : 'Student';
    $_SESSION['email'] = $normalizedEmail;
    $_SESSION['role'] = 'student';
    $_SESSION['available_roles'] = ['student'];
    $_SESSION['primary_role'] = 'student';
    $_SESSION['college_id'] = null;
    $_SESSION['college_name'] = null;
    $_SESSION['student_google_sub'] = $safeGoogleSub;
    $_SESSION['student_login_at'] = date('c');

    return 'student';
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
    if (!$stmt) {
        return;
    }

    $types .= 'i';
    $values[] = $userId;
    synk_stmt_bind_params($stmt, $types, $values);
    $stmt->execute();
    $stmt->close();
}
