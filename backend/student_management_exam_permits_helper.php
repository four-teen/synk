<?php

require_once __DIR__ . '/student_management_helper.php';

function synk_student_management_exam_permits_table_name(): string
{
    return 'tbl_student_management_exam_permits';
}

function synk_student_management_exam_permits_bind_params(mysqli_stmt $stmt, string $types, array $values): void
{
    if ($types === '' || empty($values)) {
        return;
    }

    $references = [$types];
    foreach ($values as $index => $value) {
        $references[] = &$values[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $references);
}

function synk_student_management_ensure_exam_permits_schema(mysqli $conn): void
{
    $tableName = synk_student_management_exam_permits_table_name();
    $sql = "
        CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `roster_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `import_batch_key` VARCHAR(64) NOT NULL DEFAULT '',
            `source_file_name` VARCHAR(255) NOT NULL DEFAULT '',
            `report_name` VARCHAR(100) NOT NULL DEFAULT '',
            `campus_name` VARCHAR(255) NOT NULL DEFAULT '',
            `campus_location` VARCHAR(255) NOT NULL DEFAULT '',
            `subject_section_raw` VARCHAR(255) NOT NULL DEFAULT '',
            `subject_code` VARCHAR(100) NOT NULL DEFAULT '',
            `subject_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `program_code` VARCHAR(50) NOT NULL DEFAULT '',
            `program_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `section_name` VARCHAR(50) NOT NULL DEFAULT '',
            `descriptive_title` VARCHAR(255) NOT NULL DEFAULT '',
            `student_credit` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `faculty_name` VARCHAR(255) NOT NULL DEFAULT '',
            `faculty_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `room_name` VARCHAR(255) NOT NULL DEFAULT '',
            `class_schedule_text` VARCHAR(255) NOT NULL DEFAULT '',
            `schedule_slot_1_day` VARCHAR(20) NOT NULL DEFAULT '',
            `schedule_slot_1_start_time` TIME NULL DEFAULT NULL,
            `schedule_slot_1_end_time` TIME NULL DEFAULT NULL,
            `schedule_slot_2_day` VARCHAR(20) NOT NULL DEFAULT '',
            `schedule_slot_2_start_time` TIME NULL DEFAULT NULL,
            `schedule_slot_2_end_time` TIME NULL DEFAULT NULL,
            `schedule_slot_3_day` VARCHAR(20) NOT NULL DEFAULT '',
            `schedule_slot_3_start_time` TIME NULL DEFAULT NULL,
            `schedule_slot_3_end_time` TIME NULL DEFAULT NULL,
            `student_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `student_number` INT UNSIGNED NOT NULL DEFAULT 0,
            `student_name_raw` VARCHAR(255) NOT NULL DEFAULT '',
            `last_name` VARCHAR(150) NOT NULL DEFAULT '',
            `first_name` VARCHAR(150) NOT NULL DEFAULT '',
            `middle_name` VARCHAR(150) NOT NULL DEFAULT '',
            `suffix_name` VARCHAR(50) NOT NULL DEFAULT '',
            `uploaded_by` INT UNSIGNED NULL DEFAULT NULL,
            `source_row_number` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`roster_id`),
            KEY `idx_exam_permits_batch` (`import_batch_key`),
            KEY `idx_exam_permits_subject_section` (`campus_name`, `subject_section_raw`),
            KEY `idx_exam_permits_student` (`student_id`),
            KEY `idx_exam_permits_program` (`program_id`),
            KEY `idx_exam_permits_faculty` (`faculty_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to prepare the exam permit upload table.');
    }

    $indexStatements = [
        'idx_exam_permits_batch' => "ALTER TABLE `{$tableName}` ADD INDEX `idx_exam_permits_batch` (`import_batch_key`)",
        'idx_exam_permits_subject_section' => "ALTER TABLE `{$tableName}` ADD INDEX `idx_exam_permits_subject_section` (`campus_name`, `subject_section_raw`)",
        'idx_exam_permits_student' => "ALTER TABLE `{$tableName}` ADD INDEX `idx_exam_permits_student` (`student_id`)",
        'idx_exam_permits_program' => "ALTER TABLE `{$tableName}` ADD INDEX `idx_exam_permits_program` (`program_id`)",
        'idx_exam_permits_faculty' => "ALTER TABLE `{$tableName}` ADD INDEX `idx_exam_permits_faculty` (`faculty_id`)",
        'idx_exam_permits_batch_created' => "ALTER TABLE `{$tableName}` ADD INDEX `idx_exam_permits_batch_created` (`import_batch_key`, `created_at`)",
        'idx_exam_permits_section_student' => "ALTER TABLE `{$tableName}` ADD INDEX `idx_exam_permits_section_student` (`campus_name`, `subject_section_raw`, `student_number`)",
    ];

    foreach ($indexStatements as $indexName => $indexSql) {
        if (!synk_table_has_index($conn, $tableName, $indexName)) {
            if (!$conn->query($indexSql)) {
                throw new RuntimeException('Unable to optimize the exam permit upload table indexes.');
            }
        }
    }
}

