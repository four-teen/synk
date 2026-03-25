<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_students'])) {
    if ($studentManagementPageError === '') {
        try {
            if (!isset($_FILES['student_xlsx'])) {
                throw new RuntimeException('Select an XLSX file to upload.');
            }

            $uploadedFile = $_FILES['student_xlsx'];
            $originalName = trim((string)($uploadedFile['name'] ?? ''));
            $tmpName = (string)($uploadedFile['tmp_name'] ?? '');
            $errorCode = (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new RuntimeException(student_management_upload_error_message($errorCode));
            }

            if ($originalName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('The uploaded file could not be processed.');
            }

            if (strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
                throw new RuntimeException('Only XLSX workbooks are supported for student uploads.');
            }

            $importSummary = synk_student_management_import_students(
                $conn,
                $tmpName,
                $originalName,
                (int)($_SESSION['user_id'] ?? 0)
            );

            student_management_push_flash(
                'success',
                sprintf(
                    'Imported %s students for %s %s.',
                    number_format((int)$importSummary['inserted_rows']),
                    (string)$importSummary['academic_year_label'],
                    (string)$importSummary['semester_label']
                ),
                sprintf(
                    'Programs detected: %s. Year levels detected: %s. Replaced previous rows for the same term batch: %s.',
                    number_format((int)$importSummary['program_count']),
                    number_format((int)$importSummary['year_level_count']),
                    number_format((int)$importSummary['deleted_rows'])
                )
            );
        } catch (Throwable $e) {
            student_management_push_flash(
                'danger',
                $e->getMessage(),
                'The workbook must match the LIST sheet structure from the registrar export.'
            );
        }
    } else {
        student_management_push_flash(
            'danger',
            $studentManagementPageError,
            'The module schema could not be prepared, so the upload was skipped.'
        );
    }

    student_management_redirect('upload.php');
}

$flash = student_management_pull_flash();
$summary = [
    'total_students' => 0,
    'total_programs' => 0,
    'total_terms' => 0,
    'unassigned_students' => 0,
    'last_uploaded_at' => '',
];
$latestBatch = [
    'academic_year_label' => '',
    'semester_label' => '',
    'college_name' => '',
    'campus_name' => '',
    'student_count' => 0,
    'program_count' => 0,
    'year_level_count' => 0,
    'last_uploaded_at' => '',
];

if ($studentManagementPageError === '') {
    try {
        $summary = synk_student_management_summary($conn);
        $latestBatch = synk_student_management_latest_batch($conn);
    } catch (Throwable $e) {
        $studentManagementPageError = $e->getMessage();
    }
}

$hasLatestBatch = $latestBatch['academic_year_label'] !== '' && $latestBatch['semester_label'] !== '';
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
    <title>Upload Student Data | Synk</title>

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
                      <span class="batch-chip mb-3">Dedicated Upload Page</span>
                      <h3 class="fw-bold mb-2 text-primary">Upload student workbooks separately from the dashboard and directory.</h3>
                      <p class="text-muted mb-0">
                        This page only handles registrar-style XLSX uploads. The dashboard stays focused on summary analytics,
                        while this screen manages import validation and batch replacement.
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
                  <div class="fw-semibold">Student management is unavailable.</div>
                  <div class="small mt-1"><?php echo htmlspecialchars($studentManagementPageError, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
              <?php endif; ?>

              <div class="row g-4">
                <div class="col-lg-7">
                  <div class="card section-card h-100">
                    <div class="card-header">
                      <h5 class="mb-1">Upload Student Workbook</h5>
                      <p class="text-muted mb-0">Import a new batch into `tbl_student_management` without affecting other modules.</p>
                    </div>
                    <div class="card-body">
                      <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="upload_students" value="1" />

                        <div class="mb-3">
                          <label for="student_xlsx" class="form-label">XLSX file</label>
                          <input
                            type="file"
                            class="form-control"
                            id="student_xlsx"
                            name="student_xlsx"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            required
                          />
                        </div>

                        <div class="upload-note p-3 mb-3">
                          <div class="fw-semibold mb-2">Workbook expectations</div>
                          <ul class="ps-3 mb-0">
                            <li>The parser reads the <strong>LIST</strong> sheet.</li>
                            <li>Name columns are expected as LASTNAME, FIRSTNAME, MIDDLENAME, and optional suffix.</li>
                            <li>`year_level` is derived from the year heading and `program_id` stays at `0` by default.</li>
                            <li>`email_address` is generated as `firstnamelastname@sksu.edu.ph`.</li>
                            <li>Existing rows are replaced only for the same academic year, semester, college, and campus batch.</li>
                          </ul>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                          <button type="submit" class="btn btn-primary" <?php echo $studentManagementPageError !== '' ? 'disabled' : ''; ?>>
                            <i class="bx bx-upload me-1"></i> Upload Student Data
                          </button>
                          <a href="directory.php" class="btn btn-outline-secondary">View Imported Records</a>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <div class="col-lg-5">
                  <div class="card section-card mb-4">
                    <div class="card-header">
                      <h5 class="mb-1">Latest Imported Batch</h5>
                      <p class="text-muted mb-0">Most recent student batch currently loaded into the separate module.</p>
                    </div>
                    <div class="card-body">
                      <?php if ($hasLatestBatch): ?>
                        <div class="mb-3">
                          <div class="fw-semibold"><?php echo htmlspecialchars((string)$latestBatch['academic_year_label'], ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string)$latestBatch['semester_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                          <div class="text-muted small"><?php echo htmlspecialchars((string)$latestBatch['college_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                          <div class="text-muted small"><?php echo htmlspecialchars((string)$latestBatch['campus_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="row g-3">
                          <div class="col-6">
                            <div class="border rounded-3 p-3 h-100">
                              <div class="small text-muted">Students</div>
                              <div class="fw-bold fs-4"><?php echo number_format((int)$latestBatch['student_count']); ?></div>
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="border rounded-3 p-3 h-100">
                              <div class="small text-muted">Programs</div>
                              <div class="fw-bold fs-4"><?php echo number_format((int)$latestBatch['program_count']); ?></div>
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="border rounded-3 p-3 h-100">
                              <div class="small text-muted">Year Levels</div>
                              <div class="fw-bold fs-4"><?php echo number_format((int)$latestBatch['year_level_count']); ?></div>
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="border rounded-3 p-3 h-100">
                              <div class="small text-muted">Uploaded</div>
                              <div class="fw-semibold"><?php echo htmlspecialchars(student_management_format_datetime((string)$latestBatch['last_uploaded_at']), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                          </div>
                        </div>
                      <?php else: ?>
                        <div class="alert alert-light border mb-0">
                          No student batch has been uploaded yet.
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="card section-card">
                    <div class="card-header">
                      <h5 class="mb-1">Module Totals</h5>
                      <p class="text-muted mb-0">Quick status view of the student-management storage.</p>
                    </div>
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>Total students</span>
                        <strong><?php echo number_format((int)$summary['total_students']); ?></strong>
                      </div>
                      <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>Total programs</span>
                        <strong><?php echo number_format((int)$summary['total_programs']); ?></strong>
                      </div>
                      <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>Total terms</span>
                        <strong><?php echo number_format((int)$summary['total_terms']); ?></strong>
                      </div>
                      <div class="d-flex justify-content-between align-items-center py-2">
                        <span>Program ID 0 rows</span>
                        <strong><?php echo number_format((int)$summary['unassigned_students']); ?></strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php include '../../footer.php'; ?>

            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>

      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
  </body>
</html>
