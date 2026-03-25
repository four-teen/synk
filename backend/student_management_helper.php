<?php

require_once __DIR__ . '/schema_helper.php';

function synk_student_management_table_name(): string
{
    return 'tbl_student_management';
}

function synk_student_management_require_admin(): void
{
    if (!isset($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../../index.php');
        exit;
    }
}

function synk_student_management_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function synk_student_management_normalize_space(string $value): string
{
    $trimmed = trim($value);
    $normalized = preg_replace('/\s+/u', ' ', $trimmed);

    return $normalized === null ? $trimmed : $normalized;
}

function synk_student_management_year_level_from_label(string $label): int
{
    $normalized = strtolower(synk_student_management_normalize_space($label));

    $map = [
        'first year' => 1,
        '1st year' => 1,
        'second year' => 2,
        '2nd year' => 2,
        'third year' => 3,
        '3rd year' => 3,
        'fourth year' => 4,
        '4th year' => 4,
        'fifth year' => 5,
        '5th year' => 5,
    ];

    return $map[$normalized] ?? 0;
}

function synk_student_management_year_label(int $yearLevel): string
{
    $map = [
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
        5 => '5th Year',
    ];

    return $map[$yearLevel] ?? 'Not Set';
}

function synk_student_management_ascii_token(string $value): string
{
    $normalized = synk_student_management_normalize_space($value);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($converted) && $converted !== '') {
            $normalized = $converted;
        }
    }

    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

    return $normalized ?? '';
}

function synk_student_management_build_email(string $firstName, string $lastName): string
{
    $token = synk_student_management_ascii_token($firstName) . synk_student_management_ascii_token($lastName);
    if ($token === '') {
        return '';
    }

    return $token . '@sksu.edu.ph';
}

function synk_student_management_ensure_schema(mysqli $conn): void
{
    $tableName = synk_student_management_table_name();

    $createSql = "
        CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `student_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `academic_year_label` VARCHAR(32) NOT NULL DEFAULT '',
            `semester_label` VARCHAR(64) NOT NULL DEFAULT '',
            `source_sheet_name` VARCHAR(64) NOT NULL DEFAULT '',
            `source_file_name` VARCHAR(255) NOT NULL DEFAULT '',
            `college_name` VARCHAR(255) NOT NULL DEFAULT '',
            `campus_name` VARCHAR(255) NOT NULL DEFAULT '',
            `source_program_name` VARCHAR(255) NOT NULL DEFAULT '',
            `year_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `student_number` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_name` VARCHAR(150) NOT NULL DEFAULT '',
            `first_name` VARCHAR(150) NOT NULL DEFAULT '',
            `middle_name` VARCHAR(150) NOT NULL DEFAULT '',
            `suffix_name` VARCHAR(50) NOT NULL DEFAULT '',
            `email_address` VARCHAR(255) NOT NULL DEFAULT '',
            `program_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `uploaded_by` INT UNSIGNED NULL DEFAULT NULL,
            `source_row_number` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`student_id`),
            KEY `idx_student_term` (`academic_year_label`, `semester_label`),
            KEY `idx_student_program_year` (`source_program_name`, `year_level`),
            KEY `idx_student_email` (`email_address`),
            KEY `idx_student_program_id` (`program_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($createSql)) {
        throw new RuntimeException('Unable to create the student management table.');
    }

    $alterStatements = [
        'academic_year_label' => "ALTER TABLE `{$tableName}` ADD COLUMN `academic_year_label` VARCHAR(32) NOT NULL DEFAULT '' AFTER `student_id`",
        'semester_label' => "ALTER TABLE `{$tableName}` ADD COLUMN `semester_label` VARCHAR(64) NOT NULL DEFAULT '' AFTER `academic_year_label`",
        'source_sheet_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `source_sheet_name` VARCHAR(64) NOT NULL DEFAULT '' AFTER `semester_label`",
        'source_file_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `source_file_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `source_sheet_name`",
        'college_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `college_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `source_file_name`",
        'campus_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `campus_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `college_name`",
        'source_program_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `source_program_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `campus_name`",
        'year_level' => "ALTER TABLE `{$tableName}` ADD COLUMN `year_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `source_program_name`",
        'student_number' => "ALTER TABLE `{$tableName}` ADD COLUMN `student_number` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `year_level`",
        'last_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `last_name` VARCHAR(150) NOT NULL DEFAULT '' AFTER `student_number`",
        'first_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `first_name` VARCHAR(150) NOT NULL DEFAULT '' AFTER `last_name`",
        'middle_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `middle_name` VARCHAR(150) NOT NULL DEFAULT '' AFTER `first_name`",
        'suffix_name' => "ALTER TABLE `{$tableName}` ADD COLUMN `suffix_name` VARCHAR(50) NOT NULL DEFAULT '' AFTER `middle_name`",
        'email_address' => "ALTER TABLE `{$tableName}` ADD COLUMN `email_address` VARCHAR(255) NOT NULL DEFAULT '' AFTER `suffix_name`",
        'program_id' => "ALTER TABLE `{$tableName}` ADD COLUMN `program_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `email_address`",
        'uploaded_by' => "ALTER TABLE `{$tableName}` ADD COLUMN `uploaded_by` INT UNSIGNED NULL DEFAULT NULL AFTER `program_id`",
        'source_row_number' => "ALTER TABLE `{$tableName}` ADD COLUMN `source_row_number` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `uploaded_by`",
        'created_at' => "ALTER TABLE `{$tableName}` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `source_row_number`",
        'updated_at' => "ALTER TABLE `{$tableName}` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`",
    ];

    foreach ($alterStatements as $columnName => $sql) {
        if (!synk_table_has_column($conn, $tableName, $columnName)) {
            if (!$conn->query($sql)) {
                throw new RuntimeException('Unable to update the student management table schema.');
            }
        }
    }
}

