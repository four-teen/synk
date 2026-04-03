<?php

require_once __DIR__ . '/student_management_helper.php';
require_once __DIR__ . '/academic_term_helper.php';

function synk_student_management_enrollment_table_name(): string
{
    return 'tbl_student_management_enrolled_subjects';
}

function synk_student_management_exam_permits_table_name(): string
{
    return synk_student_management_enrollment_table_name();
}

function synk_student_management_legacy_exam_permits_table_name(): string
{
    return 'tbl_student_management_exam_permits';
}

function synk_student_management_db_table_exists(mysqli $conn, string $tableName): bool
{
    $safeTableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTableName}'");

    return ($result instanceof mysqli_result) && $result->num_rows > 0;
}

function synk_student_management_db_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    if (!synk_student_management_db_table_exists($conn, $tableName)) {
        return false;
    }

    $safeTableName = $conn->real_escape_string($tableName);
    $safeColumnName = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTableName}` LIKE '{$safeColumnName}'");

    return ($result instanceof mysqli_result) && $result->num_rows > 0;
}

function synk_student_management_db_index_exists(mysqli $conn, string $tableName, string $indexName): bool
{
    if (!synk_student_management_db_table_exists($conn, $tableName)) {
        return false;
    }

    $safeTableName = $conn->real_escape_string($tableName);
    $safeIndexName = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM `{$safeTableName}` WHERE Key_name = '{$safeIndexName}'");

    return ($result instanceof mysqli_result) && $result->num_rows > 0;
}

function synk_student_management_db_constraint_exists(mysqli $conn, string $tableName, string $constraintName): bool
{
    $databaseResult = $conn->query('SELECT DATABASE() AS db');
    $databaseName = '';
    if ($databaseResult instanceof mysqli_result) {
        $databaseName = (string)($databaseResult->fetch_assoc()['db'] ?? '');
        $databaseResult->close();
    }

    if ($databaseName === '') {
        return false;
    }

    $safeDatabaseName = $conn->real_escape_string($databaseName);
    $safeTableName = $conn->real_escape_string($tableName);
    $safeConstraintName = $conn->real_escape_string($constraintName);
    $result = $conn->query("
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = '{$safeDatabaseName}'
          AND TABLE_NAME = '{$safeTableName}'
          AND CONSTRAINT_NAME = '{$safeConstraintName}'
        LIMIT 1
    ");

    return ($result instanceof mysqli_result) && $result->num_rows > 0;
}

function synk_student_management_ensure_master_import_indexes(mysqli $conn): void
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();

    if (!synk_student_management_db_index_exists($conn, $tableName, 'idx_student_number')) {
        if (!$conn->query("ALTER TABLE `{$tableName}` ADD INDEX `idx_student_number` (`student_number`)")) {
            throw new RuntimeException('Unable to optimize the student management table for backward encoding.');
        }
    }
}

function synk_student_management_ensure_exam_permits_schema(mysqli $conn): void
{
    synk_student_management_ensure_master_import_indexes($conn);

    $tableName = synk_student_management_enrollment_table_name();
    $legacyTableName = synk_student_management_legacy_exam_permits_table_name();

    if (!synk_student_management_db_table_exists($conn, $tableName) && synk_student_management_db_table_exists($conn, $legacyTableName)) {
        if (!$conn->query("RENAME TABLE `{$legacyTableName}` TO `{$tableName}`")) {
            throw new RuntimeException('Unable to rename the legacy student exam permits table.');
        }
    }

    if (
        synk_student_management_db_table_exists($conn, $tableName)
        && (
            synk_student_management_db_column_exists($conn, $tableName, 'roster_id')
            || !synk_student_management_db_column_exists($conn, $tableName, 'student_enrollment_id')
        )
    ) {
        if (!$conn->query("DROP TABLE `{$tableName}`")) {
            throw new RuntimeException('Unable to rebuild the student enrollment storage table.');
        }
    }

    if (synk_student_management_db_table_exists($conn, $legacyTableName)) {
        if (!$conn->query("DROP TABLE `{$legacyTableName}`")) {
            throw new RuntimeException('Unable to remove the legacy student exam permits table.');
        }
    }

    $createSql = "
        CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `student_enrollment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `import_batch_key` VARCHAR(64) NOT NULL DEFAULT '',
            `source_file_name` VARCHAR(255) NOT NULL DEFAULT '',
            `campus_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `college_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `program_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `ay_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `semester` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `year_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `section_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `section_text` VARCHAR(150) NOT NULL DEFAULT '',
            `offering_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `subject_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `subject_code` VARCHAR(50) NOT NULL DEFAULT '',
            `descriptive_title` VARCHAR(255) NOT NULL DEFAULT '',
            `student_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `faculty_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `room_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `room_text` VARCHAR(255) NOT NULL DEFAULT '',
            `schedule_text` VARCHAR(255) NOT NULL DEFAULT '',
            `source_row_number` INT UNSIGNED NOT NULL DEFAULT 0,
            `uploaded_by` INT UNSIGNED NULL DEFAULT NULL,
            `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`student_enrollment_id`),
            KEY `idx_sm_enrolled_subject_batch` (`import_batch_key`),
            KEY `idx_sm_enrolled_subject_scope` (`campus_id`, `college_id`, `program_id`, `ay_id`, `semester`),
            KEY `idx_sm_enrolled_subject_active_scope` (`program_id`, `ay_id`, `semester`, `section_id`, `subject_id`, `is_active`),
            KEY `idx_sm_enrolled_subject_section` (`section_id`, `subject_id`),
            KEY `idx_sm_enrolled_subject_offering` (`offering_id`),
            KEY `idx_sm_enrolled_subject_student` (`student_id`),
            KEY `idx_sm_enrolled_subject_faculty` (`faculty_id`),
            KEY `idx_sm_enrolled_subject_room` (`room_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($createSql)) {
        throw new RuntimeException('Unable to prepare the student enrolled subjects table.');
    }

    if (!synk_student_management_db_column_exists($conn, $tableName, 'is_active')) {
        if (!$conn->query("ALTER TABLE `{$tableName}` ADD COLUMN `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `uploaded_by`")) {
            throw new RuntimeException('Unable to extend the student enrolled subjects table for upload history.');
        }
    }

    if (!synk_student_management_db_column_exists($conn, $tableName, 'year_level')) {
        if (!$conn->query("ALTER TABLE `{$tableName}` ADD COLUMN `year_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `semester`")) {
            throw new RuntimeException('Unable to extend the student enrolled subjects table for year level.');
        }
    }

    if (!synk_student_management_db_column_exists($conn, $tableName, 'section_text')) {
        if (!$conn->query("ALTER TABLE `{$tableName}` ADD COLUMN `section_text` VARCHAR(150) NOT NULL DEFAULT '' AFTER `section_id`")) {
            throw new RuntimeException('Unable to extend the student enrolled subjects table for section text.');
        }
    }

    if (synk_student_management_db_index_exists($conn, $tableName, 'uk_sm_enrolled_subject_scope')) {
        if (!$conn->query("ALTER TABLE `{$tableName}` DROP INDEX `uk_sm_enrolled_subject_scope`")) {
            throw new RuntimeException('Unable to update the student enrolled subjects uniqueness rule.');
        }
    }

    if (!synk_student_management_db_index_exists($conn, $tableName, 'idx_sm_enrolled_subject_active_scope')) {
        if (!$conn->query("ALTER TABLE `{$tableName}` ADD INDEX `idx_sm_enrolled_subject_active_scope` (`program_id`, `ay_id`, `semester`, `section_id`, `subject_id`, `is_active`)")) {
            throw new RuntimeException('Unable to optimize the student enrolled subjects history indexes.');
        }
    }

    $constraintName = 'fk_sm_enrolled_subject_student';
    if (!synk_student_management_db_constraint_exists($conn, $tableName, $constraintName)) {
        if (!$conn->query("
            ALTER TABLE `{$tableName}`
            ADD CONSTRAINT `{$constraintName}`
            FOREIGN KEY (`student_id`) REFERENCES `tbl_student_management` (`student_id`)
            ON DELETE CASCADE
        ")) {
            throw new RuntimeException('Unable to attach the enrolled subjects table to student management.');
        }
    }
}

function synk_student_management_semester_options(): array
{
    return [
        1 => '1st Semester',
        2 => '2nd Semester',
        3 => 'Midyear',
    ];
}

function synk_student_management_semester_label_from_number(int $semester): string
{
    $options = synk_student_management_semester_options();
    return $options[$semester] ?? '';
}

function synk_student_management_fetch_college_catalog(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query("
        SELECT
            college_id,
            campus_id,
            college_code,
            college_name
        FROM tbl_college
        WHERE status = 'active'
        ORDER BY college_name ASC
    ");

    if (!($result instanceof mysqli_result)) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'college_id' => (int)($row['college_id'] ?? 0),
            'campus_id' => (int)($row['campus_id'] ?? 0),
            'college_code' => (string)($row['college_code'] ?? ''),
            'college_name' => (string)($row['college_name'] ?? ''),
        ];
    }

    $result->close();
    return $rows;
}

function synk_student_management_fetch_section_catalog(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query("
        SELECT
            s.section_id,
            s.program_id,
            s.ay_id,
            s.semester,
            CAST(s.year_level AS UNSIGNED) AS year_level,
            s.section_name,
            COALESCE(s.full_section, '') AS full_section
        FROM tbl_sections s
        WHERE s.status = 'active'
        ORDER BY
            s.program_id ASC,
            s.ay_id DESC,
            s.semester ASC,
            CAST(s.year_level AS UNSIGNED) ASC,
            s.section_name ASC,
            s.section_id ASC
    ");

    if (!($result instanceof mysqli_result)) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'section_id' => (int)($row['section_id'] ?? 0),
            'program_id' => (int)($row['program_id'] ?? 0),
            'ay_id' => (int)($row['ay_id'] ?? 0),
            'semester' => (int)($row['semester'] ?? 0),
            'year_level' => (int)($row['year_level'] ?? 0),
            'section_name' => (string)($row['section_name'] ?? ''),
            'full_section' => (string)($row['full_section'] ?? ''),
        ];
    }

    $result->close();
    return $rows;
}

