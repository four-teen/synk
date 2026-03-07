<?php
session_start();
ob_start();

require_once 'db.php';
require_once __DIR__ . '/academic_term_helper.php';
require_once __DIR__ . '/offering_scope_helper.php';

header('Content-Type: application/json');

function query_college_faculty_designation_style(int $designationId, string $designationName): string
{
    $designationName = trim($designationName);
    if ($designationName === '') {
        return '';
    }

    $seed = ($designationId > 0) ? $designationId : (int)sprintf('%u', crc32(strtolower($designationName)));
    $hue = (($seed * 47) + 17) % 360;

    return "background-color:hsla({$hue}, 78%, 91%, 0.95);color:hsla({$hue}, 72%, 32%, 1);border-color:hsla({$hue}, 68%, 78%, 1);";
}

function query_college_faculty_describe_columns(mysqli $conn, string $tableName): array
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `{$tableName}`");

    if (!($result instanceof mysqli_result)) {
        return $columns;
    }

    while ($row = $result->fetch_assoc()) {
        $fieldName = strtolower((string)($row['Field'] ?? ''));
        if ($fieldName !== '') {
            $columns[$fieldName] = $row;
        }
    }

    return $columns;
}

function query_college_faculty_schema_info(mysqli $conn): array
{
    $facultyColumns = query_college_faculty_describe_columns($conn, 'tbl_faculty');
    $assignmentColumns = query_college_faculty_describe_columns($conn, 'tbl_college_faculty');

    $designationTableResult = $conn->query("SHOW TABLES LIKE 'tbl_designation'");
    $hasDesignationTable = ($designationTableResult instanceof mysqli_result && $designationTableResult->num_rows > 0);
    $designationColumns = $hasDesignationTable
        ? query_college_faculty_describe_columns($conn, 'tbl_designation')
        : [];

    $designationTextColumn = null;
    foreach (['designation', 'designation_name'] as $candidate) {
        if (isset($facultyColumns[$candidate])) {
            $designationTextColumn = $candidate;
            break;
        }
    }

    return [
        'has_middle_name' => isset($facultyColumns['middle_name']),
        'has_ext_name' => isset($facultyColumns['ext_name']),
        'has_designation_id' => isset($facultyColumns['designation_id']),
        'designation_text_column' => $designationTextColumn,
        'designation_table_exists' => $hasDesignationTable,
        'designation_table_has_name' => isset($designationColumns['designation_name']),
        'assignment_has_ay_id' => isset($assignmentColumns['ay_id']),
        'assignment_has_semester' => isset($assignmentColumns['semester']),
    ];
}

function query_college_faculty_term_ready(array $schema): bool
{
    return $schema['assignment_has_ay_id'] && $schema['assignment_has_semester'];
}

function query_college_faculty_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || count($params) === 0) {
        return true;
    }

    $bindParams = [$types];
    foreach ($params as $index => &$value) {
        $bindParams[] = &$value;
    }

    return call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

function query_college_faculty_format_name(array $row): string
{
    $fullName = trim((string)($row['last_name'] ?? '')) . ', ' . trim((string)($row['first_name'] ?? ''));

    $middleName = trim((string)($row['middle_name'] ?? ''));
    if ($middleName !== '') {
        $fullName .= ' ' . strtoupper(substr($middleName, 0, 1)) . '.';
    }

    $extName = trim((string)($row['ext_name'] ?? ''));
    if ($extName !== '') {
        $fullName .= ', ' . $extName;
    }

    return trim($fullName, " ,");
}

