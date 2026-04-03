<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../backend/student_management_exam_permits_helper.php';

function student_management_csv_upload_error_message(int $errorCode): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed form size.',
        UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Select a CSV file to upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];

    return $map[$errorCode] ?? 'Unable to upload the selected CSV file.';
}

function student_management_csv_import_error_details(Throwable $error): string
{
    $message = trim($error->getMessage());
    $csvHints = [
        'The CSV file does not contain the expected old-system roster layout.',
        'The CSV file does not match the expected old-system student list header.',
        'No student rows were found in the uploaded CSV file.',
    ];

    if (in_array($message, $csvHints, true)) {
        return 'The CSV must match the old-system roster format with Subject/Section, Descriptive Title, Instructor(s), Building/Room, and Student\'s Name rows.';
    }

    return 'Nothing was committed. Review the selected scope and the CSV content, then try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_import_batch'])) {
    if ($studentManagementPageError === '') {
        try {
            $summary = synk_student_management_delete_import_batch(
                $conn,
                (string)($_POST['import_batch_key'] ?? '')
            );

            $detailParts = [
                'Deleted enrolled-subject rows: ' . number_format((int)$summary['deleted_rows']),
                'Removed orphan student master rows: ' . number_format((int)$summary['deleted_students']),
            ];

            if (!empty($summary['deleted_section'])) {
                $detailParts[] = 'Removed the now-unused section that was tied to this upload.';
            } else {
                $blockedBy = is_array($summary['section_cleanup_blocked_by'] ?? null) ? $summary['section_cleanup_blocked_by'] : [];
                if (!empty($blockedBy)) {
                    $detailParts[] = 'Section was kept because it is still referenced elsewhere.';
                }
            }

            if (trim((string)($summary['restored_batch_key'] ?? '')) !== '') {
                $detailParts[] = 'Restored the previous archived batch for this subject-section automatically.';
            } elseif (!empty($summary['was_active'])) {
                $detailParts[] = 'No archived batch was available to restore.';
            } else {
                $detailParts[] = 'The deleted batch was archived only, so no current batch was changed.';
            }

            student_management_push_flash(
                'success',
                sprintf(
                    'Deleted uploaded batch %s.',
                    (string)($summary['source_file_name'] ?? '')
                ),
                implode(' ', $detailParts)
            );
        } catch (Throwable $e) {
            student_management_push_flash(
                'danger',
                $e->getMessage(),
                'No batch rows were changed.'
            );
        }
    } else {
        student_management_push_flash(
            'danger',
            $studentManagementPageError,
            'The module schema could not be prepared, so the batch delete was skipped.'
        );
    }

    student_management_redirect('exam-permits.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_exam_permits_csv'])) {
    if ($studentManagementPageError === '') {
        try {
            if (!isset($_FILES['exam_permits_csv'])) {
                throw new RuntimeException('Select a CSV file to upload.');
            }

            $uploadedFile = $_FILES['exam_permits_csv'];
            $originalName = trim((string)($uploadedFile['name'] ?? ''));
            $tmpName = (string)($uploadedFile['tmp_name'] ?? '');
            $errorCode = (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new RuntimeException(student_management_csv_upload_error_message($errorCode));
            }

            if ($originalName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('The uploaded CSV file could not be processed.');
            }

            if (strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'csv') {
                throw new RuntimeException('Only CSV files from the old student roster format are supported on this page.');
            }

            $scope = [
                'campus_id' => (int)($_POST['campus_id'] ?? 0),
                'college_id' => (int)($_POST['college_id'] ?? 0),
                'program_id' => (int)($_POST['program_id'] ?? 0),
                'ay_id' => (int)($_POST['ay_id'] ?? 0),
                'semester' => (int)($_POST['semester'] ?? 0),
                'year_level' => (int)($_POST['year_level'] ?? 0),
                'section_suffix' => (string)($_POST['section_suffix'] ?? ''),
            ];

            $summary = synk_student_management_import_exam_permits_csv(
                $conn,
                $tmpName,
                $originalName,
                $scope,
                (int)($_SESSION['user_id'] ?? 0)
            );

            student_management_push_flash(
                'success',
                sprintf(
                    'Imported %s enrolled-subject rows for %s in %s %s.',
                    number_format((int)$summary['student_count']),
                    (string)$summary['section_display'],
                    (string)$summary['academic_year_label'],
                    (string)$summary['semester_label']
                ),
                sprintf(
                    'Subject: %s. Matched existing students: %s. Updated student master rows: %s. Added new students: %s. Replaced previous enrollment rows: %s. Faculty ID: %s. Room link: %s.',
                    (string)$summary['subject_code'],
                    number_format((int)$summary['matched_students']),
                    number_format((int)$summary['updated_students']),
                    number_format((int)$summary['inserted_students']),
                    number_format((int)$summary['deleted_rows']),
                    number_format((int)$summary['faculty_id']),
                    !empty($summary['room_linked']) ? 'Linked to tbl_rooms' : 'Stored as imported room text only'
                )
            );
        } catch (Throwable $e) {
            student_management_push_flash(
                'danger',
                $e->getMessage(),
                student_management_csv_import_error_details($e)
            );
        }
    } else {
        student_management_push_flash(
            'danger',
            $studentManagementPageError,
            'The module schema could not be prepared, so the CSV upload was skipped.'
        );
    }

    student_management_redirect('exam-permits.php');
}