function synk_student_management_fetch_campus_record(mysqli $conn, int $campusId): ?array
{
    if ($campusId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT campus_id, campus_code, campus_name
        FROM tbl_campus
        WHERE campus_id = ?
          AND status = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $campusId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? [
        'campus_id' => (int)($row['campus_id'] ?? 0),
        'campus_code' => (string)($row['campus_code'] ?? ''),
        'campus_name' => (string)($row['campus_name'] ?? ''),
    ] : null;
}

function synk_student_management_fetch_college_record(mysqli $conn, int $collegeId): ?array
{
    if ($collegeId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT college_id, campus_id, college_code, college_name
        FROM tbl_college
        WHERE college_id = ?
          AND status = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $collegeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? [
        'college_id' => (int)($row['college_id'] ?? 0),
        'campus_id' => (int)($row['campus_id'] ?? 0),
        'college_code' => (string)($row['college_code'] ?? ''),
        'college_name' => (string)($row['college_name'] ?? ''),
    ] : null;
}

function synk_student_management_fetch_program_scope_record(mysqli $conn, int $programId): ?array
{
    if ($programId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            p.program_id,
            p.college_id,
            p.program_code,
            p.program_name,
            COALESCE(p.major, '') AS major,
            c.campus_id,
            c.college_name,
            cam.campus_name
        FROM tbl_program p
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus cam
            ON cam.campus_id = c.campus_id
        WHERE p.program_id = ?
          AND p.status = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $programId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    $programName = (string)($row['program_name'] ?? '');
    $major = (string)($row['major'] ?? '');

    return [
        'program_id' => (int)($row['program_id'] ?? 0),
        'college_id' => (int)($row['college_id'] ?? 0),
        'campus_id' => (int)($row['campus_id'] ?? 0),
        'program_code' => (string)($row['program_code'] ?? ''),
        'program_name' => $programName,
        'major' => $major,
        'college_name' => (string)($row['college_name'] ?? ''),
        'campus_name' => (string)($row['campus_name'] ?? ''),
        'source_program_name' => synk_student_management_program_source_name($programName, $major),
    ];
}

function synk_student_management_fetch_academic_year_record(mysqli $conn, int $ayId): ?array
{
    if ($ayId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT ay_id, ay
        FROM tbl_academic_years
        WHERE ay_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $ayId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? [
        'ay_id' => (int)($row['ay_id'] ?? 0),
        'ay' => (string)($row['ay'] ?? ''),
    ] : null;
}

function synk_student_management_fetch_section_scope_record(mysqli $conn, int $sectionId): ?array
{
    if ($sectionId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            section_id,
            program_id,
            ay_id,
            semester,
            CAST(year_level AS UNSIGNED) AS year_level,
            section_name,
            COALESCE(full_section, '') AS full_section
        FROM tbl_sections
        WHERE section_id = ?
          AND status = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? [
        'section_id' => (int)($row['section_id'] ?? 0),
        'program_id' => (int)($row['program_id'] ?? 0),
        'ay_id' => (int)($row['ay_id'] ?? 0),
        'semester' => (int)($row['semester'] ?? 0),
        'year_level' => (int)($row['year_level'] ?? 0),
        'section_name' => (string)($row['section_name'] ?? ''),
        'full_section' => (string)($row['full_section'] ?? ''),
    ] : null;
}

function synk_student_management_resolve_upload_scope(mysqli $conn, array $scope): array
{
    $campusId = max(0, (int)($scope['campus_id'] ?? 0));
    $collegeId = max(0, (int)($scope['college_id'] ?? 0));
    $programId = max(0, (int)($scope['program_id'] ?? 0));
    $ayId = max(0, (int)($scope['ay_id'] ?? 0));
    $semester = max(0, (int)($scope['semester'] ?? 0));
    $yearLevel = max(0, (int)($scope['year_level'] ?? 0));
    $sectionSuffix = synk_student_management_normalize_section_suffix((string)($scope['section_suffix'] ?? ''));
    $semesterOptions = synk_student_management_semester_options();

    if (
        $campusId <= 0
        || $collegeId <= 0
        || $programId <= 0
        || $ayId <= 0
        || !isset($semesterOptions[$semester])
        || $yearLevel <= 0
        || $sectionSuffix === ''
    ) {
        throw new RuntimeException('Select campus, college, program, academic year, semester, year level, and section before uploading.');
    }

    $campus = synk_student_management_fetch_campus_record($conn, $campusId);
    $college = synk_student_management_fetch_college_record($conn, $collegeId);
    $program = synk_student_management_fetch_program_scope_record($conn, $programId);
    $academicYear = synk_student_management_fetch_academic_year_record($conn, $ayId);

    if (!$campus || !$college || !$program || !$academicYear) {
        throw new RuntimeException('The selected upload scope is no longer available in the master tables.');
    }

    if ((int)$college['campus_id'] !== $campusId) {
        throw new RuntimeException('The selected college does not belong to the chosen campus.');
    }

    if ((int)$program['college_id'] !== $collegeId || (int)$program['campus_id'] !== $campusId) {
        throw new RuntimeException('The selected program does not belong to the chosen campus and college.');
    }

    $sectionName = synk_student_management_build_scoped_section_name($yearLevel, $sectionSuffix);
    $sectionDisplay = trim((string)$program['program_code'] . ' ' . $sectionName);

    return [
        'campus_id' => $campusId,
        'campus_name' => (string)$campus['campus_name'],
        'college_id' => $collegeId,
        'college_name' => (string)$college['college_name'],
        'program_id' => $programId,
        'program_code' => (string)$program['program_code'],
        'program_name' => (string)$program['program_name'],
        'source_program_name' => (string)$program['source_program_name'],
        'ay_id' => $ayId,
        'academic_year_label' => (string)$academicYear['ay'],
        'semester' => $semester,
        'semester_label' => (string)$semesterOptions[$semester],
        'year_level' => $yearLevel,
        'section_suffix' => $sectionSuffix,
        'section_id' => 0,
        'section_name' => $sectionName,
        'section_display' => $sectionDisplay,
    ];
}

function synk_student_management_strip_utf8_bom(string $value): string
{
    return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
}

function synk_student_management_normalize_csv_encoding(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('//u', $value) === 1) {
        return $value;
    }

    if (function_exists('iconv')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    return $value;
}

function synk_student_management_csv_cell(array $row, int $index): string
{
    $value = (string)($row[$index] ?? '');
    $value = synk_student_management_strip_utf8_bom($value);
    $value = synk_student_management_normalize_csv_encoding($value);

    return synk_student_management_normalize_space($value);
}

function synk_student_management_normalize_lookup_key(string $value): string
{
    $normalized = strtoupper(synk_student_management_normalize_space($value));
    $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $normalized);

    return synk_student_management_normalize_space((string)($normalized ?? ''));
}

function synk_student_management_normalize_subject_code(string $value): string
{
    return str_replace(' ', '', synk_student_management_normalize_lookup_key($value));
}

function synk_student_management_normalize_section_suffix(string $value): string
{
    $normalized = strtoupper(synk_student_management_normalize_space($value));
    $normalized = preg_replace('/[^A-Z]/', '', $normalized);
    $normalized = (string)($normalized ?? '');

    if ($normalized === '') {
        return '';
    }

    $suffix = substr($normalized, 0, 1);
    if ($suffix < 'A' || $suffix > 'I') {
        return '';
    }

    return $suffix;
}

function synk_student_management_build_scoped_section_name(int $yearLevel, string $sectionSuffix): string
{
    $normalizedSuffix = synk_student_management_normalize_section_suffix($sectionSuffix);
    if ($yearLevel <= 0 || $normalizedSuffix === '') {
        return '';
    }

    return (string)$yearLevel . $normalizedSuffix;
}

function synk_student_management_build_student_name_key(string $lastName, string $firstName, string $middleName = '', string $suffixName = ''): string
{
    $fullName = trim(implode(' ', array_filter([
        trim($lastName) !== '' ? trim($lastName) . ',' : '',
        trim($firstName),
        trim($middleName),
        trim($suffixName),
    ])));

    return synk_student_management_normalize_lookup_key($fullName);
}

function synk_student_management_parse_subject_section_field(string $value): array
{
    $normalized = synk_student_management_normalize_space($value);
    $subjectCode = '';
    $programCode = '';
    $sectionName = '';

    if ($normalized !== '') {
        $parts = preg_split('/\s*-\s*/', $normalized, 2);
        $subjectCode = synk_student_management_normalize_space((string)($parts[0] ?? ''));
        $programSection = synk_student_management_normalize_space((string)($parts[1] ?? ''));

        if ($programSection !== '') {
            $lastDashPosition = strrpos($programSection, '-');
            if ($lastDashPosition !== false) {
                $programCode = synk_student_management_normalize_space(substr($programSection, 0, $lastDashPosition));
                $sectionName = synk_student_management_normalize_space(substr($programSection, $lastDashPosition + 1));
            } else {
                $programCode = $programSection;
            }
        }
    }

    return [
        'subject_section_raw' => $normalized,
        'subject_code' => $subjectCode,
        'program_code' => $programCode,
        'section_name' => $sectionName,
    ];
}

function synk_student_management_extract_year_level_from_section(string $sectionName): int
{
    if (preg_match('/(\d+)/', $sectionName, $matches)) {
        $yearLevel = (int)($matches[1] ?? 0);
        if ($yearLevel > 0 && $yearLevel <= 9) {
            return $yearLevel;
        }
    }

    return 0;
}

function synk_student_management_parse_csv_student_name(string $studentName): array
{
    $normalized = synk_student_management_normalize_space($studentName);
    if ($normalized === '') {
        return [
            'student_name_raw' => '',
            'last_name' => '',
            'first_name' => '',
            'middle_name' => '',
            'suffix_name' => '',
            'name_key' => '',
        ];
    }

    $parts = explode(',', $normalized, 2);
    $lastName = synk_student_management_normalize_space((string)($parts[0] ?? ''));
    $givenNames = synk_student_management_normalize_space((string)($parts[1] ?? ''));
    $suffixName = '';
    $firstName = '';
    $middleName = '';

    if ($givenNames !== '') {
        $tokens = preg_split('/\s+/', $givenNames) ?: [];
        $suffixCandidates = ['JR', 'JR.', 'SR', 'SR.', 'II', 'III', 'IV', 'V'];
        if (!empty($tokens)) {
            $lastToken = strtoupper((string)end($tokens));
            if (in_array($lastToken, $suffixCandidates, true)) {
                $suffixName = synk_student_management_normalize_space((string)array_pop($tokens));
            }
        }

        if (count($tokens) === 1) {
            $firstName = synk_student_management_normalize_space((string)$tokens[0]);
        } elseif (count($tokens) === 2) {
            $firstName = synk_student_management_normalize_space((string)$tokens[0]);
            $middleName = synk_student_management_normalize_space((string)$tokens[1]);
        } elseif (count($tokens) > 2) {
            $middleName = synk_student_management_normalize_space((string)array_pop($tokens));
            $firstName = synk_student_management_normalize_space(implode(' ', $tokens));
        }
    }

    return [
        'student_name_raw' => $normalized,
        'last_name' => $lastName,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'suffix_name' => $suffixName,
        'name_key' => synk_student_management_build_student_name_key($lastName, $firstName, $middleName, $suffixName),
    ];
}

function synk_student_management_parse_exam_permits_csv(string $csvPath): array
{
    if (!is_file($csvPath)) {
        throw new RuntimeException('The uploaded CSV file could not be found.');
    }

    $handle = fopen($csvPath, 'rb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Unable to open the uploaded CSV file.');
    }

    $rows = [];
    try {
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
    } finally {
        fclose($handle);
    }

    if (count($rows) < 8) {
        throw new RuntimeException('The CSV file does not contain the expected old-system roster layout.');
    }

    $campusHeader = synk_student_management_csv_cell($rows[0], 0);
    $campusName = $campusHeader;
    if (preg_match('/-\s*(.+)$/', $campusHeader, $matches)) {
        $campusName = synk_student_management_normalize_space((string)($matches[1] ?? ''));
    }

    $subjectSection = synk_student_management_parse_subject_section_field(
        synk_student_management_csv_cell($rows[3], 3)
    );

    $parsed = [
        'campus_header' => $campusHeader,
        'campus_name' => $campusName,
        'campus_location' => synk_student_management_csv_cell($rows[1], 0),
        'report_name' => synk_student_management_csv_cell($rows[2], 0),
        'subject_section_raw' => (string)$subjectSection['subject_section_raw'],
        'subject_code' => (string)$subjectSection['subject_code'],
        'program_code' => (string)$subjectSection['program_code'],
        'section_name' => (string)$subjectSection['section_name'],
        'descriptive_title' => synk_student_management_csv_cell($rows[4], 3),
        'class_schedule_text' => synk_student_management_csv_cell($rows[4], 9),
        'faculty_name' => synk_student_management_csv_cell($rows[5], 3),
        'room_name' => synk_student_management_csv_cell($rows[5], 9),
        'students' => [],
    ];

    $headerNo = strtoupper(synk_student_management_csv_cell($rows[6], 0));
    $headerName = strtoupper(synk_student_management_csv_cell($rows[6], 1));
    if ($headerNo !== 'NO.' || strpos($headerName, "STUDENT'S NAME") === false) {
        throw new RuntimeException('The CSV file does not match the expected old-system student list header.');
    }

    for ($index = 7; $index < count($rows); $index++) {
        $currentRow = $rows[$index];
        $rowNumber = synk_student_management_csv_cell($currentRow, 0);
        $studentName = synk_student_management_csv_cell($currentRow, 1);
        $studentNumberRaw = preg_replace('/\D+/', '', synk_student_management_csv_cell($currentRow, 4));

        if ($studentName === '') {
            continue;
        }

        $upperStudentName = strtoupper($studentName);
        if (strpos($upperStudentName, 'NOTHING FOLLOWS') !== false || strpos($upperStudentName, 'DATE PRINTED') !== false) {
            break;
        }

        if ($rowNumber === '' || !ctype_digit($rowNumber)) {
            continue;
        }

        $nameParts = synk_student_management_parse_csv_student_name($studentName);
        $parsed['students'][] = [
            'source_row_number' => $index + 1,
            'row_number' => (int)$rowNumber,
            'student_number' => $studentNumberRaw !== '' ? (int)$studentNumberRaw : 0,
            'student_name_raw' => (string)$nameParts['student_name_raw'],
            'last_name' => (string)$nameParts['last_name'],
            'first_name' => (string)$nameParts['first_name'],
            'middle_name' => (string)$nameParts['middle_name'],
            'suffix_name' => (string)$nameParts['suffix_name'],
            'name_key' => (string)$nameParts['name_key'],
        ];
    }

    if (empty($parsed['students'])) {
        throw new RuntimeException('No student rows were found in the uploaded CSV file.');
    }

    return $parsed;
}

function synk_student_management_validate_import_scope(array $parsed, array $scope): void
{
    $csvCampusKey = synk_student_management_normalize_lookup_key((string)($parsed['campus_name'] ?? ''));
    $scopeCampusKey = synk_student_management_normalize_lookup_key((string)($scope['campus_name'] ?? ''));
    if ($csvCampusKey !== '' && $scopeCampusKey !== '' && $csvCampusKey !== $scopeCampusKey) {
        throw new RuntimeException('The uploaded CSV campus does not match the selected campus.');
    }

    $csvProgramKey = synk_student_management_normalize_lookup_key((string)($parsed['program_code'] ?? ''));
    $scopeProgramKey = synk_student_management_normalize_lookup_key((string)($scope['program_code'] ?? ''));
    if ($csvProgramKey === '' || $scopeProgramKey === '' || $csvProgramKey !== $scopeProgramKey) {
        throw new RuntimeException('The uploaded CSV program code does not match the selected program.');
    }

    $csvSectionKey = synk_student_management_normalize_lookup_key((string)($parsed['section_name'] ?? ''));
    $scopeSectionKey = synk_student_management_normalize_lookup_key((string)($scope['section_name'] ?? ''));
    $scopeSectionDisplayKey = synk_student_management_normalize_lookup_key((string)($scope['section_display'] ?? ''));
    if (
        $csvSectionKey !== ''
        && $scopeSectionKey !== ''
        && $csvSectionKey !== $scopeSectionKey
        && $csvSectionKey !== $scopeSectionDisplayKey
    ) {
        throw new RuntimeException('The uploaded CSV section does not match the selected year level and section.');
    }

    $csvYearLevel = synk_student_management_extract_year_level_from_section((string)($parsed['section_name'] ?? ''));
    $scopeYearLevel = max(0, (int)($scope['year_level'] ?? 0));
    if ($csvYearLevel > 0 && $scopeYearLevel > 0 && $csvYearLevel !== $scopeYearLevel) {
        throw new RuntimeException('The uploaded CSV section year level does not match the selected year level.');
    }

    if (trim((string)($parsed['subject_code'] ?? '')) === '') {
        throw new RuntimeException('The uploaded CSV is missing a valid subject value.');
    }
}

function synk_student_management_resolve_section(mysqli $conn, array $scope, string $sectionName): array
{
    $normalizedSectionName = strtoupper(synk_student_management_normalize_space($sectionName));
    if ($normalizedSectionName === '') {
        throw new RuntimeException('The selected upload scope does not contain a valid section name.');
    }

    $fullSection = trim((string)$scope['program_code'] . ' ' . $normalizedSectionName);
    $stmt = $conn->prepare("
        SELECT
            section_id,
            year_level,
            section_name,
            full_section
        FROM tbl_sections
        WHERE program_id = ?
          AND ay_id = ?
          AND semester = ?
          AND status = 'active'
          AND (
                UPPER(section_name) = UPPER(?)
             OR REPLACE(UPPER(full_section), ' ', '') = REPLACE(UPPER(?), ' ', '')
          )
        ORDER BY section_id ASC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the section lookup query.');
    }

    $stmt->bind_param(
        'iiiss',
        $scope['program_id'],
        $scope['ay_id'],
        $scope['semester'],
        $normalizedSectionName,
        $fullSection
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (is_array($row)) {
        return [
            'section_id' => (int)($row['section_id'] ?? 0),
            'year_level' => (int)($row['year_level'] ?? 0),
            'section_name' => (string)($row['section_name'] ?? ''),
            'full_section' => (string)($row['full_section'] ?? ''),
        ];
    }

    $stmt = $conn->prepare("
        SELECT
            section_id,
            year_level,
            section_name,
            full_section
        FROM tbl_sections
        WHERE program_id = ?
          AND ay_id = ?
          AND semester = ?
          AND (
                UPPER(section_name) = UPPER(?)
             OR REPLACE(UPPER(full_section), ' ', '') = REPLACE(UPPER(?), ' ', '')
          )
        ORDER BY section_id ASC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the inactive section lookup query.');
    }

    $stmt->bind_param(
        'iiiss',
        $scope['program_id'],
        $scope['ay_id'],
        $scope['semester'],
        $normalizedSectionName,
        $fullSection
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (is_array($row)) {
        $sectionId = (int)($row['section_id'] ?? 0);
        $stmt = $conn->prepare("
            UPDATE tbl_sections
            SET
                year_level = ?,
                section_name = ?,
                full_section = ?,
                status = 'active'
            WHERE section_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException('Unable to reactivate the selected section.');
        }

        $yearLevel = max(1, (int)($scope['year_level'] ?? synk_student_management_extract_year_level_from_section($normalizedSectionName)));
        $stmt->bind_param(
            'issi',
            $yearLevel,
            $normalizedSectionName,
            $fullSection,
            $sectionId
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to reactivate the selected section.');
        }

        $stmt->close();

        return [
            'section_id' => $sectionId,
            'year_level' => $yearLevel,
            'section_name' => $normalizedSectionName,
            'full_section' => $fullSection,
        ];
    }

    $stmt = $conn->prepare("
        INSERT INTO tbl_sections (
            program_id,
            ay_id,
            semester,
            year_level,
            section_name,
            full_section,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the section create query.');
    }

    $yearLevel = max(1, (int)($scope['year_level'] ?? synk_student_management_extract_year_level_from_section($normalizedSectionName)));
    $stmt->bind_param(
        'iiiiss',
        $scope['program_id'],
        $scope['ay_id'],
        $scope['semester'],
        $yearLevel,
        $normalizedSectionName,
        $fullSection
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to create the selected section automatically.');
    }

    $sectionId = (int)$stmt->insert_id;
    $stmt->close();

    return [
        'section_id' => $sectionId,
        'year_level' => $yearLevel,
        'section_name' => $normalizedSectionName,
        'full_section' => $fullSection,
    ];
}

function synk_student_management_pick_subject_match(array $rows, string $subjectCode, string $descriptiveTitle): ?array
{
    if (empty($rows)) {
        return null;
    }

    $normalizedTitle = synk_student_management_normalize_lookup_key($descriptiveTitle);
    foreach ($rows as $row) {
        if (
            $normalizedTitle !== ''
            && synk_student_management_normalize_lookup_key((string)($row['descriptive_title'] ?? '')) === $normalizedTitle
        ) {
            return $row;
        }
    }

    $normalizedCode = synk_student_management_normalize_subject_code($subjectCode);
    foreach ($rows as $row) {
        if (synk_student_management_normalize_subject_code((string)($row['subject_code'] ?? '')) === $normalizedCode) {
            return $row;
        }
    }

    return $rows[0];
}

function synk_student_management_resolve_subject_and_offering(
    mysqli $conn,
    array $scope,
    array $section,
    string $subjectCode,
    string $descriptiveTitle
): array {
    $rows = [];
    $stmt = $conn->prepare("
        SELECT
            po.offering_id,
            sm.sub_id AS subject_id,
            sm.sub_code AS subject_code,
            sm.sub_description AS descriptive_title
        FROM tbl_prospectus_offering po
        INNER JOIN tbl_prospectus_subjects ps
            ON ps.ps_id = po.ps_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        WHERE po.program_id = ?
          AND po.ay_id = ?
          AND po.semester = ?
          AND po.section_id = ?
          AND REPLACE(UPPER(sm.sub_code), ' ', '') = REPLACE(UPPER(?), ' ', '')
        ORDER BY po.offering_id ASC
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the prospectus offering lookup query.');
    }

    $stmt->bind_param(
        'iiiis',
        $scope['program_id'],
        $scope['ay_id'],
        $scope['semester'],
        $section['section_id'],
        $subjectCode
    );
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
        $rows[] = [
            'offering_id' => (int)($row['offering_id'] ?? 0),
            'subject_id' => (int)($row['subject_id'] ?? 0),
            'subject_code' => (string)($row['subject_code'] ?? ''),
            'descriptive_title' => (string)($row['descriptive_title'] ?? ''),
            'resolved_via' => 'tbl_prospectus_offering',
        ];
    }
    $stmt->close();

    $matched = synk_student_management_pick_subject_match($rows, $subjectCode, $descriptiveTitle);
    if (is_array($matched)) {
        return $matched;
    }

    $rows = [];
    $stmt = $conn->prepare("
        SELECT
            sub_id AS subject_id,
            sub_code AS subject_code,
            sub_description AS descriptive_title
        FROM tbl_subject_masterlist
        WHERE REPLACE(UPPER(sub_code), ' ', '') = REPLACE(UPPER(?), ' ', '')
        ORDER BY sub_id ASC
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the subject masterlist lookup query.');
    }

    $stmt->bind_param('s', $subjectCode);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
        $rows[] = [
            'offering_id' => 0,
            'subject_id' => (int)($row['subject_id'] ?? 0),
            'subject_code' => (string)($row['subject_code'] ?? ''),
            'descriptive_title' => (string)($row['descriptive_title'] ?? ''),
            'resolved_via' => 'tbl_subject_masterlist',
        ];
    }
    $stmt->close();

    $matched = synk_student_management_pick_subject_match($rows, $subjectCode, $descriptiveTitle);
    if (is_array($matched)) {
        return $matched;
    }

    throw new RuntimeException('No matching subject was found in the selected program scope or the subject masterlist.');
}

function synk_student_management_fetch_scoped_faculty_rows(mysqli $conn, array $scope): array
{
    $rows = [];
    $queries = [
        "
            SELECT
                f.faculty_id,
                f.last_name,
                f.first_name,
                COALESCE(f.middle_name, '') AS middle_name,
                COALESCE(f.ext_name, '') AS ext_name
            FROM tbl_college_faculty cf
            INNER JOIN tbl_faculty f
                ON f.faculty_id = cf.faculty_id
            WHERE cf.college_id = ?
              AND cf.status = 'active'
              AND f.status = 'active'
              AND cf.ay_id = ?
              AND cf.semester = ?
            ORDER BY f.last_name ASC, f.first_name ASC, f.faculty_id ASC
        ",
        "
            SELECT
                f.faculty_id,
                f.last_name,
                f.first_name,
                COALESCE(f.middle_name, '') AS middle_name,
                COALESCE(f.ext_name, '') AS ext_name
            FROM tbl_college_faculty cf
            INNER JOIN tbl_faculty f
                ON f.faculty_id = cf.faculty_id
            WHERE cf.college_id = ?
              AND cf.status = 'active'
              AND f.status = 'active'
            ORDER BY f.last_name ASC, f.first_name ASC, f.faculty_id ASC
        ",
        "
            SELECT
                faculty_id,
                last_name,
                first_name,
                COALESCE(middle_name, '') AS middle_name,
                COALESCE(ext_name, '') AS ext_name
            FROM tbl_faculty
            WHERE status = 'active'
            ORDER BY last_name ASC, first_name ASC, faculty_id ASC
        ",
    ];

    foreach ($queries as $index => $sql) {
        $currentRows = [];
        if ($index < 2) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                continue;
            }

            if ($index === 0) {
                $stmt->bind_param('iii', $scope['college_id'], $scope['ay_id'], $scope['semester']);
            } else {
                $stmt->bind_param('i', $scope['college_id']);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
                $currentRows[] = $row;
            }
            $stmt->close();
        } else {
            $result = $conn->query($sql);
            while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
                $currentRows[] = $row;
            }
        }

        if (!empty($currentRows)) {
            $rows = $currentRows;
            break;
        }
    }

    return $rows;
}

function synk_student_management_faculty_name_keys(array $row): array
{
    $firstName = (string)($row['first_name'] ?? '');
    $middleName = (string)($row['middle_name'] ?? '');
    $lastName = (string)($row['last_name'] ?? '');
    $extName = (string)($row['ext_name'] ?? '');

    $keys = [
        synk_student_management_normalize_lookup_key(implode(' ', array_filter([$firstName, $middleName, $lastName, $extName]))),
        synk_student_management_normalize_lookup_key(implode(' ', array_filter([$firstName, $lastName, $extName]))),
        synk_student_management_normalize_lookup_key(implode(' ', array_filter([$lastName, $firstName, $middleName, $extName]))),
    ];

    return array_values(array_unique(array_filter($keys)));
}

function synk_student_management_resolve_faculty(mysqli $conn, array $scope, string $facultyName): array
{
    $normalizedFacultyName = synk_student_management_normalize_lookup_key($facultyName);
    if ($normalizedFacultyName === '') {
        throw new RuntimeException('The CSV does not contain a valid instructor name.');
    }

    $catalog = [];
    foreach (synk_student_management_fetch_scoped_faculty_rows($conn, $scope) as $row) {
        $displayName = trim(implode(' ', array_filter([
            (string)($row['first_name'] ?? ''),
            (string)($row['middle_name'] ?? ''),
            (string)($row['last_name'] ?? ''),
            (string)($row['ext_name'] ?? ''),
        ])));

        $normalizedRow = [
            'faculty_id' => (int)($row['faculty_id'] ?? 0),
            'faculty_name' => $displayName,
        ];

        foreach (synk_student_management_faculty_name_keys($row) as $key) {
            if (!isset($catalog[$key])) {
                $catalog[$key] = [];
            }
            $catalog[$key][] = $normalizedRow;
        }
    }

    $matches = $catalog[$normalizedFacultyName] ?? [];
    if (count($matches) === 1) {
        return $matches[0];
    }

    if (count($matches) > 1) {
        throw new RuntimeException('The instructor name matches multiple faculty records in the selected college scope.');
    }

    throw new RuntimeException('The instructor could not be matched to tbl_faculty for the selected college and term.');
}

function synk_student_management_normalize_room_lookup(string $value): string
{
    $normalized = strtoupper(synk_student_management_normalize_space($value));
    $normalized = str_replace(['ROOM', 'BUILDING'], ' ', $normalized);
    $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $normalized);

    return synk_student_management_normalize_space((string)($normalized ?? ''));
}

