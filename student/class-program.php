<?php
session_start();
ob_start();

include '../backend/db.php';
require_once '../backend/student_portal_helper.php';

synk_student_require_login($conn);

$currentTerm = synk_fetch_current_academic_term($conn);
$termReady = (int)($currentTerm['ay_id'] ?? 0) > 0 && (int)($currentTerm['semester'] ?? 0) > 0;

$campuses = synk_student_fetch_campuses($conn);
$selectedCampusId = synk_student_select_valid_id($campuses, (int)($_GET['campus_id'] ?? 0), 'campus_id');

$colleges = synk_student_fetch_colleges($conn, $selectedCampusId);
$selectedCollegeId = synk_student_select_valid_id($colleges, (int)($_GET['college_id'] ?? 0), 'college_id');

$programs = synk_student_fetch_programs_for_schedule(
    $conn,
    (int)($currentTerm['ay_id'] ?? 0),
    (int)($currentTerm['semester'] ?? 0),
    $selectedCampusId,
    $selectedCollegeId
);
$selectedProgramId = synk_student_select_valid_id($programs, (int)($_GET['program_id'] ?? 0), 'program_id');

$sections = synk_student_fetch_sections_for_program(
    $conn,
    (int)($currentTerm['ay_id'] ?? 0),
    (int)($currentTerm['semester'] ?? 0),
    $selectedProgramId
);

$selectedSectionId = synk_student_select_valid_id($sections, (int)($_GET['section_id'] ?? 0), 'section_id');
if ($selectedProgramId > 0 && $selectedSectionId <= 0 && !empty($sections)) {
    $selectedSectionId = (int)($sections[0]['section_id'] ?? 0);
}

$selectedSectionId = synk_student_select_valid_id($sections, $selectedSectionId, 'section_id');
$schedulePayload = $selectedSectionId > 0 && $termReady
    ? synk_student_fetch_section_schedule(
        $conn,
        $selectedSectionId,
        (int)($currentTerm['ay_id'] ?? 0),
        (int)($currentTerm['semester'] ?? 0)
    )
    : ['meta' => [], 'rows' => [], 'rooms_text' => 'TBA'];

