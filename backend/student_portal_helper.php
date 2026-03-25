<?php

require_once __DIR__ . '/auth_useraccount.php';
require_once __DIR__ . '/academic_term_helper.php';
require_once __DIR__ . '/offering_scope_helper.php';
require_once __DIR__ . '/schedule_block_helper.php';

function synk_student_require_login(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: login.php');
        exit;
    }

    $role = (string)($_SESSION['role'] ?? '');
    if ($role === 'student') {
        return;
    }

    $redirectPath = synk_role_redirect_path($role);
    header('Location: ../' . ($redirectPath ?? 'index.php'));
    exit;
}

function synk_student_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function synk_student_title_case(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    return ucwords(strtolower($trimmed));
}

function synk_student_format_program_label(array $row, bool $includeCollege = false): string
{
    $programCode = strtoupper(trim((string)($row['program_code'] ?? '')));
    $programName = synk_student_title_case((string)($row['program_name'] ?? ''));
    $major = synk_student_title_case((string)($row['major'] ?? $row['program_major'] ?? ''));

    $label = trim(implode(' - ', array_filter([$programCode, $programName], static function ($value) {
        return trim((string)$value) !== '';
    })));

    if ($major !== '') {
        $label .= ($label !== '' ? ' ' : '') . '(Major in ' . $major . ')';
    }

    if ($includeCollege) {
        $collegeName = trim((string)($row['college_name'] ?? ''));
        $campusCode = strtoupper(trim((string)($row['campus_code'] ?? '')));
        $scopeParts = [];
        if ($collegeName !== '') {
            $scopeParts[] = $collegeName;
        }
        if ($campusCode !== '') {
            $scopeParts[] = $campusCode;
        }
        if (!empty($scopeParts)) {
            $label .= ($label !== '' ? ' ' : '') . '[' . implode(' | ', $scopeParts) . ']';
        }
    }

    return $label !== '' ? $label : 'Program';
}

function synk_student_select_valid_id(array $rows, int $selectedId, string $keyName): int
{
    if ($selectedId <= 0) {
        return 0;
    }

    foreach ($rows as $row) {
        if ((int)($row[$keyName] ?? 0) === $selectedId) {
            return $selectedId;
        }
    }

    return 0;
}

function synk_student_fetch_campuses(mysqli $conn): array
{
    $sql = "
        SELECT DISTINCT
            ca.campus_id,
            ca.campus_code,
            ca.campus_name
        FROM tbl_campus ca
        INNER JOIN tbl_college c
            ON c.campus_id = ca.campus_id
           AND c.status = 'active'
        INNER JOIN tbl_program p
            ON p.college_id = c.college_id
           AND p.status = 'active'
        ORDER BY ca.campus_name ASC, ca.campus_code ASC
    ";

    $result = $conn->query($sql);
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->close();
    }

    return $rows;
}

