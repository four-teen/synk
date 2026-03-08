<?php
/**
 * ============================================================================
 * File Path : synk/backend/query_generate_offerings.php
 * Page Name : Generate Prospectus Offerings (AJAX)
 * ============================================================================
 * PURPOSE:
 * - Sync offerings for a selected prospectus + AY + semester
 * - Add offerings for active sections scoped to the live term
 * - Keep out-of-scope offerings for history/preservation
 * - Make active scheduling consume only the live synced offering set
 * ============================================================================
 */
session_start();
header('Content-Type: application/json');
include 'db.php';
require_once __DIR__ . '/offering_scope_helper.php';

function offering_pair_key(int $psId, int $sectionId): string
{
    return $psId . '|' . $sectionId;
}

function build_target_map(
    array $subjectsByYear,
    array $sectionsByYear,
    int $programId,
    int $prospectusId,
    int $ayId,
    int $semester
): array {
    $targetMap = [];

    foreach ($subjectsByYear as $yearLevel => $psIds) {
        $sectionIds = $sectionsByYear[$yearLevel] ?? [];
        if (empty($sectionIds)) {
            continue;
        }

        foreach ($psIds as $psId) {
            foreach ($sectionIds as $sectionId) {
                $targetMap[offering_pair_key((int)$psId, (int)$sectionId)] = [
                    'program_id' => $programId,
                    'prospectus_id' => $prospectusId,
                    'ps_id' => (int)$psId,
                    'year_level' => (int)$yearLevel,
                    'semester' => $semester,
                    'ay_id' => $ayId,
                    'section_id' => (int)$sectionId
                ];
            }
        }
    }

    return $targetMap;
}