if (isset($_POST['load_college_faculty'])) {
    $college_id = (int)($_POST['college_id'] ?? 0);
    $page = max(1, (int)($_POST['page'] ?? 1));
    $pageSize = (int)($_POST['page_size'] ?? 20);
    $pageSize = max(10, min(50, $pageSize));
    $offset = ($page - 1) * $pageSize;
    $search = trim((string)($_POST['search'] ?? ''));

    $currentTerm = synk_fetch_current_academic_term($conn);
    $currentAyId = (int)$currentTerm['ay_id'];
    $currentSemester = (int)$currentTerm['semester'];

    $response = [
        'status' => 'success',
        'data' => [],
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => 0,
            'has_more' => false,
        ],
        'term' => [
            'ay_id' => $currentAyId,
            'semester' => $currentSemester,
            'ay_label' => (string)($currentTerm['ay_label'] ?? ''),
            'semester_label' => (string)($currentTerm['semester_label'] ?? ''),
            'term_text' => (string)($currentTerm['term_text'] ?? 'Current academic term'),
        ],
    ];

    if ($college_id <= 0) {
        echo json_encode($response);
        exit;
    }

    $schema = query_college_faculty_schema_info($conn);
    $termReady = query_college_faculty_term_ready($schema);

    $selectParts = [
        'cf.college_faculty_id',
        'f.faculty_id',
        'f.last_name',
        'f.first_name',
        $schema['has_middle_name'] ? 'f.middle_name' : 'NULL AS middle_name',
        $schema['has_ext_name'] ? 'f.ext_name' : 'NULL AS ext_name',
        "LOWER(TRIM(cf.status)) AS status",
    ];

    $joinSql = '';
    $designationNameExpr = "''";
    $facultyDesignationExpr = 'NULL';

    if ($schema['designation_text_column'] !== null) {
        $designationColumn = $schema['designation_text_column'];
        $facultyDesignationExpr = "NULLIF(TRIM(f.`{$designationColumn}`), '')";
    }

    if ($schema['has_designation_id']) {
        $selectParts[] = 'f.designation_id';
    } else {
        $selectParts[] = 'NULL AS designation_id';
    }

    if (
        $schema['has_designation_id']
        && $schema['designation_table_exists']
        && $schema['designation_table_has_name']
    ) {
        $joinSql .= "\n        LEFT JOIN tbl_designation d ON d.designation_id = f.designation_id";
        $designationNameExpr = "COALESCE(NULLIF(TRIM(d.designation_name), ''), {$facultyDesignationExpr}, '')";
    } elseif ($facultyDesignationExpr !== 'NULL') {
        $designationNameExpr = "COALESCE({$facultyDesignationExpr}, '')";
    }

    $selectParts[] = "{$designationNameExpr} AS designation_name";

    $nameSearchExpr = "CONCAT_WS(' ', f.last_name, f.first_name, "
        . ($schema['has_middle_name'] ? 'f.middle_name' : 'NULL')
        . ', '
        . ($schema['has_ext_name'] ? 'f.ext_name' : 'NULL')
        . ')';

    $whereParts = ['cf.college_id = ?'];
    $whereParams = [$college_id];
    $whereTypes = 'i';

    if ($termReady && $currentAyId > 0 && $currentSemester > 0) {
        $whereParts[] = '((cf.ay_id = ? AND cf.semester = ?) OR (cf.ay_id IS NULL AND cf.semester IS NULL))';
        $whereParams[] = $currentAyId;
        $whereParams[] = $currentSemester;
        $whereTypes .= 'ii';
    }

    if ($search !== '') {
        $like = '%' . $search . '%';
        $whereParts[] = "(
            {$nameSearchExpr} LIKE ?
            OR {$designationNameExpr} LIKE ?
            OR cf.status LIKE ?
        )";
        $whereParams[] = $like;
        $whereParams[] = $like;
        $whereParams[] = $like;
        $whereTypes .= 'sss';
    }

    $baseTableSql = "
        FROM tbl_college_faculty cf
        INNER JOIN tbl_faculty f ON cf.faculty_id = f.faculty_id{$joinSql}
    ";
    $whereSql = 'WHERE ' . implode(' AND ', $whereParts);

    $countSql = "SELECT COUNT(*) AS total {$baseTableSql} {$whereSql}";
    $countStmt = $conn->prepare($countSql);

    if (!($countStmt instanceof mysqli_stmt)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unable to prepare faculty count query.',
        ]);
        exit;
    }

    query_college_faculty_bind_params($countStmt, $whereTypes, $whereParams);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRows = 0;

    if ($countResult instanceof mysqli_result) {
        $countRow = $countResult->fetch_assoc();
        $totalRows = (int)($countRow['total'] ?? 0);
    }

    $countStmt->close();

    $assignedSelect = '0 AS assigned_count';
    $assignedJoinSql = '';
    $assignedParams = [];
    $assignedTypes = '';

    if ($currentAyId > 0 && $currentSemester > 0) {
        $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');
        $assignedSelect = 'COALESCE(term_assignments.assigned_count, 0) AS assigned_count';
        $assignedJoinSql = "
        LEFT JOIN (
            SELECT
                fw.faculty_id,
                COUNT(DISTINCT fw.schedule_id) AS assigned_count
            FROM tbl_faculty_workload_sched fw
            INNER JOIN tbl_class_schedule cs ON cs.schedule_id = fw.schedule_id
            INNER JOIN tbl_prospectus_offering o ON o.offering_id = cs.offering_id
            {$liveOfferingJoins}
            INNER JOIN tbl_program p ON p.program_id = o.program_id
            WHERE fw.ay_id = ?
              AND fw.semester = ?
              AND o.ay_id = ?
              AND o.semester = ?
              AND p.college_id = ?
            GROUP BY fw.faculty_id
        ) term_assignments ON term_assignments.faculty_id = f.faculty_id";
        $assignedParams = [$currentAyId, $currentSemester, $currentAyId, $currentSemester, $college_id];
        $assignedTypes = 'iiiii';
    }

    $dataSql = "
        SELECT
            " . implode(",\n            ", $selectParts) . ",
            {$assignedSelect}
        {$baseTableSql}
        {$assignedJoinSql}
        {$whereSql}
        ORDER BY
            CASE WHEN LOWER(TRIM(cf.status)) = 'active' THEN 0 ELSE 1 END,
            f.last_name ASC,
            f.first_name ASC,
            f.faculty_id ASC
        LIMIT ? OFFSET ?
    ";

    $dataStmt = $conn->prepare($dataSql);

    if (!($dataStmt instanceof mysqli_stmt)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unable to prepare faculty list query.',
        ]);
        exit;
    }

    $dataParams = array_merge($assignedParams, $whereParams, [$pageSize, $offset]);
    $dataTypes = $assignedTypes . $whereTypes . 'ii';
    query_college_faculty_bind_params($dataStmt, $dataTypes, $dataParams);
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();

    if ($dataResult instanceof mysqli_result) {
        while ($row = $dataResult->fetch_assoc()) {
            $designationName = trim((string)($row['designation_name'] ?? ''));
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($status !== 'inactive') {
                $status = 'active';
            }

            $response['data'][] = [
                'college_faculty_id' => (int)($row['college_faculty_id'] ?? 0),
                'faculty_id' => (int)($row['faculty_id'] ?? 0),
                'full_name' => query_college_faculty_format_name($row),
                'designation_id' => (int)($row['designation_id'] ?? 0),
                'designation_name' => $designationName,
                'designation_style' => query_college_faculty_designation_style((int)($row['designation_id'] ?? 0), $designationName),
                'status' => $status,
                'assigned_count' => (int)($row['assigned_count'] ?? 0),
            ];
        }
    }

    $dataStmt->close();

    $response['pagination']['total'] = $totalRows;
    $response['pagination']['has_more'] = ($offset + count($response['data'])) < $totalRows;

    echo json_encode($response);
    exit;
}

