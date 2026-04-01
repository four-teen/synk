<?php

require_once __DIR__ . '/schema_helper.php';

function synk_schedule_merge_table_name(): string
{
    return 'tbl_class_schedule_merge';
}

function synk_schedule_merge_normalize_offering_ids(array $offeringIds): array
{
    $normalized = [];

    foreach ($offeringIds as $offeringId) {
        $value = (int)$offeringId;
        if ($value > 0) {
            $normalized[$value] = $value;
        }
    }

    return array_values($normalized);
}

function synk_schedule_merge_array_first_key(array $values)
{
    foreach ($values as $key => $_unused) {
        return $key;
    }

    return null;
}

function synk_schedule_merge_table_exists(mysqli $conn): bool
{
    synk_schedule_merge_ensure_table($conn);
    return synk_table_exists($conn, synk_schedule_merge_table_name());
}

function synk_schedule_merge_ensure_table(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $tableName = synk_schedule_merge_table_name();

    if (!synk_table_exists($conn, $tableName)) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `schedule_merge_id` INT NOT NULL AUTO_INCREMENT,
                `owner_offering_id` INT NOT NULL,
                `member_offering_id` INT NOT NULL,
                `created_by` INT NULL,
                `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`schedule_merge_id`),
                UNIQUE KEY `uniq_schedule_merge_member` (`member_offering_id`),
                KEY `idx_schedule_merge_owner` (`owner_offering_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    $columns = synk_describe_columns($conn, $tableName);
    $clauses = [];

    if (!isset($columns['created_by'])) {
        $clauses[] = "ADD COLUMN created_by INT NULL";
    }

    if (!isset($columns['date_created'])) {
        $clauses[] = "ADD COLUMN date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
    }

    if (!empty($clauses)) {
        $conn->query("ALTER TABLE `{$tableName}` " . implode(', ', $clauses));
    }

    if (!synk_table_has_index($conn, $tableName, 'uniq_schedule_merge_member')) {
        $conn->query("ALTER TABLE `{$tableName}` ADD UNIQUE INDEX `uniq_schedule_merge_member` (`member_offering_id`)");
    }

    if (!synk_table_has_index($conn, $tableName, 'idx_schedule_merge_owner')) {
        $conn->query("ALTER TABLE `{$tableName}` ADD INDEX `idx_schedule_merge_owner` (`owner_offering_id`)");
    }

    $ensured = true;
}

function synk_schedule_merge_scheduled_offering_join_sql(
    mysqli $conn,
    string $scheduleAlias = 'sched',
    string $offeringAlias = 'o'
): string {
    synk_schedule_merge_ensure_table($conn);
    $tableName = synk_schedule_merge_table_name();

    if (!synk_table_exists($conn, $tableName)) {
        return "
            LEFT JOIN (
                SELECT DISTINCT offering_id
                FROM tbl_class_schedule
            ) {$scheduleAlias}
                ON {$scheduleAlias}.offering_id = {$offeringAlias}.offering_id
        ";
    }

    return "
        LEFT JOIN (
            SELECT DISTINCT offering_id
            FROM tbl_class_schedule
            UNION
            SELECT DISTINCT merge_map.member_offering_id AS offering_id
            FROM `{$tableName}` merge_map
            INNER JOIN tbl_class_schedule owner_sched
                ON owner_sched.offering_id = merge_map.owner_offering_id
        ) {$scheduleAlias}
            ON {$scheduleAlias}.offering_id = {$offeringAlias}.offering_id
    ";
}

function synk_schedule_merge_load_member_to_owner_map(mysqli $conn, array $offeringIds): array
{
    synk_schedule_merge_ensure_table($conn);

    $normalizedIds = synk_schedule_merge_normalize_offering_ids($offeringIds);
    if (empty($normalizedIds) || !synk_table_exists($conn, synk_schedule_merge_table_name())) {
        return [];
    }

    $idList = implode(',', array_map('intval', $normalizedIds));
    $sql = "
        SELECT owner_offering_id, member_offering_id
        FROM `" . synk_schedule_merge_table_name() . "`
        WHERE member_offering_id IN ({$idList})
    ";

    $rows = [];
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $memberId = (int)($row['member_offering_id'] ?? 0);
            $ownerId = (int)($row['owner_offering_id'] ?? 0);
            if ($memberId > 0 && $ownerId > 0) {
                $rows[$memberId] = $ownerId;
            }
        }
    }

    return $rows;
}

