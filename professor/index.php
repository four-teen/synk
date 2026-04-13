<?php
session_start();
ob_start();

include '../backend/db.php';
require_once '../backend/professor_portal_helper.php';

synk_professor_require_login($conn);

function professor_role_label(string $role): string
{
    $role = strtolower(trim($role));
    if ($role === 'admin') {
        return 'Administrator';
    }

    if ($role === 'scheduler') {
        return 'Scheduler';
    }

    if ($role === 'professor') {
        return 'Professor';
    }

    return strtoupper($role);
}

$professorPortalContext = synk_professor_resolve_portal_context($conn);
$facultyId = (int)($professorPortalContext['faculty_id'] ?? 0);
$facultyLink = is_array($professorPortalContext['faculty_link'] ?? null)
    ? $professorPortalContext['faculty_link']
    : null;
$facultyName = trim((string)($professorPortalContext['faculty_name'] ?? 'Professor'));
$accountName = trim((string)($professorPortalContext['account_name'] ?? 'Professor'));
$professorEmail = trim((string)($professorPortalContext['email'] ?? (string)($_SESSION['email'] ?? '')));
$facultyIsLinked = !empty($professorPortalContext['faculty_is_linked']);
$facultyIsActive = !array_key_exists('faculty_is_active', $professorPortalContext)
    || !empty($professorPortalContext['faculty_is_active']);
$currentTerm = synk_fetch_current_academic_term($conn);

$workloadTermOptions = $facultyId > 0
    ? synk_professor_fetch_workload_term_options($conn, $facultyId)
    : [];
$selectedPreviewTerm = null;

foreach ($workloadTermOptions as $termOption) {
    if ((int)($termOption['ay_id'] ?? 0) === (int)($currentTerm['ay_id'] ?? 0) && (int)($termOption['semester'] ?? 0) === (int)($currentTerm['semester'] ?? 0)) {
        $selectedPreviewTerm = $termOption;
        break;
    }
}

if ($selectedPreviewTerm === null && !empty($workloadTermOptions)) {
    $selectedPreviewTerm = $workloadTermOptions[0];
}

$previewAyId = (int)($selectedPreviewTerm['ay_id'] ?? 0);
$previewSemester = (int)($selectedPreviewTerm['semester'] ?? 0);
$previewTermLabel = trim((string)($selectedPreviewTerm['term_label'] ?? ''));
$previewRows = ($facultyId > 0 && $previewAyId > 0 && $previewSemester > 0)
    ? synk_professor_fetch_workload_rows($conn, $facultyId, $previewAyId, $previewSemester)
    : [];
$previewRows = array_slice($previewRows, 0, 3);
$previewSubjectCount = (int)($selectedPreviewTerm['workload_count'] ?? 0);
$previewStudentCount = (int)($selectedPreviewTerm['student_count'] ?? 0);
$workloadTermCount = count($workloadTermOptions);

$availableRoles = array_values(array_filter(array_map('strval', (array)($_SESSION['available_roles'] ?? []))));
$otherRoles = array_values(array_filter($availableRoles, static function (string $role): bool {
    return strtolower(trim($role)) !== 'professor';
}));
$otherRolesLabel = empty($otherRoles)
    ? 'Professor only'
    : implode(' + ', array_map('professor_role_label', $otherRoles));

$facultyStatusLabel = 'Faculty linked';
$facultyStatusBadgeClass = 'bg-label-success';
if (!$facultyIsLinked) {
    $facultyStatusLabel = 'Needs faculty link';
    $facultyStatusBadgeClass = 'bg-label-warning';
} elseif (!$facultyIsActive) {
    $facultyStatusLabel = 'Inactive faculty record';
    $facultyStatusBadgeClass = 'bg-label-danger';
}

$workloadUrl = 'workload.php';
if ($previewAyId > 0 && $previewSemester > 0) {
    $workloadUrl .= '?ay_id=' . $previewAyId . '&semester=' . $previewSemester;
}

