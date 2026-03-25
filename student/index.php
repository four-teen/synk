<?php
session_start();
ob_start();

include '../backend/db.php';
require_once '../backend/student_portal_helper.php';

synk_student_require_login();

$currentTerm = synk_fetch_current_academic_term($conn);
$campuses = synk_student_fetch_campuses($conn);

$selectedCampusId = synk_student_select_valid_id($campuses, (int)($_GET['campus_id'] ?? 0), 'campus_id');
$colleges = synk_student_fetch_colleges($conn, $selectedCampusId);
$selectedCollegeId = synk_student_select_valid_id($colleges, (int)($_GET['college_id'] ?? 0), 'college_id');

$summary = synk_student_fetch_dashboard_summary(
    $conn,
    (int)($currentTerm['ay_id'] ?? 0),
    (int)($currentTerm['semester'] ?? 0),
    $selectedCampusId,
    $selectedCollegeId
);

$programCards = synk_student_fetch_dashboard_program_cards(
    $conn,
    (int)($currentTerm['ay_id'] ?? 0),
    (int)($currentTerm['semester'] ?? 0),
    $selectedCampusId,
    $selectedCollegeId,
    9
);

$activeCampusName = 'All Campuses';
foreach ($campuses as $campusRow) {
    if ((int)($campusRow['campus_id'] ?? 0) === $selectedCampusId) {
        $activeCampusName = (string)($campusRow['campus_name'] ?? $activeCampusName);
        break;
    }
}

$activeCollegeName = 'All Colleges';
foreach ($colleges as $collegeRow) {
    if ((int)($collegeRow['college_id'] ?? 0) === $selectedCollegeId) {
        $activeCollegeName = (string)($collegeRow['college_name'] ?? $activeCollegeName);
        break;
    }
}