function synk_student_management_strip_utf8_bom(string $value): string
{
    return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
}

function synk_student_management_csv_cell(array $row, int $index): string
{
    $value = (string)($row[$index] ?? '');
    $value = synk_student_management_strip_utf8_bom($value);
    return synk_student_management_normalize_space($value);
}

function synk_student_management_normalize_lookup_key(string $value): string
{
    $normalized = strtoupper(synk_student_management_normalize_space($value));
    $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $normalized);
    $normalized = synk_student_management_normalize_space((string)$normalized);
    return $normalized;
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

function synk_student_management_parse_schedule_time(?string $value): ?string
{
    $normalized = strtolower(str_replace(' ', '', synk_student_management_normalize_space((string)$value)));
    if ($normalized === '') {
        return null;
    }

    $formats = ['g:ia', 'g:iA', 'h:ia', 'h:iA'];
    foreach ($formats as $format) {
        $time = DateTime::createFromFormat($format, $normalized);
        if ($time instanceof DateTime) {
            return $time->format('H:i:s');
        }
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return null;
    }

    return date('H:i:s', $timestamp);
}

function synk_student_management_parse_schedule_slots(string $value): array
{
    $normalized = synk_student_management_normalize_space($value);
    $slots = [];

    if ($normalized !== '' && preg_match_all('/([A-Z]{1,5})\s+(\d{1,2}:\d{2}\s*[APap][Mm])-(\d{1,2}:\d{2}\s*[APap][Mm])/', strtoupper($normalized), $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $slots[] = [
                'day' => synk_student_management_normalize_space((string)($match[1] ?? '')),
                'start_time' => synk_student_management_parse_schedule_time((string)($match[2] ?? '')),
                'end_time' => synk_student_management_parse_schedule_time((string)($match[3] ?? '')),
            ];
        }
    }

    for ($index = count($slots); $index < 3; $index++) {
        $slots[] = [
            'day' => '',
            'start_time' => null,
            'end_time' => null,
        ];
    }

    return array_slice($slots, 0, 3);
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
        throw new RuntimeException('The CSV file does not contain the expected exam permit layout.');
    }

    $campusHeader = synk_student_management_csv_cell($rows[0], 0);
    $campusName = $campusHeader;
    if (preg_match('/-\s*(.+)$/', $campusHeader, $matches)) {
        $campusName = synk_student_management_normalize_space((string)($matches[1] ?? ''));
    }

    $subjectSection = synk_student_management_parse_subject_section_field(
        synk_student_management_csv_cell($rows[3], 3)
    );

    $studentCreditRaw = preg_replace('/[^0-9.]+/', '', synk_student_management_csv_cell($rows[3], 9));
    $studentCredit = is_string($studentCreditRaw) && $studentCreditRaw !== '' ? (float)$studentCreditRaw : 0.0;
    $classScheduleText = synk_student_management_csv_cell($rows[4], 9);

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
        'student_credit' => $studentCredit,
        'class_schedule_text' => $classScheduleText,
        'faculty_name' => synk_student_management_csv_cell($rows[5], 3),
        'room_name' => synk_student_management_csv_cell($rows[5], 9),
        'schedule_slots' => synk_student_management_parse_schedule_slots($classScheduleText),
        'students' => [],
    ];

    $headerNo = strtoupper(synk_student_management_csv_cell($rows[6], 0));
    $headerName = strtoupper(synk_student_management_csv_cell($rows[6], 1));
    if ($headerNo !== 'NO.' || strpos($headerName, "STUDENT'S NAME") === false) {
        throw new RuntimeException('The CSV file does not match the expected exam permit student list header.');
    }

    for ($index = 7; $index < count($rows); $index++) {
        $currentRow = $rows[$index];
        $rowNumber = synk_student_management_csv_cell($currentRow, 0);
        $studentName = synk_student_management_csv_cell($currentRow, 1);
        $studentIdRaw = preg_replace('/\D+/', '', synk_student_management_csv_cell($currentRow, 4));

        if ($studentName === '') {
            continue;
        }

        $upperStudentName = strtoupper($studentName);
        if (strpos($upperStudentName, 'NOTHING FOLLOWS') !== false || $upperStudentName === 'DATE PRINTED') {
            break;
        }

        if ($rowNumber === '' || !ctype_digit($rowNumber)) {
            continue;
        }

        $nameParts = synk_student_management_parse_csv_student_name($studentName);
        $parsed['students'][] = [
            'source_row_number' => $index + 1,
            'row_number' => (int)$rowNumber,
            'student_number' => $studentIdRaw !== '' ? (int)$studentIdRaw : 0,
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

function synk_student_management_fetch_subject_catalog(mysqli $conn): array
{
    $catalog = [];
    $result = $conn->query("
        SELECT
            sub_id,
            sub_code,
            sub_description
        FROM tbl_subject_masterlist
        WHERE status = 'active'
        ORDER BY sub_id ASC
    ");

    if (!($result instanceof mysqli_result)) {
        return $catalog;
    }

    while ($row = $result->fetch_assoc()) {
        $codeKey = synk_student_management_normalize_lookup_key((string)($row['sub_code'] ?? ''));
        if ($codeKey === '') {
            continue;
        }

        $catalog[$codeKey][] = [
            'subject_id' => (int)($row['sub_id'] ?? 0),
            'subject_code' => (string)($row['sub_code'] ?? ''),
            'sub_description' => (string)($row['sub_description'] ?? ''),
            'description_key' => synk_student_management_normalize_lookup_key((string)($row['sub_description'] ?? '')),
        ];
    }

    $result->close();
    return $catalog;
}

function synk_student_management_resolve_subject_catalog_match(array $catalog, string $subjectCode, string $descriptiveTitle): array
{
    $codeKey = synk_student_management_normalize_lookup_key($subjectCode);
    $candidates = $catalog[$codeKey] ?? [];
    $descriptionKey = synk_student_management_normalize_lookup_key($descriptiveTitle);

    foreach ($candidates as $candidate) {
        if ($descriptionKey !== '' && $descriptionKey === (string)($candidate['description_key'] ?? '')) {
            return $candidate;
        }
    }

    return !empty($candidates)
        ? (array)$candidates[0]
        : ['subject_id' => 0, 'subject_code' => $subjectCode, 'sub_description' => $descriptiveTitle];
}

function synk_student_management_resolve_program_catalog_match(array $programCatalog, string $programCode, string $campusName): array
{
    $codeKey = synk_student_management_normalize_lookup_key($programCode);
    $campusKey = synk_student_management_normalize_lookup_key($campusName);
    $fallback = null;

    foreach ($programCatalog as $program) {
        $programKey = synk_student_management_normalize_lookup_key((string)($program['program_code'] ?? ''));
        if ($programKey !== $codeKey) {
            continue;
        }

        if ($fallback === null) {
            $fallback = $program;
        }

        if ($campusKey !== '' && synk_student_management_normalize_lookup_key((string)($program['campus_name'] ?? '')) === $campusKey) {
            return $program;
        }
    }

    return is_array($fallback)
        ? $fallback
        : [
            'program_id' => 0,
            'program_code' => $programCode,
            'program_name' => '',
            'major' => '',
            'college_name' => '',
            'campus_name' => $campusName,
            'source_program_name' => $programCode,
        ];
}

function synk_student_management_fetch_faculty_catalog(mysqli $conn): array
{
    $catalog = [];
    $result = $conn->query("
        SELECT
            faculty_id,
            last_name,
            first_name,
            COALESCE(middle_name, '') AS middle_name,
            COALESCE(ext_name, '') AS ext_name
        FROM tbl_faculty
        WHERE status = 'active'
        ORDER BY faculty_id ASC
    ");

    if (!($result instanceof mysqli_result)) {
        return $catalog;
    }

    while ($row = $result->fetch_assoc()) {
        $fullSimple = synk_student_management_normalize_lookup_key(
            implode(' ', array_filter([
                (string)($row['first_name'] ?? ''),
                (string)($row['last_name'] ?? ''),
                (string)($row['ext_name'] ?? ''),
            ]))
        );
        $fullWithMiddle = synk_student_management_normalize_lookup_key(
            implode(' ', array_filter([
                (string)($row['first_name'] ?? ''),
                (string)($row['middle_name'] ?? ''),
                (string)($row['last_name'] ?? ''),
                (string)($row['ext_name'] ?? ''),
            ]))
        );

        $normalizedRow = [
            'faculty_id' => (int)($row['faculty_id'] ?? 0),
            'faculty_name' => trim(implode(' ', array_filter([
                (string)($row['first_name'] ?? ''),
                (string)($row['middle_name'] ?? ''),
                (string)($row['last_name'] ?? ''),
                (string)($row['ext_name'] ?? ''),
            ]))),
        ];

        foreach (array_filter([$fullSimple, $fullWithMiddle]) as $key) {
            if (!isset($catalog[$key])) {
                $catalog[$key] = $normalizedRow;
            }
        }
    }

    $result->close();
    return $catalog;
}

function synk_student_management_resolve_faculty_catalog_match(array $catalog, string $facultyName): array
{
    $key = synk_student_management_normalize_lookup_key($facultyName);
    return $catalog[$key] ?? ['faculty_id' => 0, 'faculty_name' => $facultyName];
}

function synk_student_management_fetch_student_name_catalog(mysqli $conn): array
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();
    $catalog = [];
    $result = $conn->query("
        SELECT
            student_id,
            student_number,
            last_name,
            first_name,
            middle_name,
            suffix_name,
            email_address
        FROM `{$tableName}`
        ORDER BY student_id DESC
    ");

    if (!($result instanceof mysqli_result)) {
        return $catalog;
    }

    while ($row = $result->fetch_assoc()) {
        $key = synk_student_management_build_student_name_key(
            (string)($row['last_name'] ?? ''),
            (string)($row['first_name'] ?? ''),
            (string)($row['middle_name'] ?? ''),
            (string)($row['suffix_name'] ?? '')
        );

        if ($key === '' || isset($catalog[$key])) {
            continue;
        }

        $catalog[$key] = [
            'student_id' => (int)($row['student_id'] ?? 0),
            'student_number' => (int)($row['student_number'] ?? 0),
            'email_address' => (string)($row['email_address'] ?? ''),
            'last_name' => (string)($row['last_name'] ?? ''),
            'first_name' => (string)($row['first_name'] ?? ''),
            'middle_name' => (string)($row['middle_name'] ?? ''),
            'suffix_name' => (string)($row['suffix_name'] ?? ''),
        ];
    }

    $result->close();
    return $catalog;
}

function synk_student_management_update_student_number_only(mysqli $conn, int $studentId, int $studentNumber): void
{
    if ($studentId <= 0 || $studentNumber <= 0) {
        return;
    }

    $tableName = synk_student_management_table_name();
    $stmt = $conn->prepare("
        UPDATE `{$tableName}`
        SET student_number = ?
        WHERE student_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to update the matched student number.');
    }

    $stmt->bind_param('ii', $studentNumber, $studentId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to save the matched student ID number.');
    }

    $stmt->close();
}

function synk_student_management_insert_csv_student_record(
    mysqli $conn,
    array $studentRow,
    array $program,
    array $context,
    int $uploadedBy = 0
): int {
    $tableName = synk_student_management_table_name();
    $studentNumber = max(0, (int)($studentRow['student_number'] ?? 0));
    $emailAddress = synk_student_management_build_email(
        (string)($studentRow['first_name'] ?? ''),
        (string)($studentRow['last_name'] ?? '')
    );
    $yearLevel = synk_student_management_extract_year_level_from_section((string)($context['section_name'] ?? ''));
    $programId = max(0, (int)($program['program_id'] ?? 0));
    $sourceProgramName = trim((string)($program['source_program_name'] ?? (string)($context['program_code'] ?? '')));
    $collegeName = trim((string)($program['college_name'] ?? ''));
    $campusName = trim((string)($context['campus_name'] ?? (string)($program['campus_name'] ?? '')));
    $sourceSheetName = trim((string)($context['report_name'] ?? 'CSV IMPORT'));
    $sourceFileName = trim((string)($context['source_file_name'] ?? 'CSV Upload'));
    $academicYearLabel = trim((string)($context['academic_year_label'] ?? ''));
    $semesterLabel = trim((string)($context['semester_label'] ?? ''));
    $sourceRowNumber = max(0, (int)($studentRow['source_row_number'] ?? 0));
    $uploadedByValue = $uploadedBy > 0 ? $uploadedBy : 0;

    $stmt = $conn->prepare("
        INSERT INTO `{$tableName}` (
            academic_year_label,
            semester_label,
            source_sheet_name,
            source_file_name,
            college_name,
            campus_name,
            source_program_name,
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
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new RuntimeException('Unable to insert the missing student into the student table.');
    }

    $stmt->bind_param(
        'sssssssiisssssiii',
        $academicYearLabel,
        $semesterLabel,
        $sourceSheetName,
        $sourceFileName,
        $collegeName,
        $campusName,
        $sourceProgramName,
        $yearLevel,
        $studentNumber,
        $studentRow['last_name'],
        $studentRow['first_name'],
        $studentRow['middle_name'],
        $studentRow['suffix_name'],
        $emailAddress,
        $programId,
        $uploadedByValue,
        $sourceRowNumber
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to insert a missing student into the student table.');
    }

    $insertId = (int)$stmt->insert_id;
    $stmt->close();

    return $insertId;
}

function synk_student_management_import_exam_permits_csv(
    mysqli $conn,
    string $csvPath,
    string $originalName,
    int $uploadedBy = 0
): array {
    synk_student_management_ensure_schema($conn);
    synk_student_management_ensure_exam_permits_schema($conn);

    $parsed = synk_student_management_parse_exam_permits_csv($csvPath);
    $subjectCatalog = synk_student_management_fetch_subject_catalog($conn);
    $programCatalog = synk_student_management_fetch_program_catalog($conn);
    $facultyCatalog = synk_student_management_fetch_faculty_catalog($conn);
    $studentCatalog = synk_student_management_fetch_student_name_catalog($conn);
    $latestBatch = synk_student_management_latest_batch($conn);

    $subject = synk_student_management_resolve_subject_catalog_match(
        $subjectCatalog,
        (string)($parsed['subject_code'] ?? ''),
        (string)($parsed['descriptive_title'] ?? '')
    );
    $program = synk_student_management_resolve_program_catalog_match(
        $programCatalog,
        (string)($parsed['program_code'] ?? ''),
        (string)($parsed['campus_name'] ?? '')
    );
    $faculty = synk_student_management_resolve_faculty_catalog_match(
        $facultyCatalog,
        (string)($parsed['faculty_name'] ?? '')
    );

    $parsed['source_file_name'] = synk_student_management_normalize_space($originalName);
    $parsed['academic_year_label'] = trim((string)($latestBatch['academic_year_label'] ?? ''));
    $parsed['semester_label'] = trim((string)($latestBatch['semester_label'] ?? ''));

    $tableName = synk_student_management_exam_permits_table_name();
    $importBatchKey = date('YmdHis') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
    $deletedRows = 0;
    $updatedStudents = 0;
    $insertedStudents = 0;
    $matchedStudents = 0;

    $deleteStmt = $conn->prepare("
        DELETE FROM `{$tableName}`
        WHERE campus_name = ?
          AND subject_section_raw = ?
    ");

    if (!$deleteStmt) {
        throw new RuntimeException('Unable to prepare the roster replacement query.');
    }

    $deleteStmt->bind_param(
        'ss',
        $parsed['campus_name'],
        $parsed['subject_section_raw']
    );

    $insertStmt = $conn->prepare("
        INSERT INTO `{$tableName}` (
            import_batch_key,
            source_file_name,
            report_name,
            campus_name,
            campus_location,
            subject_section_raw,
            subject_code,
            subject_id,
            program_code,
            program_id,
            section_name,
            descriptive_title,
            student_credit,
            faculty_name,
            faculty_id,
            room_name,
            class_schedule_text,
            schedule_slot_1_day,
            schedule_slot_1_start_time,
            schedule_slot_1_end_time,
            schedule_slot_2_day,
            schedule_slot_2_start_time,
            schedule_slot_2_end_time,
            schedule_slot_3_day,
            schedule_slot_3_start_time,
            schedule_slot_3_end_time,
            student_id,
            student_number,
            student_name_raw,
            last_name,
            first_name,
            middle_name,
            suffix_name,
            uploaded_by,
            source_row_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insertStmt) {
        $deleteStmt->close();
        throw new RuntimeException('Unable to prepare the exam permit insert query.');
    }

    try {
        $conn->begin_transaction();

        if (!$deleteStmt->execute()) {
            throw new RuntimeException('Unable to replace the previous roster import for this section.');
        }

        $deletedRows = max(0, (int)$deleteStmt->affected_rows);

        foreach ($parsed['students'] as $studentRow) {
            $studentKey = (string)($studentRow['name_key'] ?? '');
            $matchedStudent = $studentKey !== '' ? ($studentCatalog[$studentKey] ?? null) : null;
            $studentId = 0;
            $studentNumber = max(0, (int)($studentRow['student_number'] ?? 0));

            if (is_array($matchedStudent) && (int)($matchedStudent['student_id'] ?? 0) > 0) {
                $studentId = (int)$matchedStudent['student_id'];
                $matchedStudents++;

                if ($studentNumber > 0 && (int)($matchedStudent['student_number'] ?? 0) !== $studentNumber) {
                    synk_student_management_update_student_number_only($conn, $studentId, $studentNumber);
                    $updatedStudents++;
                    $studentCatalog[$studentKey]['student_number'] = $studentNumber;
                }
            } else {
                $studentId = synk_student_management_insert_csv_student_record(
                    $conn,
                    $studentRow,
                    $program,
                    $parsed,
                    $uploadedBy
                );
                $insertedStudents++;
                $studentCatalog[$studentKey] = [
                    'student_id' => $studentId,
                    'student_number' => $studentNumber,
                    'email_address' => synk_student_management_build_email(
                        (string)($studentRow['first_name'] ?? ''),
                        (string)($studentRow['last_name'] ?? '')
                    ),
                    'last_name' => (string)($studentRow['last_name'] ?? ''),
                    'first_name' => (string)($studentRow['first_name'] ?? ''),
                    'middle_name' => (string)($studentRow['middle_name'] ?? ''),
                    'suffix_name' => (string)($studentRow['suffix_name'] ?? ''),
                ];
            }

            $uploadedByValue = $uploadedBy > 0 ? $uploadedBy : 0;
            $scheduleSlots = $parsed['schedule_slots'];
            $insertValues = [
                $importBatchKey,
                (string)$parsed['source_file_name'],
                (string)$parsed['report_name'],
                (string)$parsed['campus_name'],
                (string)$parsed['campus_location'],
                (string)$parsed['subject_section_raw'],
                (string)$parsed['subject_code'],
                (int)($subject['subject_id'] ?? 0),
                (string)$parsed['program_code'],
                (int)($program['program_id'] ?? 0),
                (string)$parsed['section_name'],
                (string)$parsed['descriptive_title'],
                (float)($parsed['student_credit'] ?? 0),
                (string)$parsed['faculty_name'],
                (int)($faculty['faculty_id'] ?? 0),
                (string)$parsed['room_name'],
                (string)$parsed['class_schedule_text'],
                (string)($scheduleSlots[0]['day'] ?? ''),
                $scheduleSlots[0]['start_time'] ?? null,
                $scheduleSlots[0]['end_time'] ?? null,
                (string)($scheduleSlots[1]['day'] ?? ''),
                $scheduleSlots[1]['start_time'] ?? null,
                $scheduleSlots[1]['end_time'] ?? null,
                (string)($scheduleSlots[2]['day'] ?? ''),
                $scheduleSlots[2]['start_time'] ?? null,
                $scheduleSlots[2]['end_time'] ?? null,
                $studentId,
                $studentNumber,
                (string)($studentRow['student_name_raw'] ?? ''),
                (string)($studentRow['last_name'] ?? ''),
                (string)($studentRow['first_name'] ?? ''),
                (string)($studentRow['middle_name'] ?? ''),
                (string)($studentRow['suffix_name'] ?? ''),
                $uploadedByValue,
                (int)($studentRow['source_row_number'] ?? 0),
            ];

            synk_student_management_exam_permits_bind_params(
                $insertStmt,
                'sssssssisissdsisssssssssssiisssssii',
                $insertValues
            );

            if (!$insertStmt->execute()) {
                throw new RuntimeException('Unable to insert one of the exam permit student rows.');
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $insertStmt->close();
        $deleteStmt->close();
        throw $e;
    }

    $insertStmt->close();
    $deleteStmt->close();

    return [
        'import_batch_key' => $importBatchKey,
        'source_file_name' => (string)$parsed['source_file_name'],
        'subject_section_raw' => (string)$parsed['subject_section_raw'],
        'subject_code' => (string)$parsed['subject_code'],
        'subject_id' => (int)($subject['subject_id'] ?? 0),
        'program_code' => (string)$parsed['program_code'],
        'program_id' => (int)($program['program_id'] ?? 0),
        'section_name' => (string)$parsed['section_name'],
        'faculty_name' => (string)$parsed['faculty_name'],
        'faculty_id' => (int)($faculty['faculty_id'] ?? 0),
        'room_name' => (string)$parsed['room_name'],
        'class_schedule_text' => (string)$parsed['class_schedule_text'],
        'student_count' => count($parsed['students']),
        'updated_students' => $updatedStudents,
        'inserted_students' => $insertedStudents,
        'matched_students' => $matchedStudents,
        'deleted_rows' => $deletedRows,
    ];
}

function synk_student_management_exam_permits_latest_batch(mysqli $conn): array
{
    synk_student_management_ensure_exam_permits_schema($conn);
    $tableName = synk_student_management_exam_permits_table_name();
    $result = $conn->query("
        SELECT
            import_batch_key,
            source_file_name,
            report_name,
            campus_name,
            subject_section_raw,
            subject_code,
            subject_id,
            program_code,
            program_id,
            section_name,
            descriptive_title,
            faculty_name,
            faculty_id,
            room_name,
            class_schedule_text,
            COUNT(*) AS student_count,
            MAX(created_at) AS imported_at
        FROM `{$tableName}`
        GROUP BY
            import_batch_key,
            source_file_name,
            report_name,
            campus_name,
            subject_section_raw,
            subject_code,
            subject_id,
            program_code,
            program_id,
            section_name,
            descriptive_title,
            faculty_name,
            faculty_id,
            room_name,
            class_schedule_text
        ORDER BY imported_at DESC
        LIMIT 1
    ");

    if (!($result instanceof mysqli_result)) {
        return [
            'import_batch_key' => '',
            'source_file_name' => '',
            'report_name' => '',
            'campus_name' => '',
            'subject_section_raw' => '',
            'subject_code' => '',
            'subject_id' => 0,
            'program_code' => '',
            'program_id' => 0,
            'section_name' => '',
            'descriptive_title' => '',
            'faculty_name' => '',
            'faculty_id' => 0,
            'room_name' => '',
            'class_schedule_text' => '',
            'student_count' => 0,
            'imported_at' => '',
        ];
    }

    $row = $result->fetch_assoc() ?: [];
    $result->close();

    return [
        'import_batch_key' => (string)($row['import_batch_key'] ?? ''),
        'source_file_name' => (string)($row['source_file_name'] ?? ''),
        'report_name' => (string)($row['report_name'] ?? ''),
        'campus_name' => (string)($row['campus_name'] ?? ''),
        'subject_section_raw' => (string)($row['subject_section_raw'] ?? ''),
        'subject_code' => (string)($row['subject_code'] ?? ''),
        'subject_id' => (int)($row['subject_id'] ?? 0),
        'program_code' => (string)($row['program_code'] ?? ''),
        'program_id' => (int)($row['program_id'] ?? 0),
        'section_name' => (string)($row['section_name'] ?? ''),
        'descriptive_title' => (string)($row['descriptive_title'] ?? ''),
        'faculty_name' => (string)($row['faculty_name'] ?? ''),
        'faculty_id' => (int)($row['faculty_id'] ?? 0),
        'room_name' => (string)($row['room_name'] ?? ''),
        'class_schedule_text' => (string)($row['class_schedule_text'] ?? ''),
        'student_count' => (int)($row['student_count'] ?? 0),
        'imported_at' => (string)($row['imported_at'] ?? ''),
    ];
}
