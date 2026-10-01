<?php

require_once __DIR__ . '/professor_portal_helper.php';

// Navigation only: do not load every historical year's classes to build a dropdown.
function synk_professor_workload_available_terms(mysqli $conn, int $facultyId): array
{
    if ($facultyId <= 0 || !synk_table_exists($conn, 'tbl_faculty_workload_sched')) {
        return [];
    }
    $stmt = $conn->prepare("SELECT fw.ay_id, fw.semester, COALESCE(ay.ay, '') AS academic_year_label
        FROM tbl_faculty_workload_sched fw
        LEFT JOIN tbl_academic_years ay ON ay.ay_id = fw.ay_id
        WHERE fw.faculty_id = ? AND fw.ay_id > 0 AND fw.semester BETWEEN 1 AND 3
        GROUP BY fw.ay_id, fw.semester, ay.ay");
    $stmt->bind_param('i', $facultyId);
    $stmt->execute();
    $terms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $terms;
}

function synk_professor_workload_navigation(array $terms, array $currentTerm, int $requestedYear = 0, ?int $requestedSemester = null): array
{
    $years = [];
    foreach ($terms as $term) {
        $id = (int)($term['ay_id'] ?? 0);
        $semester = (int)($term['semester'] ?? 0);
        if ($id <= 0 || $semester < 1 || $semester > 3) {
            continue;
        }
        if (!isset($years[$id])) {
            $label = trim((string)($term['academic_year_label'] ?? ''));
            preg_match('/(?:19|20|21)\d{2}/', $label, $match);
            $years[$id] = ['ay_id' => $id, 'label' => $label !== '' ? $label : 'Academic year ' . $id,
                'start_year' => (int)($match[0] ?? 0), 'semesters' => []];
        }
        $years[$id]['semesters'][$semester] = $semester;
    }
    uasort($years, static function (array $left, array $right): int {
        return ($right['start_year'] <=> $left['start_year'])
            ?: strnatcasecmp($right['label'], $left['label'])
            ?: ($right['ay_id'] <=> $left['ay_id']);
    });
    foreach ($years as &$year) {
        sort($year['semesters']);
    }
    unset($year);
    $yearIds = array_keys($years);
    $latestId = (int)($yearIds[0] ?? 0);
    $selectedId = isset($years[$requestedYear]) ? $requestedYear : $latestId;
    $selected = $years[$selectedId] ?? null;
    $semester = 0;
    if ($selected) {
        if ($requestedSemester === 0) {
            $semester = 0;
        } elseif ($requestedSemester !== null && in_array($requestedSemester, $selected['semesters'], true)) {
            $semester = $requestedSemester;
        } elseif ($requestedSemester !== null) {
            // A stale semester link still shows the requested year's available classes.
            $semester = 0;
        } elseif ($selectedId === (int)($currentTerm['ay_id'] ?? 0)
            && in_array((int)($currentTerm['semester'] ?? 0), $selected['semesters'], true)) {
            $semester = (int)$currentTerm['semester'];
        } else {
            $semester = max($selected['semesters']);
        }
    }
    return ['years' => $years, 'latest_id' => $latestId, 'selected_year' => $selected,
        'ay_id' => $selectedId, 'semester' => $semester];
}

function synk_professor_workload_query_integer($value): ?int
{
    if (!is_string($value) || !preg_match('/^\d{1,9}$/D', $value)) {
        return null;
    }
    return (int)$value;
}
