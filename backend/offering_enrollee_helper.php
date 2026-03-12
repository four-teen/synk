<?php

require_once __DIR__ . '/schema_helper.php';

function synk_offering_enrollee_table_name(): string
{
    return 'tbl_offering_enrollee_counts';
}

function synk_offering_enrollee_table_exists(mysqli $conn): bool
{
    return synk_table_exists($conn, synk_offering_enrollee_table_name());
}

function synk_normalize_offering_ids(array $offeringIds): array
{
    $normalized = [];

    foreach ($offeringIds as $offeringId) {
        $value = (int)$offeringId;
        if ($value > 0) {
            $normalized[$value] = true;
        }
    }

    return array_keys($normalized);
}

function synk_fetch_offering_enrollee_count_map(mysqli $conn, array $offeringIds): array
{
    $normalizedIds = synk_normalize_offering_ids($offeringIds);
    if (empty($normalizedIds) || !synk_offering_enrollee_table_exists($conn)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
    $types = str_repeat('i', count($normalizedIds));
    $params = array_values($normalizedIds);

    $sql = "
        SELECT offering_id, total_enrollees
        FROM " . synk_offering_enrollee_table_name() . "
        WHERE offering_id IN ({$placeholders})
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    synk_bind_dynamic_params($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();

    $countMap = [];
    while ($res instanceof mysqli_result && ($row = $res->fetch_assoc())) {
        $countMap[(int)($row['offering_id'] ?? 0)] = max(0, (int)($row['total_enrollees'] ?? 0));
    }

    $stmt->close();

    return $countMap;
}

function synk_offering_enrollee_count_for_map(array $countMap, int $offeringId): int
{
    return max(0, (int)($countMap[$offeringId] ?? 0));
}
