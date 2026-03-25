<?php
require_once __DIR__ . '/bootstrap.php';

$flash = student_management_pull_flash();
$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'year_level' => max(0, (int)($_GET['year_level'] ?? 0)),
    'source_program_name' => trim((string)($_GET['source_program_name'] ?? '')),
    'academic_year_label' => trim((string)($_GET['academic_year_label'] ?? '')),
    'semester_label' => trim((string)($_GET['semester_label'] ?? '')),
];

$programOptions = [];
$termOptions = [];
$academicYearOptions = [];
$semesterOptions = [];
$records = [];
$totalRecords = 0;
$perPage = 100;
$requestedPage = max(1, (int)($_GET['page'] ?? 1));
$currentPage = $requestedPage;
$totalPages = 1;

if ($studentManagementPageError === '') {
    try {
        $programOptions = synk_student_management_distinct_programs($conn);
        $termOptions = synk_student_management_distinct_terms($conn);

        foreach ($termOptions as $termOption) {
            $academicYearLabel = trim((string)($termOption['academic_year_label'] ?? ''));
            $semesterLabel = trim((string)($termOption['semester_label'] ?? ''));

            if ($academicYearLabel !== '') {
                $academicYearOptions[$academicYearLabel] = $academicYearLabel;
            }
            if ($semesterLabel !== '') {
                $semesterOptions[$semesterLabel] = $semesterLabel;
            }
        }

        $totalRecords = synk_student_management_count_records($conn, $filters);
        $totalPages = max(1, (int)ceil($totalRecords / $perPage));
        $currentPage = min($requestedPage, $totalPages);
        $records = synk_student_management_fetch_records(
            $conn,
            $filters,
            $perPage,
            ($currentPage - 1) * $perPage
        );
    } catch (Throwable $e) {
        $studentManagementPageError = $e->getMessage();
    }
}