function inspect_existing_offerings(mysqli $conn, int $prospectusId, int $ayId, int $semester, array $targetMap): array
{
    $stats = [
        'total_existing_rows' => 0,
        'current_synced_rows' => 0,
        'out_of_scope_existing' => 0,
        'out_of_scope_scheduled' => 0,
        'duplicate_pairs' => 0
    ];

    $scheduledOfferingJoin = synk_scheduled_offering_join_sql('sched', 'o');
    $stmt = $conn->prepare("
        SELECT
            o.offering_id,
            o.ps_id,
            o.section_id,
            IF(sched.offering_id IS NULL, 0, 1) AS has_schedule
        FROM tbl_prospectus_offering o
        {$scheduledOfferingJoin}
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
        ORDER BY o.offering_id
    ");
    $stmt->bind_param('iii', $prospectusId, $ayId, $semester);
    $stmt->execute();
    $res = $stmt->get_result();

    $grouped = [];
    while ($row = $res->fetch_assoc()) {
        $stats['total_existing_rows']++;

        $sectionId = (int)($row['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $stats['out_of_scope_existing']++;
            if ((int)$row['has_schedule'] === 1) {
                $stats['out_of_scope_scheduled']++;
            }
            continue;
        }

        $key = offering_pair_key((int)$row['ps_id'], $sectionId);
        $grouped[$key][] = $row;
    }
    $stmt->close();

    foreach ($grouped as $key => $rows) {
        if (count($rows) > 1) {
            $stats['duplicate_pairs'] += count($rows) - 1;
        }

        if (isset($targetMap[$key])) {
            $stats['current_synced_rows']++;
            continue;
        }

        $stats['out_of_scope_existing'] += count($rows);
        foreach ($rows as $row) {
            if ((int)$row['has_schedule'] === 1) {
                $stats['out_of_scope_scheduled']++;
            }
        }
    }

    return $stats;
}

function choose_canonical_existing_row(array $rows): int
{
    $bestIndex = 0;

    foreach ($rows as $idx => $row) {
        $best = $rows[$bestIndex];
        $rowLocked = strtolower(trim((string)($row['status'] ?? ''))) === 'locked';
        $bestLocked = strtolower(trim((string)($best['status'] ?? ''))) === 'locked';

        if ($rowLocked !== $bestLocked) {
            if ($rowLocked) {
                $bestIndex = $idx;
            }
            continue;
        }

        $rowHasSchedule = (int)($row['has_schedule'] ?? 0) === 1;
        $bestHasSchedule = (int)($best['has_schedule'] ?? 0) === 1;
        if ($rowHasSchedule !== $bestHasSchedule) {
            if ($rowHasSchedule) {
                $bestIndex = $idx;
            }
            continue;
        }

        if ((int)$row['offering_id'] < (int)$best['offering_id']) {
            $bestIndex = $idx;
        }
    }

    return $bestIndex;
}

function get_offering_context(mysqli $conn, int $prospectusId, int $ayId, int $semester, int $collegeId): array
{
    $ctx = [
        'program_id' => 0,
        'program_code' => '',
        'program_name' => '',
        'ay_label' => '',
        'subjectsByYear' => [],
        'sectionsByYear' => [],
        'summary' => [
            'total_subject_rows' => 0,
            'total_active_sections' => 0,
            'potential_offerings' => 0,
            'total_existing_rows' => 0,
            'current_synced_rows' => 0,
            'out_of_scope_existing' => 0,
            'out_of_scope_scheduled' => 0,
            'duplicate_pairs' => 0
        ],
        'blockers' => [],
        'warnings' => []
    ];

    $ayStmt = $conn->prepare('SELECT ay FROM tbl_academic_years WHERE ay_id = ? LIMIT 1');
    $ayStmt->bind_param('i', $ayId);
    $ayStmt->execute();
    $ctx['ay_label'] = $ayStmt->get_result()->fetch_assoc()['ay'] ?? '';
    $ayStmt->close();

    if ($ctx['ay_label'] === '') {
        $ctx['blockers'][] = 'Selected academic year was not found.';
        return $ctx;
    }

    $progStmt = $conn->prepare("
        SELECT h.program_id, p.program_code, p.program_name
        FROM tbl_prospectus_header h
        INNER JOIN tbl_program p ON p.program_id = h.program_id
        WHERE h.prospectus_id = ?
          AND p.college_id = ?
        LIMIT 1
    ");
    $progStmt->bind_param('ii', $prospectusId, $collegeId);
    $progStmt->execute();
    $prog = $progStmt->get_result()->fetch_assoc();
    $progStmt->close();

    if (!$prog) {
        $ctx['blockers'][] = 'Prospectus not found or not allowed for your college.';
        return $ctx;
    }

    $ctx['program_id'] = (int)$prog['program_id'];
    $ctx['program_code'] = (string)$prog['program_code'];
    $ctx['program_name'] = (string)$prog['program_name'];

    $sub = $conn->prepare("
        SELECT ps.ps_id, pys.year_level
        FROM tbl_prospectus_year_sem pys
        INNER JOIN tbl_prospectus_subjects ps ON ps.pys_id = pys.pys_id
        WHERE pys.prospectus_id = ?
          AND pys.semester = ?
    ");
    $sub->bind_param('ii', $prospectusId, $semester);
    $sub->execute();
    $subjectsRes = $sub->get_result();
    while ($s = $subjectsRes->fetch_assoc()) {
        $yearLevel = (int)$s['year_level'];
        $ctx['subjectsByYear'][$yearLevel][] = (int)$s['ps_id'];
        $ctx['summary']['total_subject_rows']++;
    }
    $sub->close();

    if (empty($ctx['subjectsByYear'])) {
        $ctx['blockers'][] = 'No prospectus subjects found for the selected semester.';
        return $ctx;
    }

    $sec = $conn->prepare("
        SELECT section_id, year_level
        FROM tbl_sections
        WHERE program_id = ?
          AND ay_id = ?
          AND semester = ?
          AND status = 'active'
    ");
    $sec->bind_param('iii', $ctx['program_id'], $ayId, $semester);
    $sec->execute();
    $sectionsRes = $sec->get_result();
    while ($row = $sectionsRes->fetch_assoc()) {
        $yearLevel = (int)$row['year_level'];
        $sectionId = (int)$row['section_id'];
        if ($sectionId <= 0) {
            continue;
        }
        $ctx['sectionsByYear'][$yearLevel][] = $sectionId;
        $ctx['summary']['total_active_sections']++;
    }
    $sec->close();

    if (empty($ctx['sectionsByYear'])) {
        $ctx['warnings'][] = 'No active sections found for selected AY and semester. Sync will preserve existing rows but no live offerings will be generated.';
    }

    foreach ($ctx['subjectsByYear'] as $yearLevel => $psIds) {
        $subjectCount = count($psIds);
        $sectionCount = count($ctx['sectionsByYear'][$yearLevel] ?? []);

        if ($sectionCount === 0) {
            $ctx['warnings'][] = "Year level {$yearLevel} has prospectus subjects but no active sections.";
            continue;
        }

        $ctx['summary']['potential_offerings'] += ($subjectCount * $sectionCount);
    }

    foreach ($ctx['sectionsByYear'] as $yearLevel => $sectionIds) {
        if (empty($ctx['subjectsByYear'][$yearLevel])) {
            $ctx['warnings'][] = "Year level {$yearLevel} has active sections but no subjects in selected semester.";
        }
    }

    $targetMap = build_target_map(
        $ctx['subjectsByYear'],
        $ctx['sectionsByYear'],
        (int)$ctx['program_id'],
        $prospectusId,
        $ayId,
        $semester
    );
    $ctx['summary'] = array_merge(
        $ctx['summary'],
        inspect_existing_offerings($conn, $prospectusId, $ayId, $semester, $targetMap)
    );

    if ($ctx['summary']['out_of_scope_existing'] > 0) {
        $ctx['warnings'][] = 'Existing out-of-scope offerings will be retained for history, but hidden from active scheduling.';
    }

    if ($ctx['summary']['out_of_scope_scheduled'] > 0) {
        $ctx['warnings'][] = 'Some out-of-scope offerings already have schedules. Those schedules will be preserved but excluded from the live scheduling set.';
    }

    if ($ctx['summary']['duplicate_pairs'] > 0) {
        $ctx['warnings'][] = 'Duplicate offering pairs were detected. Review and clean them up to avoid duplicate display in downstream pages.';
    }

    return $ctx;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$collegeId = (int)($_SESSION['college_id'] ?? 0);
if ($collegeId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing college context']);
    exit;
}

if (!isset($_POST['generate_offerings']) && !isset($_POST['validate_offerings_context'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed']);
    exit;
}

$prospectusId = (int)($_POST['prospectus_id'] ?? 0);
$ayId = (int)($_POST['ay_id'] ?? 0);
$semester = (int)($_POST['semester'] ?? 0);

if (!$prospectusId || !$ayId || !$semester) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

if (!in_array($semester, [1, 2, 3], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid semester value']);
    exit;
}

$context = get_offering_context($conn, $prospectusId, $ayId, $semester, $collegeId);

if (isset($_POST['validate_offerings_context'])) {
    echo json_encode([
        'status' => empty($context['blockers']) ? 'ok' : 'error',
        'can_generate' => empty($context['blockers']),
        'message' => empty($context['blockers'])
            ? 'Validation passed.'
            : 'Validation failed. Please fix required data before generating.',
        'program_code' => $context['program_code'],
        'program_name' => $context['program_name'],
        'ay_label' => $context['ay_label'],
        'semester' => $semester,
        'summary' => $context['summary'],
        'blockers' => $context['blockers'],
        'warnings' => $context['warnings']
    ]);
    exit;
}

if (!empty($context['blockers'])) {
    echo json_encode([
        'status' => 'error',
        'message' => implode(' ', $context['blockers']),
        'blockers' => $context['blockers']
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $programId = (int)$context['program_id'];
    $subjectsByYear = $context['subjectsByYear'];
    $sectionsByYear = $context['sectionsByYear'];
    $targetMap = build_target_map($subjectsByYear, $sectionsByYear, $programId, $prospectusId, $ayId, $semester);
    $scheduledOfferingJoin = synk_scheduled_offering_join_sql('sched', 'o');

    $existingStmt = $conn->prepare("
        SELECT
            o.offering_id,
            o.program_id,
            o.ps_id,
            o.year_level,
            o.semester,
            o.ay_id,
            o.section_id,
            o.status,
            IF(sched.offering_id IS NULL, 0, 1) AS has_schedule
        FROM tbl_prospectus_offering o
        {$scheduledOfferingJoin}
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
        ORDER BY o.offering_id
    ");
    $existingStmt->bind_param('iii', $prospectusId, $ayId, $semester);
    $existingStmt->execute();
    $existingRes = $existingStmt->get_result();

    $existingByKey = [];
    while ($row = $existingRes->fetch_assoc()) {
        $sectionId = (int)($row['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $existingByKey['__invalid__'][] = $row;
            continue;
        }

        $existingByKey[offering_pair_key((int)$row['ps_id'], $sectionId)][] = $row;
    }
    $existingStmt->close();

    $insertStmt = $conn->prepare("
        INSERT INTO tbl_prospectus_offering
            (program_id, prospectus_id, ps_id, year_level, semester, ay_id, section_id, status, date_created)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $syncStmt = $conn->prepare("
        UPDATE tbl_prospectus_offering
        SET program_id = ?, year_level = ?, semester = ?, ay_id = ?, section_id = ?
        WHERE offering_id = ?
    ");

    $inserted = 0;
    $syncedExisting = 0;
    $updatedExisting = 0;
    $retainedOutOfScope = 0;
    $retainedOutOfScopeScheduled = 0;
    $duplicatePairs = 0;

    foreach ($targetMap as $key => $target) {
        if (!isset($existingByKey[$key]) || empty($existingByKey[$key])) {
            $insertProgramId = (int)$target['program_id'];
            $insertProspectusId = (int)$target['prospectus_id'];
            $insertPsId = (int)$target['ps_id'];
            $insertYearLevel = (int)$target['year_level'];
            $insertSemester = (int)$target['semester'];
            $insertAyId = (int)$target['ay_id'];
            $insertSectionId = (int)$target['section_id'];
            $insertStmt->bind_param(
                'iiiiiii',
                $insertProgramId,
                $insertProspectusId,
                $insertPsId,
                $insertYearLevel,
                $insertSemester,
                $insertAyId,
                $insertSectionId
            );
            $insertStmt->execute();
            $inserted++;
            continue;
        }

        $rows = $existingByKey[$key];
        if (count($rows) > 1) {
            $duplicatePairs += count($rows) - 1;
        }

        $canonicalIndex = choose_canonical_existing_row($rows);
        $canonical = $rows[$canonicalIndex];

        $needsUpdate =
            (int)$canonical['program_id'] !== $target['program_id'] ||
            (int)$canonical['year_level'] !== $target['year_level'] ||
            (int)$canonical['semester'] !== $target['semester'] ||
            (int)$canonical['ay_id'] !== $target['ay_id'] ||
            (int)$canonical['section_id'] !== $target['section_id'];

        if ($needsUpdate) {
            $syncProgramId = (int)$target['program_id'];
            $syncYearLevel = (int)$target['year_level'];
            $syncSemester = (int)$target['semester'];
            $syncAyId = (int)$target['ay_id'];
            $syncSectionId = (int)$target['section_id'];
            $syncOfferingId = (int)$canonical['offering_id'];
            $syncStmt->bind_param(
                'iiiiii',
                $syncProgramId,
                $syncYearLevel,
                $syncSemester,
                $syncAyId,
                $syncSectionId,
                $syncOfferingId
            );
            $syncStmt->execute();
            $updatedExisting += $syncStmt->affected_rows > 0 ? 1 : 0;
        }

        $syncedExisting++;
        unset($existingByKey[$key]);
    }

    $insertStmt->close();
    $syncStmt->close();

    foreach ($existingByKey as $rows) {
        foreach ($rows as $row) {
            $retainedOutOfScope++;
            if ((int)($row['has_schedule'] ?? 0) === 1) {
                $retainedOutOfScopeScheduled++;
            }
        }
    }

    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

    $toActive = $conn->prepare("
        UPDATE tbl_prospectus_offering o
        {$liveOfferingJoins}
        {$scheduledOfferingJoin}
        SET o.status = 'active'
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND (o.status IS NULL OR o.status = 'pending')
          AND sched.offering_id IS NOT NULL
    ");
    $toActive->bind_param('iii', $prospectusId, $ayId, $semester);
    $toActive->execute();
    $updatedActive = $toActive->affected_rows;
    $toActive->close();

    $toPending = $conn->prepare("
        UPDATE tbl_prospectus_offering o
        {$liveOfferingJoins}
        {$scheduledOfferingJoin}
        SET o.status = 'pending'
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND o.status = 'active'
          AND sched.offering_id IS NULL
    ");
    $toPending->bind_param('iii', $prospectusId, $ayId, $semester);
    $toPending->execute();
    $updatedPending = $toPending->affected_rows;
    $toPending->close();

    $conn->commit();

    echo json_encode([
        'status' => 'ok',
        'inserted' => $inserted,
        'synced_existing' => $syncedExisting,
        'updated_existing' => $updatedExisting,
        'retained_out_of_scope' => $retainedOutOfScope,
        'retained_out_of_scope_scheduled' => $retainedOutOfScopeScheduled,
        'duplicate_pairs' => $duplicatePairs,
        'updated_active' => $updatedActive,
        'updated_pending' => $updatedPending
    ]);
} catch (Throwable $e) {
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    error_log('query_generate_offerings.php error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to sync offerings. Please contact administrator if this persists.'
    ]);
}