$flash = student_management_pull_flash();
$latestImport = [
    'source_file_name' => '',
    'campus_name' => '',
    'college_name' => '',
    'program_code' => '',
    'program_name' => '',
    'academic_year_label' => '',
    'semester_label' => '',
    'year_level' => 0,
    'section_display' => '',
    'subject_code' => '',
    'descriptive_title' => '',
    'faculty_name' => '',
    'room_name' => '',
    'schedule_text' => '',
    'student_count' => 0,
    'imported_at' => '',
];
$campuses = [];
$colleges = [];
$programs = [];
$academicYears = [];
$recentImports = [];
$semesterOptions = synk_student_management_semester_options();
$currentTerm = synk_fetch_current_academic_term($conn);
$defaultAyId = (int)($currentTerm['ay_id'] ?? 0);
$defaultSemester = (int)($currentTerm['semester'] ?? 0);

if ($studentManagementPageError === '') {
    try {
        synk_student_management_ensure_exam_permits_schema($conn);
        $latestImport = synk_student_management_exam_permits_latest_batch($conn);
        $campuses = synk_student_management_fetch_active_campuses($conn);
        $colleges = synk_student_management_fetch_college_catalog($conn);
        $programs = synk_student_management_fetch_program_catalog($conn);
        $academicYears = synk_student_management_fetch_academic_year_catalog($conn);
        $recentImports = synk_student_management_exam_permits_recent_batches($conn);
    } catch (Throwable $e) {
        $studentManagementPageError = $e->getMessage();
    }
}

