<?php
session_start();

require_once 'db.php';
require_once 'auth_config.php';
require_once 'auth_useraccount.php';
require_once 'professor_portal_helper.php';
require_once 'registrar_scope_helper.php';

$responseFormat = strtolower(trim((string)($_POST['response_format'] ?? $_GET['response_format'] ?? '')));
$wantsJsonResponse = $responseFormat === 'json';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    if ($wantsJsonResponse) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'unauthorized']);
    } else {
        echo 'unauthorized';
    }
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

function query_accounts_roles_include_program_chair(array $roles): bool
{
    return in_array('program_chair', array_map(static function ($role): string {
        return strtolower(trim((string)$role));
    }, $roles), true);
}

function query_accounts_roles_include_registrar(array $roles): bool
{
    return synk_registrar_roles_include_registrar($roles);
}

function query_accounts_program_chair_college_label(array $row): string
{
    $collegeCode = trim((string)($row['college_code'] ?? ''));
    $collegeName = trim((string)($row['college_name'] ?? ''));

    if ($collegeCode !== '' && $collegeName !== '') {
        return $collegeCode . ' - ' . $collegeName;
    }

    return $collegeName !== '' ? $collegeName : $collegeCode;
}

function query_accounts_missing_required_columns(mysqli $conn, array $requiredColumns): array
{
    $missing = [];

    foreach ($requiredColumns as $column) {
        if (!synk_useraccount_has_column($conn, $column)) {
            $missing[] = $column;
        }
    }

    return $missing;
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
        $payload['primary_role'],
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
        $payload['primary_role'],
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
    $roles = synk_normalize_supported_roles($input['roles'] ?? ($input['role'] ?? []));
    $primaryRole = strtolower(trim((string)($input['primary_role'] ?? ($input['role'] ?? ''))));
    $incomingCollegeIds = $input['college_ids'] ?? ($input['college_id'] ?? []);
    $defaultCollegeRaw = $input['default_college_id'] ?? ($input['college_id'] ?? '');
    $programChairCollegeRaw = $input['program_chair_college_id'] ?? '';
    $registrarCampusRaw = $input['registrar_campus_id'] ?? '';
    $collegeIds = synk_parse_scheduler_college_ids($incomingCollegeIds);
    $defaultCollegeId = $defaultCollegeRaw === '' || $defaultCollegeRaw === null ? null : (int)$defaultCollegeRaw;
    $programChairCollegeId = $programChairCollegeRaw === '' || $programChairCollegeRaw === null ? null : (int)$programChairCollegeRaw;
    $registrarCampusId = $registrarCampusRaw === '' || $registrarCampusRaw === null ? null : (int)$registrarCampusRaw;
    $normalizedAccess = synk_scheduler_normalize_access_payload(
        $roles,
        $collegeIds,
        $defaultCollegeId
    );

    if ($primaryRole === '' && !empty($roles)) {
        $primaryRole = $roles[0];
    }

    $payload = [
        'user_id' => (int)($input['user_id'] ?? 0),
        'username' => trim((string)($input['username'] ?? '')),
        'email' => synk_normalize_email((string)($input['email'] ?? '')),
        'roles' => $roles,
        'primary_role' => $primaryRole,
        'status' => trim((string)($input['status'] ?? 'active')),
        'college_id' => query_accounts_roles_include_program_chair($roles)
            ? ($programChairCollegeId !== null && $programChairCollegeId > 0 ? $programChairCollegeId : null)
            : $normalizedAccess['default_college_id'],
        'college_ids' => $normalizedAccess['college_ids'],
        'default_college_id' => $normalizedAccess['default_college_id'],
        'program_chair_college_id' => $programChairCollegeId !== null && $programChairCollegeId > 0 ? $programChairCollegeId : null,
        'registrar_campus_id' => $registrarCampusId !== null && $registrarCampusId > 0 ? $registrarCampusId : null,
        'faculty_id' => max(0, (int)($input['faculty_id'] ?? 0)),
        'password' => synk_build_placeholder_password(),
    ];

    if (($requireUserId && $payload['user_id'] <= 0) || $payload['email'] === '' || empty($payload['roles'])) {
        return ['error' => 'missing'];
    }

    if ($payload['username'] === '' && !synk_professor_role_selected($payload['roles'])) {
        return ['error' => 'missing'];
    }

    if ($payload['primary_role'] === '' || !in_array($payload['primary_role'], $payload['roles'], true)) {
        return ['error' => 'invalid_primary_role'];
    }

    if (!in_array($payload['status'], ['active', 'inactive'], true)) {
        return ['error' => 'invalid_status'];
    }

    if (!synk_is_allowed_email_domain($payload['email'], $allowedDomain)) {
        return ['error' => 'invalid_domain'];
    }

    if (synk_scheduler_roles_include_scheduler($payload['roles']) && empty($payload['college_ids'])) {
        return ['error' => 'need_college'];
    }

    if (query_accounts_roles_include_program_chair($payload['roles']) && (int)($payload['program_chair_college_id'] ?? 0) <= 0) {
        return ['error' => 'need_program_chair_college'];
    }

    if (query_accounts_roles_include_registrar($payload['roles']) && (int)($payload['registrar_campus_id'] ?? 0) <= 0) {
        return ['error' => 'need_registrar_campus'];
    }

    if (synk_professor_role_selected($payload['roles']) && (int)$payload['faculty_id'] <= 0) {
        return ['error' => 'need_faculty'];
    }

    return ['payload' => $payload];
}