function synk_schedule_merge_resolve_owner_id(int $offeringId, array $memberToOwner): int
{
    $resolved = $offeringId;
    $visited = [];

    while (isset($memberToOwner[$resolved]) && !isset($visited[$resolved])) {
        $visited[$resolved] = true;
        $candidate = (int)$memberToOwner[$resolved];
        if ($candidate <= 0 || $candidate === $resolved) {
            break;
        }
        $resolved = $candidate;
    }

    return $resolved;
}

function synk_schedule_merge_load_owner_to_members_map(mysqli $conn, array $ownerIds): array
{
    synk_schedule_merge_ensure_table($conn);

    $normalizedIds = synk_schedule_merge_normalize_offering_ids($ownerIds);
    if (empty($normalizedIds) || !synk_table_exists($conn, synk_schedule_merge_table_name())) {
        return [];
    }

    $idList = implode(',', array_map('intval', $normalizedIds));
    $sql = "
        SELECT owner_offering_id, member_offering_id
        FROM `" . synk_schedule_merge_table_name() . "`
        WHERE owner_offering_id IN ({$idList})
        ORDER BY owner_offering_id ASC, member_offering_id ASC
    ";

    $map = [];
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $ownerId = (int)($row['owner_offering_id'] ?? 0);
            $memberId = (int)($row['member_offering_id'] ?? 0);
            if ($ownerId <= 0 || $memberId <= 0) {
                continue;
            }

            if (!isset($map[$ownerId])) {
                $map[$ownerId] = [];
            }

            $map[$ownerId][] = $memberId;
        }
    }

    foreach ($map as $ownerId => $memberIds) {
        $map[$ownerId] = synk_schedule_merge_normalize_offering_ids($memberIds);
    }

    return $map;
}

function synk_schedule_merge_load_section_rows_by_offering(mysqli $conn, array $offeringIds): array
{
    $normalizedIds = synk_schedule_merge_normalize_offering_ids($offeringIds);
    if (empty($normalizedIds)) {
        return [];
    }

    $idList = implode(',', array_map('intval', $normalizedIds));
    $sql = "
        SELECT
            o.offering_id,
            COALESCE(NULLIF(TRIM(sec.section_name), ''), CONCAT('Section ', o.section_id)) AS section_name,
            COALESCE(NULLIF(TRIM(p.program_code), ''), '') AS program_code,
            COALESCE(NULLIF(TRIM(p.major), ''), '') AS major,
            COALESCE(
                NULLIF(TRIM(sec.full_section), ''),
                CONCAT(
                    COALESCE(NULLIF(TRIM(p.program_code), ''), 'Program'),
                    ' ',
                    COALESCE(NULLIF(TRIM(sec.section_name), ''), CONCAT('Section ', o.section_id))
                )
            ) AS full_section
        FROM tbl_prospectus_offering o
        INNER JOIN tbl_program p
            ON p.program_id = o.program_id
        LEFT JOIN tbl_sections sec
            ON sec.section_id = o.section_id
        WHERE o.offering_id IN ({$idList})
    ";

    $rows = [];
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $offeringId = (int)($row['offering_id'] ?? 0);
            if ($offeringId <= 0) {
                continue;
            }

            $rows[$offeringId] = [
                'offering_id' => $offeringId,
                'section_name' => (string)($row['section_name'] ?? ''),
                'program_code' => (string)($row['program_code'] ?? ''),
                'major' => (string)($row['major'] ?? ''),
                'full_section' => (string)($row['full_section'] ?? '')
            ];
        }
    }

    return $rows;
}

