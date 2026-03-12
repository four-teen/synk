<?php
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';
require_once '../backend/offering_scope_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$collegeId = (int)($_SESSION['college_id'] ?? 0);
$currentTerm = synk_fetch_current_academic_term($conn);

$prospectus_id = (string)($_GET['prospectus_id'] ?? '');
$ay_id = (string)($_GET['ay_id'] ?? ($currentTerm['ay_id'] ?? ''));
$semester = (string)($_GET['semester'] ?? ($currentTerm['semester'] ?? ''));
$doPrint = isset($_GET['print']) && $_GET['print'] === '1';

function semesterLabel($sem) {
    switch ((string)$sem) {
        case '1':
            return 'FIRST SEMESTER';
        case '2':
            return 'SECOND SEMESTER';
        case '3':
            return 'MIDYEAR';
        default:
            return 'SEMESTER';
    }
}

function normalizeCampusLabel($campusName) {
    $label = strtoupper(trim((string)$campusName));
    $label = preg_replace('/\s+CAMPUS$/i', '', $label ?? '');
    $label = trim((string)$label);

    return $label !== '' ? $label . ' CAMPUS' : 'CAMPUS';
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$campusLabel = 'CAMPUS';
if ($collegeId > 0) {
    $campusStmt = $conn->prepare("
        SELECT tc.campus_name
        FROM tbl_college col
        INNER JOIN tbl_campus tc ON tc.campus_id = col.campus_id
        WHERE col.college_id = ?
        LIMIT 1
    ");

    if ($campusStmt instanceof mysqli_stmt) {
        $campusStmt->bind_param("i", $collegeId);
        $campusStmt->execute();
        $campusRes = $campusStmt->get_result();
        $campusRow = $campusRes ? $campusRes->fetch_assoc() : null;

        if (is_array($campusRow)) {
            $campusLabel = normalizeCampusLabel($campusRow['campus_name'] ?? '');
        }

        $campusStmt->close();
    }
}

$ayLabel = '';
if ($ay_id !== '') {
    $ayStmt = $conn->prepare("
        SELECT ay
        FROM tbl_academic_years
        WHERE ay_id = ?
        LIMIT 1
    ");

    if ($ayStmt instanceof mysqli_stmt) {
        $ayIdInt = (int)$ay_id;
        $ayStmt->bind_param("i", $ayIdInt);
        $ayStmt->execute();
        $ayRes = $ayStmt->get_result();
        $ayRow = $ayRes ? $ayRes->fetch_assoc() : null;

        if (is_array($ayRow)) {
            $ayLabel = (string)($ayRow['ay'] ?? '');
        }

        $ayStmt->close();
    }
}

$prospectusOptions = [];
if ($collegeId > 0) {
    $prospectusQuery = "
        SELECT
            h.prospectus_id,
            p.program_code,
            p.program_name,
            h.effective_sy
        FROM tbl_prospectus_header h
        INNER JOIN tbl_program p ON p.program_id = h.program_id
        WHERE p.college_id = ?
        ORDER BY p.program_code ASC, h.effective_sy DESC
    ";
    $prospectusStmt = $conn->prepare($prospectusQuery);

    if ($prospectusStmt instanceof mysqli_stmt) {
        $prospectusStmt->bind_param("i", $collegeId);
        $prospectusStmt->execute();
        $prospectusRes = $prospectusStmt->get_result();

        while ($prospectusRes && ($row = $prospectusRes->fetch_assoc())) {
            $prospectusOptions[] = $row;
        }

        $prospectusStmt->close();
    }
}

$selectedProgramLabel = '';
if ($prospectus_id !== '') {
    foreach ($prospectusOptions as $option) {
        if ((string)($option['prospectus_id'] ?? '') !== $prospectus_id) {
            continue;
        }

        $programCode = trim((string)($option['program_code'] ?? ''));
        $programName = trim((string)($option['program_name'] ?? ''));
        $selectedProgramLabel = trim($programCode !== '' && $programName !== ''
            ? $programCode . ' - ' . $programName
            : ($programCode !== '' ? $programCode : $programName));
        break;
    }
}

$hasFilters = $prospectus_id !== '' && $ay_id !== '' && $semester !== '';
$courses = [];

if ($hasFilters && $collegeId > 0) {
    $liveOfferingJoins = synk_live_offering_join_sql('o', 'sec', 'ps', 'pys', 'ph');

    $sql = "
        SELECT
            sm.sub_code,
            sm.sub_description,
            sec.section_name,
            cs.days_json,
            cs.time_start,
            cs.time_end,
            r.room_name
        FROM tbl_prospectus_offering o
        {$liveOfferingJoins}
        INNER JOIN tbl_program p ON p.program_id = o.program_id
        INNER JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
        LEFT JOIN tbl_class_schedule cs ON cs.offering_id = o.offering_id
        LEFT JOIN tbl_rooms r ON r.room_id = cs.room_id
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND p.college_id = ?
        ORDER BY sm.sub_code, sec.section_name
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $prospectusInt = (int)$prospectus_id;
        $ayInt = (int)$ay_id;
        $semesterInt = (int)$semester;
        $stmt->bind_param("iiii", $prospectusInt, $ayInt, $semesterInt, $collegeId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($res && ($row = $res->fetch_assoc())) {
            $code = (string)($row['sub_code'] ?? '');
            if ($code === '') {
                continue;
            }

            if (!isset($courses[$code])) {
                $courses[$code] = [
                    'desc' => (string)($row['sub_description'] ?? ''),
                    'rows' => []
                ];
            }

            $courses[$code]['rows'][] = $row;
        }

        $stmt->close();
    }
}
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

  <title>Faculty Workload Report | Synk</title>

  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" type="text/css" href="custom_css.css" />

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
    .report-page-title {
      color: #344760;
    }

    .report-filter-card {
      border: 1px solid #e5ecf6;
      border-radius: 1rem;
      box-shadow: 0 18px 40px rgba(31, 45, 61, 0.06);
      background: linear-gradient(180deg, #fcfdff 0%, #f6f9fd 100%);
    }

    .report-filter-note {
      color: #74849a;
      font-size: 0.85rem;
    }

    .report-filter-actions {
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
      height: 100%;
      justify-content: flex-end;
    }

    .report-panel {
      position: relative;
      border: 1px solid #e5ecf6;
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 18px 40px rgba(31, 45, 61, 0.06);
      overflow: hidden;
    }

    .report-panel-body {
      padding: 1.25rem;
    }

    .report-loading-overlay {
      position: absolute;
      inset: 0;
      z-index: 6;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      background: rgba(248, 251, 255, 0.9);
      backdrop-filter: blur(4px);
    }

    .report-loading-overlay.is-visible {
      display: flex;
    }

    .report-loading-card {
      width: min(420px, 100%);
      padding: 1.3rem 1.4rem;
      border: 1px solid #d7e3f1;
      border-radius: 1.1rem;
      background: #fff;
      box-shadow: 0 20px 50px rgba(31, 45, 61, 0.14);
    }

    .report-loading-topline {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.85rem;
    }

    .report-loading-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.38rem 0.72rem;
      border-radius: 999px;
      background: #eef4ff;
      color: #3f5fb4;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .report-loading-percent {
      font-size: 1.6rem;
      font-weight: 800;
      color: #2d4260;
      line-height: 1;
    }

    .report-loading-title {
      font-size: 1rem;
      font-weight: 700;
      color: #2f435f;
      margin-bottom: 0.2rem;
    }

    .report-loading-stage {
      color: #73839a;
      font-size: 0.86rem;
      margin-bottom: 0.9rem;
    }

    .report-progress-track {
      height: 0.72rem;
      border-radius: 999px;
      background: #e8eef8;
      overflow: hidden;
      margin-bottom: 0.75rem;
    }

    .report-progress-fill {
      width: 0%;
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, #696cff 0%, #51b0ff 50%, #20c997 100%);
      transition: width 0.15s ease;
    }

    .report-loading-caption {
      color: #8391a6;
      font-size: 0.76rem;
    }

    .report-empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 320px;
      padding: 2rem 1.5rem;
      text-align: center;
      color: #708198;
      background: linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
    }

    .report-empty-state i {
      font-size: 2.3rem;
      color: #9ab0d0;
      margin-bottom: 0.85rem;
    }

    .print-area {
      background: #fff;
    }

    .print-header {
      display: none;
      gap: 14px;
      align-items: center;
      border-bottom: 2px solid #000;
      padding-bottom: 6px;
      margin-bottom: 8px;
    }

    .print-title {
      flex: 1;
      text-align: center;
      line-height: 1.08;
    }

    .print-title > div {
      margin: 0;
    }

    .print-title .uni {
      font-weight: 800;
      font-size: 17px;
      line-height: 1.05;
    }

    .print-title .main {
      font-weight: 900;
      margin-top: 3px;
      line-height: 1.05;
    }

    .print-title .program-line,
    .print-title .campus-line,
    .print-title .term-line {
      margin-top: 2px;
    }

    .print-title .program-line {
      font-weight: 700;
      font-size: 13px;
      line-height: 1.08;
      text-transform: uppercase;
    }

    .group-title {
      background: #eaf2fb;
      font-weight: 800;
      text-transform: uppercase;
      border: 1px solid #000;
      border-bottom: none;
      padding: 6px 8px;
      color: #334760;
    }

    .report-print-sheet {
      width: 100%;
    }

    .report-table-wrap {
      overflow-x: auto;
    }

    table.report-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }

    table.report-table th,
    table.report-table td {
      border: 1px solid #000;
      padding: 6px 8px;
    }

    table.report-table th {
      background: #f7f7f7;
      text-align: center;
    }

    table.report-table thead {
      display: table-header-group;
    }

    table.report-table tfoot {
      display: table-footer-group;
    }

    .course-code {
      font-weight: 800;
      width: 140px;
    }

    .course-desc {
      font-weight: 700;
    }

    .indent-row td {
      border-top: none;
    }

    .print-preview-mode {
      background: #eef2f7;
    }

    .print-preview-mode #layout-menu,
    .print-preview-mode #layout-navbar,
    .print-preview-mode .layout-overlay,
    .print-preview-mode .content-backdrop,
    .print-preview-mode footer,
    .print-preview-mode .footer,
    .print-preview-mode .navbar,
    .print-preview-mode .layout-menu-toggle,
    .print-preview-mode .menu-toggle,
    .print-preview-mode .no-print {
      display: none !important;
    }

    .print-preview-mode .layout-wrapper,
    .print-preview-mode .layout-container,
    .print-preview-mode .layout-page,
    .print-preview-mode .content-wrapper {
      display: block !important;
      width: 100% !important;
      max-width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      background: transparent !important;
    }

    .print-preview-mode .container-xxl {
      max-width: 210mm !important;
      width: 210mm !important;
      margin: 0 auto !important;
      padding: 10mm 9mm !important;
      background: #fff !important;
    }

    .print-preview-mode .report-panel {
      border: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      overflow: visible !important;
      background: #fff !important;
    }

    .print-preview-mode .report-panel-body {
      padding: 0 !important;
    }

    .print-preview-mode .print-header {
      display: flex !important;
    }

    .print-preview-mode .report-table-wrap {
      overflow: visible !important;
    }

    @media print {
      html,
      body {
        background: #fff !important;
      }

      body {
        margin: 0 !important;
        padding: 0 !important;
      }

      .no-print {
        display: none !important;
      }

      .layout-menu,
      .layout-navbar,
      .layout-overlay,
      .content-backdrop,
      footer,
      .footer,
      .navbar,
      .menu-toggle {
        display: none !important;
      }

      .layout-wrapper,
      .layout-container,
      .layout-page,
      .content-wrapper,
      .container-xxl {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
      }

      .report-panel {
        border: 0;
        box-shadow: none;
        border-radius: 0;
        overflow: visible;
      }

      .report-panel-body {
        padding: 0;
      }

      .report-print-sheet,
      .print-area,
      .report-table-wrap {
        width: 100% !important;
        max-width: 100% !important;
      }

      .print-header {
        display: flex !important;
        page-break-after: avoid;
        break-after: avoid-page;
      }

      .report-table-wrap {
        overflow: visible;
      }

      .group-title {
        page-break-after: avoid;
        break-after: avoid-page;
      }

      table.report-table {
        page-break-inside: auto;
      }

      table.report-table tr,
      table.report-table td,
      table.report-table th {
        page-break-inside: avoid;
        break-inside: avoid;
      }

      @page {
        size: A4 portrait;
        margin: 10mm 9mm 10mm 9mm;
      }
    }
  </style>
</head>

<body class="<?= $doPrint ? 'print-preview-mode' : '' ?>">
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php include 'sidebar.php'; ?>

      <div class="layout-page">
        <?php include 'navbar.php'; ?>

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <div class="no-print mb-4">
              <h4 class="fw-bold mb-2 report-page-title">Faculty Workload Report</h4>
              <p class="report-filter-note mb-0">
                The report updates automatically when you change the prospectus, academic year, or semester.
              </p>
            </div>

            <div class="card report-filter-card no-print mb-4">
              <div class="card-body">
                <form method="GET" class="row g-3 align-items-end" id="facultyWorkloadFilterForm" novalidate>
                  <div class="col-lg-5 col-md-6">
                    <label class="form-label" for="prospectusSelect">Prospectus</label>
                    <select name="prospectus_id" id="prospectusSelect" class="form-select" required>
                      <option value="">Select prospectus...</option>
                      <?php foreach ($prospectusOptions as $option): ?>
                        <option
                          value="<?= h($option['prospectus_id']) ?>"
                          <?= $prospectus_id === (string)$option['prospectus_id'] ? 'selected' : '' ?>
                        >
                          <?= h($option['program_code']) ?> - <?= h($option['program_name']) ?> (SY <?= h($option['effective_sy']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-lg-3 col-md-3">
                    <label class="form-label" for="aySelect">Academic Year</label>
                    <select name="ay_id" id="aySelect" class="form-select" required>
                      <option value="">Select year...</option>
                      <?php
                      $ayQ = $conn->query("SELECT ay_id, ay FROM tbl_academic_years ORDER BY ay DESC");
                      while ($ayRow = $ayQ ? $ayQ->fetch_assoc() : null):
                        if (!$ayRow) {
                            break;
                        }
                      ?>
                        <option value="<?= h($ayRow['ay_id']) ?>" <?= $ay_id === (string)$ayRow['ay_id'] ? 'selected' : '' ?>>
                          <?= h($ayRow['ay']) ?>
                        </option>
                      <?php endwhile; ?>
                    </select>
                  </div>

                  <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="semesterSelect">Semester</label>
                    <select name="semester" id="semesterSelect" class="form-select" required>
                      <option value="">Select term...</option>
                      <option value="1" <?= $semester === '1' ? 'selected' : '' ?>>First</option>
                      <option value="2" <?= $semester === '2' ? 'selected' : '' ?>>Second</option>
                      <option value="3" <?= $semester === '3' ? 'selected' : '' ?>>Midyear</option>
                    </select>
                  </div>

                  <div class="col-lg-2 col-md-12">
                    <div class="report-filter-actions">
                      <?php if ($hasFilters): ?>
                        <a
                          class="btn btn-outline-primary"
                          href="?prospectus_id=<?= urlencode($prospectus_id) ?>&ay_id=<?= urlencode($ay_id) ?>&semester=<?= urlencode($semester) ?>&print=1"
                          target="_blank"
                          rel="noopener"
                        >
                          Print View
                        </a>
                      <?php else: ?>
                        <button class="btn btn-outline-secondary" type="button" disabled>Print View</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <div class="report-panel" id="facultyReportPanel">
              <div id="reportLoadingOverlay" class="report-loading-overlay" aria-hidden="true">
                <div class="report-loading-card">
                  <div class="report-loading-topline">
                    <div class="report-loading-badge">
                      <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                      <span>Loading</span>
                    </div>
                    <div class="report-loading-percent" id="reportLoadingPercent">0%</div>
                  </div>
                  <div class="report-loading-title">Preparing faculty workload report</div>
                  <div class="report-loading-stage" id="reportLoadingStage">Checking selected filters...</div>
                  <div class="report-progress-track">
                    <div class="report-progress-fill" id="reportLoadingBar"></div>
                  </div>
                  <div class="report-loading-caption">The report updates automatically after every filter change.</div>
                </div>
              </div>

              <div class="report-panel-body">
                <?php if (!$hasFilters): ?>
                  <div class="report-empty-state">
                    <i class="bx bx-filter-alt"></i>
                    <h5 class="mb-2">Select a prospectus to load the report</h5>
                    <p class="mb-0">
                      Academic year and semester are ready. Once you choose a prospectus, the report will load automatically.
                    </p>
                  </div>
                <?php else: ?>
                  <div class="print-area report-print-sheet">
                    <div class="print-header">
                      <div class="print-title">
                        <div class="uni">SULTAN KUDARAT STATE UNIVERSITY</div>
                        <div class="main">ALPHABETICAL LIST OF COURSES</div>
                        <?php if ($selectedProgramLabel !== ''): ?>
                          <div class="program-line"><?= h($selectedProgramLabel) ?></div>
                        <?php endif; ?>
                        <div class="campus-line"><?= h($campusLabel) ?></div>
                        <div class="term-line"><?= h(semesterLabel($semester)) ?>, AY <?= h($ayLabel) ?></div>
                      </div>
                    </div>

                    <div class="group-title">Program Offerings</div>

                    <?php if (!empty($courses)): ?>
                      <div class="report-table-wrap">
                        <table class="report-table">
                          <thead>
                            <tr>
                              <th>Course Code</th>
                              <th>Section</th>
                              <th>Class Schedule</th>
                              <th>Room</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($courses as $code => $data): ?>
                              <tr>
                                <td class="course-code"><?= h($code) ?></td>
                                <td colspan="3" class="course-desc"><?= h($data['desc']) ?></td>
                              </tr>
                              <?php foreach ($data['rows'] as $row): ?>
                                <?php
                                $decodedDays = json_decode((string)($row['days_json'] ?? ''), true);
                                $days = (is_array($decodedDays) && !empty($decodedDays)) ? implode('', $decodedDays) : '-';
                                $time = (!empty($row['time_start']) && !empty($row['time_end']))
                                    ? date("h:i A", strtotime((string)$row['time_start'])) . ' - ' . date("h:i A", strtotime((string)$row['time_end']))
                                    : '-';
                                $roomName = trim((string)($row['room_name'] ?? ''));
                                ?>
                                <tr class="indent-row">
                                  <td></td>
                                  <td><?= h($row['section_name'] ?? '-') ?></td>
                                  <td><?= h($days) ?> <?= h($time) ?></td>
                                  <td><?= h($roomName !== '' ? $roomName : '-') ?></td>
                                </tr>
                              <?php endforeach; ?>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <div class="report-empty-state" style="min-height: 260px;">
                        <i class="bx bx-folder-open"></i>
                        <h5 class="mb-2">No workload rows found for this filter</h5>
                        <p class="mb-0">
                          Try another prospectus, academic year, or semester to view available schedule data.
                        </p>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
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
  <script src="../assets/js/main.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var form = document.getElementById('facultyWorkloadFilterForm');
      var overlay = document.getElementById('reportLoadingOverlay');
      var loadingPercent = document.getElementById('reportLoadingPercent');
      var loadingBar = document.getElementById('reportLoadingBar');
      var loadingStage = document.getElementById('reportLoadingStage');
      var progressTimer = null;
      var navigateTimer = null;
      var progressValue = 0;
      var isNavigating = false;

      function setLoaderProgress(nextValue) {
        progressValue = Math.max(0, Math.min(99, nextValue));

        if (loadingPercent) {
          loadingPercent.textContent = progressValue + '%';
        }

        if (loadingBar) {
          loadingBar.style.width = progressValue + '%';
        }

        if (!loadingStage) {
          return;
        }

        if (progressValue < 20) {
          loadingStage.textContent = 'Checking selected filters...';
        } else if (progressValue < 45) {
          loadingStage.textContent = 'Loading prospectus offerings...';
        } else if (progressValue < 70) {
          loadingStage.textContent = 'Collecting section schedules and rooms...';
        } else if (progressValue < 90) {
          loadingStage.textContent = 'Building the printable report view...';
        } else {
          loadingStage.textContent = 'Finalizing report...';
        }
      }

      function stopLoaderProgress() {
        if (progressTimer) {
          window.clearInterval(progressTimer);
          progressTimer = null;
        }
      }

      function stopPendingNavigation() {
        if (navigateTimer) {
          window.clearTimeout(navigateTimer);
          navigateTimer = null;
        }
      }

      function hideLoader() {
        stopLoaderProgress();

        if (overlay) {
          overlay.classList.remove('is-visible');
          overlay.setAttribute('aria-hidden', 'true');
        }

        setLoaderProgress(0);
      }

      function showLoader() {
        if (!overlay) {
          return;
        }

        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        setLoaderProgress(6);
        stopLoaderProgress();

        progressTimer = window.setInterval(function () {
          if (progressValue >= 94) {
            stopLoaderProgress();
            return;
          }

          var increment = progressValue < 35 ? 6 : (progressValue < 70 ? 4 : 1);
          setLoaderProgress(progressValue + increment);
        }, 140);
      }

      function formHasCompleteFilters() {
        if (!form) {
          return false;
        }

        var prospectus = form.elements['prospectus_id'] ? form.elements['prospectus_id'].value.trim() : '';
        var ay = form.elements['ay_id'] ? form.elements['ay_id'].value.trim() : '';
        var sem = form.elements['semester'] ? form.elements['semester'].value.trim() : '';

        return prospectus !== '' && ay !== '' && sem !== '';
      }

      function navigateWithFilters() {
        if (!form || isNavigating || !formHasCompleteFilters()) {
          return;
        }

        var params = new URLSearchParams(new FormData(form));
        params.delete('print');

        var nextSearch = params.toString();
        var currentSearch = window.location.search.replace(/^\?/, '');

        if (nextSearch === currentSearch) {
          return;
        }

        isNavigating = true;
        showLoader();
        window.location.assign(window.location.pathname + '?' + nextSearch);
      }

      if (form) {
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          stopPendingNavigation();
          navigateWithFilters();
        });

        Array.prototype.forEach.call(form.querySelectorAll('select'), function (select) {
          select.addEventListener('change', function () {
            stopPendingNavigation();
            navigateTimer = window.setTimeout(function () {
              navigateWithFilters();
            }, 220);
          });
        });
      }

      window.addEventListener('pageshow', function () {
        isNavigating = false;
        stopPendingNavigation();
        hideLoader();
      });

      hideLoader();
    });
  </script>

  <?php if ($doPrint && $hasFilters): ?>
    <script>
      window.addEventListener('load', function () {
        window.print();
      });
    </script>
  <?php endif; ?>
</body>
</html>