function synk_student_management_zip_read(ZipArchive $zip, string $entryName): string
{
    $content = $zip->getFromName($entryName);
    if ($content === false || $content === '') {
        throw new RuntimeException('Invalid XLSX structure: missing ' . $entryName . '.');
    }

    return $content;
}

function synk_student_management_load_xml(string $content): SimpleXMLElement
{
    $xml = simplexml_load_string($content);
    if (!$xml instanceof SimpleXMLElement) {
        throw new RuntimeException('Unable to read spreadsheet XML content.');
    }

    return $xml;
}

function synk_student_management_load_shared_strings(ZipArchive $zip): array
{
    if ($zip->locateName('xl/sharedStrings.xml') === false) {
        return [];
    }

    $sharedStringsXml = synk_student_management_load_xml(
        synk_student_management_zip_read($zip, 'xl/sharedStrings.xml')
    );
    $sharedStringsXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $items = $sharedStringsXml->xpath('/main:sst/main:si');
    $sharedStrings = [];

    foreach ($items ?: [] as $item) {
        $item->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $textNodes = $item->xpath('.//main:t');
        $chunks = [];
        foreach ($textNodes ?: [] as $textNode) {
            $chunks[] = (string)$textNode;
        }
        $sharedStrings[] = implode('', $chunks);
    }

    return $sharedStrings;
}

function synk_student_management_resolve_sheet_entry(ZipArchive $zip, string $sheetName): string
{
    $workbookXml = synk_student_management_load_xml(
        synk_student_management_zip_read($zip, 'xl/workbook.xml')
    );
    $workbookXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbookXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $relationshipsXml = synk_student_management_load_xml(
        synk_student_management_zip_read($zip, 'xl/_rels/workbook.xml.rels')
    );
    $relationshipsXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $relationshipMap = [];
    foreach ($relationshipsXml->xpath('/rel:Relationships/rel:Relationship') ?: [] as $relationship) {
        $attributes = $relationship->attributes();
        $relationshipId = (string)($attributes['Id'] ?? '');
        $target = (string)($attributes['Target'] ?? '');
        if ($relationshipId !== '' && $target !== '') {
            $relationshipMap[$relationshipId] = $target;
        }
    }

    foreach ($workbookXml->xpath('/main:workbook/main:sheets/main:sheet') ?: [] as $sheet) {
        $attributes = $sheet->attributes();
        $relationAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $currentSheetName = synk_student_management_normalize_space((string)($attributes['name'] ?? ''));
        $relationshipId = (string)($relationAttributes['id'] ?? '');

        if (strcasecmp($currentSheetName, $sheetName) !== 0) {
            continue;
        }

        if (!isset($relationshipMap[$relationshipId])) {
            break;
        }

        $target = ltrim($relationshipMap[$relationshipId], '/');
        if (strpos($target, 'xl/') !== 0) {
            $target = 'xl/' . $target;
        }

        return $target;
    }

    throw new RuntimeException('The uploaded workbook does not contain the expected ' . $sheetName . ' sheet.');
}

