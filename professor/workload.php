<?php
session_start();
ob_start();

include '../backend/db.php';
require_once '../backend/professor_portal_helper.php';

synk_professor_require_login($conn);

$professorPortalContext = synk_professor_resolve_portal_context($conn);
$facultyId = (int)($professorPortalContext['faculty_id'] ?? 0);
$facultyName = trim((string)($professorPortalContext['faculty_name'] ?? 'Professor'));
$professorEmail = trim((string)($professorPortalContext['email'] ?? (string)($_SESSION['email'] ?? '')));
$facultyIsLinked = !empty($professorPortalContext['faculty_is_linked']);
$facultyIsActive = !array_key_exists('faculty_is_active', $professorPortalContext)
    || !empty($professorPortalContext['faculty_is_active']);
$currentTerm = synk_fetch_current_academic_term($conn);

$workloadTermOptions = $facultyId > 0
    ? synk_professor_fetch_workload_term_options($conn, $facultyId)
    : [];
$requestedAyId = max(0, (int)($_GET['ay_id'] ?? 0));
$requestedSemester = max(0, (int)($_GET['semester'] ?? 0));
$selectedTerm = null;

foreach ($workloadTermOptions as $termOption) {
    if ((int)($termOption['ay_id'] ?? 0) === $requestedAyId && (int)($termOption['semester'] ?? 0) === $requestedSemester) {
        $selectedTerm = $termOption;
        break;
    }
}

if ($selectedTerm === null && $requestedAyId > 0) {
    foreach ($workloadTermOptions as $termOption) {
        if ((int)($termOption['ay_id'] ?? 0) === $requestedAyId) {
            $selectedTerm = $termOption;
            break;
        }
    }
}

if ($selectedTerm === null) {
    foreach ($workloadTermOptions as $termOption) {
        if ((int)($termOption['ay_id'] ?? 0) === (int)($currentTerm['ay_id'] ?? 0) && (int)($termOption['semester'] ?? 0) === (int)($currentTerm['semester'] ?? 0)) {
            $selectedTerm = $termOption;
            break;
        }
    }
}

if ($selectedTerm === null && !empty($workloadTermOptions)) {
    $selectedTerm = $workloadTermOptions[0];
}

$selectedAyId = (int)($selectedTerm['ay_id'] ?? 0);
$selectedSemester = (int)($selectedTerm['semester'] ?? 0);
$selectedTermLabel = trim((string)($selectedTerm['term_label'] ?? ''));
$workloadRows = ($facultyId > 0 && $selectedAyId > 0 && $selectedSemester > 0)
    ? synk_professor_fetch_workload_rows($conn, $facultyId, $selectedAyId, $selectedSemester)
    : [];

$totalStudents = 0;
foreach ($workloadRows as $workloadRow) {
    $totalStudents += max(0, (int)($workloadRow['student_count'] ?? 0));
}

$academicYearOptions = [];
foreach ($workloadTermOptions as $termOption) {
    $ayId = (int)($termOption['ay_id'] ?? 0);
    if ($ayId <= 0 || isset($academicYearOptions[$ayId])) {
        continue;
    }

    $academicYearOptions[$ayId] = [
        'ay_id' => $ayId,
        'academic_year_label' => (string)($termOption['academic_year_label'] ?? ''),
    ];
}

$semesterOptions = array_values(array_filter($workloadTermOptions, static function (array $termOption) use ($selectedAyId): bool {
    return (int)($termOption['ay_id'] ?? 0) === $selectedAyId;
}));