function synk_student_management_fetch_scoped_room_rows(mysqli $conn, array $scope): array
{
    $rows = [];

    if (synk_student_management_db_table_exists($conn, 'tbl_room_college_access')) {
        $stmt = $conn->prepare("
            SELECT DISTINCT
                r.room_id,
                r.room_code,
                COALESCE(r.room_name, '') AS room_name
            FROM tbl_room_college_access acc
            INNER JOIN tbl_rooms r
                ON r.room_id = acc.room_id
            WHERE acc.college_id = ?
              AND acc.ay_id = ?
              AND acc.semester = ?
              AND r.status = 'active'
            ORDER BY r.room_code ASC, r.room_name ASC, r.room_id ASC
        ");

        if ($stmt) {
            $stmt->bind_param('iii', $scope['college_id'], $scope['ay_id'], $scope['semester']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
                $rows[] = [
                    'room_id' => (int)($row['room_id'] ?? 0),
                    'room_code' => (string)($row['room_code'] ?? ''),
                    'room_name' => (string)($row['room_name'] ?? ''),
                ];
            }
            $stmt->close();
        }
    }

    if (!empty($rows)) {
        return $rows;
    }

    $stmt = $conn->prepare("
        SELECT
            room_id,
            room_code,
            COALESCE(room_name, '') AS room_name
        FROM tbl_rooms
        WHERE college_id = ?
          AND status = 'active'
          AND (
                (ay_id = ? AND semester = ?)
             OR ay_id IS NULL
             OR ay_id = 0
          )
        ORDER BY room_code ASC, room_name ASC, room_id ASC
    ");

    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param('iii', $scope['college_id'], $scope['ay_id'], $scope['semester']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
        $rows[] = [
            'room_id' => (int)($row['room_id'] ?? 0),
            'room_code' => (string)($row['room_code'] ?? ''),
            'room_name' => (string)($row['room_name'] ?? ''),
        ];
    }
    $stmt->close();

    return $rows;
}

function synk_student_management_room_lookup_keys(array $row): array
{
    $roomCode = (string)($row['room_code'] ?? '');
    $roomName = (string)($row['room_name'] ?? '');
    $values = [
        $roomCode,
        $roomName,
        trim($roomCode . ' ' . $roomName),
    ];

    $keys = [];
    foreach ($values as $value) {
        $key = synk_student_management_normalize_room_lookup($value);
        if ($key !== '') {
            $keys[] = $key;
        }
    }

    return array_values(array_unique($keys));
}

function synk_student_management_room_display_name(array $room): string
{
    $roomCode = trim((string)($room['room_code'] ?? ''));
    $roomName = trim((string)($room['room_name'] ?? ''));

    if ($roomCode !== '' && $roomName !== '') {
        return $roomCode . ' - ' . $roomName;
    }

    return $roomCode !== '' ? $roomCode : $roomName;
}

function synk_student_management_resolve_room(mysqli $conn, array $scope, string $roomText): array
{
    $normalizedRoom = synk_student_management_normalize_room_lookup($roomText);
    if ($normalizedRoom === '') {
        return [
            'room_id' => 0,
            'room_code' => '',
            'room_name' => '',
            'room_text' => '',
            'room_display' => '',
            'matched' => false,
        ];
    }

    $rows = synk_student_management_fetch_scoped_room_rows($conn, $scope);
    $exactMatches = [];

    foreach ($rows as $row) {
        foreach (synk_student_management_room_lookup_keys($row) as $key) {
            if ($key === $normalizedRoom) {
                $exactMatches[] = $row;
                break;
            }
        }
    }

    if (count($exactMatches) === 1) {
        $room = $exactMatches[0];
        return [
            'room_id' => (int)$room['room_id'],
            'room_code' => (string)$room['room_code'],
            'room_name' => (string)$room['room_name'],
            'room_text' => synk_student_management_normalize_space($roomText),
            'room_display' => synk_student_management_room_display_name($room),
            'matched' => true,
        ];
    }

    $fallbackMatches = [];
    foreach ($rows as $row) {
        foreach (synk_student_management_room_lookup_keys($row) as $key) {
            if (strpos($key, $normalizedRoom) !== false || strpos($normalizedRoom, $key) !== false) {
                $fallbackMatches[] = $row;
                break;
            }
        }
    }

    if (count($fallbackMatches) === 1) {
        $room = $fallbackMatches[0];
        return [
            'room_id' => (int)$room['room_id'],
            'room_code' => (string)$room['room_code'],
            'room_name' => (string)$room['room_name'],
            'room_text' => synk_student_management_normalize_space($roomText),
            'room_display' => synk_student_management_room_display_name($room),
            'matched' => true,
        ];
    }

    return [
        'room_id' => 0,
        'room_code' => '',
        'room_name' => '',
        'room_text' => synk_student_management_normalize_space($roomText),
        'room_display' => synk_student_management_normalize_space($roomText),
        'matched' => false,
    ];
}

function synk_student_management_fetch_master_student_catalog(mysqli $conn): array
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();
    $catalog = [
        'by_student_number' => [],
        'by_name_key' => [],
    ];

    $result = $conn->query("
        SELECT
            student_id,
            ay_id,
            semester,
            source_sheet_name,
            source_file_name,
            year_level,
            student_number,
            last_name,
            first_name,
            middle_name,
            suffix_name,
            email_address,
            program_id,
            uploaded_by,
            source_row_number
        FROM `{$tableName}`
        ORDER BY student_id ASC
    ");

    if (!($result instanceof mysqli_result)) {
        return $catalog;
    }

    while ($row = $result->fetch_assoc()) {
        $normalizedRow = [
            'student_id' => (int)($row['student_id'] ?? 0),
            'ay_id' => (int)($row['ay_id'] ?? 0),
            'semester' => (int)($row['semester'] ?? 0),
            'source_sheet_name' => (string)($row['source_sheet_name'] ?? ''),
            'source_file_name' => (string)($row['source_file_name'] ?? ''),
            'year_level' => (int)($row['year_level'] ?? 0),
            'student_number' => (int)($row['student_number'] ?? 0),
            'last_name' => (string)($row['last_name'] ?? ''),
            'first_name' => (string)($row['first_name'] ?? ''),
            'middle_name' => (string)($row['middle_name'] ?? ''),
            'suffix_name' => (string)($row['suffix_name'] ?? ''),
            'email_address' => (string)($row['email_address'] ?? ''),
            'program_id' => (int)($row['program_id'] ?? 0),
            'uploaded_by' => (int)($row['uploaded_by'] ?? 0),
            'source_row_number' => (int)($row['source_row_number'] ?? 0),
        ];

        $studentNumber = (int)$normalizedRow['student_number'];
        if ($studentNumber > 0 && !isset($catalog['by_student_number'][$studentNumber])) {
            $catalog['by_student_number'][$studentNumber] = $normalizedRow;
        }

        $nameKey = synk_student_management_build_student_name_key(
            (string)$normalizedRow['last_name'],
            (string)$normalizedRow['first_name'],
            (string)$normalizedRow['middle_name'],
            (string)$normalizedRow['suffix_name']
        );
        if ($nameKey !== '' && !isset($catalog['by_name_key'][$nameKey])) {
            $catalog['by_name_key'][$nameKey] = $normalizedRow;
        }
    }

    $result->close();
    return $catalog;
}

function synk_student_management_find_existing_master_student(array $catalog, array $studentRow): ?array
{
    $studentNumber = max(0, (int)($studentRow['student_number'] ?? 0));
    if ($studentNumber > 0 && isset($catalog['by_student_number'][$studentNumber])) {
        return $catalog['by_student_number'][$studentNumber];
    }

    $nameKey = (string)($studentRow['name_key'] ?? '');
    if ($nameKey === '' || !isset($catalog['by_name_key'][$nameKey])) {
        return null;
    }

    $matched = $catalog['by_name_key'][$nameKey];
    $matchedStudentNumber = max(0, (int)($matched['student_number'] ?? 0));
    if ($studentNumber > 0 && $matchedStudentNumber > 0 && $matchedStudentNumber !== $studentNumber) {
        return null;
    }

    return $matched;
}

function synk_student_management_build_master_student_record(
    array $studentRow,
    array $scope,
    int $yearLevel,
    string $sourceFileName,
    int $uploadedBy = 0
): array {
    $firstName = (string)($studentRow['first_name'] ?? '');
    $lastName = (string)($studentRow['last_name'] ?? '');

    return [
        'ay_id' => (int)$scope['ay_id'],
        'semester' => (int)$scope['semester'],
        'source_sheet_name' => 'BACKWARD CSV',
        'source_file_name' => synk_student_management_normalize_space($sourceFileName),
        'year_level' => max(0, $yearLevel),
        'student_number' => max(0, (int)($studentRow['student_number'] ?? 0)),
        'last_name' => $lastName,
        'first_name' => $firstName,
        'middle_name' => (string)($studentRow['middle_name'] ?? ''),
        'suffix_name' => (string)($studentRow['suffix_name'] ?? ''),
        'email_address' => synk_student_management_prepare_email_address('', $firstName, $lastName),
        'program_id' => (int)$scope['program_id'],
        'uploaded_by' => $uploadedBy > 0 ? $uploadedBy : 0,
        'source_row_number' => max(0, (int)($studentRow['source_row_number'] ?? 0)),
    ];
}

function synk_student_management_merge_master_student_record(array $existingRecord, array $incomingRecord): array
{
    $pickString = static function (string $incomingValue, string $existingValue): string {
        return $incomingValue !== '' ? $incomingValue : $existingValue;
    };
    $pickInt = static function (int $incomingValue, int $existingValue): int {
        return $incomingValue > 0 ? $incomingValue : $existingValue;
    };

    return [
        'ay_id' => $pickInt(
            (int)($incomingRecord['ay_id'] ?? 0),
            (int)($existingRecord['ay_id'] ?? 0)
        ),
        'semester' => $pickInt(
            (int)($incomingRecord['semester'] ?? 0),
            (int)($existingRecord['semester'] ?? 0)
        ),
        'source_sheet_name' => $pickString(
            (string)($incomingRecord['source_sheet_name'] ?? ''),
            (string)($existingRecord['source_sheet_name'] ?? '')
        ),
        'source_file_name' => $pickString(
            (string)($incomingRecord['source_file_name'] ?? ''),
            (string)($existingRecord['source_file_name'] ?? '')
        ),
        'year_level' => $pickInt(
            (int)($incomingRecord['year_level'] ?? 0),
            (int)($existingRecord['year_level'] ?? 0)
        ),
        'student_number' => $pickInt(
            (int)($incomingRecord['student_number'] ?? 0),
            (int)($existingRecord['student_number'] ?? 0)
        ),
        'last_name' => $pickString(
            (string)($incomingRecord['last_name'] ?? ''),
            (string)($existingRecord['last_name'] ?? '')
        ),
        'first_name' => $pickString(
            (string)($incomingRecord['first_name'] ?? ''),
            (string)($existingRecord['first_name'] ?? '')
        ),
        'middle_name' => $pickString(
            (string)($incomingRecord['middle_name'] ?? ''),
            (string)($existingRecord['middle_name'] ?? '')
        ),
        'suffix_name' => $pickString(
            (string)($incomingRecord['suffix_name'] ?? ''),
            (string)($existingRecord['suffix_name'] ?? '')
        ),
        'email_address' => $pickString(
            (string)($incomingRecord['email_address'] ?? ''),
            (string)($existingRecord['email_address'] ?? '')
        ),
        'program_id' => $pickInt(
            (int)($incomingRecord['program_id'] ?? 0),
            (int)($existingRecord['program_id'] ?? 0)
        ),
        'uploaded_by' => $pickInt(
            (int)($incomingRecord['uploaded_by'] ?? 0),
            (int)($existingRecord['uploaded_by'] ?? 0)
        ),
        'source_row_number' => $pickInt(
            (int)($incomingRecord['source_row_number'] ?? 0),
            (int)($existingRecord['source_row_number'] ?? 0)
        ),
    ];
}

function synk_student_management_master_record_has_changes(array $existingRecord, array $targetRecord): bool
{
    $stringFields = [
        'source_sheet_name',
        'source_file_name',
        'last_name',
        'first_name',
        'middle_name',
        'suffix_name',
        'email_address',
    ];
    foreach ($stringFields as $field) {
        if ((string)($existingRecord[$field] ?? '') !== (string)($targetRecord[$field] ?? '')) {
            return true;
        }
    }

    $intFields = [
        'ay_id',
        'semester',
        'year_level',
        'student_number',
        'program_id',
        'uploaded_by',
        'source_row_number',
    ];
    foreach ($intFields as $field) {
        if ((int)($existingRecord[$field] ?? 0) !== (int)($targetRecord[$field] ?? 0)) {
            return true;
        }
    }

    return false;
}

function synk_student_management_update_master_student_record(mysqli $conn, int $studentId, array $record): void
{
    $tableName = synk_student_management_table_name();
    $stmt = $conn->prepare("
        UPDATE `{$tableName}`
        SET
            ay_id = ?,
            semester = ?,
            source_sheet_name = ?,
            source_file_name = ?,
            year_level = ?,
            student_number = ?,
            last_name = ?,
            first_name = ?,
            middle_name = ?,
            suffix_name = ?,
            email_address = ?,
            program_id = ?,
            uploaded_by = ?,
            source_row_number = ?
        WHERE student_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the matched student update query.');
    }

    $stmt->bind_param(
        'iissiisssssiiii',
        $record['ay_id'],
        $record['semester'],
        $record['source_sheet_name'],
        $record['source_file_name'],
        $record['year_level'],
        $record['student_number'],
        $record['last_name'],
        $record['first_name'],
        $record['middle_name'],
        $record['suffix_name'],
        $record['email_address'],
        $record['program_id'],
        $record['uploaded_by'],
        $record['source_row_number'],
        $studentId
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to refresh the matched student record.');
    }

    $stmt->close();
}

function synk_student_management_insert_master_student_record(mysqli $conn, array $record): int
{
    $tableName = synk_student_management_table_name();
    $stmt = $conn->prepare("
        INSERT INTO `{$tableName}` (
            ay_id,
            semester,
            source_sheet_name,
            source_file_name,
            year_level,
            student_number,
            last_name,
            first_name,
            middle_name,
            suffix_name,
            email_address,
            program_id,
            uploaded_by,
            source_row_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to insert a student into the student management table.');
    }

    $stmt->bind_param(
        'iissiisssssiii',
        $record['ay_id'],
        $record['semester'],
        $record['source_sheet_name'],
        $record['source_file_name'],
        $record['year_level'],
        $record['student_number'],
        $record['last_name'],
        $record['first_name'],
        $record['middle_name'],
        $record['suffix_name'],
        $record['email_address'],
        $record['program_id'],
        $record['uploaded_by'],
        $record['source_row_number']
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to insert a student into the student management table.');
    }

    $insertId = (int)$stmt->insert_id;
    $stmt->close();

    return $insertId;
}

function synk_student_management_refresh_master_student_catalog(array &$catalog, array $record, int $studentId): void
{
    $record['student_id'] = $studentId;
    $studentNumber = max(0, (int)($record['student_number'] ?? 0));
    if ($studentNumber > 0) {
        $catalog['by_student_number'][$studentNumber] = $record;
    }

    $nameKey = synk_student_management_build_student_name_key(
        (string)($record['last_name'] ?? ''),
        (string)($record['first_name'] ?? ''),
        (string)($record['middle_name'] ?? ''),
        (string)($record['suffix_name'] ?? '')
    );
    if ($nameKey !== '') {
        $catalog['by_name_key'][$nameKey] = $record;
    }
}

function synk_student_management_delete_existing_enrollment_scope(
    mysqli $conn,
    array $scope,
    array $section,
    array $subject
): int {
    $tableName = synk_student_management_enrollment_table_name();

    if ((int)($subject['subject_id'] ?? 0) <= 0) {
        return 0;
    }

    if ((int)($subject['offering_id'] ?? 0) > 0) {
        $stmt = $conn->prepare("
            UPDATE `{$tableName}`
            SET is_active = 0
            WHERE program_id = ?
              AND ay_id = ?
              AND semester = ?
              AND section_id = ?
              AND offering_id = ?
              AND is_active = 1
        ");

        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the enrollment replacement query.');
        }

        $stmt->bind_param(
            'iiiii',
            $scope['program_id'],
            $scope['ay_id'],
            $scope['semester'],
            $section['section_id'],
            $subject['offering_id']
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE `{$tableName}`
            SET is_active = 0
            WHERE program_id = ?
              AND ay_id = ?
              AND semester = ?
              AND section_id = ?
              AND subject_id = ?
              AND is_active = 1
        ");

        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the enrollment replacement query.');
        }

        $stmt->bind_param(
            'iiiii',
            $scope['program_id'],
            $scope['ay_id'],
            $scope['semester'],
            $section['section_id'],
            $subject['subject_id']
        );
    }

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to replace the previous student subject rows for this scope.');
    }

    $deletedRows = max(0, (int)$stmt->affected_rows);
    $stmt->close();

    return $deletedRows;
}

function synk_student_management_import_exam_permits_csv(
    mysqli $conn,
    string $csvPath,
    string $originalName,
    array $scope,
    int $uploadedBy = 0
): array {
    synk_student_management_ensure_exam_permits_schema($conn);

    $resolvedScope = synk_student_management_resolve_upload_scope($conn, $scope);
    $parsed = synk_student_management_parse_exam_permits_csv($csvPath);
    synk_student_management_validate_import_scope($parsed, $resolvedScope);

    $studentCatalog = synk_student_management_fetch_master_student_catalog($conn);
    $tableName = synk_student_management_enrollment_table_name();
    $importBatchKey = date('YmdHis') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
    $deletedRows = 0;
    $updatedStudents = 0;
    $insertedStudents = 0;
    $matchedStudents = 0;

    $insertStmt = $conn->prepare("
        INSERT INTO `{$tableName}` (
            import_batch_key,
            source_file_name,
            campus_id,
            college_id,
            program_id,
            ay_id,
            semester,
            year_level,
            section_id,
            section_text,
            offering_id,
            subject_id,
            subject_code,
            descriptive_title,
            student_id,
            faculty_id,
            room_id,
            room_text,
            schedule_text,
            source_row_number,
            uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare the student enrolled subjects insert query.');
    }

    try {
        $conn->begin_transaction();

        $section = synk_student_management_resolve_section(
            $conn,
            $resolvedScope,
            (string)($resolvedScope['section_name'] ?? '')
        );
        $subject = synk_student_management_resolve_subject_and_offering(
            $conn,
            $resolvedScope,
            $section,
            (string)($parsed['subject_code'] ?? ''),
            (string)($parsed['descriptive_title'] ?? '')
        );
        $faculty = synk_student_management_resolve_faculty(
            $conn,
            $resolvedScope,
            (string)($parsed['faculty_name'] ?? '')
        );
        $room = synk_student_management_resolve_room(
            $conn,
            $resolvedScope,
            (string)($parsed['room_name'] ?? '')
        );
        $resolvedYearLevel = max(
            1,
            (int)($section['year_level'] ?? synk_student_management_extract_year_level_from_section((string)($parsed['section_name'] ?? '')))
        );
        $resolvedSectionText = synk_student_management_normalize_space((string)($section['full_section'] ?? $parsed['section_name'] ?? ''));

        $deletedRows = synk_student_management_delete_existing_enrollment_scope(
            $conn,
            $resolvedScope,
            $section,
            $subject
        );

        foreach ($parsed['students'] as $studentRow) {
            $matchedStudent = synk_student_management_find_existing_master_student($studentCatalog, $studentRow);
            $studentRecord = synk_student_management_build_master_student_record(
                $studentRow,
                $resolvedScope,
                $resolvedYearLevel,
                $originalName,
                $uploadedBy
            );

            if (is_array($matchedStudent) && (int)($matchedStudent['student_id'] ?? 0) > 0) {
                $studentId = (int)$matchedStudent['student_id'];
                $matchedStudents++;

                $mergedStudentRecord = synk_student_management_merge_master_student_record(
                    $matchedStudent,
                    $studentRecord
                );

                if (synk_student_management_master_record_has_changes($matchedStudent, $mergedStudentRecord)) {
                    synk_student_management_update_master_student_record($conn, $studentId, $mergedStudentRecord);
                    $updatedStudents++;
                }

                synk_student_management_refresh_master_student_catalog($studentCatalog, $mergedStudentRecord, $studentId);
            } else {
                $studentId = synk_student_management_insert_master_student_record($conn, $studentRecord);
                $insertedStudents++;
                synk_student_management_refresh_master_student_catalog($studentCatalog, $studentRecord, $studentId);
            }

            $insertValues = [
                $importBatchKey,
                synk_student_management_normalize_space($originalName),
                (int)$resolvedScope['campus_id'],
                (int)$resolvedScope['college_id'],
                (int)$resolvedScope['program_id'],
                (int)$resolvedScope['ay_id'],
                (int)$resolvedScope['semester'],
                $resolvedYearLevel,
                (int)$section['section_id'],
                $resolvedSectionText,
                (int)($subject['offering_id'] ?? 0),
                (int)($subject['subject_id'] ?? 0),
                (string)($subject['subject_code'] ?? $parsed['subject_code']),
                (string)($subject['descriptive_title'] ?? $parsed['descriptive_title']),
                $studentId,
                (int)($faculty['faculty_id'] ?? 0),
                (int)($room['room_id'] ?? 0),
                (string)($room['room_text'] ?? ''),
                (string)($parsed['class_schedule_text'] ?? ''),
                max(0, (int)($studentRow['source_row_number'] ?? 0)),
                $uploadedBy > 0 ? $uploadedBy : 0,
            ];

            if (!synk_bind_dynamic_params($insertStmt, 'ssiiiiiiisiissiiissii', $insertValues)) {
                throw new RuntimeException('Unable to bind a student enrolled subject row.');
            }

            if (!$insertStmt->execute()) {
                throw new RuntimeException('Unable to insert one of the student enrolled subject rows.');
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $insertStmt->close();
        throw $e;
    }

    $insertStmt->close();

    return [
        'import_batch_key' => $importBatchKey,
        'source_file_name' => synk_student_management_normalize_space($originalName),
        'campus_id' => (int)$resolvedScope['campus_id'],
        'campus_name' => (string)$resolvedScope['campus_name'],
        'college_id' => (int)$resolvedScope['college_id'],
        'college_name' => (string)$resolvedScope['college_name'],
        'program_id' => (int)$resolvedScope['program_id'],
        'program_code' => (string)$resolvedScope['program_code'],
        'program_name' => (string)$resolvedScope['program_name'],
        'academic_year_label' => (string)$resolvedScope['academic_year_label'],
        'ay_id' => (int)$resolvedScope['ay_id'],
        'semester' => (int)$resolvedScope['semester'],
        'semester_label' => (string)$resolvedScope['semester_label'],
        'year_level' => $resolvedYearLevel,
        'subject_code' => (string)($subject['subject_code'] ?? $parsed['subject_code']),
        'subject_id' => (int)($subject['subject_id'] ?? 0),
        'subject_title' => (string)($subject['descriptive_title'] ?? $parsed['descriptive_title']),
        'subject_link_source' => (string)($subject['resolved_via'] ?? ''),
        'section_id' => (int)($section['section_id'] ?? 0),
        'section_name' => (string)($section['section_name'] ?? $parsed['section_name']),
        'section_display' => $resolvedSectionText,
        'faculty_id' => (int)($faculty['faculty_id'] ?? 0),
        'faculty_name' => (string)($faculty['faculty_name'] ?? ''),
        'room_id' => (int)($room['room_id'] ?? 0),
        'room_name' => (string)($room['room_display'] ?? ''),
        'room_linked' => !empty($room['matched']),
        'schedule_text' => (string)($parsed['class_schedule_text'] ?? ''),
        'student_count' => count($parsed['students']),
        'matched_students' => $matchedStudents,
        'updated_students' => $updatedStudents,
        'inserted_students' => $insertedStudents,
        'deleted_rows' => $deletedRows,
    ];
}

function synk_student_management_exam_permits_latest_batch(mysqli $conn): array
{
    synk_student_management_ensure_exam_permits_schema($conn);
    $tableName = synk_student_management_enrollment_table_name();
    $result = $conn->query("
        SELECT
            es.import_batch_key,
            es.source_file_name,
            COALESCE(cam.campus_name, '') AS campus_name,
            COALESCE(col.college_name, '') AS college_name,
            COALESCE(p.program_code, '') AS program_code,
            COALESCE(p.program_name, '') AS program_name,
            COALESCE(ay.ay, '') AS academic_year_label,
            es.semester,
            es.year_level,
            COALESCE(NULLIF(sec.section_name, ''), es.section_text) AS section_name,
            COALESCE(NULLIF(sec.full_section, ''), es.section_text) AS section_display,
            es.subject_code,
            es.descriptive_title,
            TRIM(CONCAT_WS(' ', f.first_name, f.middle_name, f.last_name, f.ext_name)) AS faculty_name,
            CASE
                WHEN es.room_id > 0 THEN TRIM(CONCAT_WS(' - ', r.room_code, NULLIF(r.room_name, '')))
                ELSE es.room_text
            END AS room_name,
            es.schedule_text,
            COUNT(*) AS student_count,
            MAX(es.created_at) AS imported_at
        FROM `{$tableName}` es
        LEFT JOIN tbl_campus cam
            ON cam.campus_id = es.campus_id
        LEFT JOIN tbl_college col
            ON col.college_id = es.college_id
        LEFT JOIN tbl_program p
            ON p.program_id = es.program_id
        LEFT JOIN tbl_academic_years ay
            ON ay.ay_id = es.ay_id
        LEFT JOIN tbl_sections sec
            ON sec.section_id = es.section_id
        LEFT JOIN tbl_faculty f
            ON f.faculty_id = es.faculty_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = es.room_id
        WHERE es.is_active = 1
        GROUP BY
            es.import_batch_key,
            es.source_file_name,
            cam.campus_name,
            col.college_name,
            p.program_code,
            p.program_name,
            ay.ay,
            es.semester,
            es.year_level,
            sec.section_name,
            sec.full_section,
            es.section_text,
            es.subject_code,
            es.descriptive_title,
            f.first_name,
            f.middle_name,
            f.last_name,
            f.ext_name,
            es.room_id,
            r.room_code,
            r.room_name,
            es.room_text,
            es.schedule_text
        ORDER BY imported_at DESC
        LIMIT 1
    ");

    if (!($result instanceof mysqli_result)) {
        return [
            'import_batch_key' => '',
            'source_file_name' => '',
            'campus_name' => '',
            'college_name' => '',
            'program_code' => '',
            'program_name' => '',
            'academic_year_label' => '',
            'semester' => 0,
            'semester_label' => '',
            'section_name' => '',
            'section_display' => '',
            'subject_code' => '',
            'descriptive_title' => '',
            'faculty_name' => '',
            'room_name' => '',
            'schedule_text' => '',
            'student_count' => 0,
            'imported_at' => '',
        ];
    }

    $row = $result->fetch_assoc() ?: [];
    $result->close();
    $semester = (int)($row['semester'] ?? 0);

    return [
        'import_batch_key' => (string)($row['import_batch_key'] ?? ''),
        'source_file_name' => (string)($row['source_file_name'] ?? ''),
        'campus_name' => (string)($row['campus_name'] ?? ''),
        'college_name' => (string)($row['college_name'] ?? ''),
            'program_code' => (string)($row['program_code'] ?? ''),
            'program_name' => (string)($row['program_name'] ?? ''),
            'academic_year_label' => (string)($row['academic_year_label'] ?? ''),
            'semester' => $semester,
            'semester_label' => synk_student_management_semester_label_from_number($semester),
            'year_level' => (int)($row['year_level'] ?? 0),
            'section_name' => (string)($row['section_name'] ?? ''),
            'section_display' => (string)($row['section_display'] ?? ''),
            'subject_code' => (string)($row['subject_code'] ?? ''),
            'descriptive_title' => (string)($row['descriptive_title'] ?? ''),
            'faculty_name' => (string)($row['faculty_name'] ?? ''),
        'room_name' => (string)($row['room_name'] ?? ''),
        'schedule_text' => (string)($row['schedule_text'] ?? ''),
        'student_count' => (int)($row['student_count'] ?? 0),
        'imported_at' => (string)($row['imported_at'] ?? ''),
    ];
}

function synk_student_management_exam_permits_recent_batches(mysqli $conn, int $limit = 12): array
{
    synk_student_management_ensure_exam_permits_schema($conn);
    $tableName = synk_student_management_enrollment_table_name();
    $limit = max(1, min(50, $limit));
    $rows = [];

    $result = $conn->query("
        SELECT
            es.import_batch_key,
            es.source_file_name,
            COALESCE(cam.campus_name, '') AS campus_name,
            COALESCE(col.college_name, '') AS college_name,
            COALESCE(p.program_code, '') AS program_code,
            COALESCE(ay.ay, '') AS academic_year_label,
            es.semester,
            es.year_level,
            COALESCE(NULLIF(sec.full_section, ''), es.section_text) AS section_display,
            es.subject_code,
            MIN(es.is_active) AS is_active,
            COUNT(*) AS student_count,
            MAX(es.created_at) AS imported_at
        FROM `{$tableName}` es
        LEFT JOIN tbl_campus cam
            ON cam.campus_id = es.campus_id
        LEFT JOIN tbl_college col
            ON col.college_id = es.college_id
        LEFT JOIN tbl_program p
            ON p.program_id = es.program_id
        LEFT JOIN tbl_academic_years ay
            ON ay.ay_id = es.ay_id
        LEFT JOIN tbl_sections sec
            ON sec.section_id = es.section_id
        GROUP BY
            es.import_batch_key,
            es.source_file_name,
            cam.campus_name,
            col.college_name,
            p.program_code,
            ay.ay,
            es.semester,
            es.year_level,
            sec.full_section,
            es.section_text,
            es.subject_code
        ORDER BY imported_at DESC
        LIMIT {$limit}
    ");

    if (!($result instanceof mysqli_result)) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $semester = (int)($row['semester'] ?? 0);
        $rows[] = [
            'import_batch_key' => (string)($row['import_batch_key'] ?? ''),
            'source_file_name' => (string)($row['source_file_name'] ?? ''),
            'campus_name' => (string)($row['campus_name'] ?? ''),
            'college_name' => (string)($row['college_name'] ?? ''),
            'program_code' => (string)($row['program_code'] ?? ''),
            'academic_year_label' => (string)($row['academic_year_label'] ?? ''),
            'semester' => $semester,
            'semester_label' => synk_student_management_semester_label_from_number($semester),
            'year_level' => (int)($row['year_level'] ?? 0),
            'section_display' => (string)($row['section_display'] ?? ''),
            'subject_code' => (string)($row['subject_code'] ?? ''),
            'is_active' => (int)($row['is_active'] ?? 0),
            'student_count' => (int)($row['student_count'] ?? 0),
            'imported_at' => (string)($row['imported_at'] ?? ''),
        ];
    }

    $result->close();

    return $rows;
}

function synk_student_management_fetch_import_batch_summary(mysqli $conn, string $importBatchKey): ?array
{
    synk_student_management_ensure_exam_permits_schema($conn);
    $tableName = synk_student_management_enrollment_table_name();
    $batchKey = trim($importBatchKey);
    if ($batchKey === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            import_batch_key,
            source_file_name,
            program_id,
            ay_id,
            semester,
            section_id,
            offering_id,
            subject_id,
            MIN(is_active) AS is_active,
            COUNT(*) AS student_count,
            MAX(created_at) AS imported_at
        FROM `{$tableName}`
        WHERE import_batch_key = ?
        GROUP BY
            import_batch_key,
            source_file_name,
            program_id,
            ay_id,
            semester,
            section_id,
            offering_id,
            subject_id
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $batchKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    return [
        'import_batch_key' => (string)($row['import_batch_key'] ?? ''),
        'source_file_name' => (string)($row['source_file_name'] ?? ''),
        'program_id' => (int)($row['program_id'] ?? 0),
        'ay_id' => (int)($row['ay_id'] ?? 0),
        'semester' => (int)($row['semester'] ?? 0),
        'section_id' => (int)($row['section_id'] ?? 0),
        'offering_id' => (int)($row['offering_id'] ?? 0),
        'subject_id' => (int)($row['subject_id'] ?? 0),
        'is_active' => (int)($row['is_active'] ?? 0),
        'student_count' => (int)($row['student_count'] ?? 0),
        'imported_at' => (string)($row['imported_at'] ?? ''),
    ];
}

function synk_student_management_find_replacement_batch_key(mysqli $conn, array $batchSummary): string
{
    $tableName = synk_student_management_enrollment_table_name();
    $batchKey = trim((string)($batchSummary['import_batch_key'] ?? ''));
    if ($batchKey === '') {
        return '';
    }

    if ((int)($batchSummary['offering_id'] ?? 0) > 0) {
        $stmt = $conn->prepare("
            SELECT import_batch_key, MAX(created_at) AS imported_at
            FROM `{$tableName}`
            WHERE program_id = ?
              AND ay_id = ?
              AND semester = ?
              AND section_id = ?
              AND offering_id = ?
              AND is_active = 0
              AND import_batch_key <> ?
            GROUP BY import_batch_key
            ORDER BY imported_at DESC
            LIMIT 1
        ");

        if (!$stmt) {
            return '';
        }

        $stmt->bind_param(
            'iiiiis',
            $batchSummary['program_id'],
            $batchSummary['ay_id'],
            $batchSummary['semester'],
            $batchSummary['section_id'],
            $batchSummary['offering_id'],
            $batchKey
        );
    } else {
        $stmt = $conn->prepare("
            SELECT import_batch_key, MAX(created_at) AS imported_at
            FROM `{$tableName}`
            WHERE program_id = ?
              AND ay_id = ?
              AND semester = ?
              AND section_id = ?
              AND subject_id = ?
              AND is_active = 0
              AND import_batch_key <> ?
            GROUP BY import_batch_key
            ORDER BY imported_at DESC
            LIMIT 1
        ");

        if (!$stmt) {
            return '';
        }

        $stmt->bind_param(
            'iiiiis',
            $batchSummary['program_id'],
            $batchSummary['ay_id'],
            $batchSummary['semester'],
            $batchSummary['section_id'],
            $batchSummary['subject_id'],
            $batchKey
        );
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? trim((string)($row['import_batch_key'] ?? '')) : '';
}

function synk_student_management_delete_orphan_master_students(mysqli $conn, array $studentIds): int
{
    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
    if (empty($studentIds)) {
        return 0;
    }

    $studentTable = synk_student_management_table_name();
    $enrollmentTable = synk_student_management_enrollment_table_name();
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $conn->prepare("
        DELETE sm
        FROM `{$studentTable}` sm
        LEFT JOIN `{$enrollmentTable}` es
            ON es.student_id = sm.student_id
        WHERE sm.student_id IN ({$placeholders})
          AND es.student_id IS NULL
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare orphan student cleanup.');
    }

    $types = str_repeat('i', count($studentIds));
    if (!synk_bind_dynamic_params($stmt, $types, $studentIds)) {
        $stmt->close();
        throw new RuntimeException('Unable to bind orphan student cleanup values.');
    }

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to remove orphan student master rows.');
    }

    $deletedStudents = max(0, (int)$stmt->affected_rows);
    $stmt->close();

    return $deletedStudents;
}

function synk_student_management_fetch_section_reference_tables(mysqli $conn): array
{
    $tables = [];
    $result = $conn->query("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND COLUMN_NAME = 'section_id'
          AND TABLE_NAME <> 'tbl_sections'
        ORDER BY TABLE_NAME ASC
    ");

    if (!($result instanceof mysqli_result)) {
        return $tables;
    }

    while ($row = $result->fetch_assoc()) {
        $tableName = trim((string)($row['TABLE_NAME'] ?? ''));
        if ($tableName !== '') {
            $tables[] = $tableName;
        }
    }

    $result->close();

    return array_values(array_unique($tables));
}

function synk_student_management_delete_unused_section(mysqli $conn, int $sectionId): array
{
    $sectionId = max(0, $sectionId);
    if ($sectionId <= 0) {
        return [
            'deleted' => false,
            'blocked_by' => [],
        ];
    }

    $blockedBy = [];
    foreach (synk_student_management_fetch_section_reference_tables($conn) as $tableName) {
        $safeTableName = str_replace('`', '``', $tableName);
        $stmt = $conn->prepare("SELECT COUNT(*) AS ref_count FROM `{$safeTableName}` WHERE section_id = ?");
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('i', $sectionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        $refCount = (int)($row['ref_count'] ?? 0);
        if ($refCount > 0) {
            $blockedBy[] = [
                'table' => $tableName,
                'count' => $refCount,
            ];
        }
    }

    if (!empty($blockedBy)) {
        return [
            'deleted' => false,
            'blocked_by' => $blockedBy,
        ];
    }

    $stmt = $conn->prepare("
        DELETE FROM tbl_sections
        WHERE section_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare section cleanup.');
    }

    $stmt->bind_param('i', $sectionId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to remove the unused section created for backward encoding.');
    }

    $deleted = (int)$stmt->affected_rows > 0;
    $stmt->close();

    return [
        'deleted' => $deleted,
        'blocked_by' => [],
    ];
}

function synk_student_management_delete_import_batch(mysqli $conn, string $importBatchKey): array
{
    synk_student_management_ensure_exam_permits_schema($conn);
    $tableName = synk_student_management_enrollment_table_name();
    $batchKey = trim($importBatchKey);
    if ($batchKey === '') {
        throw new RuntimeException('Select a valid uploaded batch to delete.');
    }

    $batchSummary = synk_student_management_fetch_import_batch_summary($conn, $batchKey);
    if (!$batchSummary) {
        throw new RuntimeException('The selected uploaded batch could not be found.');
    }

    $studentIds = [];
    $stmt = $conn->prepare("
        SELECT DISTINCT student_id
        FROM `{$tableName}`
        WHERE import_batch_key = ?
          AND student_id > 0
        ORDER BY student_id ASC
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the uploaded-batch student lookup.');
    }

    $stmt->bind_param('s', $batchKey);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
        $studentIds[] = (int)($row['student_id'] ?? 0);
    }
    $stmt->close();

    $restoredBatchKey = '';
    $restoredRows = 0;
    $deletedRows = 0;
    $deletedStudents = 0;
    $deletedSection = false;
    $sectionCleanupBlockedBy = [];
    $wasActive = (int)($batchSummary['is_active'] ?? 0) === 1;

    try {
        $conn->begin_transaction();

        if ($wasActive) {
            $restoredBatchKey = synk_student_management_find_replacement_batch_key($conn, $batchSummary);
        }

        $stmt = $conn->prepare("
            DELETE FROM `{$tableName}`
            WHERE import_batch_key = ?
        ");

        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the uploaded-batch delete query.');
        }

        $stmt->bind_param('s', $batchKey);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to delete the selected uploaded batch.');
        }

        $deletedRows = max(0, (int)$stmt->affected_rows);
        $stmt->close();

        if ($deletedRows <= 0) {
            throw new RuntimeException('No uploaded rows were deleted for the selected batch.');
        }

        if ($restoredBatchKey !== '') {
            $stmt = $conn->prepare("
                UPDATE `{$tableName}`
                SET is_active = 1
                WHERE import_batch_key = ?
            ");

            if (!$stmt) {
                throw new RuntimeException('Unable to prepare the archived-batch restore query.');
            }

            $stmt->bind_param('s', $restoredBatchKey);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Unable to restore the previous uploaded batch.');
            }

            $restoredRows = max(0, (int)$stmt->affected_rows);
            $stmt->close();
        }

        $deletedStudents = synk_student_management_delete_orphan_master_students($conn, $studentIds);
        $sectionCleanup = synk_student_management_delete_unused_section($conn, (int)($batchSummary['section_id'] ?? 0));
        $deletedSection = !empty($sectionCleanup['deleted']);
        $sectionCleanupBlockedBy = is_array($sectionCleanup['blocked_by'] ?? null) ? $sectionCleanup['blocked_by'] : [];

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return [
        'import_batch_key' => (string)$batchSummary['import_batch_key'],
        'source_file_name' => (string)$batchSummary['source_file_name'],
        'student_count' => (int)$batchSummary['student_count'],
        'deleted_rows' => $deletedRows,
        'deleted_students' => $deletedStudents,
        'deleted_section' => $deletedSection,
        'section_cleanup_blocked_by' => $sectionCleanupBlockedBy,
        'restored_batch_key' => $restoredBatchKey,
        'restored_rows' => $restoredRows,
        'was_active' => $wasActive,
    ];
}