function synk_student_fetch_colleges(mysqli $conn, int $campusId = 0): array
{
    $sql = "
        SELECT DISTINCT
            c.college_id,
            c.college_code,
            c.college_name,
            ca.campus_id,
            ca.campus_code,
            ca.campus_name
        FROM tbl_college c
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        INNER JOIN tbl_program p
            ON p.college_id = c.college_id
           AND p.status = 'active'
        WHERE c.status = 'active'
    ";

    $types = '';
    $params = [];

    if ($campusId > 0) {
        $sql .= " AND c.campus_id = ?";
        $types .= 'i';
        $params[] = $campusId;
    }

    $sql .= "
        ORDER BY ca.campus_name ASC, c.college_name ASC, c.college_code ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        synk_stmt_bind_params($stmt, $types, $params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function synk_student_fetch_dashboard_summary(mysqli $conn, int $ayId, int $semester, int $campusId = 0, int $collegeId = 0): array
{
    $summary = [
        'program_count' => 0,
        'prospectus_count' => 0,
        'section_count' => 0,
        'schedule_count' => 0,
    ];

    $sql = "
        SELECT
            COUNT(DISTINCT p.program_id) AS program_count,
            COUNT(DISTINCT h.prospectus_id) AS prospectus_count,
            COUNT(DISTINCT CASE WHEN sec.section_id IS NOT NULL THEN o.section_id END) AS section_count,
            COUNT(DISTINCT cs.schedule_id) AS schedule_count
        FROM tbl_program p
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        LEFT JOIN tbl_prospectus_header h
            ON h.program_id = p.program_id
        LEFT JOIN tbl_prospectus_offering o
            ON o.program_id = p.program_id
           AND o.ay_id = ?
           AND o.semester = ?
        LEFT JOIN tbl_sections sec
            ON sec.section_id = o.section_id
           AND sec.status = 'active'
        LEFT JOIN tbl_class_schedule cs
            ON cs.offering_id = o.offering_id
        WHERE p.status = 'active'
          AND c.status = 'active'
    ";

    $types = 'ii';
    $params = [$ayId, $semester];

    if ($campusId > 0) {
        $sql .= " AND c.campus_id = ?";
        $types .= 'i';
        $params[] = $campusId;
    }

    if ($collegeId > 0) {
        $sql .= " AND c.college_id = ?";
        $types .= 'i';
        $params[] = $collegeId;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $summary;
    }

    synk_stmt_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $summary;
    }

    return [
        'program_count' => (int)($row['program_count'] ?? 0),
        'prospectus_count' => (int)($row['prospectus_count'] ?? 0),
        'section_count' => (int)($row['section_count'] ?? 0),
        'schedule_count' => (int)($row['schedule_count'] ?? 0),
    ];
}

function synk_student_fetch_dashboard_program_cards(
    mysqli $conn,
    int $ayId,
    int $semester,
    int $campusId = 0,
    int $collegeId = 0,
    int $limit = 8
): array {
    $sql = "
        SELECT
            p.program_id,
            p.program_code,
            p.program_name,
            COALESCE(p.major, '') AS major,
            c.college_id,
            c.college_code,
            c.college_name,
            ca.campus_id,
            ca.campus_code,
            ca.campus_name,
            COUNT(DISTINCT h.prospectus_id) AS prospectus_count,
            COUNT(DISTINCT CASE WHEN sec.section_id IS NOT NULL THEN o.section_id END) AS section_count,
            COUNT(DISTINCT cs.schedule_id) AS schedule_count
        FROM tbl_program p
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        LEFT JOIN tbl_prospectus_header h
            ON h.program_id = p.program_id
        LEFT JOIN tbl_prospectus_offering o
            ON o.program_id = p.program_id
           AND o.ay_id = ?
           AND o.semester = ?
        LEFT JOIN tbl_sections sec
            ON sec.section_id = o.section_id
           AND sec.status = 'active'
        LEFT JOIN tbl_class_schedule cs
            ON cs.offering_id = o.offering_id
        WHERE p.status = 'active'
          AND c.status = 'active'
    ";

    $types = 'ii';
    $params = [$ayId, $semester];

    if ($campusId > 0) {
        $sql .= " AND c.campus_id = ?";
        $types .= 'i';
        $params[] = $campusId;
    }

    if ($collegeId > 0) {
        $sql .= " AND c.college_id = ?";
        $types .= 'i';
        $params[] = $collegeId;
    }

    $sql .= "
        GROUP BY
            p.program_id,
            p.program_code,
            p.program_name,
            p.major,
            c.college_id,
            c.college_code,
            c.college_name,
            ca.campus_id,
            ca.campus_code,
            ca.campus_name
        HAVING prospectus_count > 0 OR section_count > 0
        ORDER BY
            schedule_count DESC,
            section_count DESC,
            prospectus_count DESC,
            p.program_name ASC,
            p.major ASC,
            p.program_code ASC
        LIMIT ?
    ";

    $safeLimit = max(1, min(24, $limit));
    $types .= 'i';
    $params[] = $safeLimit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    synk_stmt_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function synk_student_fetch_programs_for_prospectus(mysqli $conn, int $campusId = 0, int $collegeId = 0): array
{
    $sql = "
        SELECT
            p.program_id,
            p.program_code,
            p.program_name,
            COALESCE(p.major, '') AS major,
            c.college_id,
            c.college_code,
            c.college_name,
            ca.campus_id,
            ca.campus_code,
            ca.campus_name,
            COUNT(DISTINCT h.prospectus_id) AS prospectus_count
        FROM tbl_program p
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        INNER JOIN tbl_prospectus_header h
            ON h.program_id = p.program_id
        WHERE p.status = 'active'
          AND c.status = 'active'
    ";

    $types = '';
    $params = [];

    if ($campusId > 0) {
        $sql .= " AND c.campus_id = ?";
        $types .= 'i';
        $params[] = $campusId;
    }

    if ($collegeId > 0) {
        $sql .= " AND c.college_id = ?";
        $types .= 'i';
        $params[] = $collegeId;
    }

    $sql .= "
        GROUP BY
            p.program_id,
            p.program_code,
            p.program_name,
            p.major,
            c.college_id,
            c.college_code,
            c.college_name,
            ca.campus_id,
            ca.campus_code,
            ca.campus_name
        ORDER BY
            ca.campus_name ASC,
            c.college_name ASC,
            p.program_name ASC,
            p.major ASC,
            p.program_code ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        synk_stmt_bind_params($stmt, $types, $params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function synk_student_fetch_prospectus_versions(mysqli $conn, int $programId): array
{
    if ($programId <= 0) {
        return [];
    }

    $sql = "
        SELECT
            h.prospectus_id,
            h.program_id,
            h.cmo_no,
            h.effective_sy,
            COUNT(DISTINCT ys.pys_id) AS term_count,
            COUNT(ps.ps_id) AS subject_count
        FROM tbl_prospectus_header h
        LEFT JOIN tbl_prospectus_year_sem ys
            ON ys.prospectus_id = h.prospectus_id
        LEFT JOIN tbl_prospectus_subjects ps
            ON ps.pys_id = ys.pys_id
        WHERE h.program_id = ?
        GROUP BY
            h.prospectus_id,
            h.program_id,
            h.cmo_no,
            h.effective_sy
        ORDER BY h.effective_sy DESC, h.prospectus_id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $programId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function synk_student_normalize_prospectus_subject_values(float $lecHours, float $labValue, ?float $storedTotalUnits = null): array
{
    $safeLecHours = max(0.0, $lecHours);
    $displayLabHours = round(
        synk_lab_contact_hours($safeLecHours, max(0.0, $labValue), (float)($storedTotalUnits ?? 0.0)),
        2
    );

    return [
        'lec_units' => round($safeLecHours, 2),
        'lab_units' => $displayLabHours,
        'total_units' => round(synk_subject_units_total($safeLecHours, $displayLabHours, 0.0), 2),
    ];
}

function synk_student_fetch_prospectus_sheet(mysqli $conn, int $prospectusId): ?array
{
    if ($prospectusId <= 0) {
        return null;
    }

    $headerSql = "
        SELECT
            h.prospectus_id,
            h.program_id,
            h.cmo_no,
            h.effective_sy,
            p.program_name,
            p.program_code,
            COALESCE(p.major, '') AS major,
            c.college_name,
            c.college_code,
            ca.campus_name,
            ca.campus_code
        FROM tbl_prospectus_header h
        INNER JOIN tbl_program p
            ON p.program_id = h.program_id
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        WHERE h.prospectus_id = ?
        LIMIT 1
    ";

    $headerStmt = $conn->prepare($headerSql);
    if (!$headerStmt) {
        return null;
    }

    $headerStmt->bind_param('i', $prospectusId);
    $headerStmt->execute();
    $header = $headerStmt->get_result()->fetch_assoc();
    $headerStmt->close();

    if (!$header) {
        return null;
    }

    $structure = [];
    $subjects = [];

    $detailSql = "
        SELECT
            pys.pys_id,
            pys.year_level,
            pys.semester,
            ps.ps_id,
            s.sub_code,
            s.sub_description,
            ps.lec_units,
            ps.lab_units,
            ps.total_units,
            ps.prerequisites,
            ps.sort_order
        FROM tbl_prospectus_year_sem pys
        LEFT JOIN tbl_prospectus_subjects ps
            ON ps.pys_id = pys.pys_id
        LEFT JOIN tbl_subject_masterlist s
            ON s.sub_id = ps.sub_id
        WHERE pys.prospectus_id = ?
        ORDER BY pys.year_level ASC, pys.semester ASC, ps.sort_order ASC, s.sub_code ASC
    ";

    $detailStmt = $conn->prepare($detailSql);
    if (!$detailStmt) {
        return null;
    }

    $detailStmt->bind_param('i', $prospectusId);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    while ($row = $detailResult->fetch_assoc()) {
        $year = (string)($row['year_level'] ?? '');
        $semester = (string)($row['semester'] ?? '');

        if (!isset($structure[$year])) {
            $structure[$year] = [];
        }
        if (!isset($structure[$year][$semester])) {
            $structure[$year][$semester] = [];
        }

        if ((int)($row['ps_id'] ?? 0) <= 0) {
            continue;
        }

        if (!isset($subjects[$year])) {
            $subjects[$year] = [];
        }
        if (!isset($subjects[$year][$semester])) {
            $subjects[$year][$semester] = [];
        }

        $normalized = synk_student_normalize_prospectus_subject_values(
            (float)($row['lec_units'] ?? 0),
            (float)($row['lab_units'] ?? 0),
            isset($row['total_units']) && $row['total_units'] !== null ? (float)$row['total_units'] : null
        );

        $subjects[$year][$semester][] = [
            'sub_code' => (string)($row['sub_code'] ?? ''),
            'sub_description' => (string)($row['sub_description'] ?? ''),
            'lec_units' => $normalized['lec_units'],
            'lab_units' => $normalized['lab_units'],
            'total_units' => $normalized['total_units'],
            'prerequisites' => trim((string)($row['prerequisites'] ?? '')) !== ''
                ? trim((string)$row['prerequisites'])
                : 'None',
        ];
    }

    $detailStmt->close();

    return [
        'header' => $header,
        'structure' => $structure,
        'subjects' => $subjects,
    ];
}

function synk_student_fetch_programs_for_schedule(
    mysqli $conn,
    int $ayId,
    int $semester,
    int $campusId = 0,
    int $collegeId = 0
): array {
    if ($ayId <= 0 || $semester <= 0) {
        return [];
    }

    $sql = "
        SELECT
            p.program_id,
            p.program_code,
            p.program_name,
            COALESCE(p.major, '') AS major,
            c.college_id,
            c.college_code,
            c.college_name,
            ca.campus_id,
            ca.campus_code,
            ca.campus_name,
            COUNT(DISTINCT sec.section_id) AS section_count
        FROM tbl_program p
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        INNER JOIN tbl_sections sec
            ON sec.program_id = p.program_id
           AND sec.ay_id = ?
           AND sec.semester = ?
           AND sec.status = 'active'
        INNER JOIN tbl_prospectus_offering o
            ON o.section_id = sec.section_id
           AND o.program_id = sec.program_id
           AND o.ay_id = sec.ay_id
           AND o.semester = sec.semester
        WHERE p.status = 'active'
          AND c.status = 'active'
    ";

    $types = 'ii';
    $params = [$ayId, $semester];

    if ($campusId > 0) {
        $sql .= " AND c.campus_id = ?";
        $types .= 'i';
        $params[] = $campusId;
    }

    if ($collegeId > 0) {
        $sql .= " AND c.college_id = ?";
        $types .= 'i';
        $params[] = $collegeId;
    }

    $sql .= "
        GROUP BY
            p.program_id,
            p.program_code,
            p.program_name,
            p.major,
            c.college_id,
            c.college_code,
            c.college_name,
            ca.campus_id,
            ca.campus_code,
            ca.campus_name
        ORDER BY
            ca.campus_name ASC,
            c.college_name ASC,
            p.program_name ASC,
            p.major ASC,
            p.program_code ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    synk_stmt_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function synk_student_fetch_sections_for_program(mysqli $conn, int $ayId, int $semester, int $programId): array
{
    if ($ayId <= 0 || $semester <= 0 || $programId <= 0) {
        return [];
    }

    $sql = "
        SELECT
            sec.section_id,
            sec.full_section,
            sec.year_level,
            sec.section_name,
            p.program_code,
            p.program_name,
            COALESCE(p.major, '') AS major,
            c.college_name,
            ca.campus_name
        FROM tbl_sections sec
        INNER JOIN tbl_program p
            ON p.program_id = sec.program_id
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        INNER JOIN tbl_prospectus_offering o
            ON o.section_id = sec.section_id
           AND o.program_id = sec.program_id
           AND o.ay_id = sec.ay_id
           AND o.semester = sec.semester
        WHERE sec.program_id = ?
          AND sec.ay_id = ?
          AND sec.semester = ?
          AND sec.status = 'active'
        GROUP BY
            sec.section_id,
            sec.full_section,
            sec.year_level,
            sec.section_name,
            p.program_code,
            p.program_name,
            p.major,
            c.college_name,
            ca.campus_name
        ORDER BY
            sec.year_level ASC,
            sec.section_name ASC,
            sec.full_section ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iii', $programId, $ayId, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $fullSection = trim((string)($row['full_section'] ?? ''));
        $programCode = trim((string)($row['program_code'] ?? ''));
        $sectionName = trim((string)($row['section_name'] ?? ''));

        $row['label'] = $fullSection !== '' ? $fullSection : trim($programCode . ' ' . $sectionName);
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function synk_student_fetch_section_schedule(mysqli $conn, int $sectionId, int $ayId, int $semester): array
{
    $payload = [
        'meta' => [],
        'rows' => [],
        'rooms_text' => 'TBA',
    ];

    if ($sectionId <= 0 || $ayId <= 0 || $semester <= 0) {
        return $payload;
    }

    $contextSql = "
        SELECT
            sec.section_id,
            sec.section_name,
            sec.full_section,
            sec.year_level,
            p.program_code,
            p.program_name,
            COALESCE(p.major, '') AS program_major,
            c.college_name,
            c.college_code,
            ca.campus_name,
            ca.campus_code
        FROM tbl_sections sec
        INNER JOIN tbl_program p
            ON p.program_id = sec.program_id
        INNER JOIN tbl_college c
            ON c.college_id = p.college_id
        INNER JOIN tbl_campus ca
            ON ca.campus_id = c.campus_id
        WHERE sec.section_id = ?
          AND sec.ay_id = ?
          AND sec.semester = ?
          AND sec.status = 'active'
        LIMIT 1
    ";

    $contextStmt = $conn->prepare($contextSql);
    if (!$contextStmt) {
        return $payload;
    }

    $contextStmt->bind_param('iii', $sectionId, $ayId, $semester);
    $contextStmt->execute();
    $context = $contextStmt->get_result()->fetch_assoc();
    $contextStmt->close();

    if (!$context) {
        return $payload;
    }

    $liveOfferingJoins = synk_live_offering_join_sql('po', 'sec', 'ps', 'pys', 'ph');
    $scheduleSql = "
        SELECT
            cs.schedule_id,
            cs.schedule_type,
            cs.time_start,
            cs.time_end,
            cs.days_json,
            sm.sub_code AS subject_code,
            sm.sub_description AS subject_description,
            COALESCE(
                NULLIF(
                    TRIM(
                        GROUP_CONCAT(
                            DISTINCT CONCAT(f.last_name, ', ', f.first_name)
                            ORDER BY f.last_name ASC, f.first_name ASC
                            SEPARATOR ' / '
                        )
                    ),
                    ''
                ),
                'TBA'
            ) AS faculty_name,
            COALESCE(
                NULLIF(TRIM(r.room_code), ''),
                NULLIF(TRIM(r.room_name), ''),
                'TBA'
            ) AS room_label
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        {$liveOfferingJoins}
        INNER JOIN tbl_program p
            ON p.program_id = po.program_id
        INNER JOIN tbl_subject_masterlist sm
            ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_rooms r
            ON r.room_id = cs.room_id
        LEFT JOIN tbl_faculty_workload_sched fws
            ON fws.schedule_id = cs.schedule_id
        LEFT JOIN tbl_faculty f
            ON f.faculty_id = fws.faculty_id
        WHERE po.section_id = ?
          AND po.ay_id = ?
          AND po.semester = ?
        GROUP BY
            cs.schedule_id,
            cs.schedule_type,
            cs.time_start,
            cs.time_end,
            cs.days_json,
            sm.sub_code,
            sm.sub_description,
            r.room_code,
            r.room_name
        ORDER BY
            cs.time_start ASC,
            sm.sub_code ASC,
            FIELD(cs.schedule_type, 'LEC', 'LAB'),
            cs.schedule_id ASC
    ";

    $scheduleStmt = $conn->prepare($scheduleSql);
    if (!$scheduleStmt) {
        return $payload;
    }

    $scheduleStmt->bind_param('iii', $sectionId, $ayId, $semester);
    $scheduleStmt->execute();
    $scheduleResult = $scheduleStmt->get_result();

    $rooms = [];
    $rows = [];

    while ($row = $scheduleResult->fetch_assoc()) {
        $daysRaw = json_decode((string)($row['days_json'] ?? '[]'), true);
        if (!is_array($daysRaw)) {
            $daysRaw = [];
        }

        $roomLabel = trim((string)($row['room_label'] ?? ''));
        if ($roomLabel !== '' && strtoupper($roomLabel) !== 'TBA') {
            $rooms[$roomLabel] = true;
        }

        $rows[] = [
            'schedule_id' => (int)($row['schedule_id'] ?? 0),
            'schedule_type' => synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC')),
            'time_start' => (string)($row['time_start'] ?? ''),
            'time_end' => (string)($row['time_end'] ?? ''),
            'subject_code' => (string)($row['subject_code'] ?? ''),
            'subject_description' => (string)($row['subject_description'] ?? ''),
            'faculty_name' => (string)($row['faculty_name'] ?? 'TBA'),
            'room_label' => $roomLabel !== '' ? $roomLabel : 'TBA',
            'days_raw' => $daysRaw,
        ];
    }

    $scheduleStmt->close();

    $context['rooms_text'] = !empty($rooms) ? implode(', ', array_keys($rooms)) : 'TBA';
    $payload['meta'] = $context;
    $payload['rows'] = $rows;
    $payload['rooms_text'] = $context['rooms_text'];

    return $payload;
}

function synk_student_schedule_day_columns(): array
{
    return [
        ['key' => 'M', 'label' => 'Mon'],
        ['key' => 'T', 'label' => 'Tue'],
        ['key' => 'W', 'label' => 'Wed'],
        ['key' => 'TH', 'label' => 'Thu'],
        ['key' => 'F', 'label' => 'Fri'],
        ['key' => 'S', 'label' => 'Sat'],
    ];
}

function synk_student_schedule_day_order(): array
{
    return [
        'M' => 1,
        'T' => 2,
        'W' => 3,
        'TH' => 4,
        'F' => 5,
        'S' => 6,
    ];
}

function synk_student_normalize_grid_day_token(string $day): string
{
    $token = strtoupper(trim($day));
    return $token === 'TH' ? 'TH' : $token;
}

function synk_student_normalize_grid_days($days): array
{
    if (!is_array($days)) {
        return [];
    }

    $order = synk_student_schedule_day_order();
    $seen = [];

    foreach ($days as $day) {
        $token = synk_student_normalize_grid_day_token((string)$day);
        if ($token !== '' && isset($order[$token])) {
            $seen[$token] = true;
        }
    }

    $tokens = array_keys($seen);
    usort($tokens, static function ($left, $right) use ($order) {
        return ($order[$left] ?? 99) <=> ($order[$right] ?? 99);
    });

    return $tokens;
}

function synk_student_time_to_minutes(string $time): int
{
    if (!preg_match('/^(\d{2}):(\d{2})/', trim($time), $matches)) {
        return 0;
    }

    return ((int)$matches[1] * 60) + (int)$matches[2];
}

function synk_student_minutes_to_ampm(int $minutes): string
{
    $safeMinutes = max(0, $minutes);
    $hour = (int)floor($safeMinutes / 60);
    $minute = $safeMinutes % 60;
    $period = $hour >= 12 ? 'PM' : 'AM';
    $hour = $hour % 12 ?: 12;

    return $hour . ':' . str_pad((string)$minute, 2, '0', STR_PAD_LEFT) . ' ' . $period;
}

function synk_student_format_time_range(int $startMinutes, int $endMinutes): string
{
    return synk_student_minutes_to_ampm($startMinutes) . ' - ' . synk_student_minutes_to_ampm($endMinutes);
}

function synk_student_build_schedule_matrix(array $rows): array
{
    $slotInterval = 30;
    $dayStart = 7 * 60;
    $dayEnd = 18 * 60;
    $slots = [];
    $occupancy = [];
    $warnings = [];

    foreach (synk_student_schedule_day_columns() as $dayColumn) {
        $occupancy[$dayColumn['key']] = [];
    }

    for ($minutes = $dayStart; $minutes < $dayEnd; $minutes += $slotInterval) {
        $slots[] = $minutes;
    }

    foreach ($rows as $row) {
        $start = synk_student_time_to_minutes((string)($row['time_start'] ?? ''));
        $end = synk_student_time_to_minutes((string)($row['time_end'] ?? ''));
        $days = synk_student_normalize_grid_days($row['days_raw'] ?? []);

        $start = max($dayStart, $start);
        $end = min($dayEnd, $end);

        if (empty($days) || $end <= $start) {
            continue;
        }

        $hasConflict = false;

        foreach ($days as $dayKey) {
            for ($cursor = $start; $cursor < $end; $cursor += $slotInterval) {
                if (isset($occupancy[$dayKey][$cursor])) {
                    $hasConflict = true;
                    break 2;
                }
            }
        }

        if ($hasConflict) {
            $subjectCode = trim((string)($row['subject_code'] ?? '')) ?: 'Scheduled class';
            $warnings[] = $subjectCode . ' overlaps an existing cell and was skipped in the grid view.';
            continue;
        }

        $block = $row;
        $block['_slot_span'] = max(1, (int)ceil(($end - $start) / $slotInterval));

        foreach ($days as $dayKey) {
            $occupancy[$dayKey][$start] = [
                'type' => 'start',
                'block' => $block,
            ];

            for ($cursor = $start + $slotInterval; $cursor < $end; $cursor += $slotInterval) {
                $occupancy[$dayKey][$cursor] = [
                    'type' => 'covered',
                    'start' => $start,
                ];
            }
        }
    }

    return [
        'slots' => $slots,
        'occupancy' => $occupancy,
        'warnings' => array_values(array_unique($warnings)),
    ];
}