$professorPortalDisplayName = $facultyName !== '' ? $facultyName : $accountName;
$professorPortalDisplayEmail = $professorEmail;
$professorPortalFacultyStatusLabel = $facultyStatusLabel;
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

    <title>Professor Dashboard | Synk</title>

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
      .professor-dashboard-card,
      .professor-dashboard-hero,
      .professor-dashboard-note {
        border: 1px solid #dce5f1;
        border-radius: 22px;
        box-shadow: 0 18px 38px rgba(67, 89, 113, 0.08);
      }

      .professor-dashboard-hero {
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 55%, #f1f8ef 100%);
      }

      .professor-dashboard-note {
        background: linear-gradient(135deg, #fff8ea 0%, #fffdf6 100%);
        color: #855b16;
      }

      .professor-dashboard-kicker {
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

      .professor-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.85rem;
      }

      .professor-summary-card {
        border: 1px solid #e2e9f2;
        border-radius: 18px;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.88);
      }

      .professor-summary-label {
        display: block;
        color: #7d8ea5;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
      }

      .professor-summary-value {
        display: block;
        margin-top: 0.35rem;
        color: #33475b;
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.55;
      }

      .professor-preview-grid {
        display: grid;
        gap: 0.85rem;
      }

      .professor-preview-entry {
        border: 1px solid #e3eaf3;
        border-radius: 16px;
        background: #fbfdff;
        padding: 1rem;
      }

      .professor-preview-code {
        margin: 0;
        font-size: 0.98rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        color: #25364a;
        text-transform: uppercase;
      }

      .professor-preview-title {
        margin: 0.3rem 0 0;
        color: #53667f;
        line-height: 1.55;
      }

      .professor-preview-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 0.6rem;
        margin-top: 0.9rem;
      }

      .professor-preview-meta-line {
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
        color: #4b5e78;
        font-size: 0.88rem;
        line-height: 1.5;
      }

      .professor-preview-meta-line i {
        color: #6c7bf2;
        font-size: 1rem;
        margin-top: 0.08rem;
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
                <div class="card professor-dashboard-note mb-4">
                  <div class="card-body p-3">
                    Link this professor account to a faculty record so the dashboard and workload page can load the correct enrolled subject records.
                  </div>
                </div>
              <?php endif; ?>

              <div class="card professor-dashboard-hero mb-4">
                <div class="card-body p-4">
                  <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                      <span class="professor-dashboard-kicker">
                        <i class="bx bx-home-circle"></i>
                        Dashboard
                      </span>
                      <h4 class="mt-3 mb-2">Welcome, <?php echo synk_professor_h($facultyName); ?>.</h4>
                      <p class="mb-3 text-muted">
                        The dashboard is better as a quick overview only, while the full subject-by-subject teaching list now lives in the separate Workload page.
                      </p>
                      <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-label-primary"><?php echo synk_professor_h($previewTermLabel !== '' ? $previewTermLabel : ($currentTerm['term_text'] ?? 'Current academic term')); ?></span>
                        <span class="badge <?php echo synk_professor_h($facultyStatusBadgeClass); ?>"><?php echo synk_professor_h($facultyStatusLabel); ?></span>
                        <span class="badge bg-label-info"><?php echo synk_professor_h($otherRolesLabel); ?></span>
                      </div>
                    </div>
                    <div class="col-lg-4 text-center">
                      <img
                        src="../assets/img/illustrations/man-with-laptop-light.png"
                        alt="Professor Dashboard Overview"
                        class="img-fluid"
                        style="max-height: 150px;"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <div class="professor-summary-grid mb-4">
                <div class="professor-summary-card">
                  <span class="professor-summary-label">Linked Faculty</span>
                  <span class="professor-summary-value"><?php echo synk_professor_h($facultyLink['faculty_name'] ?? 'Not linked'); ?></span>
                </div>
                <div class="professor-summary-card">
                  <span class="professor-summary-label">Account Email</span>
                  <span class="professor-summary-value"><?php echo synk_professor_h($professorEmail !== '' ? $professorEmail : 'N/A'); ?></span>
                </div>
                <div class="professor-summary-card">
                  <span class="professor-summary-label">Current Workload</span>
                  <span class="professor-summary-value"><?php echo (int)$previewSubjectCount; ?> subjects this term</span>
                </div>
                <div class="professor-summary-card">
                  <span class="professor-summary-label">Current Students</span>
                  <span class="professor-summary-value"><?php echo (int)$previewStudentCount; ?> students this term</span>
                </div>
                <div class="professor-summary-card">
                  <span class="professor-summary-label">Recorded Terms</span>
                  <span class="professor-summary-value"><?php echo (int)$workloadTermCount; ?> academic terms</span>
                </div>
                <div class="professor-summary-card">
                  <span class="professor-summary-label">Quick Access</span>
                  <span class="professor-summary-value">
                    <a href="<?php echo synk_professor_h($workloadUrl); ?>" class="btn btn-sm btn-primary mt-2">
                      <i class="bx bx-briefcase-alt me-1"></i> Open Workload
                    </a>
                  </span>
                </div>
              </div>

              <div class="card professor-dashboard-card mb-4">
                <div class="card-body p-4">
                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                    <div>
                      <span class="professor-dashboard-kicker">
                        <i class="bx bx-list-ul"></i>
                        Snapshot
                      </span>
                      <h5 class="mt-3 mb-1">Current term workload preview</h5>
                      <p class="text-muted mb-0">
                        This is a small preview from the selected term. Open Workload for the full academic year and semester filtered view.
                      </p>
                    </div>
                    <a href="<?php echo synk_professor_h($workloadUrl); ?>" class="btn btn-outline-primary">
                      <i class="bx bx-right-arrow-alt me-1"></i> View Full Workload
                    </a>
                  </div>

                  <?php if (empty($previewRows)): ?>
                    <div class="text-muted">
                      <?php if (!$facultyIsLinked): ?>
                        No preview can be shown until the account is linked to a faculty record.
                      <?php else: ?>
                        No enrolled subject workload is available yet for the current or latest term.
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div class="professor-preview-grid">
                      <?php foreach ($previewRows as $previewRow): ?>
                        <article class="professor-preview-entry">
                          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div>
                              <h6 class="professor-preview-code"><?php echo synk_professor_h((string)($previewRow['subject_code'] ?? 'NO CODE')); ?></h6>
                              <p class="professor-preview-title"><?php echo synk_professor_h((string)($previewRow['descriptive_title'] ?? 'Untitled subject')); ?></p>
                            </div>
                            <span class="badge bg-label-primary"><?php echo synk_professor_h((string)($previewRow['section_display'] ?? 'Section')); ?></span>
                          </div>

                          <div class="professor-preview-meta">
                            <div class="professor-preview-meta-line">
                              <i class="bx bx-time-five"></i>
                              <span><?php echo synk_professor_h((string)($previewRow['schedule_text'] ?? 'Schedule not available')); ?></span>
                            </div>
                            <div class="professor-preview-meta-line">
                              <i class="bx bx-map"></i>
                              <span><?php echo synk_professor_h((string)($previewRow['room_name'] ?? 'Room not assigned')); ?></span>
                            </div>
                            <div class="professor-preview-meta-line">
                              <i class="bx bx-group"></i>
                              <span><?php echo (int)($previewRow['student_count'] ?? 0); ?> students</span>
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