if (isset($_POST['load_faculty_dropdown'])) {
    $college_id = (int)($_POST['college_id'] ?? 0);
    $data = [];
    $schema = query_college_faculty_schema_info($conn);
    $currentTerm = synk_fetch_current_academic_term($conn);
    $currentAyId = (int)$currentTerm['ay_id'];
    $currentSemester = (int)$currentTerm['semester'];
    $termReady = query_college_faculty_term_ready($schema);

    $selectSql = "
        SELECT
            f.faculty_id,
            f.last_name,
            f.first_name,
            " . ($schema['has_middle_name'] ? 'f.middle_name' : 'NULL AS middle_name') . ",
            " . ($schema['has_ext_name'] ? 'f.ext_name' : 'NULL AS ext_name') . "
        FROM tbl_faculty f
    ";

    $types = '';
    $params = [];

    if ($college_id > 0) {
        $selectSql .= "
            LEFT JOIN tbl_college_faculty cf
                ON cf.faculty_id = f.faculty_id
               AND cf.college_id = ?
               AND LOWER(TRIM(cf.status)) = 'active'
        ";
        $types .= 'i';
        $params[] = $college_id;

        if ($termReady && $currentAyId > 0 && $currentSemester > 0) {
            $selectSql .= "
               AND ((cf.ay_id = ? AND cf.semester = ?) OR (cf.ay_id IS NULL AND cf.semester IS NULL))
            ";
            $types .= 'ii';
            $params[] = $currentAyId;
            $params[] = $currentSemester;
        }
    }

    $selectSql .= "
        WHERE f.status = 'active'
    ";

    if ($college_id > 0) {
        $selectSql .= "
          AND cf.college_faculty_id IS NULL
        ";
    }

    $selectSql .= "
        ORDER BY f.last_name ASC, f.first_name ASC
    ";

    $stmt = $conn->prepare($selectSql);
    if (!($stmt instanceof mysqli_stmt)) {
        echo json_encode($data);
        exit;
    }

    query_college_faculty_bind_params($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                'id' => (int)$row['faculty_id'],
                'text' => query_college_faculty_format_name($row),
            ];
        }
    }

    $stmt->close();

    echo json_encode($data);
    exit;
}