function synk_student_management_cell_value(SimpleXMLElement $cell, array $sharedStrings): string
{
    $attributes = $cell->attributes();
    $cellType = (string)($attributes['t'] ?? '');

    if ($cellType === 'inlineStr') {
        $cell->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $textNodes = $cell->xpath('.//main:t');
        $chunks = [];
        foreach ($textNodes ?: [] as $textNode) {
            $chunks[] = (string)$textNode;
        }

        return synk_student_management_normalize_space(implode('', $chunks));
    }

    $value = synk_student_management_normalize_space((string)($cell->v ?? ''));
    if ($cellType === 's' && $value !== '') {
        $index = (int)$value;
        return synk_student_management_normalize_space($sharedStrings[$index] ?? '');
    }

    return $value;
}

function synk_student_management_cell_column(string $cellReference): string
{
    if ($cellReference === '') {
        return '';
    }

    if (preg_match('/^[A-Z]+/i', $cellReference, $matches)) {
        return strtoupper($matches[0]);
    }

    return '';
}

function synk_student_management_parse_xlsx(string $xlsxPath): array
{
    if (!is_file($xlsxPath)) {
        throw new RuntimeException('The uploaded XLSX file could not be found.');
    }

    $zip = new ZipArchive();
    if ($zip->open($xlsxPath) !== true) {
        throw new RuntimeException('Unable to open the uploaded XLSX file.');
    }

    try {
        $sharedStrings = synk_student_management_load_shared_strings($zip);
        $sheetEntry = synk_student_management_resolve_sheet_entry($zip, 'LIST');
        $worksheetXml = synk_student_management_load_xml(
            synk_student_management_zip_read($zip, $sheetEntry)
        );
        $worksheetXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = $worksheetXml->xpath('/main:worksheet/main:sheetData/main:row');
        if (empty($rows)) {
            throw new RuntimeException('The LIST sheet does not contain readable rows.');
        }

        $metadata = [
            'sheet_name' => 'LIST',
            'college_name' => '',
            'campus_name' => '',
            'semester_label' => '',
            'academic_year_label' => '',
        ];

        $students = [];
        $pendingProgramBase = '';
        $currentProgram = '';
        $currentYearLevel = 0;

        foreach ($rows as $row) {
            $rowNumber = (int)($row['r'] ?? 0);
            $values = [];

            foreach ($row->c as $cell) {
                $cellAttributes = $cell->attributes();
                $column = synk_student_management_cell_column((string)($cellAttributes['r'] ?? ''));
                if ($column === '') {
                    continue;
                }

                $values[$column] = synk_student_management_cell_value($cell, $sharedStrings);
            }

            $columnA = synk_student_management_normalize_space((string)($values['A'] ?? ''));
            if ($columnA === '') {
                continue;
            }

            if ($metadata['college_name'] === '' && stripos($columnA, 'College of') === 0) {
                $metadata['college_name'] = $columnA;
                continue;
            }

            if ($metadata['campus_name'] === '' && stripos($columnA, 'Campus') !== false) {
                $metadata['campus_name'] = $columnA;
                continue;
            }

            if ($metadata['semester_label'] === '' && stripos($columnA, 'semester') !== false && stripos($columnA, 'list of') === false) {
                $metadata['semester_label'] = ucwords(strtolower($columnA));
                continue;
            }

            if ($metadata['academic_year_label'] === '' && preg_match('/^\d{4}\s*-\s*\d{4}$/', $columnA) === 1) {
                $metadata['academic_year_label'] = preg_replace('/\s*-\s*/', '-', $columnA) ?? $columnA;
                continue;
            }

            $yearLevel = synk_student_management_year_level_from_label($columnA);
            if ($yearLevel > 0) {
                if ($pendingProgramBase !== '') {
                    $currentProgram = $pendingProgramBase;
                    $pendingProgramBase = '';
                }

                $currentYearLevel = $yearLevel;
                continue;
            }

            if (preg_match('/^\d+$/', $columnA) === 1) {
                $studentNumber = (int)$columnA;
                $lastName = synk_student_management_normalize_space((string)($values['B'] ?? ''));
                $firstName = synk_student_management_normalize_space((string)($values['C'] ?? ''));
                $middleName = synk_student_management_normalize_space((string)($values['D'] ?? ''));
                $suffixName = synk_student_management_normalize_space((string)($values['E'] ?? ''));

                if ($middleName === '*' || $middleName === '-') {
                    $middleName = '';
                }
                if ($suffixName === '*' || $suffixName === '-') {
                    $suffixName = '';
                }

                if ($currentProgram === '' || $currentYearLevel <= 0 || $lastName === '' || $firstName === '') {
                    continue;
                }

                $students[] = [
                    'row_number' => $rowNumber,
                    'student_number' => $studentNumber,
                    'source_program_name' => $currentProgram,
                    'year_level' => $currentYearLevel,
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'suffix_name' => $suffixName,
                    'email_address' => synk_student_management_build_email($firstName, $lastName),
                ];
                continue;
            }

            if (stripos($columnA, 'Bachelor of') === 0) {
                $pendingProgramBase = synk_student_management_normalize_space($columnA);
                $currentProgram = $pendingProgramBase;
                $currentYearLevel = 0;
                continue;
            }

            if ($pendingProgramBase !== '') {
                $currentProgram = synk_student_management_normalize_space($pendingProgramBase . ' ' . $columnA);
                $pendingProgramBase = '';
                $currentYearLevel = 0;
            }
        }

        if ($metadata['semester_label'] === '') {
            $metadata['semester_label'] = 'Unknown Semester';
        }
        if ($metadata['academic_year_label'] === '') {
            $metadata['academic_year_label'] = 'Unknown Academic Year';
        }

        if (empty($students)) {
            throw new RuntimeException('No student rows were detected in the uploaded workbook.');
        }

        return [
            'metadata' => $metadata,
            'students' => $students,
        ];
    } finally {
        $zip->close();
    }
}

