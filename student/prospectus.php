<?php
session_start();
ob_start();

include '../backend/db.php';
require_once '../backend/student_portal_helper.php';

synk_student_require_login();

$campuses = synk_student_fetch_campuses($conn);
$selectedCampusId = synk_student_select_valid_id($campuses, (int)($_GET['campus_id'] ?? 0), 'campus_id');

$colleges = synk_student_fetch_colleges($conn, $selectedCampusId);
$selectedCollegeId = synk_student_select_valid_id($colleges, (int)($_GET['college_id'] ?? 0), 'college_id');

$programs = synk_student_fetch_programs_for_prospectus($conn, $selectedCampusId, $selectedCollegeId);
$selectedProgramId = synk_student_select_valid_id($programs, (int)($_GET['program_id'] ?? 0), 'program_id');

$prospectusVersions = synk_student_fetch_prospectus_versions($conn, $selectedProgramId);
$requestedProspectusId = (int)($_GET['prospectus_id'] ?? 0);
$selectedProspectusId = synk_student_select_valid_id($prospectusVersions, $requestedProspectusId, 'prospectus_id');

if ($selectedProgramId > 0 && $selectedProspectusId <= 0 && !empty($prospectusVersions)) {
    $selectedProspectusId = (int)($prospectusVersions[0]['prospectus_id'] ?? 0);
}

$selectedProgram = null;
foreach ($programs as $programRow) {
    if ((int)($programRow['program_id'] ?? 0) === $selectedProgramId) {
        $selectedProgram = $programRow;
        break;
    }
}