if (isset($_POST['save_assignment'])) {
    $college_id = (int)($_POST['college_id'] ?? 0);
    $faculty_id = (int)($_POST['faculty_id'] ?? 0);
    $schema = query_college_faculty_schema_info($conn);
    $termReady = query_college_faculty_term_ready($schema);
    $currentTerm = synk_fetch_current_academic_term($conn);
    $currentAyId = (int)$currentTerm['ay_id'];
    $currentSemester = (int)$currentTerm['semester'];

    if ($college_id <= 0 || $faculty_id <= 0) {
        echo json_encode(['status' => 'invalid']);
        exit;
    }

    if ($termReady && ($currentAyId <= 0 || $currentSemester <= 0)) {
        echo json_encode(['status' => 'error']);
        exit;
    }

    if ($termReady) {
        $check = $conn->prepare("
            SELECT college_faculty_id, LOWER(TRIM(status)) AS status
            FROM tbl_college_faculty
            WHERE college_id = ? AND faculty_id = ?
              AND ((ay_id = ? AND semester = ?) OR (ay_id IS NULL AND semester IS NULL))
            LIMIT 1
        ");
    } else {
        $check = $conn->prepare("
            SELECT college_faculty_id, LOWER(TRIM(status)) AS status
            FROM tbl_college_faculty
            WHERE college_id = ? AND faculty_id = ?
            LIMIT 1
        ");
    }

    if (!($check instanceof mysqli_stmt)) {
        echo json_encode(['status' => 'error']);
        exit;
    }

    if ($termReady) {
        $check->bind_param('iiii', $college_id, $faculty_id, $currentAyId, $currentSemester);
    } else {
        $check->bind_param('ii', $college_id, $faculty_id);
    }
    $check->execute();
    $checkResult = $check->get_result();
    $existing = ($checkResult instanceof mysqli_result) ? $checkResult->fetch_assoc() : null;
    $check->close();

    if (is_array($existing)) {
        $existingStatus = strtolower(trim((string)($existing['status'] ?? '')));

        if ($existingStatus === 'active') {
            echo json_encode(['status' => 'duplicate']);
            exit;
        }

        $reactivateStmt = $conn->prepare("
            UPDATE tbl_college_faculty
            SET status = 'active'
            WHERE college_faculty_id = ?
        ");

        if (!($reactivateStmt instanceof mysqli_stmt)) {
            echo json_encode(['status' => 'error']);
            exit;
        }

        $collegeFacultyId = (int)$existing['college_faculty_id'];
        $reactivateStmt->bind_param('i', $collegeFacultyId);

        if ($reactivateStmt->execute()) {
            echo json_encode(['status' => 'reactivated']);
        } else {
            echo json_encode(['status' => 'error']);
        }

        $reactivateStmt->close();
        exit;
    }

    if ($termReady) {
        $stmt = $conn->prepare("
            INSERT INTO tbl_college_faculty (college_id, faculty_id, ay_id, semester, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO tbl_college_faculty (college_id, faculty_id, status)
            VALUES (?, ?, 'active')
        ");
    }

    if (!($stmt instanceof mysqli_stmt)) {
        echo json_encode(['status' => 'error']);
        exit;
    }

    if ($termReady) {
        $stmt->bind_param('iiii', $college_id, $faculty_id, $currentAyId, $currentSemester);
    } else {
        $stmt->bind_param('ii', $college_id, $faculty_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'inserted']);
    } else {
        echo json_encode(['status' => 'error']);
    }

    $stmt->close();
    exit;
}

if (isset($_POST['remove_assignment'])) {
    $college_faculty_id = (int)($_POST['college_faculty_id'] ?? 0);

    if ($college_faculty_id <= 0) {
        echo json_encode(['status' => 'invalid']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE tbl_college_faculty
        SET status = 'inactive'
        WHERE college_faculty_id = ?
    ");

    if (!($stmt instanceof mysqli_stmt)) {
        echo json_encode(['status' => 'error']);
        exit;
    }

    $stmt->bind_param('i', $college_faculty_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'removed']);
    } else {
        echo json_encode(['status' => 'error']);
    }

    $stmt->close();
    exit;
}

echo json_encode([
    'status' => 'invalid_request',
]);