$hasLatestImport = trim((string)($latestImport['import_batch_key'] ?? '')) !== '';
?>
<!DOCTYPE html>
<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Backward Encoding CSV | Synk</title>

    <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>

    <style>
      .upload-hero { border: 0; border-radius: 1.25rem; background: linear-gradient(145deg, #eef6ff 0%, #ffffff 48%, #f8fbff 100%); }
      .section-card { border: 0; border-radius: 1.1rem; box-shadow: 0 16px 35px rgba(15, 23, 42, 0.08); }
      .upload-note { border-radius: 1rem; border: 1px dashed rgba(54, 90, 148, 0.3); background: #f8fbff; }
      .batch-chip { display: inline-flex; padding: 0.25rem 0.6rem; border-radius: 999px; background: #eaf2ff; color: #2f5ca8; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
      .summary-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #8592a3; font-weight: 700; }
      .scope-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
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
              <div class="card upload-hero mb-4">
                <div class="card-body p-4 p-lg-5">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                      <span class="batch-chip mb-3">Single Upload Page</span>
                      <h3 class="fw-bold mb-2 text-primary">Backward-encode old student roster CSV files into the rebuilt student module.</h3>
                      <p class="text-muted mb-0">
                        This page stores one row per student in <code>tbl_student_management</code> and one row per student-subject enrollment in the renamed enrolled-subjects table.
                      </p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                      <a href="index.php" class="btn btn-outline-primary">Back to Dashboard</a>
                      <a href="directory.php" class="btn btn-primary">Open Student Directory</a>
                    </div>
                  </div>
                </div>
              </div>

              <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars((string)($flash['status'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?> alert-dismissible mb-4" role="alert">
                  <div class="fw-semibold"><?php echo htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php if (!empty($flash['details'])): ?>
                    <div class="small mt-1"><?php echo htmlspecialchars((string)$flash['details'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if ($studentManagementPageError !== ''): ?>
                <div class="alert alert-danger mb-4" role="alert">
                  <div class="fw-semibold">Backward encoding is unavailable.</div>
                  <div class="small mt-1"><?php echo htmlspecialchars($studentManagementPageError, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
              <?php endif; ?>

              <div class="row g-4">
                <div class="col-lg-7">
                  <div class="card section-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">Upload Old System CSV</h5>
                      <p class="text-muted mb-0">Select the scope first, then upload one subject-section CSV from the legacy system.</p>
                    </div>
                    <div class="card-body">
                      <form method="post" enctype="multipart/form-data" id="backwardEncodingForm">
                        <input type="hidden" name="upload_exam_permits_csv" value="1" />

                        <div class="scope-grid mb-3">
                          <div><label class="form-label" for="campus_id">Campus</label><select class="form-select" id="campus_id" name="campus_id" required><option value="">Select campus</option><?php foreach ($campuses as $campus): ?><option value="<?php echo (int)$campus['campus_id']; ?>"><?php echo htmlspecialchars((string)$campus['campus_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                          <div><label class="form-label" for="college_id">College</label><select class="form-select" id="college_id" name="college_id" required disabled><option value="">Select college</option></select></div>
                          <div><label class="form-label" for="program_id">Program</label><select class="form-select" id="program_id" name="program_id" required disabled><option value="">Select program</option></select></div>
                          <div><label class="form-label" for="ay_id">Academic Year</label><select class="form-select" id="ay_id" name="ay_id" required><option value="">Select academic year</option><?php foreach ($academicYears as $academicYear): ?><option value="<?php echo (int)$academicYear['ay_id']; ?>" <?php echo $defaultAyId === (int)$academicYear['ay_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$academicYear['ay'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                          <div><label class="form-label" for="semester">Semester</label><select class="form-select" id="semester" name="semester" required><?php foreach ($semesterOptions as $semesterValue => $semesterLabel): ?><option value="<?php echo (int)$semesterValue; ?>" <?php echo $defaultSemester === (int)$semesterValue ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$semesterLabel, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                          <div><label class="form-label" for="year_level">Year Level</label><select class="form-select" id="year_level" name="year_level" required disabled><option value="">Select year level</option></select></div>
                          <div><label class="form-label" for="section_suffix">Section</label><select class="form-select" id="section_suffix" name="section_suffix" required disabled><option value="">Select section</option></select></div>
                        </div>

                        <div class="mb-3">
                          <label for="exam_permits_csv" class="form-label">CSV file</label>
                          <input type="file" class="form-control" id="exam_permits_csv" name="exam_permits_csv" accept=".csv,text/csv" required />
                        </div>

                        <div class="upload-note p-3 mb-3">
                          <div class="fw-semibold mb-2">Import rules</div>
                          <ul class="ps-3 mb-0">
                            <li>The CSV must match the old roster layout like the file you attached.</li>
                            <li>The selected campus, college, program, academic year, semester, year level, and section drive the linking and validation.</li>
                            <li>Section choices now run from <code>A</code> to <code>I</code>; missing scoped sections are created automatically during upload.</li>
                            <li>Students are inserted into <code>tbl_student_management</code> once only, then refreshed on later uploads.</li>
                            <li>Subject rows are stored in the renamed enrolled-subjects table, so one student can have many subject records.</li>
                            <li>The selected section is now the source of truth; the CSV section is checked only to catch mismatches.</li>
                            <li>Subjects resolve through <code>tbl_prospectus_offering</code> first, then fall back to <code>tbl_subject_masterlist</code> if needed.</li>
                            <li>Instructor must map to <code>tbl_faculty</code>; room tries to map to <code>tbl_rooms</code> inside the selected college scope.</li>
                          </ul>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                          <button type="submit" class="btn btn-primary" <?php echo $studentManagementPageError !== '' ? 'disabled' : ''; ?>><i class="bx bx-upload me-1"></i> Upload Backward Encoding CSV</button>
                          <a href="directory.php" class="btn btn-outline-secondary">View Student Master</a>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <div class="col-lg-5">
                  <div class="card section-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">Latest Import</h5>
                      <p class="text-muted mb-0">Most recent enrolled-subject batch currently stored in the rebuilt student module.</p>
                    </div>
                    <div class="card-body">
                      <?php if ($hasLatestImport): ?>
                        <div class="row g-3">
                          <div class="col-sm-6"><div class="summary-label">Campus</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['campus_name'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">College</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['college_name'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">Program</div><div class="fw-semibold"><?php echo htmlspecialchars(trim((string)$latestImport['program_code'] . ' - ' . (string)$latestImport['program_name']), ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">Term</div><div class="fw-semibold"><?php echo htmlspecialchars(trim((string)$latestImport['academic_year_label'] . ' ' . (string)$latestImport['semester_label']), ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">Year Level</div><div class="fw-semibold"><?php echo (int)($latestImport['year_level'] ?? 0) > 0 ? 'Year ' . (int)$latestImport['year_level'] : 'Not set'; ?></div></div>
                          <div class="col-12"><div class="summary-label">Section</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['section_display'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-12"><div class="summary-label">Subject</div><div class="fw-semibold"><?php echo htmlspecialchars(trim((string)$latestImport['subject_code'] . ' - ' . (string)$latestImport['descriptive_title']), ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">Faculty</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['faculty_name'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">Room</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['room_name'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-12"><div class="summary-label">Schedule</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['schedule_text'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">Students</div><div class="fw-semibold"><?php echo number_format((int)$latestImport['student_count']); ?></div></div>
                          <div class="col-sm-6"><div class="summary-label">Imported</div><div class="fw-semibold"><?php echo htmlspecialchars(student_management_format_datetime((string)$latestImport['imported_at']), ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-12"><div class="summary-label">Source File</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['source_file_name'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                          <div class="col-12">
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete the latest uploaded batch and restore the previous archived batch if one exists?');">
                              <input type="hidden" name="delete_import_batch" value="1" />
                              <input type="hidden" name="import_batch_key" value="<?php echo htmlspecialchars((string)$latestImport['import_batch_key'], ENT_QUOTES, 'UTF-8'); ?>" />
                              <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bx bx-trash me-1"></i> Delete Latest Batch</button>
                            </form>
                          </div>
                          <?php if (!empty($recentImports)): ?>
                            <div class="col-12">
                              <div class="summary-label">Recent Uploaded Files</div>
                              <div class="border rounded-3 p-3">
                                <?php foreach ($recentImports as $recentIndex => $recentImport): ?>
                                  <div class="d-flex justify-content-between align-items-start gap-3 py-2<?php echo $recentIndex < count($recentImports) - 1 ? ' border-bottom' : ''; ?>">
                                    <div>
                                      <div class="fw-semibold">
                                        <?php echo htmlspecialchars((string)$recentImport['source_file_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ((int)($recentImport['is_active'] ?? 0) === 1): ?>
                                          <span class="badge bg-label-success ms-1">Current</span>
                                        <?php else: ?>
                                          <span class="badge bg-label-secondary ms-1">Archived</span>
                                        <?php endif; ?>
                                      </div>
                                      <div class="small text-muted">
                                        <?php
                                          echo htmlspecialchars(
                                              trim(implode(' | ', array_filter([
                                                  (string)$recentImport['program_code'],
                                                  (string)$recentImport['section_display'],
                                                  (string)$recentImport['subject_code'],
                                                  trim((string)$recentImport['academic_year_label'] . ' ' . (string)$recentImport['semester_label']),
                                              ]))),
                                              ENT_QUOTES,
                                              'UTF-8'
                                          );
                                        ?>
                                      </div>
                                    </div>
                                    <div class="text-end">
                                      <div class="small fw-semibold"><?php echo number_format((int)$recentImport['student_count']); ?> rows</div>
                                      <div class="small text-muted"><?php echo htmlspecialchars(student_management_format_datetime((string)$recentImport['imported_at']), ENT_QUOTES, 'UTF-8'); ?></div>
                                      <form method="post" class="mt-2" onsubmit="return confirm('Delete this uploaded batch? If it is the current batch, the newest archived batch for the same subject-section will be restored automatically.');">
                                        <input type="hidden" name="delete_import_batch" value="1" />
                                        <input type="hidden" name="import_batch_key" value="<?php echo htmlspecialchars((string)$recentImport['import_batch_key'], ENT_QUOTES, 'UTF-8'); ?>" />
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete Batch</button>
                                      </form>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-muted">No backward-encoding CSV has been uploaded yet.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php include '../../footer.php'; ?>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script>
      (function () {
        const colleges = <?php echo json_encode($colleges, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const programs = <?php echo json_encode($programs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const yearLevels = [1, 2, 3, 4, 5, 6];
        const sectionSuffixes = ["A", "B", "C", "D", "E", "F", "G", "H", "I"];
        const campusField = document.getElementById("campus_id");
        const collegeField = document.getElementById("college_id");
        const programField = document.getElementById("program_id");
        const ayField = document.getElementById("ay_id");
        const semesterField = document.getElementById("semester");
        const yearLevelField = document.getElementById("year_level");
        const sectionField = document.getElementById("section_suffix");

        function resetSelect(select, placeholder) {
          select.innerHTML = '<option value="">' + placeholder + '</option>';
          select.disabled = true;
        }

        function fillSelect(select, items, valueKey, labelBuilder) {
          items.forEach(function (item) {
            const option = document.createElement("option");
            option.value = String(item[valueKey] || "");
            option.textContent = labelBuilder(item);
            select.appendChild(option);
          });
          select.disabled = items.length === 0;
        }

        function resetSectionScope() {
          resetSelect(yearLevelField, "Select year level");
          resetSelect(sectionField, "Select section");
        }

        function getSelectedProgram() {
          const programId = Number(programField.value || 0);
          return programs.find(function (program) {
            return Number(program.program_id || 0) === programId;
          });
        }

        function syncPrograms() {
          const collegeId = Number(collegeField.value || 0);
          resetSelect(programField, "Select program");
          resetSectionScope();
          if (collegeId <= 0) {
            return;
          }

          const filteredPrograms = programs.filter(function (program) {
            return Number(program.program_id || 0) > 0 && Number(program.college_id || 0) === collegeId;
          });

          fillSelect(programField, filteredPrograms, "program_id", function (program) {
            return String(program.program_code || "") + " - " + String(program.source_program_name || program.program_name || "");
          });
        }

        function syncColleges() {
          const campusId = Number(campusField.value || 0);
          resetSelect(collegeField, "Select college");
          resetSelect(programField, "Select program");
          resetSectionScope();
          if (campusId <= 0) {
            return;
          }

          const filteredColleges = colleges.filter(function (college) {
            return Number(college.campus_id || 0) === campusId;
          });

          fillSelect(collegeField, filteredColleges, "college_id", function (college) {
            return String(college.college_name || "");
          });
        }

        function syncYearLevels() {
          resetSectionScope();
          if (Number(programField.value || 0) <= 0 || Number(ayField.value || 0) <= 0 || Number(semesterField.value || 0) <= 0) {
            return;
          }

          fillSelect(yearLevelField, yearLevels.map(function (yearLevel) {
            return { year_level: yearLevel };
          }), "year_level", function (item) {
            return "Year " + String(item.year_level || "");
          });
        }

        function syncSections() {
          resetSelect(sectionField, "Select section");

          const yearLevel = Number(yearLevelField.value || 0);
          const selectedProgram = getSelectedProgram();
          if (yearLevel <= 0 || !selectedProgram) {
            return;
          }

          fillSelect(sectionField, sectionSuffixes.map(function (suffix) {
            return { section_suffix: suffix };
          }), "section_suffix", function (section) {
            return String(selectedProgram.program_code || "") + " " + String(yearLevel) + String(section.section_suffix || "");
          });
        }

        campusField.addEventListener("change", syncColleges);
        collegeField.addEventListener("change", syncPrograms);
        programField.addEventListener("change", syncYearLevels);
        ayField.addEventListener("change", syncYearLevels);
        semesterField.addEventListener("change", syncYearLevels);
        yearLevelField.addEventListener("change", syncSections);
      })();
    </script>
  </body>
</html>