function query_accounts_apply_professor_identity(mysqli $conn, array $payload): array
{
    if (!synk_professor_role_selected($payload['roles'] ?? [])) {
        return ['payload' => $payload];
    }

    $facultyRecord = synk_professor_fetch_faculty_record_by_id($conn, (int)($payload['faculty_id'] ?? 0), true);
    if (!is_array($facultyRecord)) {
        return ['error' => 'invalid_faculty'];
    }

    $facultyName = trim((string)($facultyRecord['faculty_name'] ?? ''));
    if ($facultyName === '') {
        return ['error' => 'invalid_faculty'];
    }

    $payload['username'] = $facultyName;

    return ['payload' => $payload];
}

function query_accounts_render_college_access_html(array $accessRows): string
{
    if (empty($accessRows)) {
        return "<span class='text-muted'>N/A</span>";
    }

    $chunks = [];
    foreach ($accessRows as $row) {
        $label = htmlspecialchars((string)($row['display_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $defaultBadge = !empty($row['is_default'])
            ? " <span class='badge bg-label-primary ms-1'>Default</span>"
            : "";

        $chunks[] = "<div class='small text-wrap mb-1'>{$label}{$defaultBadge}</div>";
    }

    return implode('', $chunks);
}

function query_accounts_render_registrar_scope_html(array $scopeRows): string
{
    if (empty($scopeRows)) {
        return '';
    }

    $chunks = [];
    foreach ($scopeRows as $row) {
        $label = htmlspecialchars((string)($row['display_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        if ($label === '') {
            continue;
        }

        $defaultBadge = !empty($row['is_default'])
            ? " <span class='badge bg-label-primary ms-1'>Default</span>"
            : '';

        $chunks[] = "<div class='small text-wrap mb-1'>{$label} <span class='badge bg-label-danger ms-1'>Registrar</span>{$defaultBadge}</div>";
    }

    return implode('', $chunks);
}

function query_accounts_role_payload(array $roleRows, string $fallbackRole = ''): array
{
    $primaryRole = synk_useraccount_primary_role($roleRows, $fallbackRole);
    $payload = [];

    foreach ($roleRows as $row) {
        $role = strtolower(trim((string)($row['role'] ?? '')));
        if ($role === '') {
            continue;
        }

        $payload[] = [
            'role' => $role,
            'label' => synk_role_label($role),
            'is_primary' => $role === $primaryRole,
        ];
    }

    return $payload;
}

function query_accounts_role_text(array $roleRows, string $fallbackRole = ''): string
{
    $parts = [];

    foreach (query_accounts_role_payload($roleRows, $fallbackRole) as $role) {
        $label = (string)($role['label'] ?? '');
        if ($label === '') {
            continue;
        }

        if (!empty($role['is_primary'])) {
            $label .= ' (Default)';
        }

        $parts[] = $label;
    }

    return !empty($parts) ? implode(' + ', $parts) : synk_role_label($fallbackRole);
}

function query_accounts_payload_from_row(array $row, array $roleRows, array $accessRows, array $registrarScopeRows = [], ?array $facultyLink = null): array
{
    $primaryRole = synk_useraccount_primary_role($roleRows, (string)($row['role'] ?? ''));
    $programChairCollegeLabel = query_accounts_program_chair_college_label($row);
    $defaultRegistrarCampusId = synk_registrar_default_campus_id($registrarScopeRows);
    $defaultRegistrarScopeRow = synk_registrar_scope_row_for_campus($registrarScopeRows, $defaultRegistrarCampusId);

    return [
        'id' => (int)($row['user_id'] ?? 0),
        'username' => (string)($row['username'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'provider' => (string)($row['auth_provider'] ?? 'legacy'),
        'role' => $primaryRole,
        'role_label' => query_accounts_role_text($roleRows, (string)($row['role'] ?? '')),
        'roles' => array_values(array_map(static function (array $item): string {
            return (string)($item['role'] ?? '');
        }, $roleRows)),
        'role_payload' => query_accounts_role_payload($roleRows, (string)($row['role'] ?? '')),
        'primary_role' => $primaryRole,
        'primary_role_label' => synk_role_label($primaryRole),
        'status' => (string)($row['status'] ?? ''),
        'college_ids' => array_values(array_map(static function (array $item): int {
            return (int)($item['college_id'] ?? 0);
        }, $accessRows)),
        'default_college_id' => synk_scheduler_default_college_id($accessRows),
        'college_access' => array_values(array_map(static function (array $item): array {
            return [
                'college_id' => (int)($item['college_id'] ?? 0),
                'display_label' => (string)($item['display_label'] ?? ''),
                'is_default' => !empty($item['is_default'])
            ];
        }, $accessRows)),
        'program_chair_college_id' => (int)($row['college_id'] ?? 0),
        'program_chair_college_label' => $programChairCollegeLabel,
        'registrar_campus_id' => $defaultRegistrarCampusId,
        'registrar_campus_label' => (string)($defaultRegistrarScopeRow['display_label'] ?? ''),
        'registrar_scope' => array_values(array_map(static function (array $item): array {
            return [
                'campus_id' => (int)($item['campus_id'] ?? 0),
                'display_label' => (string)($item['display_label'] ?? ''),
                'is_default' => !empty($item['is_default']),
            ];
        }, $registrarScopeRows)),
        'faculty_id' => (int)($facultyLink['faculty_id'] ?? 0),
        'faculty_label' => (string)($facultyLink['faculty_name'] ?? ''),
        'faculty_status' => (string)($facultyLink['faculty_status'] ?? ''),
    ];
}

if (isset($_POST['load_accounts'])) {
    $fields = ['u.*', 'c.college_code', 'c.college_name'];
    $sql = "
        SELECT " . implode(', ', $fields) . "
        FROM tbl_useraccount u
        LEFT JOIN tbl_college c ON u.college_id = c.college_id
        ORDER BY u.user_id DESC
    ";

    $res = $conn->query($sql);
    $rows = [];
    $fallbackRolesByUser = [];

    while ($res instanceof mysqli_result && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
        $fallbackRolesByUser[(int)($row['user_id'] ?? 0)] = (string)($row['role'] ?? '');
    }

    if ($res instanceof mysqli_result) {
        $res->close();
    }

    $roleRowsByUser = synk_fetch_useraccount_role_rows_bulk($conn, $fallbackRolesByUser);
    $schedulerFallbackCollegeMap = [];

    foreach ($rows as $row) {
        $userId = (int)($row['user_id'] ?? 0);
        $roleRows = $roleRowsByUser[$userId] ?? synk_useraccount_role_rows_from_fallback((string)($row['role'] ?? ''));

        if (synk_scheduler_roles_include_scheduler(array_map(static function (array $roleRow): string {
            return (string)($roleRow['role'] ?? '');
        }, $roleRows))) {
            $schedulerFallbackCollegeMap[$userId] = (int)($row['college_id'] ?? 0);
        }
    }

    $schedulerAccessByUser = synk_resolve_scheduler_access_rows_bulk($conn, $schedulerFallbackCollegeMap);
    $registrarScopesByUser = synk_fetch_registrar_scope_rows_bulk($conn, array_keys($fallbackRolesByUser));
    $facultyLinksByUser = synk_professor_fetch_faculty_links_bulk($conn, array_keys($fallbackRolesByUser));

    if ($wantsJsonResponse) {
        header('Content-Type: application/json; charset=utf-8');

        $accounts = [];
        foreach ($rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            $roleRows = $roleRowsByUser[$userId] ?? synk_useraccount_role_rows_from_fallback((string)($row['role'] ?? ''));
            $accessRows = $schedulerAccessByUser[$userId] ?? [];
            $registrarScopeRows = $registrarScopesByUser[$userId] ?? [];
            $facultyLink = $facultyLinksByUser[$userId] ?? null;
            $accounts[] = query_accounts_payload_from_row($row, $roleRows, $accessRows, $registrarScopeRows, $facultyLink);
        }

        echo json_encode([
            'status' => 'ok',
            'accounts' => $accounts
        ]);
        exit;
    }

    $i = 1;

    foreach ($rows as $row) {
        $userId = (int)($row['user_id'] ?? 0);
        $roleRows = $roleRowsByUser[$userId] ?? synk_useraccount_role_rows_from_fallback((string)($row['role'] ?? ''));
        $providerBadge = query_accounts_provider_badge($row['auth_provider'] ?? 'legacy');
        $accessRows = $schedulerAccessByUser[$userId] ?? [];
        $registrarScopeRows = $registrarScopesByUser[$userId] ?? [];
        $facultyLink = $facultyLinksByUser[$userId] ?? null;
        $collegeIds = array_values(array_map(static function (array $item): int {
            return (int)($item['college_id'] ?? 0);
        }, $accessRows));
        $defaultCollegeId = synk_scheduler_default_college_id($accessRows);
        $collegeLabel = query_accounts_render_college_access_html($accessRows);
        $collegeIdsJson = htmlspecialchars(json_encode($collegeIds), ENT_QUOTES, 'UTF-8');
        $facultyId = (int)($facultyLink['faculty_id'] ?? 0);
        $facultyName = htmlspecialchars((string)($facultyLink['faculty_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $roleValues = array_values(array_map(static function (array $roleRow): string {
            return (string)($roleRow['role'] ?? '');
        }, $roleRows));
        $hasProfessorRole = in_array('professor', $roleValues, true);
        $hasProgramChairRole = in_array('program_chair', $roleValues, true);
        $hasRegistrarRole = in_array('registrar', $roleValues, true);
        $programChairCollegeId = (int)($row['college_id'] ?? 0);
        $programChairCollegeLabel = htmlspecialchars(query_accounts_program_chair_college_label($row), ENT_QUOTES, 'UTF-8');
        $registrarCampusId = synk_registrar_default_campus_id($registrarScopeRows);
        $registrarScopeHtml = $hasRegistrarRole ? query_accounts_render_registrar_scope_html($registrarScopeRows) : '';
        $facultyLabel = $hasProfessorRole
            ? ($facultyName !== '' ? $facultyName : "<span class='text-warning'>Not linked</span>")
            : "<span class='text-muted'>N/A</span>";
        $scopeParts = [];
        if (!empty($accessRows)) {
            $scopeParts[] = query_accounts_render_college_access_html($accessRows);
        }
        if ($hasProgramChairRole && $programChairCollegeId > 0 && $programChairCollegeLabel !== '') {
            $scopeParts[] = "<div class='small text-wrap mb-1'>{$programChairCollegeLabel} <span class='badge bg-label-info ms-1'>Assigned</span></div>";
        }
        if ($registrarScopeHtml !== '') {
            $scopeParts[] = $registrarScopeHtml;
        }
        if (!empty($scopeParts)) {
            $collegeLabel = implode('', $scopeParts);
        } else {
            $collegeLabel = "<span class='text-muted'>N/A</span>";
        }
        $rolesJson = htmlspecialchars(json_encode(array_values(array_map(static function (array $roleRow): string {
            return (string)($roleRow['role'] ?? '');
        }, $roleRows))), ENT_QUOTES, 'UTF-8');
        $defaultCollegeAttr = htmlspecialchars((string)$defaultCollegeId, ENT_QUOTES, 'UTF-8');
        $primaryRole = synk_useraccount_primary_role($roleRows, (string)($row['role'] ?? ''));
        $roleLabel = query_accounts_role_text($roleRows, (string)($row['role'] ?? ''));

        $badge = (($row['status'] ?? '') === 'active')
            ? "<span class='badge bg-success'>ACTIVE</span>"
            : "<span class='badge bg-secondary'>INACTIVE</span>";

        echo "
        <tr>
          <td>{$i}</td>
          <td>" . htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') . "</td>
          <td>" . htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8') . "</td>
          <td>{$providerBadge}</td>
          <td>" . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . "</td>
          <td>{$facultyLabel}</td>
          <td>{$collegeLabel}</td>
          <td>{$badge}</td>
          <td class='text-end text-nowrap'>
            <button class='btn btn-sm btn-warning btnEditAccount'
                data-id='{$userId}'
                data-username=\"" . htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') . "\"
                data-email=\"" . htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8') . "\"
                data-roles='{$rolesJson}'
                data-primary-role='" . htmlspecialchars($primaryRole, ENT_QUOTES, 'UTF-8') . "'
                data-college-ids='{$collegeIdsJson}'
                data-default-college='{$defaultCollegeAttr}'
                data-program-chair-college='" . htmlspecialchars((string)$programChairCollegeId, ENT_QUOTES, 'UTF-8') . "'
                data-registrar-campus='" . htmlspecialchars((string)$registrarCampusId, ENT_QUOTES, 'UTF-8') . "'
                data-faculty-id='{$facultyId}'
                data-faculty-label=\"{$facultyName}\"
                data-status='" . htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8') . "'>
              <i class='bx bx-edit-alt'></i>
            </button>

            <button class='btn btn-sm btn-danger btnDeleteAccount'
                data-id='{$userId}'>
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
    $missingColumns = query_accounts_missing_required_columns(
        $conn,
        ['username', 'email', 'password', 'role', 'college_id', 'status']
    );
    if (!empty($missingColumns)) {
        echo 'schema_error';
        exit;
    }

    $validation = query_accounts_validate_payload($_POST, $allowedDomain, false);
    if (isset($validation['error'])) {
        echo $validation['error'];
        exit;
    }

    $payload = $validation['payload'];
    $professorIdentity = query_accounts_apply_professor_identity($conn, $payload);
    if (isset($professorIdentity['error'])) {
        echo $professorIdentity['error'];
        exit;
    }
    $payload = $professorIdentity['payload'];

    $q = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE email = ? LIMIT 1");
    $q->bind_param('s', $payload['email']);
    $q->execute();
    $q->store_result();
    if ($q->num_rows > 0) {
        $q->close();
        echo 'dup_email';
        exit;
    }
    $q->close();

    $conn->begin_transaction();

    try {
        [$sql, $types, $values] = query_accounts_build_insert($conn, $payload);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('save_failed');
        }

        synk_stmt_bind_params($stmt, $types, $values);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('save_failed');
        }

        $newUserId = (int)$conn->insert_id;
        $stmt->close();

        $roleError = synk_persist_useraccount_roles(
            $conn,
            $newUserId,
            $payload['roles'],
            $payload['primary_role']
        );
        if ($roleError !== null) {
            throw new RuntimeException($roleError);
        }

        $accessError = synk_persist_scheduler_college_access(
            $conn,
            $newUserId,
            $payload['roles'],
            $payload['college_ids'],
            $payload['default_college_id']
        );

        if ($accessError !== null) {
            throw new RuntimeException($accessError);
        }

        $facultyError = synk_persist_professor_faculty_link(
            $conn,
            $newUserId,
            $payload['roles'],
            $payload['faculty_id']
        );
        if ($facultyError !== null) {
            throw new RuntimeException($facultyError);
        }

        $registrarScopeError = synk_persist_registrar_scope(
            $conn,
            $newUserId,
            $payload['roles'],
            $payload['registrar_campus_id']
        );
        if ($registrarScopeError !== null) {
            throw new RuntimeException($registrarScopeError);
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        echo $e->getMessage();
        exit;
    }

    echo 'success';
    exit;
}

if (isset($_POST['update_account'])) {
    $missingColumns = query_accounts_missing_required_columns(
        $conn,
        ['user_id', 'username', 'email', 'role', 'college_id', 'status']
    );
    if (!empty($missingColumns)) {
        echo 'schema_error';
        exit;
    }

    $validation = query_accounts_validate_payload($_POST, $allowedDomain, true);
    if (isset($validation['error'])) {
        echo $validation['error'];
        exit;
    }

    $payload = $validation['payload'];
    $professorIdentity = query_accounts_apply_professor_identity($conn, $payload);
    if (isset($professorIdentity['error'])) {
        echo $professorIdentity['error'];
        exit;
    }
    $payload = $professorIdentity['payload'];

    $q = $conn->prepare("SELECT user_id FROM tbl_useraccount WHERE email = ? AND user_id <> ? LIMIT 1");
    $q->bind_param('si', $payload['email'], $payload['user_id']);
    $q->execute();
    $q->store_result();
    if ($q->num_rows > 0) {
        $q->close();
        echo 'dup_email';
        exit;
    }
    $q->close();

    $conn->begin_transaction();

    try {
        [$sql, $types, $values] = query_accounts_build_update($conn, $payload);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('save_failed');
        }

        synk_stmt_bind_params($stmt, $types, $values);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('save_failed');
        }
        $stmt->close();

        $roleError = synk_persist_useraccount_roles(
            $conn,
            (int)$payload['user_id'],
            $payload['roles'],
            $payload['primary_role']
        );
        if ($roleError !== null) {
            throw new RuntimeException($roleError);
        }

        $accessError = synk_persist_scheduler_college_access(
            $conn,
            (int)$payload['user_id'],
            $payload['roles'],
            $payload['college_ids'],
            $payload['default_college_id']
        );

        if ($accessError !== null) {
            throw new RuntimeException($accessError);
        }

        $facultyError = synk_persist_professor_faculty_link(
            $conn,
            (int)$payload['user_id'],
            $payload['roles'],
            $payload['faculty_id']
        );
        if ($facultyError !== null) {
            throw new RuntimeException($facultyError);
        }

        $registrarScopeError = synk_persist_registrar_scope(
            $conn,
            (int)$payload['user_id'],
            $payload['roles'],
            $payload['registrar_campus_id']
        );
        if ($registrarScopeError !== null) {
            throw new RuntimeException($registrarScopeError);
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        echo $e->getMessage();
        exit;
    }

    echo 'success';
    exit;
}

if (isset($_POST['delete_account'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        echo 'missing';
        exit;
    }

    $conn->begin_transaction();

    try {
        if (synk_scheduler_access_table_exists($conn)) {
            $cleanup = $conn->prepare("DELETE FROM tbl_user_college_access WHERE user_id = ?");
            if (!$cleanup) {
                throw new RuntimeException('delete_failed');
            }

            $cleanup->bind_param('i', $userId);
            if (!$cleanup->execute()) {
                $cleanup->close();
                throw new RuntimeException('delete_failed');
            }
            $cleanup->close();
        }

        if (synk_useraccount_role_table_exists($conn)) {
            $roleCleanup = $conn->prepare("DELETE FROM `" . synk_useraccount_role_table_name() . "` WHERE user_id = ?");
            if (!$roleCleanup) {
                throw new RuntimeException('delete_failed');
            }

            $roleCleanup->bind_param('i', $userId);
            if (!$roleCleanup->execute()) {
                $roleCleanup->close();
                throw new RuntimeException('delete_failed');
            }
            $roleCleanup->close();
        }

        if (synk_professor_faculty_link_table_exists($conn)) {
            $facultyCleanup = $conn->prepare("DELETE FROM `" . synk_professor_faculty_link_table_name() . "` WHERE user_id = ?");
            if (!$facultyCleanup) {
                throw new RuntimeException('delete_failed');
            }

            $facultyCleanup->bind_param('i', $userId);
            if (!$facultyCleanup->execute()) {
                $facultyCleanup->close();
                throw new RuntimeException('delete_failed');
            }
            $facultyCleanup->close();
        }

        if (synk_registrar_scope_table_exists($conn)) {
            $registrarCleanup = $conn->prepare("DELETE FROM `" . synk_registrar_scope_table_name() . "` WHERE user_id = ?");
            if (!$registrarCleanup) {
                throw new RuntimeException('delete_failed');
            }

            $registrarCleanup->bind_param('i', $userId);
            if (!$registrarCleanup->execute()) {
                $registrarCleanup->close();
                throw new RuntimeException('delete_failed');
            }
            $registrarCleanup->close();
        }

        $stmt = $conn->prepare("DELETE FROM tbl_useraccount WHERE user_id = ?");
        if (!$stmt) {
            throw new RuntimeException('delete_failed');
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('delete_failed');
        }
        $stmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        echo 'delete_failed';
        exit;
    }

    echo 'deleted';
    exit;
}