function synk_student_management_import_students(
    mysqli $conn,
    string $xlsxPath,
    string $originalFileName,
    int $uploadedBy = 0
): array {
    synk_student_management_ensure_schema($conn);

    $parsed = synk_student_management_parse_xlsx($xlsxPath);
    $metadata = $parsed['metadata'];
    $students = $parsed['students'];
    $tableName = synk_student_management_table_name();

    $deleteSql = "
        DELETE FROM `{$tableName}`
        WHERE academic_year_label = ?
          AND semester_label = ?
          AND college_name = ?
          AND campus_name = ?
    ";
    $deleteStmt = $conn->prepare($deleteSql);
    if (!$deleteStmt) {
        throw new RuntimeException('Unable to prepare the student cleanup query.');
    }

    $insertSql = "
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
    ";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        $deleteStmt->close();
        throw new RuntimeException('Unable to prepare the student insert query.');
    }

    $uniquePrograms = [];
    $uniqueYearLevels = [];
    $deletedRows = 0;

    $conn->begin_transaction();

    try {
        $academicYearLabel = (string)$metadata['academic_year_label'];
        $semesterLabel = (string)$metadata['semester_label'];
        $collegeName = (string)$metadata['college_name'];
        $campusName = (string)$metadata['campus_name'];

        $deleteStmt->bind_param(
            'ssss',
            $academicYearLabel,
            $semesterLabel,
            $collegeName,
            $campusName
        );
        if (!$deleteStmt->execute()) {
            throw new RuntimeException('Unable to clear previous student records for the same term batch.');
        }
        $deletedRows = (int)$deleteStmt->affected_rows;

        foreach ($students as $student) {
            $academicYear = (string)$metadata['academic_year_label'];
            $semester = (string)$metadata['semester_label'];
            $sheetName = (string)$metadata['sheet_name'];
            $fileName = $originalFileName;
            $collegeName = (string)$metadata['college_name'];
            $campusName = (string)$metadata['campus_name'];
            $sourceProgramName = (string)$student['source_program_name'];
            $yearLevel = (int)$student['year_level'];
            $studentNumber = (int)$student['student_number'];
            $lastName = (string)$student['last_name'];
            $firstName = (string)$student['first_name'];
            $middleName = (string)$student['middle_name'];
            $suffixName = (string)$student['suffix_name'];
            $emailAddress = (string)$student['email_address'];
            $programId = 0;
            $uploadedByValue = $uploadedBy > 0 ? $uploadedBy : 0;
            $sourceRowNumber = (int)$student['row_number'];

            if (!$insertStmt->bind_param(
                'sssssssiisssssiii',
                $academicYear,
                $semester,
                $sheetName,
                $fileName,
                $collegeName,
                $campusName,
                $sourceProgramName,
                $yearLevel,
                $studentNumber,
                $lastName,
                $firstName,
                $middleName,
                $suffixName,
                $emailAddress,
                $programId,
                $uploadedByValue,
                $sourceRowNumber
            )) {
                throw new RuntimeException('Unable to bind imported student data.');
            }

            if (!$insertStmt->execute()) {
                throw new RuntimeException('Unable to save imported student rows.');
            }

            $uniquePrograms[$sourceProgramName] = true;
            $uniqueYearLevels[$yearLevel] = true;
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $deleteStmt->close();
        $insertStmt->close();
        throw $e;
    }

    $deleteStmt->close();
    $insertStmt->close();

    return [
        'deleted_rows' => $deletedRows,
        'inserted_rows' => count($students),
        'program_count' => count($uniquePrograms),
        'year_level_count' => count($uniqueYearLevels),
        'academic_year_label' => (string)$metadata['academic_year_label'],
        'semester_label' => (string)$metadata['semester_label'],
        'college_name' => (string)$metadata['college_name'],
        'campus_name' => (string)$metadata['campus_name'],
    ];
}

