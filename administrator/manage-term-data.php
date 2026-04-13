<?php
session_start();
ob_start();

include '../backend/db.php';
require_once '../backend/academic_term_helper.php';

if (!isset($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['csrf_token'];
$currentTerm = synk_fetch_current_academic_term($conn);
$currentTermText = trim((string)($currentTerm['term_text'] ?? 'Current academic term'));
$currentTermTextEscaped = htmlspecialchars($currentTermText, ENT_QUOTES, 'UTF-8');

function synk_admin_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function synk_admin_semester_options()
{
    return [
        1 => '1st Semester',
        2 => '2nd Semester',
        3 => 'Midyear',
    ];
}

function synk_admin_normalize_confirmation_phrase($value)
{
    $value = strtoupper(trim((string)$value));
    return preg_replace('/\s+/', ' ', $value);
}

function synk_admin_build_scope_query($campusId, $collegeId, $ayId, $semester)
{
    $params = [];

    if ($campusId > 0) {
        $params['campus_id'] = $campusId;
    }

    if ($collegeId > 0) {
        $params['college_id'] = $collegeId;
    }

    if ($ayId > 0) {
        $params['ay_id'] = $ayId;
    }

    if ($semester > 0) {
        $params['semester'] = $semester;
    }

    return http_build_query($params);
}

function synk_admin_redirect_with_flash($campusId, $collegeId, $ayId, $semester, $type, $message)
{
    $_SESSION['term_data_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    $query = synk_admin_build_scope_query($campusId, $collegeId, $ayId, $semester);
    $location = 'manage-term-data.php' . ($query !== '' ? '?' . $query : '');
    header('Location: ' . $location);
    exit;
}

function synk_admin_fetch_campuses(mysqli $conn)
{
    $items = [];
    $sql = "
        SELECT campus_id, campus_code, campus_name, status
        FROM tbl_campus
        ORDER BY
            CASE WHEN status = 'active' THEN 0 ELSE 1 END,
            campus_name ASC,
            campus_code ASC
    ";
    $result = $conn->query($sql);
    if (!$result) {
        return $items;
    }

    while ($row = $result->fetch_assoc()) {
        $items[(int)$row['campus_id']] = $row;
    }

    return $items;
}

function synk_admin_fetch_colleges(mysqli $conn)
{
    $items = [];
    $sql = "
        SELECT
            c.college_id,
            c.campus_id,
            c.college_code,
            c.college_name,
            c.status,
            cp.campus_name,
            cp.campus_code
        FROM tbl_college c
        LEFT JOIN tbl_campus cp
            ON cp.campus_id = c.campus_id
        ORDER BY
            CASE WHEN c.status = 'active' THEN 0 ELSE 1 END,
            cp.campus_name ASC,
            c.college_name ASC,
            c.college_code ASC
    ";
    $result = $conn->query($sql);
    if (!$result) {
        return $items;
    }

    while ($row = $result->fetch_assoc()) {
        $items[(int)$row['college_id']] = $row;
    }

    return $items;
}

function synk_admin_fetch_academic_years(mysqli $conn)
{
    $items = [];
    $sql = "
        SELECT ay_id, ay, status
        FROM tbl_academic_years
        ORDER BY ay_id DESC
    ";
    $result = $conn->query($sql);
    if (!$result) {
        return $items;
    }

    while ($row = $result->fetch_assoc()) {
        $items[(int)$row['ay_id']] = $row;
    }

    return $items;
}

function synk_admin_college_display_name($college)
{
    $code = trim((string)($college['college_code'] ?? ''));
    $name = trim((string)($college['college_name'] ?? ''));

    if ($code !== '' && $name !== '') {
        return $code . ' - ' . $name;
    }

    return $name !== '' ? $name : $code;
}

function synk_admin_college_confirmation_token($college)
{
    $code = strtoupper(trim((string)($college['college_code'] ?? '')));
    if ($code !== '') {
        return $code;
    }

    return synk_admin_normalize_confirmation_phrase((string)($college['college_name'] ?? 'COLLEGE'));
}

function synk_admin_resolve_scope($campuses, $colleges, $academicYears, $campusId, $collegeId, $ayId, $semester)
{
    $semesterOptions = synk_admin_semester_options();
    $errors = [];
    $campus = null;
    $college = null;
    $academicYear = null;

    if ($campusId > 0) {
        if (!isset($campuses[$campusId])) {
            $errors[] = 'Selected campus is invalid.';
        } else {
            $campus = $campuses[$campusId];
        }
    }

    if ($collegeId > 0) {
        if (!isset($colleges[$collegeId])) {
            $errors[] = 'Selected college is invalid.';
        } else {
            $college = $colleges[$collegeId];
        }
    }

    if ($campus !== null && $college !== null && (int)$college['campus_id'] !== (int)$campus['campus_id']) {
        $errors[] = 'Selected college does not belong to the selected campus.';
    }

    if ($ayId > 0) {
        if (!isset($academicYears[$ayId])) {
            $errors[] = 'Selected academic year is invalid.';
        } else {
            $academicYear = $academicYears[$ayId];
        }
    }

    if ($semester > 0 && !isset($semesterOptions[$semester])) {
        $errors[] = 'Selected semester is invalid.';
    }

    return [
        'errors' => $errors,
        'campus' => $campus,
        'college' => $college,
        'academic_year' => $academicYear,
        'semester_label' => $semesterOptions[$semester] ?? '',
    ];
}

function synk_admin_fetch_scope_preview(mysqli $conn, $collegeId, $ayId, $semester)
{
    $default = [
        'programs_total' => 0,
        'sections_total' => 0,
        'offerings_total' => 0,
        'scheduled_offerings_total' => 0,
        'schedule_rows_total' => 0,
        'faculty_assignments_total' => 0,
    ];

    $sql = "
        SELECT
            COUNT(DISTINCT p.program_id) AS programs_total,
            COUNT(DISTINCT po.section_id) AS sections_total,
            COUNT(DISTINCT po.offering_id) AS offerings_total,
            COUNT(DISTINCT CASE WHEN cs.schedule_id IS NOT NULL THEN po.offering_id END) AS scheduled_offerings_total,
            COUNT(DISTINCT cs.schedule_id) AS schedule_rows_total,
            COUNT(DISTINCT fws.workload_id) AS faculty_assignments_total
        FROM tbl_prospectus_offering po
        INNER JOIN tbl_program p
            ON p.program_id = po.program_id
        LEFT JOIN tbl_class_schedule cs
            ON cs.offering_id = po.offering_id
        LEFT JOIN tbl_faculty_workload_sched fws
            ON fws.schedule_id = cs.schedule_id
           AND fws.ay_id = po.ay_id
           AND fws.semester = po.semester
        WHERE p.college_id = ?
          AND po.ay_id = ?
          AND po.semester = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('iii', $collegeId, $ayId, $semester);
    if (!$stmt->execute()) {
        $stmt->close();
        return $default;
    }

    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    return [
        'programs_total' => (int)($row['programs_total'] ?? 0),
        'sections_total' => (int)($row['sections_total'] ?? 0),
        'offerings_total' => (int)($row['offerings_total'] ?? 0),
        'scheduled_offerings_total' => (int)($row['scheduled_offerings_total'] ?? 0),
        'schedule_rows_total' => (int)($row['schedule_rows_total'] ?? 0),
        'faculty_assignments_total' => (int)($row['faculty_assignments_total'] ?? 0),
    ];
}

function synk_admin_fetch_campus_trend(mysqli $conn, $campuses, $maxTerms = 6)
{
    $trend = [
        'categories' => [],
        'series' => [],
    ];

    $terms = [];
    $termSql = "
        SELECT DISTINCT
            po.ay_id,
            po.semester,
            ay.ay AS ay_label
        FROM tbl_prospectus_offering po
        LEFT JOIN tbl_academic_years ay
            ON ay.ay_id = po.ay_id
        ORDER BY po.ay_id DESC, po.semester DESC
        LIMIT " . (int)$maxTerms;

    $termResult = $conn->query($termSql);
    if (!$termResult) {
        return $trend;
    }

    while ($row = $termResult->fetch_assoc()) {
        $termKey = (int)$row['ay_id'] . '-' . (int)$row['semester'];
        $terms[$termKey] = [
            'ay_id' => (int)$row['ay_id'],
            'semester' => (int)$row['semester'],
            'label' => trim((string)($row['ay_label'] ?? '')) . ' - ' . synk_semester_label((int)$row['semester']),
        ];
    }

    if (empty($terms)) {
        return $trend;
    }

    $terms = array_reverse($terms, true);
    $trend['categories'] = array_values(array_map(static function ($item) {
        return $item['label'];
    }, $terms));

    $counts = [];
    $countSql = "
        SELECT
            col.campus_id,
            po.ay_id,
            po.semester,
            COUNT(cs.schedule_id) AS scheduled_classes
        FROM tbl_prospectus_offering po
        INNER JOIN tbl_program p
            ON p.program_id = po.program_id
        INNER JOIN tbl_college col
            ON col.college_id = p.college_id
        LEFT JOIN tbl_class_schedule cs
            ON cs.offering_id = po.offering_id
        GROUP BY col.campus_id, po.ay_id, po.semester
    ";

    $countResult = $conn->query($countSql);
    if ($countResult) {
        while ($row = $countResult->fetch_assoc()) {
            $campusId = (int)$row['campus_id'];
            $termKey = (int)$row['ay_id'] . '-' . (int)$row['semester'];
            $counts[$campusId][$termKey] = (int)$row['scheduled_classes'];
        }
    }

    foreach ($campuses as $campusId => $campus) {
        $seriesData = [];
        $total = 0;

        foreach ($terms as $termKey => $termMeta) {
            $value = (int)($counts[$campusId][$termKey] ?? 0);
            $seriesData[] = $value;
            $total += $value;
        }

        if ($total <= 0) {
            continue;
        }

        $trend['series'][] = [
            'name' => trim((string)($campus['campus_name'] ?? 'Campus')),
            'data' => $seriesData,
        ];
    }

    return $trend;
}

function synk_admin_execute_statement(mysqli_stmt $stmt, $errorMessage)
{
    if (!$stmt->execute()) {
        throw new RuntimeException($errorMessage . ' ' . $stmt->error);
    }
}

$campuses = synk_admin_fetch_campuses($conn);
$colleges = synk_admin_fetch_colleges($conn);
$academicYears = synk_admin_fetch_academic_years($conn);
$campusTrend = synk_admin_fetch_campus_trend($conn, $campuses, 6);
$flash = $_SESSION['term_data_flash'] ?? null;
unset($_SESSION['term_data_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'clear_term_scope') {
    $postedCampusId = (int)($_POST['campus_id'] ?? 0);
    $postedCollegeId = (int)($_POST['college_id'] ?? 0);
    $postedAyId = (int)($_POST['ay_id'] ?? 0);
    $postedSemester = (int)($_POST['semester'] ?? 0);

    if (
        $csrfToken === '' ||
        empty($_POST['csrf_token']) ||
        !hash_equals($csrfToken, (string)$_POST['csrf_token'])
    ) {
        synk_admin_redirect_with_flash(
            $postedCampusId,
            $postedCollegeId,
            $postedAyId,
            $postedSemester,
            'danger',
            'Security validation failed. Reload the page and try again.'
        );
    }

    $scope = synk_admin_resolve_scope(
        $campuses,
        $colleges,
        $academicYears,
        $postedCampusId,
        $postedCollegeId,
        $postedAyId,
        $postedSemester
    );

    if (
        $postedCampusId <= 0 ||
        $postedCollegeId <= 0 ||
        $postedAyId <= 0 ||
        $postedSemester <= 0 ||
        !empty($scope['errors'])
    ) {
        synk_admin_redirect_with_flash(
            $postedCampusId,
            $postedCollegeId,
            $postedAyId,
            $postedSemester,
            'danger',
            !empty($scope['errors']) ? $scope['errors'][0] : 'Complete the campus, college, academic year, and semester first.'
        );
    }

    $previewBeforeReset = synk_admin_fetch_scope_preview($conn, $postedCollegeId, $postedAyId, $postedSemester);
    if (
        $previewBeforeReset['offerings_total'] <= 0 &&
        $previewBeforeReset['schedule_rows_total'] <= 0 &&
        $previewBeforeReset['faculty_assignments_total'] <= 0
    ) {
        synk_admin_redirect_with_flash(
            $postedCampusId,
            $postedCollegeId,
            $postedAyId,
            $postedSemester,
            'warning',
            'No operational term data was found for the selected college and term.'
        );
    }

    if ((string)($_POST['acknowledge_reset'] ?? '') !== '1') {
        synk_admin_redirect_with_flash(
            $postedCampusId,
            $postedCollegeId,
            $postedAyId,
            $postedSemester,
            'warning',
            'Confirm that you understand this action removes schedules and faculty assignments for the selected term.'
        );
    }

    $expectedPhrase = synk_admin_normalize_confirmation_phrase('CLEAR ' . synk_admin_college_confirmation_token($scope['college'] ?? []));
    $typedPhrase = synk_admin_normalize_confirmation_phrase($_POST['confirmation_phrase'] ?? '');

    if ($typedPhrase !== $expectedPhrase) {
        synk_admin_redirect_with_flash(
            $postedCampusId,
            $postedCollegeId,
            $postedAyId,
            $postedSemester,
            'warning',
            'Confirmation phrase did not match. Type ' . $expectedPhrase . ' exactly to continue.'
        );
    }

    $deletedAssignments = 0;
    $deletedSchedules = 0;

    $conn->begin_transaction();

    try {
        $deleteAssignments = $conn->prepare("
            DELETE fws
            FROM tbl_faculty_workload_sched fws
            INNER JOIN tbl_class_schedule cs
                ON cs.schedule_id = fws.schedule_id
            INNER JOIN tbl_prospectus_offering po
                ON po.offering_id = cs.offering_id
            INNER JOIN tbl_program p
                ON p.program_id = po.program_id
            WHERE p.college_id = ?
              AND po.ay_id = ?
              AND po.semester = ?
        ");

        if (!$deleteAssignments) {
            throw new RuntimeException('Failed to prepare faculty assignment reset query.');
        }

        $deleteAssignments->bind_param('iii', $postedCollegeId, $postedAyId, $postedSemester);
        synk_admin_execute_statement($deleteAssignments, 'Failed to clear faculty workload assignments.');
        $deletedAssignments = max(0, (int)$deleteAssignments->affected_rows);
        $deleteAssignments->close();

        $deleteSchedules = $conn->prepare("
            DELETE cs
            FROM tbl_class_schedule cs
            INNER JOIN tbl_prospectus_offering po
                ON po.offering_id = cs.offering_id
            INNER JOIN tbl_program p
                ON p.program_id = po.program_id
            WHERE p.college_id = ?
              AND po.ay_id = ?
              AND po.semester = ?
        ");

        if (!$deleteSchedules) {
            throw new RuntimeException('Failed to prepare class schedule reset query.');
        }

        $deleteSchedules->bind_param('iii', $postedCollegeId, $postedAyId, $postedSemester);
        synk_admin_execute_statement($deleteSchedules, 'Failed to clear class schedules.');
        $deletedSchedules = max(0, (int)$deleteSchedules->affected_rows);
        $deleteSchedules->close();

        $resetOfferingStatus = $conn->prepare("
            UPDATE tbl_prospectus_offering po
            INNER JOIN tbl_program p
                ON p.program_id = po.program_id
            SET po.status = 'pending'
            WHERE p.college_id = ?
              AND po.ay_id = ?
              AND po.semester = ?
        ");

        if (!$resetOfferingStatus) {
            throw new RuntimeException('Failed to prepare offering reset query.');
        }

        $resetOfferingStatus->bind_param('iii', $postedCollegeId, $postedAyId, $postedSemester);
        synk_admin_execute_statement($resetOfferingStatus, 'Failed to reset offering status.');
        $resetOfferingStatus->close();

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        synk_admin_redirect_with_flash(
            $postedCampusId,
            $postedCollegeId,
            $postedAyId,
            $postedSemester,
            'danger',
            'Reset failed. No records were changed.'
        );
    }

    $successMessage = sprintf(
        '%s was reset for %s. Removed %d schedule rows, removed %d faculty assignments, and set %d offerings back to pending.',
        trim((string)($scope['college']['college_name'] ?? 'Selected college')),
        trim((string)($scope['academic_year']['ay'] ?? 'selected academic year')) . ' - ' . trim((string)$scope['semester_label']),
        $deletedSchedules,
        $deletedAssignments,
        $previewBeforeReset['offerings_total']
    );

    synk_admin_redirect_with_flash(
        $postedCampusId,
        $postedCollegeId,
        $postedAyId,
        $postedSemester,
        'success',
        $successMessage
    );
}

$selectedCampusId = (int)($_GET['campus_id'] ?? 0);
$selectedCollegeId = (int)($_GET['college_id'] ?? 0);
$selectedAyId = (int)($_GET['ay_id'] ?? 0);
$selectedSemester = (int)($_GET['semester'] ?? 0);

$selectedScope = synk_admin_resolve_scope(
    $campuses,
    $colleges,
    $academicYears,
    $selectedCampusId,
    $selectedCollegeId,
    $selectedAyId,
    $selectedSemester
);

$hasValidScope = (
    $selectedCampusId > 0 &&
    $selectedCollegeId > 0 &&
    $selectedAyId > 0 &&
    $selectedSemester > 0 &&
    empty($selectedScope['errors'])
);

$scopePreview = $hasValidScope
    ? synk_admin_fetch_scope_preview($conn, $selectedCollegeId, $selectedAyId, $selectedSemester)
    : [
        'programs_total' => 0,
        'sections_total' => 0,
        'offerings_total' => 0,
        'scheduled_offerings_total' => 0,
        'schedule_rows_total' => 0,
        'faculty_assignments_total' => 0,
    ];

$expectedConfirmationPhrase = $hasValidScope
    ? synk_admin_normalize_confirmation_phrase('CLEAR ' . synk_admin_college_confirmation_token($selectedScope['college'] ?? []))
    : '';

$hasResettableScope = $hasValidScope && $scopePreview['offerings_total'] > 0;
$hasTrendData = !empty($campusTrend['categories']) && !empty($campusTrend['series']);
?>
<!DOCTYPE html>
<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />
    <title>Term Data Reset | Synk</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
      .maintenance-hero {
        border: 1px solid #e6eaf1;
        border-radius: 1rem;
        background:
          radial-gradient(circle at top right, rgba(105, 108, 255, 0.16), transparent 36%),
          linear-gradient(135deg, #ffffff 0%, #f7f9fc 100%);
      }

      .maintenance-hero .stat-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: rgba(105, 108, 255, 0.08);
        color: #5b63d3;
        font-size: 0.78rem;
        font-weight: 600;
      }

      .reset-card,
      .trend-card,
      .preview-card {
        border: 1px solid #e7ebf2;
        border-radius: 1rem;
        box-shadow: 0 10px 24px rgba(39, 52, 79, 0.05);
      }

      .preview-metric {
        border: 1px solid #edf1f7;
        border-radius: 0.9rem;
        background: #fbfcfe;
        padding: 1rem;
        height: 100%;
      }

      .preview-metric .metric-value {
        font-size: 1.7rem;
        font-weight: 700;
        line-height: 1;
        color: #364152;
      }

      .impact-table td,
      .impact-table th {
        vertical-align: middle;
      }

      .confirmation-panel {
        border: 1px dashed #d5d9e3;
        border-radius: 0.95rem;
        background: #fafbfe;
        padding: 1rem;
      }

      .confirmation-code {
        display: inline-flex;
        padding: 0.38rem 0.7rem;
        border-radius: 0.6rem;
        background: #f2f4ff;
        color: #4b57d4;
        font-weight: 700;
        letter-spacing: 0.03em;
      }

      .empty-state {
        min-height: 320px;
        border: 1px dashed #d7ddea;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 2rem;
        background: linear-gradient(180deg, rgba(248, 250, 255, 0.95), rgba(255, 255, 255, 0.96));
      }

      .scope-summary {
        border: 1px solid #eef1f6;
        border-radius: 0.9rem;
        background: #fbfcff;
        padding: 1rem;
      }

      .scope-summary-label {
        display: block;
        font-size: 0.73rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #8a94a6;
        margin-bottom: 0.22rem;
      }

      .scope-summary-value {
        color: #364152;
        font-weight: 600;
      }

      .warning-list {
        padding-left: 1rem;
        margin-bottom: 0;
      }

      #campusTrendChart {
        min-height: 340px;
      }
    </style>
  </head>

  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <?php include 'sidebar.php'; ?>

        <div class="layout-page">
          <?php include 'navbar.php'; ?>

          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="card maintenance-hero mb-4">
                <div class="card-body p-4 p-lg-5">
                  <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                      <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="stat-chip"><i class="bx bx-refresh"></i> Term Data Reset</span>
                        <span class="stat-chip"><i class="bx bx-line-chart"></i> Best trend: scheduled classes per campus</span>
                      </div>
                      <h4 class="fw-bold mb-2">Reset one college term without touching setup records</h4>
                      <p class="text-muted mb-3">
                        This page clears generated scheduling data for a selected college, academic year, and semester.
                        It removes class schedules and faculty workload assignments, then resets scoped offerings back to
                        <strong>pending</strong> so the scheduler can start that term again.
                      </p>
                      <div class="alert alert-warning mb-0">
                        Master data is preserved: campuses, colleges, programs, curriculum structures, sections, rooms,
                        and faculty records are not deleted here.
                      </div>
                    </div>
                    <div class="col-lg-4">
                      <div class="scope-summary h-100">
                        <span class="scope-summary-label">Current Academic Term</span>
                        <div class="scope-summary-value mb-3"><?php echo $currentTermTextEscaped; ?></div>

                        <span class="scope-summary-label">Recommended Trend</span>
                        <div class="scope-summary-value mb-3">Scheduled classes by campus across recent terms</div>

                        <span class="scope-summary-label">Why this graph</span>
                        <div class="text-muted small">
                          It shows which campuses are carrying the most scheduling load over time, so spikes, drops, and
                          unusually light terms are visible before you clear a college scope.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <?php if ($flash): ?>
                <div class="alert alert-<?php echo synk_admin_h($flash['type'] ?? 'info'); ?> alert-dismissible fade show" role="alert">
                  <?php echo synk_admin_h($flash['message'] ?? ''); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if (!empty($selectedScope['errors'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <?php echo synk_admin_h($selectedScope['errors'][0]); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <div class="card preview-card mb-4">
                <div class="card-body">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                      <h5 class="mb-1">Select Reset Scope</h5>
                      <p class="text-muted mb-0">
                        Choose the campus, college, academic year, and semester first. The preview below updates after you apply the scope.
                      </p>
                    </div>
                    <span class="badge bg-label-primary"><?php echo $currentTermTextEscaped; ?></span>
                  </div>

                  <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                      <label for="campusSelect" class="form-label fw-semibold">Campus</label>
                      <select class="form-select" id="campusSelect" name="campus_id" required>
                        <option value="">Select campus</option>
                        <?php foreach ($campuses as $campus): ?>
                          <?php
                            $campusId = (int)$campus['campus_id'];
                            $campusLabel = trim((string)$campus['campus_name']);
                            if ((string)($campus['status'] ?? '') !== 'active') {
                                $campusLabel .= ' (Inactive)';
                            }
                          ?>
                          <option value="<?php echo $campusId; ?>"<?php echo $selectedCampusId === $campusId ? ' selected' : ''; ?>>
                            <?php echo synk_admin_h($campusLabel); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-md-3">
                      <label for="collegeSelect" class="form-label fw-semibold">College</label>
                      <select class="form-select" id="collegeSelect" name="college_id" required>
                        <option value="">Select college</option>
                        <?php foreach ($colleges as $college): ?>
                          <?php
                            $collegeId = (int)$college['college_id'];
                            $collegeLabel = synk_admin_college_display_name($college);
                            if ((string)($college['status'] ?? '') !== 'active') {
                                $collegeLabel .= ' (Inactive)';
                            }
                          ?>
                          <option
                            value="<?php echo $collegeId; ?>"
                            data-campus-id="<?php echo (int)$college['campus_id']; ?>"
                            <?php echo $selectedCollegeId === $collegeId ? ' selected' : ''; ?>
                          >
                            <?php echo synk_admin_h($collegeLabel); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-md-3">
                      <label for="aySelect" class="form-label fw-semibold">Academic Year</label>
                      <select class="form-select" id="aySelect" name="ay_id" required>
                        <option value="">Select academic year</option>
                        <?php foreach ($academicYears as $ay): ?>
                          <?php
                            $ayId = (int)$ay['ay_id'];
                            $ayLabel = trim((string)$ay['ay']);
                            if ((string)($ay['status'] ?? '') !== 'active') {
                                $ayLabel .= ' (Inactive)';
                            }
                          ?>
                          <option value="<?php echo $ayId; ?>"<?php echo $selectedAyId === $ayId ? ' selected' : ''; ?>>
                            <?php echo synk_admin_h($ayLabel); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-md-2">
                      <label for="semesterSelect" class="form-label fw-semibold">Semester</label>
                      <select class="form-select" id="semesterSelect" name="semester" required>
                        <option value="">Select semester</option>
                        <?php foreach (synk_admin_semester_options() as $semesterValue => $semesterLabel): ?>
                          <option value="<?php echo (int)$semesterValue; ?>"<?php echo $selectedSemester === (int)$semesterValue ? ' selected' : ''; ?>>
                            <?php echo synk_admin_h($semesterLabel); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-md-1 d-grid">
                      <button class="btn btn-primary" type="submit">Apply</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="row g-4 mb-4">
                <div class="col-xl-8">
                  <div class="card trend-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-start">
                      <div>
                        <h5 class="mb-1">Campus Trend Overview</h5>
                        <small class="text-muted">
                          Best maintenance graph: smooth lines for scheduled classes per campus across recent academic terms.
                        </small>
                      </div>
                      <span class="badge bg-label-info">Recent 6 terms</span>
                    </div>
                    <div class="card-body">
                      <?php if ($hasTrendData): ?>
                        <div id="campusTrendChart"></div>
                      <?php else: ?>
                        <div class="empty-state">
                          <div>
                            <h6 class="fw-semibold mb-2">No scheduled-class trend available yet</h6>
                            <p class="text-muted mb-0">
                              Once campuses start producing class schedule rows, each campus line will appear here with a term-by-term history.
                            </p>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <div class="col-xl-4">
                  <div class="card reset-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">What This Reset Changes</h5>
                      <small class="text-muted">Use this before starting a fresh scheduling pass for one college term.</small>
                    </div>
                    <div class="card-body">
                      <ul class="warning-list text-muted">
                        <li>Deletes faculty workload assignments connected to the selected college and term.</li>
                        <li>Deletes class schedule rows for that same college and term.</li>
                        <li>Sets scoped generated class offerings back to <strong>pending</strong> so they can be scheduled again.</li>
                        <li>Does not delete the campus, college, program, faculty, section, room, or curriculum setup records.</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row g-4">
                <div class="col-xl-7">
                  <div class="card preview-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">Scope Preview</h5>
                      <small class="text-muted">
                        Review the exact academic scope before clearing anything.
                      </small>
                    </div>
                    <div class="card-body">
                      <?php if ($hasValidScope): ?>
                        <div class="row g-3 mb-4">
                          <div class="col-md-6">
                            <div class="scope-summary">
                              <span class="scope-summary-label">Campus</span>
                              <div class="scope-summary-value"><?php echo synk_admin_h($selectedScope['campus']['campus_name'] ?? ''); ?></div>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="scope-summary">
                              <span class="scope-summary-label">College</span>
                              <div class="scope-summary-value">
                                <?php echo synk_admin_h(synk_admin_college_display_name($selectedScope['college'] ?? [])); ?>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="scope-summary">
                              <span class="scope-summary-label">Academic Year</span>
                              <div class="scope-summary-value"><?php echo synk_admin_h($selectedScope['academic_year']['ay'] ?? ''); ?></div>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="scope-summary">
                              <span class="scope-summary-label">Semester</span>
                              <div class="scope-summary-value"><?php echo synk_admin_h($selectedScope['semester_label'] ?? ''); ?></div>
                            </div>
                          </div>
                        </div>

                        <div class="row g-3 mb-4">
                          <div class="col-sm-6 col-lg-3">
                            <div class="preview-metric">
                              <div class="text-muted small mb-2">Programs</div>
                              <div class="metric-value"><?php echo (int)$scopePreview['programs_total']; ?></div>
                            </div>
                          </div>
                          <div class="col-sm-6 col-lg-3">
                            <div class="preview-metric">
                              <div class="text-muted small mb-2">Sections</div>
                              <div class="metric-value"><?php echo (int)$scopePreview['sections_total']; ?></div>
                            </div>
                          </div>
                          <div class="col-sm-6 col-lg-3">
                            <div class="preview-metric">
                              <div class="text-muted small mb-2">Offerings</div>
                              <div class="metric-value"><?php echo (int)$scopePreview['offerings_total']; ?></div>
                            </div>
                          </div>
                          <div class="col-sm-6 col-lg-3">
                            <div class="preview-metric">
                              <div class="text-muted small mb-2">Scheduled Offerings</div>
                              <div class="metric-value"><?php echo (int)$scopePreview['scheduled_offerings_total']; ?></div>
                            </div>
                          </div>
                        </div>

                        <div class="table-responsive">
                          <table class="table table-sm align-middle impact-table mb-0">
                            <thead class="table-light">
                              <tr>
                                <th>Data Group</th>
                                <th class="text-end">Current Count</th>
                                <th class="text-end">After Reset</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td>Offerings in scope</td>
                                <td class="text-end"><?php echo (int)$scopePreview['offerings_total']; ?></td>
                                <td class="text-end">Remain, status becomes <strong>pending</strong></td>
                              </tr>
                              <tr>
                                <td>Scheduled offerings</td>
                                <td class="text-end"><?php echo (int)$scopePreview['scheduled_offerings_total']; ?></td>
                                <td class="text-end">0 scheduled</td>
                              </tr>
                              <tr>
                                <td>Class schedule rows</td>
                                <td class="text-end"><?php echo (int)$scopePreview['schedule_rows_total']; ?></td>
                                <td class="text-end">0 rows</td>
                              </tr>
                              <tr>
                                <td>Faculty workload assignments</td>
                                <td class="text-end"><?php echo (int)$scopePreview['faculty_assignments_total']; ?></td>
                                <td class="text-end">0 assignments</td>
                              </tr>
                              <tr>
                                <td>Sections affected</td>
                                <td class="text-end"><?php echo (int)$scopePreview['sections_total']; ?></td>
                                <td class="text-end">Remain preserved</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>

                        <?php if (!$hasResettableScope): ?>
                          <div class="alert alert-warning mt-4 mb-0">
                            No operational offerings were found in this scope, so there is nothing to reset.
                          </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <div class="empty-state">
                          <div>
                            <h6 class="fw-semibold mb-2">Choose a college term to preview</h6>
                            <p class="text-muted mb-0">
                              Apply a campus, college, academic year, and semester above. The preview will then show the exact records that will be affected.
                            </p>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <div class="col-xl-5">
                  <div class="card reset-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">Run Reset</h5>
                      <small class="text-muted">
                        This action is scoped only to the selected college and academic term.
                      </small>
                    </div>
                    <div class="card-body">
                      <?php if ($hasResettableScope): ?>
                        <div class="alert alert-danger">
                          Use this only when you want to restart scheduling for
                          <strong><?php echo synk_admin_h($selectedScope['college']['college_name'] ?? ''); ?></strong>
                          in
                          <strong><?php echo synk_admin_h(($selectedScope['academic_year']['ay'] ?? '') . ' - ' . ($selectedScope['semester_label'] ?? '')); ?></strong>.
                        </div>

                        <form method="POST" id="termResetForm">
                          <input type="hidden" name="action" value="clear_term_scope" />
                          <input type="hidden" name="csrf_token" value="<?php echo synk_admin_h($csrfToken); ?>" />
                          <input type="hidden" name="campus_id" value="<?php echo (int)$selectedCampusId; ?>" />
                          <input type="hidden" name="college_id" value="<?php echo (int)$selectedCollegeId; ?>" />
                          <input type="hidden" name="ay_id" value="<?php echo (int)$selectedAyId; ?>" />
                          <input type="hidden" name="semester" value="<?php echo (int)$selectedSemester; ?>" />

                          <div class="confirmation-panel mb-3">
                            <div class="fw-semibold mb-2">Required confirmation phrase</div>
                            <div class="confirmation-code mb-2"><?php echo synk_admin_h($expectedConfirmationPhrase); ?></div>
                            <div class="text-muted small">
                              Type the phrase exactly to confirm that you want to clear schedules and workload assignments for this scope.
                            </div>
                          </div>

                          <div class="mb-3">
                            <label for="confirmationPhrase" class="form-label fw-semibold">Type confirmation phrase</label>
                            <input
                              type="text"
                              class="form-control"
                              id="confirmationPhrase"
                              name="confirmation_phrase"
                              autocomplete="off"
                              placeholder="<?php echo synk_admin_h($expectedConfirmationPhrase); ?>"
                              data-expected="<?php echo synk_admin_h($expectedConfirmationPhrase); ?>"
                              required
                            />
                          </div>

                          <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="1" id="acknowledgeReset" name="acknowledge_reset" />
                            <label class="form-check-label" for="acknowledgeReset">
                              I understand that this removes class schedules and faculty workload assignments for the selected term,
                              while keeping the college setup and curriculum records.
                            </label>
                          </div>

                          <button
                            type="submit"
                            class="btn btn-danger w-100"
                            id="runResetButton"
                            disabled
                          >
                            Clear Selected College Term Data
                          </button>
                        </form>
                      <?php else: ?>
                        <div class="empty-state">
                          <div>
                            <h6 class="fw-semibold mb-2">Reset is locked until a valid scope is loaded</h6>
                            <p class="text-muted mb-0">
                              Apply a valid campus, college, academic year, and semester first. If no offerings exist in that scope, reset remains unavailable.
                            </p>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php include '../footer.php'; ?>

            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>

      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var campusSelect = document.getElementById('campusSelect');
        var collegeSelect = document.getElementById('collegeSelect');

        if (campusSelect && collegeSelect) {
          var collegeOptions = Array.prototype.slice.call(collegeSelect.querySelectorAll('option[data-campus-id]'));

          function syncCollegeOptions() {
            var selectedCampusId = campusSelect.value;

            collegeOptions.forEach(function (option) {
              var isVisible = !selectedCampusId || option.getAttribute('data-campus-id') === selectedCampusId;
              option.hidden = !isVisible;
            });

            var selectedOption = collegeSelect.options[collegeSelect.selectedIndex];
            if (
              selectedOption &&
              selectedOption.value &&
              selectedCampusId &&
              selectedOption.getAttribute('data-campus-id') !== selectedCampusId
            ) {
              collegeSelect.value = '';
            }
          }

          campusSelect.addEventListener('change', syncCollegeOptions);
          syncCollegeOptions();
        }

        var phraseInput = document.getElementById('confirmationPhrase');
        var acknowledgeReset = document.getElementById('acknowledgeReset');
        var runResetButton = document.getElementById('runResetButton');

        if (phraseInput && acknowledgeReset && runResetButton) {
          function normalizePhrase(value) {
            return String(value || '')
              .toUpperCase()
              .trim()
              .replace(/\s+/g, ' ');
          }

          function syncResetButton() {
            var expected = normalizePhrase(phraseInput.getAttribute('data-expected'));
            var typed = normalizePhrase(phraseInput.value);
            runResetButton.disabled = !(acknowledgeReset.checked && typed === expected);
          }

          phraseInput.addEventListener('input', syncResetButton);
          acknowledgeReset.addEventListener('change', syncResetButton);
          syncResetButton();
        }

        var trendCategories = <?php echo json_encode($campusTrend['categories']); ?>;
        var trendSeries = <?php echo json_encode($campusTrend['series']); ?>;
        var trendElement = document.getElementById('campusTrendChart');
        var hasApexCharts = typeof ApexCharts !== 'undefined';

        if (trendElement && hasApexCharts && trendCategories.length && trendSeries.length) {
          var colorPalette = ['#696cff', '#03c3ec', '#71dd37', '#ffab00', '#ff6f91', '#6f42c1', '#17a2b8', '#8898aa'];

          var trendOptions = {
            chart: {
              type: 'line',
              height: 360,
              toolbar: { show: false },
              zoom: { enabled: false }
            },
            series: trendSeries,
            colors: colorPalette,
            stroke: {
              curve: 'smooth',
              width: 3
            },
            markers: {
              size: 4,
              hover: {
                sizeOffset: 2
              }
            },
            dataLabels: {
              enabled: false
            },
            fill: {
              type: 'gradient',
              gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.18,
                opacityTo: 0.04,
                stops: [0, 95, 100]
              }
            },
            legend: {
              position: 'top',
              horizontalAlign: 'left'
            },
            tooltip: {
              shared: true,
              intersect: false
            },
            grid: {
              borderColor: '#eef1f6',
              strokeDashArray: 4
            },
            xaxis: {
              categories: trendCategories,
              labels: {
                rotate: -18,
                style: {
                  colors: '#7b8598',
                  fontSize: '12px'
                }
              }
            },
            yaxis: {
              min: 0,
              forceNiceScale: true,
              labels: {
                style: {
                  colors: '#7b8598'
                }
              },
              title: {
                text: 'Scheduled Classes',
                style: {
                  color: '#7b8598'
                }
              }
            }
          };

          new ApexCharts(trendElement, trendOptions).render();
        }
      });
    </script>
  </body>
</html>