$prospectusSheet = $selectedProspectusId > 0 ? synk_student_fetch_prospectus_sheet($conn, $selectedProspectusId) : null;
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

    <title>Prospectus Viewer | Synk Student</title>

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
      .student-filter-card,
      .student-sheet-card {
        border: 1px solid #dde5f0;
        border-radius: 22px;
        box-shadow: 0 18px 38px rgba(67, 89, 113, 0.08);
      }

      .student-sheet-card {
        background: linear-gradient(180deg, rgba(246, 250, 255, 0.96) 0%, rgba(255, 255, 255, 1) 100%);
      }

      .student-sheet-head {
        text-align: center;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid #e4ebf3;
        margin-bottom: 1.75rem;
      }

      .student-sheet-kicker {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        background: #eef5ea;
        color: #537043;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
      }

      .student-sheet-title {
        margin-top: 1rem;
        margin-bottom: 0.35rem;
        font-size: 1.65rem;
        font-weight: 800;
        color: #243246;
      }

      .student-sheet-subtitle {
        color: #6b7c93;
        font-size: 0.96rem;
      }

      .student-sheet-meta {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      .student-sheet-meta-pill {
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: #f3f6fb;
        color: #55667f;
        font-size: 0.82rem;
        font-weight: 600;
      }

      .student-year-block + .student-year-block {
        margin-top: 1.75rem;
      }

      .student-year-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 14px;
        background: linear-gradient(90deg, #f5f8ec 0%, #eef5ff 100%);
        border: 1px solid #dfe8cf;
      }

      .student-year-banner-title {
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #37465d;
      }

      .student-year-banner-note {
        color: #70829a;
        font-size: 0.8rem;
      }

      .student-semester-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
      }

      .student-semester-card {
        border: 1px solid #dfe7f1;
        border-radius: 16px;
        background: #fff;
        overflow: hidden;
      }

      .student-semester-card.midyear {
        grid-column: 1 / -1;
      }

      .student-semester-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
      }

      .student-semester-title {
        margin: 0;
        font-size: 0.92rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #39495f;
      }

      .student-semester-chip {
        padding: 0.28rem 0.65rem;
        border-radius: 999px;
        background: #eef4ff;
        color: #5168a3;
        font-size: 0.72rem;
        font-weight: 700;
      }

      .student-table-wrap {
        overflow-x: auto;
      }

      .student-table {
        width: 100%;
        margin-bottom: 0;
      }

      .student-table thead th {
        background: #f7f9fc;
        white-space: nowrap;
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .student-table td {
        vertical-align: top;
        font-size: 0.88rem;
      }

      .student-table .units-col {
        width: 72px;
        text-align: center;
        font-weight: 700;
      }

      .student-empty-state {
        border: 1px dashed #d7e0ec;
        border-radius: 18px;
        background: #fbfcff;
        padding: 2.1rem 1.25rem;
        text-align: center;
        color: #72839a;
      }

      @media print {
        @page {
          margin: 0.5in;
        }

        .layout-menu,
        .layout-navbar,
        .content-footer,
        .student-no-print {
          display: none !important;
        }

        .layout-wrapper,
        .layout-container,
        .layout-page,
        .content-wrapper,
        .container-xxl {
          margin: 0 !important;
          padding: 0 !important;
          background: #fff !important;
        }

        .student-sheet-card {
          border: 0 !important;
          box-shadow: none !important;
        }
      }

      @media (max-width: 991.98px) {
        .student-semester-grid {
          grid-template-columns: 1fr;
        }

        .student-semester-card.midyear {
          grid-column: auto;
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
              <h4 class="fw-bold mb-3">
                <i class="bx bx-book-content me-2"></i>
                Prospectus Viewer
              </h4>

              <div class="card student-filter-card mb-4 student-no-print">
                <div class="card-header">
                  <h5 class="mb-0">Load a Program Prospectus</h5>
                  <small class="text-muted">Select the campus scope, program, and curriculum version to review.</small>
                </div>
                <div class="card-body">
                  <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
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
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                      <label for="program_id" class="form-label fw-semibold">Program</label>
                      <select id="program_id" name="program_id" class="form-select">
                        <option value="0">Select Program</option>
                        <?php foreach ($programs as $programRow): ?>
                          <option value="<?php echo (int)($programRow['program_id'] ?? 0); ?>"<?php echo (int)($programRow['program_id'] ?? 0) === $selectedProgramId ? ' selected' : ''; ?>>
                            <?php echo synk_student_h(synk_student_format_program_label($programRow, true)); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label for="prospectus_id" class="form-label fw-semibold">Version</label>
                      <select id="prospectus_id" name="prospectus_id" class="form-select">
                        <option value="0">Latest Available</option>
                        <?php foreach ($prospectusVersions as $versionRow): ?>
                          <?php
                          $versionLabel = trim((string)($versionRow['effective_sy'] ?? '')) !== ''
                              ? 'SY ' . trim((string)($versionRow['effective_sy'] ?? '')) . ' - ' . trim((string)($versionRow['cmo_no'] ?? ''))
                              : trim((string)($versionRow['cmo_no'] ?? ''));
                          ?>
                          <option value="<?php echo (int)($versionRow['prospectus_id'] ?? 0); ?>"<?php echo (int)($versionRow['prospectus_id'] ?? 0) === $selectedProspectusId ? ' selected' : ''; ?>>
                            <?php echo synk_student_h($versionLabel); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                      <button type="submit" class="btn btn-primary">Load Prospectus</button>
                      <a href="prospectus.php" class="btn btn-outline-secondary">Reset</a>
                      <?php if ($prospectusSheet): ?>
                        <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
                      <?php endif; ?>
                    </div>
                  </form>
                </div>
              </div>

              <?php if ($selectedProgramId <= 0): ?>
                <div class="student-empty-state">
                  Select a program to open its prospectus and curriculum subject breakdown.
                </div>
              <?php elseif (empty($prospectusVersions)): ?>
                <div class="student-empty-state">
                  No saved prospectus version was found for the selected program.
                </div>
              <?php elseif (!$prospectusSheet): ?>
                <div class="student-empty-state">
                  The selected prospectus could not be loaded right now.
                </div>
              <?php else: ?>
                <?php
                $header = $prospectusSheet['header'];
                $structure = $prospectusSheet['structure'];
                $subjects = $prospectusSheet['subjects'];
                ?>
                <div class="card student-sheet-card">
                  <div class="card-body p-4">
                    <div class="student-sheet-head">
                      <span class="student-sheet-kicker">
                        <i class="bx bx-book"></i>
                        Student Read-Only View
                      </span>
                      <h1 class="student-sheet-title"><?php echo synk_student_h(synk_student_format_program_label($header)); ?></h1>
                      <p class="student-sheet-subtitle">
                        <?php echo synk_student_h((string)($header['college_name'] ?? '')); ?>
                        <?php if (trim((string)($header['campus_name'] ?? '')) !== ''): ?>
                          | <?php echo synk_student_h((string)($header['campus_name'] ?? '')); ?>
                        <?php endif; ?>
                      </p>
                      <div class="student-sheet-meta">
                        <span class="student-sheet-meta-pill">CMO <?php echo synk_student_h((string)($header['cmo_no'] ?? 'N/A')); ?></span>
                        <span class="student-sheet-meta-pill">Effectivity SY <?php echo synk_student_h((string)($header['effective_sy'] ?? 'N/A')); ?></span>
                        <span class="student-sheet-meta-pill"><?php echo number_format(count($prospectusVersions)); ?> saved version(s)</span>
                      </div>
                    </div>

                    <?php
                    if (!empty($structure)) {
                        ksort($structure, SORT_NUMERIC);
                    }
                    ?>

                    <?php foreach ($structure as $yearLevel => $semesterSet): ?>
                      <?php
                      if (is_array($semesterSet)) {
                          ksort($semesterSet, SORT_NUMERIC);
                      }
                      ?>
                      <section class="student-year-block">
                        <div class="student-year-banner">
                          <div class="student-year-banner-title">Year Level <?php echo synk_student_h($yearLevel); ?></div>
                          <div class="student-year-banner-note">Curriculum guide by semester</div>
                        </div>

                        <div class="student-semester-grid">
                          <?php foreach ($semesterSet as $semesterKey => $_unused): ?>
                            <?php
                            $semesterSubjects = $subjects[$yearLevel][$semesterKey] ?? [];
                            $semesterUnits = 0.0;
                            foreach ($semesterSubjects as $subjectRow) {
                                $semesterUnits += (float)($subjectRow['total_units'] ?? 0);
                            }
                            ?>
                            <div class="student-semester-card<?php echo (int)$semesterKey === 3 ? ' midyear' : ''; ?>">
                              <div class="student-semester-head">
                                <h2 class="student-semester-title"><?php echo synk_student_h(synk_semester_label((int)$semesterKey)); ?></h2>
                                <span class="student-semester-chip">
                                  <?php echo number_format(count($semesterSubjects)); ?> subject(s) | <?php echo rtrim(rtrim(number_format($semesterUnits, 2, '.', ''), '0'), '.'); ?> units
                                </span>
                              </div>

                              <div class="student-table-wrap">
                                <table class="table table-bordered student-table">
                                  <thead>
                                    <tr>
                                      <th style="min-width: 98px;">Code</th>
                                      <th style="min-width: 220px;">Description</th>
                                      <th class="units-col">Lec</th>
                                      <th class="units-col">Lab</th>
                                      <th class="units-col">Total</th>
                                      <th style="min-width: 180px;">Prerequisite</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php if (empty($semesterSubjects)): ?>
                                      <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No subjects encoded for this semester.</td>
                                      </tr>
                                    <?php else: ?>
                                      <?php foreach ($semesterSubjects as $subjectRow): ?>
                                        <tr>
                                          <td class="fw-semibold"><?php echo synk_student_h((string)($subjectRow['sub_code'] ?? '')); ?></td>
                                          <td><?php echo synk_student_h((string)($subjectRow['sub_description'] ?? '')); ?></td>
                                          <td class="units-col"><?php echo synk_student_h(rtrim(rtrim(number_format((float)($subjectRow['lec_units'] ?? 0), 2, '.', ''), '0'), '.')); ?></td>
                                          <td class="units-col"><?php echo synk_student_h(rtrim(rtrim(number_format((float)($subjectRow['lab_units'] ?? 0), 2, '.', ''), '0'), '.')); ?></td>
                                          <td class="units-col"><?php echo synk_student_h(rtrim(rtrim(number_format((float)($subjectRow['total_units'] ?? 0), 2, '.', ''), '0'), '.')); ?></td>
                                          <td><?php echo synk_student_h((string)($subjectRow['prerequisites'] ?? 'None')); ?></td>
                                        </tr>
                                      <?php endforeach; ?>
                                    <?php endif; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </section>
                    <?php endforeach; ?>
                  </div>
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