$scheduleMatrix = synk_student_build_schedule_matrix($schedulePayload['rows'] ?? []);
$dayColumns = synk_student_schedule_day_columns();
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

    <title>Class Program | Synk Student</title>

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
      .student-program-card {
        border: 1px solid #dce5f1;
        border-radius: 20px;
        box-shadow: 0 16px 34px rgba(67, 89, 113, 0.08);
      }

      .student-program-title {
        font-size: 1.35rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        color: #243a54;
        text-transform: uppercase;
      }

      .student-program-term {
        margin-top: 0.15rem;
        font-size: 0.95rem;
        color: #667b92;
      }

      .student-program-meta {
        margin-top: 1rem;
        display: grid;
        gap: 0.35rem;
      }

      .student-program-meta-line {
        font-size: 0.95rem;
        color: #304560;
      }

      .student-program-meta-key {
        font-weight: 700;
      }

      .student-grid-shell {
        border: 1px solid #d9e2ec;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
      }

      .student-grid-table {
        width: 100%;
        margin-bottom: 0;
        table-layout: fixed;
      }

      .student-grid-table thead th {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        text-align: center;
        color: #4d627a;
        background: #f9fbfd;
        border-color: #cfd8e3;
        vertical-align: middle;
      }

      .student-grid-table tbody td {
        border-color: #d9e2ec;
        color: #576c85;
        vertical-align: top;
        background: #fff;
        padding: 0.55rem 0.6rem;
      }

      .student-grid-table th:first-child,
      .student-grid-table td:first-child {
        width: 15%;
      }

      .student-grid-table th:not(:first-child),
      .student-grid-table td:not(:first-child) {
        width: 14.16%;
      }

      .student-time-cell {
        white-space: nowrap;
        font-weight: 600;
        color: #334a63;
        font-size: 0.82rem;
        background: #fbfcfe;
      }

      .student-empty-cell {
        background: #fff;
        min-height: 48px;
      }

      .student-class-cell {
        background: #fbfdff !important;
      }

      .student-class-block {
        min-height: 100%;
      }

      .student-subject-code {
        font-size: 0.88rem;
        font-weight: 700;
        color: #253a53;
        line-height: 1.25;
      }

      .student-subject-description {
        margin-top: 0.2rem;
        font-size: 0.72rem;
        line-height: 1.25;
        color: #60768f;
      }

      .student-block-line {
        margin-top: 0.2rem;
        font-size: 0.72rem;
        line-height: 1.25;
        color: #3d546d;
      }

      .student-block-chip {
        display: inline-block;
        margin-top: 0.35rem;
        padding: 0.16rem 0.42rem;
        border-radius: 999px;
        background: #eef4ff;
        color: #3d63dd;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.05em;
      }

      .student-warning-banner {
        margin-bottom: 1rem;
        border: 1px solid #ffe3a6;
        border-radius: 10px;
        background: #fff8e6;
        color: #7a5a00;
        padding: 0.75rem 0.9rem;
        font-size: 0.84rem;
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
          margin: 0.45in;
        }

        body {
          background: #fff !important;
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

        .student-program-card {
          border: 0 !important;
          box-shadow: none !important;
          margin: 0 !important;
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
                <i class="bx bx-table me-2"></i>
                Class Program
              </h4>

              <div class="card student-filter-card mb-4 student-no-print">
                <div class="card-header">
                  <h5 class="mb-0">Filter Current Section Schedule</h5>
                  <small class="text-muted">Select the current term program and section to view the weekly class grid.</small>
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
                      <label for="section_id" class="form-label fw-semibold">Section</label>
                      <select id="section_id" name="section_id" class="form-select">
                        <option value="0">Select Section</option>
                        <?php foreach ($sections as $sectionRow): ?>
                          <option value="<?php echo (int)($sectionRow['section_id'] ?? 0); ?>"<?php echo (int)($sectionRow['section_id'] ?? 0) === $selectedSectionId ? ' selected' : ''; ?>>
                            <?php echo synk_student_h((string)($sectionRow['label'] ?? 'Section')); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                      <button type="submit" class="btn btn-primary">Load Class Program</button>
                      <a href="class-program.php" class="btn btn-outline-secondary">Reset</a>
                      <?php if ($selectedSectionId > 0 && !empty($schedulePayload['meta'])): ?>
                        <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
                      <?php endif; ?>
                    </div>
                  </form>
                </div>
              </div>

              <?php if (!$termReady): ?>
                <div class="alert alert-warning mb-4" role="alert">
                  The current academic term is not configured yet, so the student class program
                  viewer cannot resolve sections for a term.
                </div>
              <?php elseif ($selectedProgramId <= 0): ?>
                <div class="student-empty-state">
                  Select a program to load the available sections for the current term.
                </div>
              <?php elseif (empty($sections)): ?>
                <div class="student-empty-state">
                  No generated sections were found for the selected program in
                  <?php echo synk_student_h((string)($currentTerm['term_text'] ?? 'the current term')); ?>.
                </div>
              <?php elseif ($selectedSectionId <= 0 || empty($schedulePayload['meta'])): ?>
                <div class="student-empty-state">
                  Select a section to view its class program.
                </div>
              <?php else: ?>
                <?php $meta = $schedulePayload['meta']; ?>
                <div class="card student-program-card">
                  <div class="card-header d-flex justify-content-between align-items-start gap-3">
                    <div>
                      <div class="student-program-title">Class Program</div>
                      <div class="student-program-term"><?php echo synk_student_h((string)($currentTerm['term_text'] ?? 'Current academic term')); ?></div>
                      <div class="student-program-meta">
                        <div class="student-program-meta-line"><span class="student-program-meta-key">Campus:</span> <?php echo synk_student_h((string)($meta['campus_name'] ?? '-')); ?></div>
                        <div class="student-program-meta-line"><span class="student-program-meta-key">College:</span> <?php echo synk_student_h((string)($meta['college_name'] ?? '-')); ?></div>
                        <div class="student-program-meta-line"><span class="student-program-meta-key">Program:</span> <?php echo synk_student_h(synk_student_format_program_label($meta)); ?></div>
                        <div class="student-program-meta-line"><span class="student-program-meta-key">Section:</span> <?php echo synk_student_h((string)($meta['full_section'] ?? '-')); ?></div>
                        <div class="student-program-meta-line"><span class="student-program-meta-key">Room/s:</span> <?php echo synk_student_h((string)($meta['rooms_text'] ?? 'TBA')); ?></div>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <?php if (!empty($scheduleMatrix['warnings'])): ?>
                      <div class="student-warning-banner">
                        <?php foreach ($scheduleMatrix['warnings'] as $warningText): ?>
                          <div><?php echo synk_student_h($warningText); ?></div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

                    <?php if (empty($schedulePayload['rows'])): ?>
                      <div class="student-empty-state">
                        No class schedule blocks are available for the selected section yet.
                      </div>
                    <?php else: ?>
                      <div class="student-grid-shell">
                        <div class="table-responsive">
                          <table class="table table-bordered student-grid-table">
                            <thead>
                              <tr>
                                <th>Time</th>
                                <?php foreach ($dayColumns as $dayColumn): ?>
                                  <th><?php echo synk_student_h((string)($dayColumn['label'] ?? '')); ?></th>
                                <?php endforeach; ?>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($scheduleMatrix['slots'] as $slotStart): ?>
                                <?php $slotEnd = (int)$slotStart + 30; ?>
                                <tr>
                                  <td class="student-time-cell"><?php echo synk_student_h(synk_student_format_time_range((int)$slotStart, $slotEnd)); ?></td>
                                  <?php foreach ($dayColumns as $dayColumn): ?>
                                    <?php
                                    $dayKey = (string)($dayColumn['key'] ?? '');
                                    $entry = $scheduleMatrix['occupancy'][$dayKey][$slotStart] ?? null;
                                    if ($entry && (string)($entry['type'] ?? '') === 'covered') {
                                        continue;
                                    }
                                    ?>
                                    <?php if ($entry && (string)($entry['type'] ?? '') === 'start'): ?>
                                      <?php $block = $entry['block']; ?>
                                      <td rowspan="<?php echo (int)($block['_slot_span'] ?? 1); ?>" class="student-class-cell">
                                        <div class="student-class-block">
                                          <?php if (trim((string)($block['subject_code'] ?? '')) !== ''): ?>
                                            <div class="student-subject-code"><?php echo synk_student_h((string)($block['subject_code'] ?? '')); ?></div>
                                          <?php endif; ?>
                                          <?php if (trim((string)($block['subject_description'] ?? '')) !== ''): ?>
                                            <div class="student-subject-description"><?php echo synk_student_h((string)($block['subject_description'] ?? '')); ?></div>
                                          <?php endif; ?>
                                          <div class="student-block-line"><?php echo synk_student_h((string)($block['faculty_name'] ?? 'TBA')); ?></div>
                                          <div class="student-block-line"><?php echo synk_student_h((string)($block['room_label'] ?? 'TBA')); ?></div>
                                          <?php if (strtoupper((string)($block['schedule_type'] ?? '')) === 'LAB'): ?>
                                            <div class="student-block-chip">LAB</div>
                                          <?php endif; ?>
                                        </div>
                                      </td>
                                    <?php else: ?>
                                      <td class="student-empty-cell"></td>
                                    <?php endif; ?>
                                  <?php endforeach; ?>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    <?php endif; ?>
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