$yearOptions = [
    1 => synk_student_management_year_label(1),
    2 => synk_student_management_year_label(2),
    3 => synk_student_management_year_label(3),
    4 => synk_student_management_year_label(4),
    5 => synk_student_management_year_label(5),
];
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
    <title>Student Directory | Synk</title>

    <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>

    <style>
      .directory-hero {
        border: 0;
        border-radius: 1.25rem;
        background: linear-gradient(140deg, #ffffff 0%, #f8fbff 50%, #eef5ff 100%);
      }

      .section-card {
        border: 0;
        border-radius: 1.1rem;
        box-shadow: 0 16px 35px rgba(15, 23, 42, 0.08);
      }

      .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
      }

      .student-table thead th {
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        white-space: nowrap;
      }

      .student-table td {
        vertical-align: middle;
        white-space: nowrap;
      }

      .student-table td.program-cell {
        min-width: 280px;
        white-space: normal;
      }

      .student-table td.email-cell {
        min-width: 220px;
      }

      .empty-state {
        padding: 3rem 1rem;
        text-align: center;
        color: #6b7280;
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
              <div class="card directory-hero mb-4">
                <div class="card-body p-4 p-lg-5">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                      <span class="badge bg-label-primary mb-3">Separate Directory Page</span>
                      <h3 class="fw-bold mb-2 text-primary">Browse imported student records without mixing upload controls into the dashboard.</h3>
                      <p class="text-muted mb-0">
                        Filter by year level, program, academic year, and semester. This page is dedicated to record review and future student-management actions.
                      </p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                      <a href="index.php" class="btn btn-outline-primary">Back to Dashboard</a>
                      <a href="upload.php" class="btn btn-primary">Upload New Batch</a>
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

              <div class="card section-card mb-4">
                <div class="card-header">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                    <div>
                      <h5 class="mb-1">Directory Filters</h5>
                      <p class="text-muted mb-0">Search imported student records in the separate module.</p>
                    </div>
                    <div class="text-muted small">
                      Showing <?php echo number_format((int)$totalRecords); ?> matching records
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <form method="get">
                    <div class="filter-grid">
                      <div>
                        <label class="form-label" for="search">Search</label>
                        <input
                          type="text"
                          class="form-control"
                          id="search"
                          name="search"
                          value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>"
                          placeholder="Name, email, or program"
                        />
                      </div>

                      <div>
                        <label class="form-label" for="year_level">Year Level</label>
                        <select class="form-select" id="year_level" name="year_level">
                          <option value="0">All year levels</option>
                          <?php foreach ($yearOptions as $yearValue => $yearLabel): ?>
                            <option value="<?php echo $yearValue; ?>" <?php echo (int)$filters['year_level'] === (int)$yearValue ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($yearLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div>
                        <label class="form-label" for="academic_year_label">Academic Year</label>
                        <select class="form-select" id="academic_year_label" name="academic_year_label">
                          <option value="">All academic years</option>
                          <?php foreach ($academicYearOptions as $academicYearLabel): ?>
                            <option value="<?php echo htmlspecialchars($academicYearLabel, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['academic_year_label'] === $academicYearLabel ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($academicYearLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div>
                        <label class="form-label" for="semester_label">Semester</label>
                        <select class="form-select" id="semester_label" name="semester_label">
                          <option value="">All semesters</option>
                          <?php foreach ($semesterOptions as $semesterLabel): ?>
                            <option value="<?php echo htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['semester_label'] === $semesterLabel ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div>
                        <label class="form-label" for="source_program_name">Program</label>
                        <select class="form-select" id="source_program_name" name="source_program_name">
                          <option value="">All programs</option>
                          <?php foreach ($programOptions as $programName): ?>
                            <option value="<?php echo htmlspecialchars($programName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['source_program_name'] === $programName ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($programName, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                      <button type="submit" class="btn btn-primary">
                        <i class="bx bx-filter-alt me-1"></i> Apply Filters
                      </button>
                      <a href="directory.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                  </form>
                </div>
              </div>

              <div class="card section-card">
                <div class="card-header">
                  <h5 class="mb-1">Student Directory Management</h5>
                  <p class="text-muted mb-0">Imported student rows with separated names, year level, generated email, and default program mapping.</p>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-hover align-middle student-table">
                      <thead>
                        <tr>
                          <th>AY</th>
                          <th>Semester</th>
                          <th>Program</th>
                          <th>Year</th>
                          <th>Student No.</th>
                          <th>Last Name</th>
                          <th>First Name</th>
                          <th>Middle Name</th>
                          <th>Suffix</th>
                          <th>Email Address</th>
                          <th>Program ID</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!empty($records)): ?>
                          <?php foreach ($records as $record): ?>
                            <tr>
                              <td><?php echo htmlspecialchars((string)($record['academic_year_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?php echo htmlspecialchars((string)($record['semester_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td class="program-cell"><?php echo htmlspecialchars((string)($record['source_program_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td>
                                <span class="badge bg-label-secondary">
                                  <?php echo htmlspecialchars(synk_student_management_year_label((int)($record['year_level'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                              </td>
                              <td><?php echo number_format((int)($record['student_number'] ?? 0)); ?></td>
                              <td><?php echo htmlspecialchars((string)($record['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?php echo htmlspecialchars((string)($record['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?php echo htmlspecialchars((string)($record['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?php echo htmlspecialchars((string)($record['suffix_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td class="email-cell"><?php echo htmlspecialchars((string)($record['email_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?php echo number_format((int)($record['program_id'] ?? 0)); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="11" class="empty-state">
                              <i class="bx bx-spreadsheet fs-1 d-block mb-2 text-primary"></i>
                              No student records matched the current filters.
                            </td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <?php if ($totalPages > 1): ?>
                    <nav aria-label="Student directory pagination" class="mt-4">
                      <ul class="pagination mb-0">
                        <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                          <a class="page-link" href="<?php echo htmlspecialchars(student_management_build_page_url('directory.php', $currentPage - 1), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                        </li>

                        <?php
                        $pageStart = max(1, $currentPage - 2);
                        $pageEnd = min($totalPages, $currentPage + 2);
                        for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++):
                        ?>
                          <li class="page-item <?php echo $pageNumber === $currentPage ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(student_management_build_page_url('directory.php', $pageNumber), ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo $pageNumber; ?>
                            </a>
                          </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                          <a class="page-link" href="<?php echo htmlspecialchars(student_management_build_page_url('directory.php', $currentPage + 1), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                        </li>
                      </ul>
                    </nav>
                  <?php endif; ?>
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