function synk_student_management_summary(mysqli $conn): array
{
    synk_student_management_ensure_schema($conn);

    $tableName = synk_student_management_table_name();
    $sql = "
        SELECT
            COUNT(*) AS total_students,
            COUNT(DISTINCT source_program_name) AS total_programs,
            COUNT(DISTINCT CONCAT(academic_year_label, '|', semester_label)) AS total_terms,
            SUM(CASE WHEN program_id = 0 THEN 1 ELSE 0 END) AS unassigned_students,
            MAX(created_at) AS last_uploaded_at
        FROM `{$tableName}`
    ";

    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : [];

    return [
        'total_students' => (int)($row['total_students'] ?? 0),
        'total_programs' => (int)($row['total_programs'] ?? 0),
        'total_terms' => (int)($row['total_terms'] ?? 0),
        'unassigned_students' => (int)($row['unassigned_students'] ?? 0),
        'last_uploaded_at' => (string)($row['last_uploaded_at'] ?? ''),
    ];
}

function synk_student_management_distinct_programs(mysqli $conn): array
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();

    $sql = "
        SELECT DISTINCT source_program_name
        FROM `{$tableName}`
        WHERE source_program_name <> ''
        ORDER BY source_program_name ASC
    ";

    $result = $conn->query($sql);
    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = (string)($row['source_program_name'] ?? '');
        }
    }

    return $rows;
}