function synk_schedule_merge_compose_base_section_label(array $row): string
{
    $fullSection = trim((string)($row['full_section'] ?? ''));
    if ($fullSection !== '') {
        return $fullSection;
    }

    $programCode = trim((string)($row['program_code'] ?? ''));
    $sectionName = trim((string)($row['section_name'] ?? ''));
    if ($programCode !== '' && $sectionName !== '') {
        return trim($programCode . ' ' . $sectionName);
    }

    return $sectionName;
}

function synk_schedule_merge_compose_section_major_label(array $row): string
{
    $baseLabel = synk_schedule_merge_compose_base_section_label($row);
    if ($baseLabel === '') {
        return '';
    }

    $major = strtoupper(trim((string)($row['major'] ?? '')));
    if ($major === '') {
        return $baseLabel;
    }

    return $baseLabel . '-' . $major;
}

function synk_schedule_merge_compose_group_label(array $groupRows): string
{
    if (empty($groupRows)) {
        return '';
    }

    $rows = array_values($groupRows);
    usort($rows, static function (array $left, array $right): int {
        $sectionCompare = strnatcasecmp(
            synk_schedule_merge_compose_base_section_label($left),
            synk_schedule_merge_compose_base_section_label($right)
        );
        if ($sectionCompare !== 0) {
            return $sectionCompare;
        }

        return strnatcasecmp(
            strtoupper(trim((string)($left['major'] ?? ''))),
            strtoupper(trim((string)($right['major'] ?? '')))
        );
    });

    $programCodes = [];
    $sectionNames = [];
    $fullSections = [];
    $majorLabelsBySection = [];

    foreach ($rows as $row) {
        $programCode = trim((string)($row['program_code'] ?? ''));
        $sectionName = trim((string)($row['section_name'] ?? ''));
        $fullSection = synk_schedule_merge_compose_base_section_label($row);
        $majorLabel = trim((string)(strtoupper((string)($row['major'] ?? ''))));

        if ($programCode !== '') {
            $programCodes[$programCode] = true;
        }

        if ($sectionName !== '') {
            $sectionNames[$sectionName] = true;
        }

        if ($fullSection !== '') {
            $fullSections[$fullSection] = true;

            if (!isset($majorLabelsBySection[$fullSection])) {
                $majorLabelsBySection[$fullSection] = [];
            }

            $majorLabelsBySection[$fullSection][$majorLabel] = true;
        }
    }

    $needsMajorDisambiguation = false;
    foreach ($majorLabelsBySection as $majorLabels) {
        $normalizedMajorLabels = array_keys($majorLabels);
        if (count($normalizedMajorLabels) > 1) {
            $needsMajorDisambiguation = true;
            break;
        }
    }

    if ($needsMajorDisambiguation) {
        $labels = [];
        foreach ($rows as $row) {
            $label = synk_schedule_merge_compose_section_major_label($row);
            if ($label !== '') {
                $labels[$label] = true;
            }
        }

        $labelValues = array_keys($labels);
        natcasesort($labelValues);

        return implode('/', $labelValues);
    }

    if (count($fullSections) <= 1) {
        $singleFullSection = synk_schedule_merge_array_first_key($fullSections);
        return $singleFullSection !== null ? (string)$singleFullSection : '';
    }

    if (count($programCodes) === 1 && count($sectionNames) === count($rows)) {
        $programCode = (string)synk_schedule_merge_array_first_key($programCodes);
        $sections = array_keys($sectionNames);
        natcasesort($sections);
        return trim($programCode . ' ' . implode('/', $sections));
    }

    $sections = array_keys($fullSections);
    natcasesort($sections);
    return implode('/', $sections);
}