$professorPortalDisplayName = $facultyName;
$professorPortalDisplayEmail = $professorEmail;
$professorPortalFacultyStatusLabel = $facultyIsLinked
    ? ($facultyIsActive ? 'Faculty linked' : 'Inactive faculty record')
    : 'Needs faculty link';
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

    <title>Professor Workload | Synk</title>

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
      .professor-workload-card,
      .professor-workload-alert,
      .professor-workload-hero {
        border: 1px solid #dce5f1;
        border-radius: 22px;
        box-shadow: 0 18px 38px rgba(67, 89, 113, 0.08);
      }

      .professor-workload-hero {
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 55%, #f1f8ef 100%);
      }

      .professor-workload-alert {
        background: linear-gradient(135deg, #fff8ea 0%, #fffdf6 100%);
        color: #855b16;
      }

      .professor-workload-kicker {
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

      .professor-workload-filter-grid,
      .professor-workload-summary {
        display: grid;
        gap: 0.9rem;
      }

      .professor-workload-filter-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      }

      .professor-workload-summary {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      }

      .professor-workload-summary-card {
        border: 1px solid #e2e9f2;
        border-radius: 18px;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.88);
      }

      .professor-workload-summary-label {
        display: block;
        color: #7d8ea5;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
      }

      .professor-workload-summary-value {
        display: block;
        margin-top: 0.35rem;
        color: #33475b;
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.55;
      }

      .professor-workload-list {
        display: grid;
        gap: 0.9rem;
      }

      .professor-workload-entry {
        border: 1px solid #e3eaf3;
        border-radius: 18px;
        background: #fbfdff;
        padding: 1rem;
      }

      .professor-workload-entry-head {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 0.75rem;
      }

      .professor-workload-code {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #25364a;
      }

      .professor-workload-title {
        margin: 0.3rem 0 0;
        color: #53667f;
        line-height: 1.55;
      }

      .professor-workload-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.65rem;
        margin-top: 0.95rem;
      }

      .professor-workload-meta-line {
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
        color: #4b5e78;
        font-size: 0.88rem;
        line-height: 1.5;
      }

      .professor-workload-meta-line i {
        color: #6c7bf2;
        font-size: 1rem;
        margin-top: 0.08rem;
      }

      .professor-workload-empty {
        border: 1px dashed #d8e1ec;
        border-radius: 18px;
        background: #fbfcff;
        padding: 2.3rem 1.25rem;
        text-align: center;
        color: #70829a;
      }

      @media (max-width: 767.98px) {
        .professor-workload-entry-head {
          flex-direction: column;
          align-items: flex-start;
        }
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
              <?php if (!$facultyIsLinked): ?>
                <div class="card professor-workload-alert mb-4">
                  <div class="card-body p-4">
                    Link this professor account to a faculty record first so workload data can be loaded from enrolled subject records.
                  </div>
                </div>
              <?php endif; ?>

              <div class="card professor-workload-hero mb-4">
                <div class="card-body p-4">
                  <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                      <span class="professor-workload-kicker">
                        <i class="bx bx-briefcase-alt"></i>
                        Workload
                      </span>
                      <h4 class="mt-3 mb-2">Faculty workload from enrolled subjects</h4>
                      <p class="mb-3 text-muted">
                        This page reads directly from <code>tbl_student_management_enrolled_subjects</code> so the professor can review actual assigned subjects by academic year and semester.
                      </p>
                      <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-label-primary"><?php echo synk_professor_h($selectedTermLabel !== '' ? $selectedTermLabel : ($currentTerm['term_text'] ?? 'Current academic term')); ?></span>
                        <span class="badge bg-label-info"><?php echo synk_professor_h($facultyName); ?></span>
                        <span class="badge bg-label-secondary"><?php echo synk_professor_h($professorEmail); ?></span>
                      </div>
                    </div>
                    <div class="col-lg-4">
                      <div class="professor-workload-summary">
                        <div class="professor-workload-summary-card">
                          <span class="professor-workload-summary-label">Loaded Subjects</span>
                          <span class="professor-workload-summary-value"><?php echo (int)count($workloadRows); ?> workload cards</span>
                        </div>
                        <div class="professor-workload-summary-card">
                          <span class="professor-workload-summary-label">Students</span>
                          <span class="professor-workload-summary-value"><?php echo (int)$totalStudents; ?> students</span>
                        </div>
                        <div class="professor-workload-summary-card">
                          <span class="professor-workload-summary-label">Academic Terms</span>
                          <span class="professor-workload-summary-value"><?php echo (int)count($workloadTermOptions); ?> terms with records</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card professor-workload-card mb-4">
                <div class="card-body p-4">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                    <div>
                      <span class="professor-workload-kicker">
                        <i class="bx bx-filter-alt"></i>
                        Filters
                      </span>
                      <h5 class="mt-3 mb-1">Select academic year and semester</h5>
                      <p class="text-muted mb-0">
                        Filter the professor workload using the enrolled subject records currently assigned to the linked faculty.
                      </p>
                    </div>
                  </div>

                  <form method="get" class="professor-workload-filter-grid">
                    <div>
                      <label class="form-label fw-semibold mb-2" for="workload_ay_id">Academic Year</label>
                      <select class="form-select" id="workload_ay_id" name="ay_id" onchange="this.form.submit()" <?php echo empty($academicYearOptions) ? 'disabled' : ''; ?>>
                        <?php if (empty($academicYearOptions)): ?>
                          <option value="0">No academic year found</option>
                        <?php else: ?>
                          <?php foreach ($academicYearOptions as $academicYearOption): ?>
                            <option
                              value="<?php echo (int)($academicYearOption['ay_id'] ?? 0); ?>"
                              <?php echo (int)($academicYearOption['ay_id'] ?? 0) === $selectedAyId ? 'selected' : ''; ?>
                            >
                              <?php echo synk_professor_h((string)($academicYearOption['academic_year_label'] ?? 'Academic Year')); ?>
                            </option>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </select>
                    </div>

                    <div>
                      <label class="form-label fw-semibold mb-2" for="workload_semester">Semester</label>
                      <select class="form-select" id="workload_semester" name="semester" onchange="this.form.submit()" <?php echo empty($semesterOptions) ? 'disabled' : ''; ?>>
                        <?php if (empty($semesterOptions)): ?>
                          <option value="0">No semester found</option>
                        <?php else: ?>
                          <?php foreach ($semesterOptions as $semesterOption): ?>
                            <option
                              value="<?php echo (int)($semesterOption['semester'] ?? 0); ?>"
                              <?php echo (int)($semesterOption['semester'] ?? 0) === $selectedSemester ? 'selected' : ''; ?>
                            >
                              <?php
                              $semesterLabel = trim((string)($semesterOption['semester_label'] ?? 'Semester'));
                              $workloadCount = (int)($semesterOption['workload_count'] ?? 0);
                              echo synk_professor_h($semesterLabel . ' (' . $workloadCount . ' subjects)');
                              ?>
                            </option>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </select>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card professor-workload-card mb-4">
                <div class="card-body p-4">
                  <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="badge bg-label-primary"><?php echo synk_professor_h($selectedTermLabel !== '' ? $selectedTermLabel : 'No term selected'); ?></span>
                    <span class="badge bg-label-info"><?php echo (int)count($workloadRows); ?> subjects</span>
                    <span class="badge bg-label-success"><?php echo (int)$totalStudents; ?> students</span>
                  </div>

                  <?php if (empty($workloadRows)): ?>
                    <div class="professor-workload-empty">
                      <i class="bx bx-book-content fs-1 d-block mb-2 text-primary"></i>
                      <div class="fw-semibold mb-1">No workload records are available for this term.</div>
                      <div>
                        <?php if (!$facultyIsLinked): ?>
                          Link the professor account first so the workload can be matched to a faculty record.
                        <?php else: ?>
                          No enrolled subject rows are assigned to this faculty for the selected academic year and semester.
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="professor-workload-list">
                      <?php foreach ($workloadRows as $workloadRow): ?>
                        <article class="professor-workload-entry">
                          <div class="professor-workload-entry-head">
                            <div>
                              <h6 class="professor-workload-code"><?php echo synk_professor_h((string)($workloadRow['subject_code'] ?? 'NO CODE')); ?></h6>
                              <p class="professor-workload-title"><?php echo synk_professor_h((string)($workloadRow['descriptive_title'] ?? 'Untitled subject')); ?></p>
                            </div>
                            <span class="badge bg-label-primary"><?php echo synk_professor_h((string)($workloadRow['section_display'] ?? 'Section')); ?></span>
                          </div>

                          <div class="professor-workload-meta">
                            <div class="professor-workload-meta-line">
                              <i class="bx bx-time-five"></i>
                              <span><?php echo synk_professor_h((string)($workloadRow['schedule_text'] ?? 'Schedule not available')); ?></span>
                            </div>
                            <div class="professor-workload-meta-line">
                              <i class="bx bx-map"></i>
                              <span><?php echo synk_professor_h((string)($workloadRow['room_name'] ?? 'Room not assigned')); ?></span>
                            </div>
                            <div class="professor-workload-meta-line">
                              <i class="bx bx-book"></i>
                              <span><?php echo synk_professor_h(trim((string)($workloadRow['program_label'] ?? '')) !== '' ? (string)$workloadRow['program_label'] : 'Program not assigned'); ?></span>
                            </div>
                            <div class="professor-workload-meta-line">
                              <i class="bx bx-layer"></i>
                              <span>Year Level <?php echo (int)($workloadRow['year_level'] ?? 0); ?></span>
                            </div>
                            <div class="professor-workload-meta-line">
                              <i class="bx bx-buildings"></i>
                              <span>
                                <?php
                                $collegeCampusParts = array_filter([
                                    trim((string)($workloadRow['college_name'] ?? '')),
                                    trim((string)($workloadRow['campus_name'] ?? '')),
                                ], static function ($value): bool {
                                    return trim((string)$value) !== '';
                                });
                                echo synk_professor_h(!empty($collegeCampusParts) ? implode(' | ', $collegeCampusParts) : 'College or campus not assigned');
                                ?>
                              </span>
                            </div>
                            <div class="professor-workload-meta-line">
                              <i class="bx bx-group"></i>
                              <span><?php echo (int)($workloadRow['student_count'] ?? 0); ?> students</span>
                            </div>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
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
