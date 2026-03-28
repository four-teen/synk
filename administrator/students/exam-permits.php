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
        'Only CSV files that match the class roster export are supported on this page.',
        'The CSV file does not contain the expected class roster layout.',
        'The CSV file does not match the expected class roster student list header.',
        'No student rows were found in the uploaded CSV file.',
    ];

    if (in_array($message, $csvHints, true)) {
        return 'The CSV must match the current class roster layout with Subject/Section, Instructor(s), Building/Room, and Student\'s Name columns.';
    }

    return 'The import was rolled back before completion. Check the student data that matches this roster and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_exam_permits_csv'])) {
    if ($studentManagementPageError === '') {
        try {
            synk_student_management_ensure_exam_permits_schema($conn);

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
                throw new RuntimeException('Only CSV files that match the class roster export are supported on this page.');
            }

            $summary = synk_student_management_import_exam_permits_csv(
                $conn,
                $tmpName,
                $originalName,
                (int)($_SESSION['user_id'] ?? 0)
            );

            student_management_push_flash(
                'success',
                sprintf(
                    'Imported %s roster rows for %s.',
                    number_format((int)$summary['student_count']),
                    (string)$summary['subject_section_raw']
                ),
                sprintf(
                    'Matched existing students: %s. Refreshed existing student records: %s. Added missing students: %s. Subject ID: %s. Program ID: %s. Faculty ID: %s. Replaced previous section rows: %s.',
                    number_format((int)$summary['matched_students']),
                    number_format((int)$summary['updated_students']),
                    number_format((int)$summary['inserted_students']),
                    number_format((int)$summary['subject_id']),
                    number_format((int)$summary['program_id']),
                    number_format((int)$summary['faculty_id']),
                    number_format((int)$summary['deleted_rows'])
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
    'report_name' => '',
    'campus_name' => '',
    'subject_section_raw' => '',
    'descriptive_title' => '',
    'faculty_name' => '',
    'room_name' => '',
    'class_schedule_text' => '',
    'student_count' => 0,
    'imported_at' => '',
    'subject_id' => 0,
    'program_id' => 0,
    'faculty_id' => 0,
];

if ($studentManagementPageError === '') {
    try {
        synk_student_management_ensure_exam_permits_schema($conn);
        $latestImport = synk_student_management_exam_permits_latest_batch($conn);
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
    <title>Class Roster CSV | Synk</title>

    <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>

    <style>
      .upload-hero {
        border: 0;
        border-radius: 1.25rem;
        background: linear-gradient(140deg, #eef2ff 0%, #ffffff 48%, #f8fbff 100%);
      }

      .section-card {
        border: 0;
        border-radius: 1.1rem;
        box-shadow: 0 16px 35px rgba(15, 23, 42, 0.08);
      }

      .upload-note {
        border-radius: 1rem;
        border: 1px dashed rgba(105, 108, 255, 0.35);
        background: #f8f9ff;
      }

      .batch-chip {
        display: inline-flex;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #5561d7;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
      }

      .summary-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8592a3;
        font-weight: 700;
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
              <div class="card upload-hero mb-4">
                <div class="card-body p-4 p-lg-5">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                      <span class="batch-chip mb-3">Dedicated CSV Page</span>
                      <h3 class="fw-bold mb-2 text-primary">Upload the class roster CSV into a separate student-management table.</h3>
                      <p class="text-muted mb-0">
                        This page parses the attached CSV layout, links subject, program, faculty, room, and schedule details,
                        then updates or inserts matching students inside the separate student module only.
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
                  <div class="fw-semibold">CSV import is unavailable.</div>
                  <div class="small mt-1"><?php echo htmlspecialchars($studentManagementPageError, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
              <?php endif; ?>

              <div class="row g-4">
                <div class="col-lg-7">
                  <div class="card section-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">Upload Class Roster CSV</h5>
                      <p class="text-muted mb-0">Import one subject-section CSV without touching the stable scheduling tables.</p>
                    </div>
                    <div class="card-body">
                      <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="upload_exam_permits_csv" value="1" />

                        <div class="mb-3">
                          <label for="exam_permits_csv" class="form-label">CSV file</label>
                          <input
                            type="file"
                            class="form-control"
                            id="exam_permits_csv"
                            name="exam_permits_csv"
                            accept=".csv,text/csv"
                            required
                          />
                        </div>

                        <div class="upload-note p-3 mb-3">
                          <div class="fw-semibold mb-2">CSV expectations</div>
                          <ul class="ps-3 mb-0">
                            <li>The parser expects the current CSV header lines before the student list.</li>
                            <li>`Subject/Section` is split into `subject_code`, `program_id`, and a separate `section_name`.</li>
                            <li>`Instructor(s)` is matched against `tbl_faculty` by given name.</li>
                            <li>`Building/Room` is stored as plain text, while `Class Schedule` is stored raw and in separate slots.</li>
                            <li>If a student name already exists in `tbl_student_management`, the student record is refreshed there using the CSV data.</li>
                            <li>If the student does not exist yet, the student is inserted into `tbl_student_management` and linked to the new roster table.</li>
                          </ul>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                          <button type="submit" class="btn btn-warning" <?php echo $studentManagementPageError !== '' ? 'disabled' : ''; ?>>
                            <i class="bx bx-spreadsheet me-1"></i> Upload Class Roster CSV
                          </button>
                          <a href="index.php" class="btn btn-outline-secondary">Return to Dashboard</a>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <div class="col-lg-5">
                  <div class="card section-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">Latest CSV Import</h5>
                      <p class="text-muted mb-0">Most recent class roster currently stored in the new student-management import table.</p>
                    </div>
                    <div class="card-body">
                      <?php if ($hasLatestImport): ?>
                        <div class="mb-3">
                          <div class="summary-label">Subject / Section</div>
                          <div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['subject_section_raw'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="row g-3">
                          <div class="col-sm-6">
                            <div class="summary-label">Campus</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['campus_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                          <div class="col-sm-6">
                            <div class="summary-label">Students</div>
                            <div class="fw-semibold"><?php echo number_format((int)$latestImport['student_count']); ?></div>
                          </div>
                          <div class="col-sm-6">
                            <div class="summary-label">Faculty</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['faculty_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                          <div class="col-sm-6">
                            <div class="summary-label">Room</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['room_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                          <div class="col-12">
                            <div class="summary-label">Schedule</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['class_schedule_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                          <div class="col-sm-4">
                            <div class="summary-label">Subject ID</div>
                            <div class="fw-semibold"><?php echo number_format((int)$latestImport['subject_id']); ?></div>
                          </div>
                          <div class="col-sm-4">
                            <div class="summary-label">Program ID</div>
                            <div class="fw-semibold"><?php echo number_format((int)$latestImport['program_id']); ?></div>
                          </div>
                          <div class="col-sm-4">
                            <div class="summary-label">Faculty ID</div>
                            <div class="fw-semibold"><?php echo number_format((int)$latestImport['faculty_id']); ?></div>
                          </div>
                          <div class="col-12">
                            <div class="summary-label">Imported</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars(student_management_format_datetime((string)$latestImport['imported_at']), ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                          <div class="col-12">
                            <div class="summary-label">Source File</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$latestImport['source_file_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                        </div>
                      <?php else: ?>
                        <div class="text-muted">
                          No class roster CSV has been uploaded yet.
                        </div>
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
  </body>
</html>