function synk_schedule_merge_load_display_context(mysqli $conn, array $offeringIds): array
{
    $normalizedIds = synk_schedule_merge_normalize_offering_ids($offeringIds);
    $context = [];

    if (empty($normalizedIds)) {
        return $context;
    }

    $memberToOwner = synk_schedule_merge_load_member_to_owner_map($conn, $normalizedIds);
    $resolvedOwnerIds = [];

    foreach ($normalizedIds as $offeringId) {
        $resolvedOwnerIds[] = synk_schedule_merge_resolve_owner_id($offeringId, $memberToOwner);
    }
    $resolvedOwnerIds = synk_schedule_merge_normalize_offering_ids($resolvedOwnerIds);

    $ownerToMembers = synk_schedule_merge_load_owner_to_members_map($conn, $resolvedOwnerIds);

    $groupOfferingIds = $normalizedIds;
    foreach ($resolvedOwnerIds as $ownerId) {
        $groupOfferingIds[] = $ownerId;
        foreach (($ownerToMembers[$ownerId] ?? []) as $memberId) {
            $groupOfferingIds[] = $memberId;
        }
    }
    $groupOfferingIds = synk_schedule_merge_normalize_offering_ids($groupOfferingIds);

    $sectionRows = synk_schedule_merge_load_section_rows_by_offering($conn, $groupOfferingIds);
    $groupLabelsByOwner = [];

    foreach ($resolvedOwnerIds as $ownerId) {
        $groupIds = [$ownerId];
        foreach (($ownerToMembers[$ownerId] ?? []) as $memberId) {
            $groupIds[] = $memberId;
        }
        $groupIds = synk_schedule_merge_normalize_offering_ids($groupIds);

        $groupRows = [];
        foreach ($groupIds as $groupOfferingId) {
            if (isset($sectionRows[$groupOfferingId])) {
                $groupRows[$groupOfferingId] = $sectionRows[$groupOfferingId];
            }
        }

        $groupLabelsByOwner[$ownerId] = synk_schedule_merge_compose_group_label($groupRows);
    }

    foreach ($normalizedIds as $offeringId) {
        $ownerId = synk_schedule_merge_resolve_owner_id($offeringId, $memberToOwner);
        $memberIds = $ownerToMembers[$ownerId] ?? [];
        $groupIds = synk_schedule_merge_normalize_offering_ids(array_merge([$ownerId], $memberIds));
        $ownRow = $sectionRows[$offeringId] ?? [
            'offering_id' => $offeringId,
            'section_name' => '',
            'program_code' => '',
            'full_section' => ''
        ];

        $context[$offeringId] = [
            'offering_id' => $offeringId,
            'owner_offering_id' => $ownerId,
            'is_merged_member' => isset($memberToOwner[$offeringId]) && $ownerId !== $offeringId,
            'has_merged_members' => $ownerId === $offeringId && !empty($memberIds),
            'member_offering_ids' => $memberIds,
            'group_offering_ids' => $groupIds,
            'group_size' => count($groupIds),
            'group_course_label' => (string)($groupLabelsByOwner[$ownerId] ?? ($ownRow['full_section'] ?? '')),
            'full_section' => (string)($ownRow['full_section'] ?? ''),
            'section_name' => (string)($ownRow['section_name'] ?? ''),
            'program_code' => (string)($ownRow['program_code'] ?? '')
        ];
    }

    return $context;
}

function synk_schedule_merge_term_has_rows(mysqli $conn, int $collegeId, int $ayId, int $semester): bool
{
    synk_schedule_merge_ensure_table($conn);

    if ($collegeId <= 0 || $ayId <= 0 || $semester <= 0 || !synk_table_exists($conn, synk_schedule_merge_table_name())) {
        return false;
    }

    $sql = "
        SELECT merge_map.schedule_merge_id
        FROM `" . synk_schedule_merge_table_name() . "` merge_map
        INNER JOIN tbl_prospectus_offering owner_offer
            ON owner_offer.offering_id = merge_map.owner_offering_id
        INNER JOIN tbl_program p
            ON p.program_id = owner_offer.program_id
        WHERE owner_offer.ay_id = ?
          AND owner_offer.semester = ?
          AND p.college_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iii', $ayId, $semester, $collegeId);
    $stmt->execute();
    $exists = ($stmt->get_result()->num_rows ?? 0) > 0;
    $stmt->close();

    return $exists;
}