function synk_student_management_distinct_terms(mysqli $conn): array
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();

    $sql = "
        SELECT DISTINCT academic_year_label, semester_label
        FROM `{$tableName}`
        ORDER BY academic_year_label DESC, semester_label DESC
    ";

    $result = $conn->query($sql);
    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'academic_year_label' => (string)($row['academic_year_label'] ?? ''),
                'semester_label' => (string)($row['semester_label'] ?? ''),
            ];
        }
    }

    return $rows;
}

function synk_student_management_build_filter_where(array $filters, string &$types, array &$params): string
{
    $clauses = [];

    $search = synk_student_management_normalize_space((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $like = '%' . $search . '%';
        $clauses[] = "(last_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR email_address LIKE ? OR source_program_name LIKE ?)";
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $yearLevel = (int)($filters['year_level'] ?? 0);
    if ($yearLevel > 0) {
        $clauses[] = 'year_level = ?';
        $types .= 'i';
        $params[] = $yearLevel;
    }

    $programName = synk_student_management_normalize_space((string)($filters['source_program_name'] ?? ''));
    if ($programName !== '') {
        $clauses[] = 'source_program_name = ?';
        $types .= 's';
        $params[] = $programName;
    }

    $academicYear = synk_student_management_normalize_space((string)($filters['academic_year_label'] ?? ''));
    if ($academicYear !== '') {
        $clauses[] = 'academic_year_label = ?';
        $types .= 's';
        $params[] = $academicYear;
    }

    $semester = synk_student_management_normalize_space((string)($filters['semester_label'] ?? ''));
    if ($semester !== '') {
        $clauses[] = 'semester_label = ?';
        $types .= 's';
        $params[] = $semester;
    }

    return empty($clauses) ? '' : (' WHERE ' . implode(' AND ', $clauses));
}

function synk_student_management_count_records(mysqli $conn, array $filters): int
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();

    $types = '';
    $params = [];
    $whereSql = synk_student_management_build_filter_where($filters, $types, $params);

    $stmt = $conn->prepare("SELECT COUNT(*) AS total_rows FROM `{$tableName}`{$whereSql}");
    if (!$stmt) {
        return 0;
    }

    if ($types !== '') {
        synk_bind_dynamic_params($stmt, $types, $params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total_rows'] ?? 0);
}

function synk_student_management_fetch_records(mysqli $conn, array $filters, int $limit = 100, int $offset = 0): array
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();

    $types = '';
    $params = [];
    $whereSql = synk_student_management_build_filter_where($filters, $types, $params);

    $safeLimit = max(1, min(250, $limit));
    $safeOffset = max(0, $offset);

    $sql = "
        SELECT
            student_id,
            academic_year_label,
            semester_label,
            source_program_name,
            year_level,
            student_number,
            last_name,
            first_name,
            middle_name,
            suffix_name,
            email_address,
            program_id,
            created_at
        FROM `{$tableName}`
        {$whereSql}
        ORDER BY
            academic_year_label DESC,
            semester_label DESC,
            source_program_name ASC,
            year_level ASC,
            student_number ASC,
            last_name ASC,
            first_name ASC
        LIMIT ?
        OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types .= 'ii';
    $params[] = $safeLimit;
    $params[] = $safeOffset;
    synk_bind_dynamic_params($stmt, $types, $params);

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function synk_student_management_latest_batch(mysqli $conn): array
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();

    $sql = "
        SELECT
            academic_year_label,
            semester_label,
            college_name,
            campus_name,
            COUNT(*) AS student_count,
            COUNT(DISTINCT source_program_name) AS program_count,
            COUNT(DISTINCT year_level) AS year_level_count,
            MAX(created_at) AS last_uploaded_at
        FROM `{$tableName}`
        GROUP BY academic_year_label, semester_label, college_name, campus_name
        ORDER BY MAX(created_at) DESC, academic_year_label DESC, semester_label DESC, campus_name ASC
        LIMIT 1
    ";

    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        return [
            'academic_year_label' => '',
            'semester_label' => '',
            'college_name' => '',
            'campus_name' => '',
            'student_count' => 0,
            'program_count' => 0,
            'year_level_count' => 0,
            'last_uploaded_at' => '',
        ];
    }

    $row = $result->fetch_assoc();

    return [
        'academic_year_label' => (string)($row['academic_year_label'] ?? ''),
        'semester_label' => (string)($row['semester_label'] ?? ''),
        'college_name' => (string)($row['college_name'] ?? ''),
        'campus_name' => (string)($row['campus_name'] ?? ''),
        'student_count' => (int)($row['student_count'] ?? 0),
        'program_count' => (int)($row['program_count'] ?? 0),
        'year_level_count' => (int)($row['year_level_count'] ?? 0),
        'last_uploaded_at' => (string)($row['last_uploaded_at'] ?? ''),
    ];
}

function synk_student_management_program_year_level_chart(mysqli $conn, array $batch = []): array
{
    synk_student_management_ensure_schema($conn);
    $tableName = synk_student_management_table_name();

    $resolvedBatch = array_merge(
        synk_student_management_latest_batch($conn),
        array_filter($batch, static function ($value) {
            return $value !== null && $value !== '';
        })
    );

    $academicYearLabel = trim((string)($resolvedBatch['academic_year_label'] ?? ''));
    $semesterLabel = trim((string)($resolvedBatch['semester_label'] ?? ''));
    $collegeName = trim((string)($resolvedBatch['college_name'] ?? ''));
    $campusName = trim((string)($resolvedBatch['campus_name'] ?? ''));

    if ($academicYearLabel === '' || $semesterLabel === '' || $collegeName === '' || $campusName === '') {
        return [
            'categories' => [],
            'series' => [],
            'max_value' => 0,
        ];
    }

    $sql = "
        SELECT
            source_program_name,
            year_level,
            COUNT(*) AS student_total
        FROM `{$tableName}`
        WHERE academic_year_label = ?
          AND semester_label = ?
          AND college_name = ?
          AND campus_name = ?
        GROUP BY source_program_name, year_level
        ORDER BY source_program_name ASC, year_level ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'categories' => [],
            'series' => [],
            'max_value' => 0,
        ];
    }

    $stmt->bind_param('ssss', $academicYearLabel, $semesterLabel, $collegeName, $campusName);
    $stmt->execute();
    $result = $stmt->get_result();

    $programMatrix = [];
    $maxValue = 0;

    while ($row = $result->fetch_assoc()) {
        $programName = (string)($row['source_program_name'] ?? '');
        $yearLevel = (int)($row['year_level'] ?? 0);
        $studentTotal = (int)($row['student_total'] ?? 0);

        if ($programName === '' || $yearLevel <= 0) {
            continue;
        }

        if (!isset($programMatrix[$programName])) {
            $programMatrix[$programName] = [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
            ];
        }

        $programMatrix[$programName][$yearLevel] = $studentTotal;
        $maxValue = max($maxValue, $studentTotal);
    }

    $stmt->close();

    if (empty($programMatrix)) {
        return [
            'categories' => [],
            'series' => [],
            'max_value' => 0,
        ];
    }

    $categories = array_keys($programMatrix);
    $series = [];

    foreach ([1, 2, 3, 4, 5] as $yearLevel) {
        $data = [];
        $hasData = false;

        foreach ($categories as $programName) {
            $value = (int)($programMatrix[$programName][$yearLevel] ?? 0);
            $data[] = $value;
            if ($value > 0) {
                $hasData = true;
            }
        }

        if ($hasData) {
            $series[] = [
                'name' => synk_student_management_year_label($yearLevel),
                'data' => $data,
            ];
        }
    }

    return [
        'categories' => $categories,
        'series' => $series,
        'max_value' => $maxValue,
    ];
}

function synk_student_management_fetch_active_campuses(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query("
        SELECT campus_id, campus_code, campus_name
        FROM tbl_campus
        WHERE status = 'active'
        ORDER BY campus_name ASC
    ");

    if (!$result instanceof mysqli_result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'campus_id' => (int)($row['campus_id'] ?? 0),
            'campus_code' => (string)($row['campus_code'] ?? ''),
            'campus_name' => (string)($row['campus_name'] ?? ''),
        ];
    }

    return $rows;
}