$termReady = (int)($currentTerm['ay_id'] ?? 0) > 0 && (int)($currentTerm['semester'] ?? 0) > 0;
$studentName = trim((string)($_SESSION['username'] ?? 'Student'));
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

    <title>Student Dashboard | Synk</title>

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
      .student-hero-card {
        border: 1px solid #dce5f1;
        border-radius: 22px;
        overflow: hidden;
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 55%, #eff9f1 100%);
        box-shadow: 0 18px 38px rgba(67, 89, 113, 0.08);
      }

      .student-hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        background: #eef4ff;
        color: #4f6595;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .student-filter-card,
      .student-kpi-card,
      .student-directory-card,
      .student-quick-card {
        border: 1px solid #dce5f1;
        border-radius: 18px;
        box-shadow: 0 12px 28px rgba(67, 89, 113, 0.06);
      }

      .student-kpi-card {
        height: 100%;
      }

      .student-kpi-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
      }

      .student-kpi-value {
        font-size: 1.8rem;
        font-weight: 800;
        color: #233446;
      }

      .student-quick-card {
        display: block;
        height: 100%;
        text-decoration: none !important;
        color: inherit;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
      }

      .student-quick-card:hover {
        transform: translateY(-2px);
        border-color: #d7b693;
        box-shadow: 0 16px 32px rgba(67, 89, 113, 0.1);
      }

      .student-directory-card {
        height: 100%;
        background: #fff;
      }

      .student-directory-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin-top: 0.85rem;
      }

      .student-directory-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: #f3f6fb;
        color: #54657e;
        font-size: 0.76rem;
        font-weight: 700;
      }

      .student-directory-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-top: 1rem;
      }

      .student-empty-state {
        border: 1px dashed #d7e0ec;
        border-radius: 18px;
        background: #fbfcff;
        padding: 2rem 1.2rem;
        text-align: center;
        color: #72839a;
      }

      .student-scope-note {
        color: #667b92;
        font-size: 0.9rem;
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
              <div class="card student-hero-card mb-4">
                <div class="card-body p-4">
                  <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                      <span class="student-hero-kicker">
                        <i class="bx bx-book-reader"></i>
                        Student Workspace
                      </span>
                      <h4 class="mt-3 mb-2">Welcome, <?php echo synk_student_h($studentName); ?>.</h4>
                      <p class="mb-3 text-muted">
                        Browse the active academic term, review curriculum prospectuses, and inspect
                        section schedules without entering the scheduler workspace.
                      </p>
                      <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-label-primary"><?php echo synk_student_h($currentTerm['term_text'] ?? 'Current academic term'); ?></span>
                        <span class="badge bg-label-info"><?php echo synk_student_h($activeCampusName); ?></span>
                        <span class="badge bg-label-success"><?php echo synk_student_h($activeCollegeName); ?></span>
                      </div>
                    </div>
                    <div class="col-lg-4 text-center">
                      <img
                        src="../assets/img/illustrations/man-with-laptop-light.png"
                        alt="Student Portal Overview"
                        class="img-fluid"
                        style="max-height: 150px;"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <div class="card student-filter-card mb-4">
                <div class="card-header">
                  <h5 class="mb-0">Scope Filter</h5>
                  <small class="student-scope-note">Narrow the student dashboard by campus and college.</small>
                </div>
                <div class="card-body">
                  <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-5">
                      <label for="campus_id" class="form-label fw-semibold">Campus</label>
                      <select id="campus_id" name="campus_id" class="form-select">
                        <option value="0">All Campuses</option>
                        <?php foreach ($campuses as $campusRow): ?>
                          <option value="<?php echo (int)($campusRow['campus_id'] ?? 0); ?>"<?php echo (int)($campusRow['campus_id'] ?? 0) === $selectedCampusId ? ' selected' : ''; ?>>
                            <?php echo synk_student_h(trim((string)($campusRow['campus_name'] ?? 'Campus')) . ' (' . trim((string)($campusRow['campus_code'] ?? '')) . ')'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-5">
                      <label for="college_id" class="form-label fw-semibold">College</label>
                      <select id="college_id" name="college_id" class="form-select">
                        <option value="0">All Colleges</option>
                        <?php foreach ($colleges as $collegeRow): ?>
                          <option value="<?php echo (int)($collegeRow['college_id'] ?? 0); ?>"<?php echo (int)($collegeRow['college_id'] ?? 0) === $selectedCollegeId ? ' selected' : ''; ?>>
                            <?php echo synk_student_h(trim((string)($collegeRow['college_name'] ?? 'College')) . ' (' . trim((string)($collegeRow['college_code'] ?? '')) . ')'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-2 d-grid gap-2">
                      <button type="submit" class="btn btn-primary">Apply</button>
                      <a href="index.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                  </form>
                </div>
              </div>

              <?php if (!$termReady): ?>
                <div class="alert alert-warning mb-4" role="alert">
                  The current academic term is not configured yet, so section and schedule counts may
                  remain empty until the administrator sets the active term.
                </div>
              <?php endif; ?>

              <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                  <div class="card student-kpi-card">
                    <div class="card-body">
                      <div class="student-kpi-icon bg-label-primary mb-3">
                        <i class="bx bx-book-content"></i>
                      </div>
                      <div class="student-kpi-value"><?php echo number_format((int)$summary['program_count']); ?></div>
                      <div class="fw-semibold">Programs</div>
                      <small class="text-muted">Programs available in this scope</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 col-xl-3">
                  <div class="card student-kpi-card">
                    <div class="card-body">
                      <div class="student-kpi-icon bg-label-success mb-3">
                        <i class="bx bx-copy-alt"></i>
                      </div>
                      <div class="student-kpi-value"><?php echo number_format((int)$summary['prospectus_count']); ?></div>
                      <div class="fw-semibold">Prospectus Versions</div>
                      <small class="text-muted">Curriculum versions ready for browsing</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 col-xl-3">
                  <div class="card student-kpi-card">
                    <div class="card-body">
                      <div class="student-kpi-icon bg-label-info mb-3">
                        <i class="bx bx-grid-alt"></i>
                      </div>
                      <div class="student-kpi-value"><?php echo number_format((int)$summary['section_count']); ?></div>
                      <div class="fw-semibold">Sections This Term</div>
                      <small class="text-muted">Sections with generated offerings</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 col-xl-3">
                  <div class="card student-kpi-card">
                    <div class="card-body">
                      <div class="student-kpi-icon bg-label-warning mb-3">
                        <i class="bx bx-time-five"></i>
                      </div>
                      <div class="student-kpi-value"><?php echo number_format((int)$summary['schedule_count']); ?></div>
                      <div class="fw-semibold">Scheduled Meetings</div>
                      <small class="text-muted">Class schedule blocks encoded for the term</small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row g-4 mb-4">
                <?php
                $prospectusScopeQuery = http_build_query([
                    'campus_id' => $selectedCampusId,
                    'college_id' => $selectedCollegeId,
                ]);
                $classProgramScopeQuery = $prospectusScopeQuery;
                ?>
                <div class="col-md-6">
                  <a href="prospectus.php<?php echo $prospectusScopeQuery !== '' ? '?' . $prospectusScopeQuery : ''; ?>" class="card student-quick-card">
                    <div class="card-body">
                      <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                          <span class="badge bg-label-success mb-2">Read Only</span>
                          <h5 class="mb-2">Open Prospectus Viewer</h5>
                          <p class="mb-0 text-muted">
                            Review the curriculum structure, subject sequence, and unit distribution
                            for each program.
                          </p>
                        </div>
                        <div class="student-kpi-icon bg-label-success">
                          <i class="bx bx-book-open"></i>
                        </div>
                      </div>
                    </div>
                  </a>
                </div>
                <div class="col-md-6">
                  <a href="class-program.php<?php echo $classProgramScopeQuery !== '' ? '?' . $classProgramScopeQuery : ''; ?>" class="card student-quick-card">
                    <div class="card-body">
                      <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                          <span class="badge bg-label-info mb-2">Current Term</span>
                          <h5 class="mb-2">Browse Class Programs</h5>
                          <p class="mb-0 text-muted">
                            Inspect section-level schedules with room, faculty, and day-time blocks in
                            one printable grid.
                          </p>
                        </div>
                        <div class="student-kpi-icon bg-label-info">
                          <i class="bx bx-table"></i>
                        </div>
                      </div>
                    </div>
                  </a>
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                  <h5 class="mb-1">Program Directory Snapshot</h5>
                  <small class="text-muted">Programs with available prospectus records or current term sections.</small>
                </div>
              </div>

              <?php if (empty($programCards)): ?>
                <div class="student-empty-state">
                  No student-ready program data was found for the selected dashboard scope.
                </div>
              <?php else: ?>
                <div class="row g-4">
                  <?php foreach ($programCards as $programRow): ?>
                    <?php
                    $prospectusLink = 'prospectus.php?' . http_build_query([
                        'campus_id' => (int)($programRow['campus_id'] ?? 0),
                        'college_id' => (int)($programRow['college_id'] ?? 0),
                        'program_id' => (int)($programRow['program_id'] ?? 0),
                    ]);
                    $classProgramLink = 'class-program.php?' . http_build_query([
                        'campus_id' => (int)($programRow['campus_id'] ?? 0),
                        'college_id' => (int)($programRow['college_id'] ?? 0),
                        'program_id' => (int)($programRow['program_id'] ?? 0),
                    ]);
                    ?>
                    <div class="col-lg-4 col-md-6">
                      <div class="card student-directory-card">
                        <div class="card-body">
                          <span class="badge bg-label-primary mb-2"><?php echo synk_student_h($programRow['college_code'] ?? 'College'); ?></span>
                          <h5 class="mb-2"><?php echo synk_student_h(synk_student_format_program_label($programRow)); ?></h5>
                          <p class="text-muted mb-0">
                            <?php echo synk_student_h((string)($programRow['college_name'] ?? '')); ?>
                            <?php if (trim((string)($programRow['campus_name'] ?? '')) !== ''): ?>
                              | <?php echo synk_student_h((string)($programRow['campus_name'] ?? '')); ?>
                            <?php endif; ?>
                          </p>

                          <div class="student-directory-meta">
                            <span class="student-directory-pill"><?php echo number_format((int)($programRow['prospectus_count'] ?? 0)); ?> prospectus</span>
                            <span class="student-directory-pill"><?php echo number_format((int)($programRow['section_count'] ?? 0)); ?> sections</span>
                            <span class="student-directory-pill"><?php echo number_format((int)($programRow['schedule_count'] ?? 0)); ?> meetings</span>
                          </div>

                          <div class="student-directory-actions">
                            <a href="<?php echo synk_student_h($prospectusLink); ?>" class="btn btn-sm btn-outline-success">View Prospectus</a>
                            <a href="<?php echo synk_student_h($classProgramLink); ?>" class="btn btn-sm btn-outline-primary">View Class Program</a>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <?php include '../footer.php'; ?>
          </div>
        </div>
      </div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
  </body>
</html>
