<?php
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$currentTerm = synk_fetch_current_academic_term($conn);
$default_ay_id = (int)$currentTerm['ay_id'];
$default_semester = (int)$currentTerm['semester'];

/* ==============================
   BUILD ROOM OPTIONS (UI USE)
============================== */
$roomOptions = "";

?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
  <meta charset="utf-8" />
  <title>Class Scheduling | Synk</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" type="text/css" href="custom_css.css">
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

  <style>
/* =====================================================
   GENERAL (UNCHANGED / SAFE)
===================================================== */
.step-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #6c757d;
}

.schedule-hint {
    font-size: 0.85rem;
    color: #6c757d;
}

.swal-top {
    z-index: 20000 !important;
}

/* Force SweetAlert above Bootstrap modal */
.swal2-container {
    z-index: 30000 !important;
}

/* Prevent modal from stealing focus */
body.swal2-shown .modal {
    filter: blur(1px);
}

/* =====================================================
   ROOM–TIME MATRIX (BASE – KEPT)
===================================================== */
.matrix-table th,
.matrix-table td {
    min-width: 110px;          /* base fallback */
    vertical-align: middle;
}

.matrix-room {
    background: #f8f9fa;
    white-space: nowrap;
}

.matrix-cell {
    border-radius: 6px;
    padding: 8px;
    text-align: center;
    font-size: 0.85rem;
    min-height: 48px;
}

.schedule-group-card {
    border: 1px solid #c9d3ea;
    border-radius: 10px;
}

.schedule-group-header {
    background: #e9f0ff;
    border-bottom: 2px solid #b8c8ea;
    color: #1f2a44;
    font-weight: 700;
}

.schedule-search-shell {
    max-width: 520px;
}

.suggestion-board {
    border: 1px solid #d8e1f2;
    border-radius: 14px;
    background: linear-gradient(180deg, #f9fbff 0%, #f4f8ff 100%);
    padding: 1rem;
    min-height: 0;
}

.suggestion-list {
    display: grid;
    gap: 0.75rem;
}

.suggestion-panel {
    margin-top: 0.85rem;
}

.suggestion-toggle-row {
    display: flex;
    justify-content: flex-start;
}

#scheduleModal .row.g-4.align-items-start > .col-lg-7,
#scheduleModal .row.g-4.align-items-start > .col-lg-5,
#dualScheduleModal .row.g-4.align-items-start > .col-lg-7,
#dualScheduleModal .row.g-4.align-items-start > .col-lg-5 {
    flex: 0 0 100%;
    max-width: 100%;
}

.suggestion-empty {
    border: 1px dashed #c7d4ee;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.75);
    color: #5f6f8d;
    padding: 1rem;
    text-align: center;
    font-size: 0.9rem;
}

.suggestion-card {
    border: 1px solid #d6e0f3;
    border-radius: 12px;
    background: #fff;
    padding: 0.9rem;
    box-shadow: 0 8px 20px rgba(31, 42, 68, 0.06);
}

.suggestion-card.best {
    border-color: #7d8bff;
}

.suggestion-card.strong {
    border-color: #7ac8a0;
}

.suggestion-fit {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.suggestion-fit.best {
    background: #e9edff;
    color: #4a58e8;
}

.suggestion-fit.strong {
    background: #e7f8ee;
    color: #16834d;
}

.suggestion-fit.valid {
    background: #eef1f5;
    color: #5a6578;
}

.suggestion-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    font-size: 0.72rem;
    background: #eef3ff;
    color: #3d5aa8;
    font-weight: 600;
}

.suggestion-slot {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2a44;
}

.suggestion-meta {
    font-size: 0.84rem;
    color: #61708f;
}

.suggestion-reasons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.suggestion-reason {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    background: #f2f5fb;
    color: #53627c;
    font-size: 0.72rem;
}

.matrix-vacant {
    background: #e9ecef;
    color: #6c757d;
}

.matrix-occupied {
    background: #0d6efd;
    color: #fff;
    font-weight: 600;
}

.matrix-entry {
    border-radius: 6px;
    color: #fff;
    padding: 4px 6px;
    margin-bottom: 4px;
    line-height: 1.15;
}

.matrix-entry:last-child {
    margin-bottom: 0;
}

.matrix-occupied .matrix-entry {
    background: rgba(255, 255, 255, 0.14);
}

.matrix-conflict .matrix-entry {
    background: #dc3545;
    color: #fff;
}

.matrix-entry small {
    color: rgba(255, 255, 255, 0.92);
}

.matrix-conflict {
    background: #fff4f4;
    border: 1px solid #ffc7c7;
}

.matrix-day {
    background: #f8f9fa;
    font-weight: 700;
    white-space: nowrap;
    min-width: 70px;
}

/* SUBJECT COLORS (auto-rotated) */
.sub-0 { background: #0d6efd; } /* blue */
.sub-1 { background: #198754; } /* green */
.sub-2 { background: #fd7e14; } /* orange */
.sub-3 { background: #6f42c1; } /* purple */
.sub-4 { background: #20c997; } /* teal */

/* =====================================================
   ROOM–TIME MATRIX (ENHANCEMENTS – SCOPED)
   👉 Scoped to #matrixModal ONLY to avoid conflicts
===================================================== */

/* Compact font sizing */
#matrixModal .matrix-table {
    font-size: 0.72rem;
}

/* Smaller header text */
#matrixModal .matrix-table th {
    font-size: 0.68rem;
    line-height: 1.1;
    white-space: nowrap;
}

/* Reduce padding + width for more columns */
#matrixModal .matrix-table th,
#matrixModal .matrix-table td {
    padding: 6px !important;
    min-width: 90px;     /* override safely */
}

/* Room column slightly wider */
#matrixModal .matrix-room {
    min-width: 140px;
    font-size: 0.75rem;
}

/* Compact cells */
#matrixModal .matrix-cell {
    padding: 6px;
    min-height: 38px;
    font-size: 0.7rem;
    line-height: 1.15;
}

/* Text hierarchy inside occupied cell */
#matrixModal .matrix-cell strong {
    font-size: 0.72rem;
}

#matrixModal .matrix-cell small {
    font-size: 0.65rem;
    opacity: 0.9;
}

/* =====================================================
   STICKY HEADER & ROOM COLUMN (UX BOOST)
===================================================== */

/* Sticky time header */
#matrixModal .matrix-table thead th {
    position: sticky;
    top: 0;
    z-index: 6;
    background: #ffffff;
}

/* Sticky ROOM column */
#matrixModal .matrix-room {
    position: sticky;
    left: 0;
    z-index: 5;
    box-shadow: 2px 0 4px rgba(0,0,0,0.05);
}

/* =====================================================
   MODAL LAYOUT IMPROVEMENTS
===================================================== */
#matrixModal .modal-body {
    padding: 0.75rem;
}

#matrixModal .matrix-shell {
    height: calc(95vh - 92px);
    display: flex;
    flex-direction: column;
    min-height: 0;
}

#matrixModal .matrix-scroll-wrap {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    border: 1px solid #d6e0f0;
    border-radius: 14px;
    background: #fff;
}

#matrixModal .matrix-table {
    width: max-content;
    min-width: 100%;
}
/* =====================================================
   FORCE ROOM–TIME MATRIX TO USE SCREEN WIDTH
   (Bootstrap modal override – SAFE & SCOPED)
===================================================== */

#matrixModal .modal-dialog {
    max-width: 95vw !important;   /* ← THIS IS THE KEY */
    width: 95vw;
    margin-left: auto;
    margin-right: auto;
}

@media (max-width: 992px) {
    #matrixModal .modal-dialog {
        max-width: 100vw !important;
        width: 100vw;
        margin: 0;
    }
}

/* Reduce chrome padding so content expands */
#matrixModal .modal-content {
    height: 92vh;
}

#matrixModal .modal-body {
    padding: 0.5rem;
}

#matrixModal .modal-dialog {
    max-width: min(98vw, 1900px) !important;
    width: min(98vw, 1900px);
    height: 95vh;
    margin: 0.75rem auto;
}

#matrixModal .modal-content {
    height: 100%;
}

#matrixModal .modal-body {
    overflow: hidden;
}

@media (min-width: 1400px) {
    #matrixModal .matrix-table th,
    #matrixModal .matrix-table td {
        min-width: 82px;
    }

    #matrixModal .matrix-room {
        min-width: 165px;
    }
}

@media (max-width: 992px) {
    #matrixModal .modal-dialog {
        max-width: 100vw !important;
        width: 100vw;
        height: 100vh;
        margin: 0;
    }

    #matrixModal .matrix-shell {
        height: calc(100vh - 92px);
    }

    #matrixModal .matrix-scroll-wrap {
        border-radius: 0;
        border-left: 0;
        border-right: 0;
        border-bottom: 0;
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
  <i class="bx bx-time-five me-2"></i> Class Scheduling
</h4>

<p class="text-muted mb-4">
  Schedule classes by defining <strong>day, time, and room</strong> for each offering.
</p>

<!-- FILTERS -->
<div class="card mb-4">
<div class="card-body">
<div class="row g-3">

<div class="col-md-4">
<label class="form-label">Prospectus</label>
<select id="prospectus_id" class="form-select">
<option value="">Select...</option>
<?php
  $prosStmt = $conn->prepare("
    SELECT
      h.prospectus_id,
      h.effective_sy,
      p.program_code,
      p.program_name,
      p.major
    FROM tbl_prospectus_header h
    JOIN tbl_program p ON p.program_id = h.program_id
    WHERE p.college_id = ?
    ORDER BY p.program_name, p.major
  ");
  $prosCollegeId = (int)$_SESSION['college_id'];
  $prosStmt->bind_param("i", $prosCollegeId);
  $prosStmt->execute();
  $q = $prosStmt->get_result();
  while ($r = $q->fetch_assoc()) {

      $label = $r['program_code'] . " — " . $r['program_name'];

      // ✅ Append major ONLY if it exists
      if (!empty($r['major'])) {
          $label .= " major in " . $r['major'];
      }

      $label .= " (SY " . $r['effective_sy'] . ")";

      echo "
          <option value='{$r['prospectus_id']}'>
              {$label}
          </option>
      ";
  }

?>
</select>
</div>

<div class="col-md-4">
<label class="form-label">Academic Year</label>
<select id="ay_id" class="form-select">
<option value="">Select...</option>
<?php
$ayQ = $conn->query("SELECT ay_id, ay FROM tbl_academic_years ORDER BY ay DESC");
while ($ay = $ayQ->fetch_assoc()) {
  $selected = ((int)$ay['ay_id'] === $default_ay_id) ? " selected" : "";
  echo "<option value='{$ay['ay_id']}'{$selected}>{$ay['ay']}</option>";
}
?>
</select>
</div>

<div class="col-md-4">
<label class="form-label">Semester</label>
<select id="semester" class="form-select">
<option value="">Select...</option>
<option value="1"<?= $default_semester === 1 ? ' selected' : '' ?>>First Semester</option>
<option value="2"<?= $default_semester === 2 ? ' selected' : '' ?>>Second Semester</option>
<option value="3"<?= $default_semester === 3 ? ' selected' : '' ?>>Midyear</option>
</select>
</div>

</div>
</div>
</div>

<!-- LIST -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <h5 class="m-0">Class Offerings</h5>
            <small class="text-muted">
              Lecture-only subjects use one schedule entry. Subjects with laboratory units require separate lecture and laboratory schedules.
            </small>
          </div>

          <button class="btn btn-outline-primary btn-sm" id="btnShowMatrix">
            <i class="bx bx-grid-alt me-1"></i> Room–Time Matrix
          </button>
        </div>
    </div>

    <div class="card-body border-bottom py-3">
      <div class="d-flex justify-content-end">
        <div class="schedule-search-shell w-100">
          <label class="form-label mb-2" for="scheduleSubjectSearch">Search Subjects</label>
          <input
            type="text"
            id="scheduleSubjectSearch"
            class="form-control"
            placeholder="Search by subject code, description, or section within the current filters"
          >
        </div>
      </div>
    </div>

    <div class="p-3" id="scheduleListContainer">
      <div class="text-center text-muted py-4">
        Filtered class offerings will load automatically.
      </div>
    </div>
</div>

</div>
<?php include '../footer.php'; ?>
</div>
</div>
</div>
</div>

<!-- MODAL -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header">
    <h5 class="modal-title">Define Class Schedule</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">

    <input type="hidden" id="sched_offering_id">

    <div class="row g-4 align-items-start">
      <div class="col-lg-7">

    <div class="mb-3">
    <strong id="sched_subject_label"></strong><br>
    <small class="text-muted" id="sched_section_label"></small>
    </div>

    <hr>

    <div class="step-label mb-2">Step 1 — When does the class meet?</div>

    <div class="row g-3 mb-3">
    <div class="col-md-6">
    <label class="form-label">Days</label><br>
    <?php
    foreach (['M','T','W','Th','F','S'] as $d) {
      echo "
      <input type='checkbox' class='btn-check sched-day' id='day_$d' value='$d'>
      <label class='btn btn-outline-secondary btn-sm me-1' for='day_$d'>$d</label>
      ";
    }
    ?>
    </div>

    <div class="col-md-3">
    <label class="form-label">Start</label>
    <input type="time" id="sched_time_start" class="form-control" min="07:30" max="17:30">
    </div>

    <div class="col-md-3">
    <label class="form-label">End</label>
    <input type="time" id="sched_time_end" class="form-control" min="07:30" max="17:30">
    </div>
    </div>

    <div class="step-label mb-2">Step 2 — Where is the class held?</div>

    <div class="mb-3">
    <select id="sched_room_id" class="form-select">
    <option value="">Select room...</option>
    </select>
    </div>

    <div class="suggestion-toggle-row">
      <button type="button" class="btn btn-outline-primary btn-sm" id="btnToggleSingleSuggestions">
        <i class="bx bx-bulb me-1"></i> Show Suggested Schedule
      </button>
    </div>
      </div>

      <div class="col-lg-5 suggestion-panel d-none" id="singleSuggestionPanel">
        <div class="suggestion-board">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div class="step-label mb-1">Suggested Slots</div>
              <div class="schedule-hint mb-0">
                Conflict-free lecture placements ranked by room match, section balance, and live room occupancy.
              </div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefreshSingleSuggestions">
              Refresh
            </button>
          </div>

          <div id="singleSuggestionBoard" class="suggestion-list mt-3">
            <div class="suggestion-empty">
              Suggestions will load when the modal opens.
            </div>
          </div>
        </div>
      </div>
    </div>

    </div>

    <div class="modal-footer">
    <button class="btn btn-outline-danger me-auto d-none" id="btnClearSchedule">
    <i class="bx bx-trash me-1"></i> Clear Schedule
    </button>
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-primary" id="btnSaveSchedule">
    <i class="bx bx-save me-1"></i> Save Class Schedule
    </button>
    </div>

    </div>
    </div>
</div>



<!-- =======================================================
     LECTURE + LAB SCHEDULING MODAL
======================================================= -->
<!-- LECTURE + LAB SCHEDULING MODAL -->
<div class="modal fade" id="dualScheduleModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Define Lecture & Laboratory Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" id="dual_offering_id">

        <div class="mb-3">
          <strong id="dual_subject_label"></strong><br>
          <small class="text-muted" id="dual_section_label"></small>
        </div>

        <hr>

        <!-- =========================
             LECTURE SCHEDULE
        ========================== -->
        <h6 class="text-primary">Lecture Schedule</h6>

        <div class="row g-4 mb-4 align-items-start">
          <div class="col-lg-7">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Days</label><br>
                <div id="lec_days"></div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Time</label>
                <div class="d-flex gap-2">
                  <input type="time" id="lec_time_start" class="form-control" min="07:30" max="17:30">
                  <input type="time" id="lec_time_end" class="form-control" min="07:30" max="17:30">
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Room</label>
                <select id="lec_room_id" class="form-select">
                  <option value="">Select lecture room...</option>
                </select>
              </div>
            </div>

            <div class="suggestion-toggle-row mt-3">
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnToggleLectureSuggestions">
                <i class="bx bx-bulb me-1"></i> Show Suggested Lecture Schedule
              </button>
            </div>
          </div>

          <div class="col-lg-5 suggestion-panel d-none" id="dualLectureSuggestionPanel">
            <div class="suggestion-board">
              <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                  <div class="step-label mb-1">Lecture Suggestions</div>
                  <div class="schedule-hint mb-0">
                    Ranked lecture slots that stay clear of section and room conflicts.
                  </div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefreshLectureSuggestions">
                  Refresh
                </button>
              </div>

              <div id="dualLectureSuggestionBoard" class="suggestion-list mt-3">
                <div class="suggestion-empty">
                  Suggestions will load when the modal opens.
                </div>
              </div>
            </div>
          </div>
        </div>

        <hr>

        <!-- =========================
             LABORATORY SCHEDULE
        ========================== -->
        <h6 class="text-success">Laboratory Schedule</h6>

        <div class="row g-4 align-items-start">
          <div class="col-lg-7">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Days</label><br>
                <div id="lab_days"></div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Time</label>
                <div class="d-flex gap-2">
                  <input type="time" id="lab_time_start" class="form-control" min="07:30" max="17:30">
                  <input type="time" id="lab_time_end" class="form-control" min="07:30" max="17:30">
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Room</label>
                <select id="lab_room_id" class="form-select">
                  <option value="">Select laboratory room...</option>
                </select>
              </div>
            </div>

            <div class="suggestion-toggle-row mt-3">
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnToggleLabSuggestions">
                <i class="bx bx-bulb me-1"></i> Show Suggested Lab Schedule
              </button>
            </div>
          </div>

          <div class="col-lg-5 suggestion-panel d-none" id="dualLabSuggestionPanel">
            <div class="suggestion-board">
              <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                  <div class="step-label mb-1">Lab Suggestions</div>
                  <div class="schedule-hint mb-0">
                    Ranked lab slots using laboratory-compatible rooms and live room occupancy.
                  </div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefreshLabSuggestions">
                  Refresh
                </button>
              </div>

              <div id="dualLabSuggestionBoard" class="suggestion-list mt-3">
                <div class="suggestion-empty">
                  Suggestions will load when the modal opens.
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-danger me-auto d-none" id="btnClearDualSchedule">
          <i class="bx bx-trash me-1"></i> Clear Schedule
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveDualSchedule">
          <i class="bx bx-save me-1"></i> Save Lecture & Lab
        </button>
      </div>

    </div>
  </div>
</div>



<!-- ROOM MATRIX MODAL -->
<div class="modal fade" id="matrixModal" tabindex="-1">
  <div class="modal-dialog modal-fullscreen-lg-down modal-xxl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bx bx-building me-1"></i> Room–Time Matrix
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div id="matrixContainer">
          <div class="text-center text-muted py-5">
            Loading room utilization…
          </div>
        </div>

      </div>

    </div>
  </div>
</div>


    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>    
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>

<script>
    const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;

    $.ajaxPrefilter(function (options) {
      const method = (options.type || options.method || "GET").toUpperCase();
      if (method !== "POST") return;

      if (typeof options.data === "string") {
        const tokenPair = "csrf_token=" + encodeURIComponent(CSRF_TOKEN);
        options.data = options.data ? (options.data + "&" + tokenPair) : tokenPair;
        return;
      }

      if (Array.isArray(options.data)) {
        options.data.push({ name: "csrf_token", value: CSRF_TOKEN });
        return;
      }

      if ($.isPlainObject(options.data)) {
        options.data.csrf_token = CSRF_TOKEN;
        return;
      }

      if (!options.data) {
        options.data = { csrf_token: CSRF_TOKEN };
      }
    });

    function buildDayButtons(containerId, prefix) {
      const days = ['M','T','W','Th','F','S'];
      let html = '';

      days.forEach(d => {
        html += `
          <input type="checkbox" class="btn-check ${prefix}-day" id="${prefix}_${d}" value="${d}">
          <label class="btn btn-outline-secondary btn-sm me-1" for="${prefix}_${d}">
            ${d}
          </label>
        `;
      });

      $("#" + containerId).html(html);
    }

    let termRoomCacheKey = "";
    let termRoomCache = [];
    let scheduleListRequest = null;
    let scheduleAutoLoadTimer = null;
    let singleSuggestionTimer = null;
    let dualSuggestionTimer = null;
    const SCHEDULE_DAY_ORDER = ["M", "T", "W", "Th", "F", "S"];

    function escapeHtml(text) {
        return String(text || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function normalizeRoomType(roomType) {
        const value = String(roomType || "").toLowerCase().trim();
        return ["lecture", "laboratory", "lec_lab"].includes(value) ? value : "lecture";
    }

    function roomMatchesSchedule(room, scheduleType) {
        const roomType = normalizeRoomType(room && room.room_type);
        const type = String(scheduleType || "").toUpperCase();

        if (type === "LAB") {
            return roomType === "laboratory" || roomType === "lec_lab";
        }

        return roomType === "lecture" || roomType === "lec_lab";
    }

    function buildRoomOptionsHtml(rooms, placeholder) {
        const list = Array.isArray(rooms) ? rooms : [];
        const optionsHtml = list.map(r =>
            `<option value="${parseInt(r.room_id, 10)}">${escapeHtml(r.label)}</option>`
        ).join("");

        return `<option value="">${escapeHtml(placeholder)}</option>${optionsHtml}`;
    }

    function setRoomOptions(selector, rooms, placeholder, selectedRoomId = "") {
        $(selector).html(buildRoomOptionsHtml(rooms, placeholder));
        if (selectedRoomId !== "") {
            $(selector).val(String(selectedRoomId));
        }
    }

    function filterRoomsForSchedule(scheduleType) {
        return termRoomCache.filter(room => roomMatchesSchedule(room, scheduleType));
    }

    function applySingleScheduleRoomOptions(selectedRoomId = "") {
        const lectureRooms = filterRoomsForSchedule("LEC");
        setRoomOptions("#sched_room_id", lectureRooms, "Select lecture room...", selectedRoomId);
        return lectureRooms.length > 0;
    }

    function applyDualScheduleRoomOptions(selectedLectureRoomId = "", selectedLabRoomId = "") {
        const lectureRooms = filterRoomsForSchedule("LEC");
        const laboratoryRooms = filterRoomsForSchedule("LAB");

        setRoomOptions("#lec_room_id", lectureRooms, "Select lecture room...", selectedLectureRoomId);
        setRoomOptions("#lab_room_id", laboratoryRooms, "Select laboratory room...", selectedLabRoomId);

        return {
            lectureCount: lectureRooms.length,
            laboratoryCount: laboratoryRooms.length
        };
    }

    function clearTermRoomOptions() {
        setRoomOptions("#sched_room_id", [], "Select lecture room...");
        setRoomOptions("#lec_room_id", [], "Select lecture room...");
        setRoomOptions("#lab_room_id", [], "Select laboratory room...");
        termRoomCache = [];
        termRoomCacheKey = "";
    }

    function abortScheduleListRequest() {
        if (scheduleListRequest && scheduleListRequest.readyState !== 4) {
            scheduleListRequest.abort();
        }
        scheduleListRequest = null;
    }

    function renderScheduleListMessage(message, tone = "muted") {
        $("#scheduleListContainer").html(
            `<div class="text-center text-${escapeHtml(tone)} py-4">${message}</div>`
        );
    }

    function updateScheduleGroupCounts() {
        $("#scheduleListContainer .schedule-group-card").each(function () {
            const card = $(this);
            const visibleRows = card.find("tbody tr.schedule-offering-row[data-search-match='1']").length;
            card.find(".schedule-group-count").text(`${visibleRows} class(es)`);
            card.toggle(visibleRows > 0);
        });
    }

    function renderScheduleSearchEmptyState() {
        $("#scheduleSearchEmptyState").remove();

        const keyword = $("#scheduleSubjectSearch").val().trim();
        if (keyword === "") {
            return;
        }

        const hasVisibleRows = $("#scheduleListContainer tr.schedule-offering-row[data-search-match='1']").length > 0;
        if (hasVisibleRows) {
            return;
        }

        $("#scheduleListContainer").append(`
            <div id="scheduleSearchEmptyState" class="text-center text-muted py-4">
                No subjects matched <strong>${escapeHtml(keyword)}</strong> in the current filtered offerings.
            </div>
        `);
    }

    function applyScheduleSearchFilter() {
        const keyword = $("#scheduleSubjectSearch").val().trim().toLowerCase();
        const rows = $("#scheduleListContainer tr.schedule-offering-row");

        if (rows.length === 0) {
            $("#scheduleSearchEmptyState").remove();
            return;
        }

        rows.each(function () {
            const row = $(this);
            const haystack = String(row.data("searchText") || row.text()).toLowerCase();
            const isMatch = keyword === "" || haystack.includes(keyword);
            row.attr("data-search-match", isMatch ? "1" : "0");
            row.toggle(isMatch);
        });

        updateScheduleGroupCounts();
        renderScheduleSearchEmptyState();
    }

    function normalizeSuggestionDays(days) {
        const list = Array.isArray(days) ? days : [];
        return SCHEDULE_DAY_ORDER.filter(day => list.includes(day));
    }

    function collectCheckedDays(selector) {
        const days = [];
        $(selector).filter(":checked").each(function () {
            days.push($(this).val());
        });
        return normalizeSuggestionDays(days);
    }

    function setSingleScheduleDays(days) {
        $(".sched-day").prop("checked", false);
        normalizeSuggestionDays(days).forEach(function (day) {
            $("#day_" + day).prop("checked", true);
        });
    }

    function setDualScheduleDays(prefix, days) {
        $("." + prefix + "-day").prop("checked", false);
        normalizeSuggestionDays(days).forEach(function (day) {
            $("#" + prefix + "_" + day).prop("checked", true);
        });
    }

    function renderSuggestionBoardState(selector, message) {
        $(selector).html(`
            <div class="suggestion-empty">${escapeHtml(message)}</div>
        `);
    }

    function buildSuggestionCardHtml(item, targetMode) {
        const reasons = Array.isArray(item.reasons) ? item.reasons : [];
        const reasonsHtml = reasons.map(reason =>
            `<span class="suggestion-reason">${escapeHtml(reason)}</span>`
        ).join("");

        return `
            <div class="suggestion-card ${escapeHtml(item.fit_class || "valid")}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="suggestion-fit ${escapeHtml(item.fit_class || "valid")}">${escapeHtml(item.fit_label || "Valid Slot")}</span>
                            <span class="suggestion-chip">${escapeHtml(item.pattern_label || "Suggested Slot")}</span>
                        </div>
                        <div class="suggestion-slot">${escapeHtml(item.days_label || "")} • ${escapeHtml(item.time_label || "")}</div>
                        <div class="suggestion-meta mt-1">${escapeHtml(item.room_label || "")}</div>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary btn-use-suggestion"
                        data-target-mode="${escapeHtml(targetMode)}"
                        data-schedule-type="${escapeHtml(item.schedule_type || "LEC")}"
                        data-room-id="${escapeHtml(item.room_id || "")}"
                        data-time-start="${escapeHtml(item.time_start || "")}"
                        data-time-end="${escapeHtml(item.time_end || "")}"
                        data-days='${escapeHtml(JSON.stringify(item.days || []))}'>
                        Use Slot
                    </button>
                </div>
                <div class="suggestion-reasons mt-3">${reasonsHtml}</div>
            </div>
        `;
    }

    function renderSuggestionBoard(selector, suggestions, targetMode, emptyMessage) {
        if (!Array.isArray(suggestions) || suggestions.length === 0) {
            renderSuggestionBoardState(selector, emptyMessage);
            return;
        }

        $(selector).html(
            suggestions.map(item => buildSuggestionCardHtml(item, targetMode)).join("")
        );
    }

    function setSuggestionToggleButton(buttonSelector, isVisible, showLabel, hideLabel) {
        $(buttonSelector).html(
            `<i class="bx bx-bulb me-1"></i> ${escapeHtml(isVisible ? hideLabel : showLabel)}`
        );
    }

    function setSuggestionPanelVisibility(panelSelector, buttonSelector, isVisible, showLabel, hideLabel) {
        $(panelSelector).toggleClass("d-none", !isVisible);
        setSuggestionToggleButton(buttonSelector, isVisible, showLabel, hideLabel);
    }

    function panelIsVisible(panelSelector) {
        return !$(panelSelector).hasClass("d-none");
    }

    function resetSingleSuggestionPanel() {
        renderSuggestionBoardState("#singleSuggestionBoard", "Click Show Suggested Schedule to load ranked schedule options.");
        setSuggestionPanelVisibility(
            "#singleSuggestionPanel",
            "#btnToggleSingleSuggestions",
            false,
            "Show Suggested Schedule",
            "Hide Suggested Schedule"
        );
    }

    function resetDualSuggestionPanels() {
        renderSuggestionBoardState("#dualLectureSuggestionBoard", "Click Show Suggested Lecture Schedule to load ranked lecture options.");
        renderSuggestionBoardState("#dualLabSuggestionBoard", "Click Show Suggested Lab Schedule to load ranked lab options.");
        setSuggestionPanelVisibility(
            "#dualLectureSuggestionPanel",
            "#btnToggleLectureSuggestions",
            false,
            "Show Suggested Lecture Schedule",
            "Hide Suggested Lecture Schedule"
        );
        setSuggestionPanelVisibility(
            "#dualLabSuggestionPanel",
            "#btnToggleLabSuggestions",
            false,
            "Show Suggested Lab Schedule",
            "Hide Suggested Lab Schedule"
        );
    }

    function buildSuggestionCardHtml(item, targetMode) {
        const reasons = Array.isArray(item.reasons) ? item.reasons : [];
        const reasonsHtml = reasons.map(reason =>
            `<span class="suggestion-reason">${escapeHtml(reason)}</span>`
        ).join("");

        return `
            <div class="suggestion-card ${escapeHtml(item.fit_class || "valid")}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="suggestion-fit ${escapeHtml(item.fit_class || "valid")}">${escapeHtml(item.fit_label || "Valid Slot")}</span>
                            <span class="suggestion-chip">${escapeHtml(item.pattern_label || "Suggested Slot")}</span>
                        </div>
                        <div class="suggestion-slot">${escapeHtml(item.days_label || "")} - ${escapeHtml(item.time_label || "")}</div>
                        <div class="suggestion-meta mt-1">${escapeHtml(item.room_label || "")}</div>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary btn-use-suggestion"
                        data-target-mode="${escapeHtml(targetMode)}"
                        data-schedule-type="${escapeHtml(item.schedule_type || "LEC")}"
                        data-room-id="${escapeHtml(item.room_id || "")}"
                        data-time-start="${escapeHtml(item.time_start || "")}"
                        data-time-end="${escapeHtml(item.time_end || "")}"
                        data-days='${escapeHtml(JSON.stringify(item.days || []))}'>
                        Use Slot
                    </button>
                </div>
                <div class="suggestion-reasons mt-3">${reasonsHtml}</div>
            </div>
        `;
    }

    function collectSingleDrafts() {
        return {
            LEC: {
                room_id: $("#sched_room_id").val() || "",
                time_start: $("#sched_time_start").val(),
                time_end: $("#sched_time_end").val(),
                days: collectCheckedDays(".sched-day")
            }
        };
    }

    function collectDualDrafts() {
        return {
            LEC: {
                room_id: $("#lec_room_id").val() || "",
                time_start: $("#lec_time_start").val(),
                time_end: $("#lec_time_end").val(),
                days: collectCheckedDays(".lec-day")
            },
            LAB: {
                room_id: $("#lab_room_id").val() || "",
                time_start: $("#lab_time_start").val(),
                time_end: $("#lab_time_end").val(),
                days: collectCheckedDays(".lab-day")
            }
        };
    }

    function requestScheduleSuggestions(offeringId, drafts, onSuccess, onError) {
        $.ajax({
            url: "../backend/load_schedule_suggestions.php",
            type: "POST",
            dataType: "json",
            data: {
                offering_id: offeringId,
                drafts_json: JSON.stringify(drafts || {})
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    onError((res && res.message) ? res.message : "Unable to load suggestions.");
                    return;
                }
                onSuccess(res);
            },
            error: function () {
                onError("Unable to load suggestions.");
            }
        });
    }

    function loadSingleScheduleSuggestions() {
        const offeringId = $("#sched_offering_id").val();

        if (!offeringId) {
            renderSuggestionBoardState("#singleSuggestionBoard", "Suggestions will appear after selecting a class.");
            return;
        }

        renderSuggestionBoardState("#singleSuggestionBoard", "Loading lecture suggestions...");

        requestScheduleSuggestions(
            offeringId,
            collectSingleDrafts(),
            function (res) {
                renderSuggestionBoard(
                    "#singleSuggestionBoard",
                    (res.suggestions && res.suggestions.LEC) ? res.suggestions.LEC : [],
                    "single",
                    "No conflict-free lecture suggestions found inside the current scheduling window."
                );
            },
            function (message) {
                renderSuggestionBoardState("#singleSuggestionBoard", message);
            }
        );
    }

    function loadDualScheduleSuggestions() {
        const offeringId = $("#dual_offering_id").val();

        if (!offeringId) {
            renderSuggestionBoardState("#dualLectureSuggestionBoard", "Suggestions will appear after selecting a class.");
            renderSuggestionBoardState("#dualLabSuggestionBoard", "Suggestions will appear after selecting a class.");
            return;
        }

        renderSuggestionBoardState("#dualLectureSuggestionBoard", "Loading lecture suggestions...");
        renderSuggestionBoardState("#dualLabSuggestionBoard", "Loading lab suggestions...");

        requestScheduleSuggestions(
            offeringId,
            collectDualDrafts(),
            function (res) {
                renderSuggestionBoard(
                    "#dualLectureSuggestionBoard",
                    (res.suggestions && res.suggestions.LEC) ? res.suggestions.LEC : [],
                    "dual",
                    "No conflict-free lecture suggestions found inside the current scheduling window."
                );
                renderSuggestionBoard(
                    "#dualLabSuggestionBoard",
                    (res.suggestions && res.suggestions.LAB) ? res.suggestions.LAB : [],
                    "dual",
                    "No conflict-free lab suggestions found inside the current scheduling window."
                );
            },
            function (message) {
                renderSuggestionBoardState("#dualLectureSuggestionBoard", message);
                renderSuggestionBoardState("#dualLabSuggestionBoard", message);
            }
        );
    }

    function queueSingleSuggestionRefresh() {
        if (singleSuggestionTimer) {
            clearTimeout(singleSuggestionTimer);
        }

        singleSuggestionTimer = window.setTimeout(function () {
            if ($("#scheduleModal").hasClass("show") && panelIsVisible("#singleSuggestionPanel")) {
                loadSingleScheduleSuggestions();
            }
        }, 220);
    }

    function queueDualSuggestionRefresh() {
        if (dualSuggestionTimer) {
            clearTimeout(dualSuggestionTimer);
        }

        dualSuggestionTimer = window.setTimeout(function () {
            if (
                $("#dualScheduleModal").hasClass("show") &&
                (panelIsVisible("#dualLectureSuggestionPanel") || panelIsVisible("#dualLabSuggestionPanel"))
            ) {
                loadDualScheduleSuggestions();
            }
        }, 220);
    }

    function applySingleSuggestion(item) {
        setSingleScheduleDays(item.days || []);
        $("#sched_time_start").val(item.time_start || "");
        $("#sched_time_end").val(item.time_end || "");
        $("#sched_room_id").val(String(item.room_id || ""));
        setSuggestionPanelVisibility(
            "#singleSuggestionPanel",
            "#btnToggleSingleSuggestions",
            false,
            "Show Suggested Schedule",
            "Hide Suggested Schedule"
        );
    }

    function applyDualSuggestion(scheduleType, item) {
        const prefix = scheduleType === "LAB" ? "lab" : "lec";

        setDualScheduleDays(prefix, item.days || []);
        $("#" + prefix + "_time_start").val(item.time_start || "");
        $("#" + prefix + "_time_end").val(item.time_end || "");
        $("#" + prefix + "_room_id").val(String(item.room_id || ""));

        if (scheduleType === "LAB") {
            setSuggestionPanelVisibility(
                "#dualLabSuggestionPanel",
                "#btnToggleLabSuggestions",
                false,
                "Show Suggested Lab Schedule",
                "Hide Suggested Lab Schedule"
            );
            return;
        }

        setSuggestionPanelVisibility(
            "#dualLectureSuggestionPanel",
            "#btnToggleLectureSuggestions",
            false,
            "Show Suggested Lecture Schedule",
            "Hide Suggested Lecture Schedule"
        );
    }

    function ensureDefaultProspectus() {
        const prospectusSelect = $("#prospectus_id");
        if (prospectusSelect.val()) {
            return;
        }

        const firstProspectus = prospectusSelect.find("option").filter(function () {
            return $(this).val() !== "";
        }).first().val();

        if (firstProspectus) {
            prospectusSelect.val(firstProspectus);
        }
    }

    function loadTermRoomOptions(forceReload = false) {
        const dfd = $.Deferred();
        const ay = $("#ay_id").val();
        const sem = $("#semester").val();

        if (!ay || !sem) {
            clearTermRoomOptions();
            dfd.reject("missing_term");
            return dfd.promise();
        }

        const key = `${ay}-${sem}`;
        if (!forceReload && key === termRoomCacheKey && termRoomCache.length > 0) {
            dfd.resolve(termRoomCache);
            return dfd.promise();
        }

        $.ajax({
            url: "../backend/load_term_room_options.php",
            type: "POST",
            dataType: "json",
            data: {
                ay_id: ay,
                semester: sem
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    clearTermRoomOptions();
                    dfd.reject((res && res.message) ? res.message : "Failed to load rooms.");
                    return;
                }

                termRoomCacheKey = key;
                termRoomCache = Array.isArray(res.rooms) ? res.rooms : [];
                if (termRoomCache.length === 0) {
                    clearTermRoomOptions();
                    dfd.reject("No rooms are available for selected AY and Semester.");
                    return;
                }
                dfd.resolve(termRoomCache);
            },
            error: function () {
                clearTermRoomOptions();
                dfd.reject("Failed to load rooms.");
            }
        });

        return dfd.promise();
    }


    function loadScheduleTable(forceRoomReload = true) {

        const pid = $("#prospectus_id").val();
        const ay  = $("#ay_id").val();
        const sem = $("#semester").val();

        if (!pid || !ay || !sem) {
            abortScheduleListRequest();
            renderScheduleListMessage("Select Prospectus, Academic Year, and Semester to view class offerings.");
            return;
        }

        abortScheduleListRequest();
        renderScheduleListMessage("Loading classes...");

        loadTermRoomOptions(forceRoomReload)
            .always(function () {
                scheduleListRequest = $.post(
                    "../backend/load_class_offerings.php",
                    {
                        prospectus_id: pid,
                        ay_id: ay,
                        semester: sem
                    },
                    function (rows) {
                        $("#scheduleListContainer").html(rows);
                        applyScheduleSearchFilter();
                    }
                ).fail(function (xhr) {
                    if (xhr.statusText === "abort") {
                        return;
                    }
                    $("#scheduleListContainer").html(
                        "<div class='text-center text-danger py-4'>Failed to load classes.</div>"
                    );
                    console.error(xhr.responseText);
                });
            });
    }

    function scheduleAutoLoad(forceRoomReload = true) {
        if (scheduleAutoLoadTimer) {
            clearTimeout(scheduleAutoLoadTimer);
        }

        scheduleAutoLoadTimer = window.setTimeout(function () {
            loadScheduleTable(forceRoomReload);
        }, 120);
    }

    function clearScheduleForOffering(offeringId, subjectLabel) {
        if (!offeringId) {
            Swal.fire("Error", "Missing offering reference.", "error");
            return;
        }

        const label = subjectLabel || "this class";

        Swal.fire({
            icon: "warning",
            title: "Clear Schedule?",
            html: `This will remove all saved schedules for <b>${escapeHtml(label)}</b>.`,
            showCancelButton: true,
            confirmButtonText: "Yes, clear it",
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" }
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: "../backend/query_class_schedule.php",
                type: "POST",
                dataType: "json",
                data: {
                    clear_schedule: 1,
                    offering_id: offeringId
                },
                success: function (res) {
                    if (res.status === "ok") {
                        Swal.fire({
                            icon: "success",
                            title: "Schedule Cleared",
                            timer: 1200,
                            showConfirmButton: false
                        });

                        $("#scheduleModal").modal("hide");
                        $("#dualScheduleModal").modal("hide");

                        setTimeout(function () {
                            loadScheduleTable();
                        }, 300);
                        return;
                    }

                    Swal.fire("Error", res.message || "Failed to clear schedule.", "error");
                },
                error: function (xhr) {
                    Swal.fire("Error", xhr.responseText || "Failed to clear schedule.", "error");
                }
            });
        });
    }

    // ===============================================================
$(document).ready(function () {

ensureDefaultProspectus();
resetSingleSuggestionPanel();
resetDualSuggestionPanels();

$("#prospectus_id, #ay_id, #semester").on("change", function () {
  if (this.id === "ay_id" || this.id === "semester") {
    clearTermRoomOptions();
  }
  scheduleAutoLoad(true);
});

$("#scheduleSubjectSearch").on("input", function () {
  applyScheduleSearchFilter();
});

$("#btnRefreshSingleSuggestions").on("click", function () {
  loadSingleScheduleSuggestions();
});

$("#btnRefreshLectureSuggestions, #btnRefreshLabSuggestions").on("click", function () {
  loadDualScheduleSuggestions();
});

$("#btnToggleSingleSuggestions").on("click", function () {
  const shouldShow = !panelIsVisible("#singleSuggestionPanel");
  setSuggestionPanelVisibility(
    "#singleSuggestionPanel",
    "#btnToggleSingleSuggestions",
    shouldShow,
    "Show Suggested Schedule",
    "Hide Suggested Schedule"
  );

  if (shouldShow) {
    loadSingleScheduleSuggestions();
  }
});

$("#btnToggleLectureSuggestions").on("click", function () {
  const shouldShow = !panelIsVisible("#dualLectureSuggestionPanel");
  setSuggestionPanelVisibility(
    "#dualLectureSuggestionPanel",
    "#btnToggleLectureSuggestions",
    shouldShow,
    "Show Suggested Lecture Schedule",
    "Hide Suggested Lecture Schedule"
  );

  if (shouldShow) {
    loadDualScheduleSuggestions();
  }
});

$("#btnToggleLabSuggestions").on("click", function () {
  const shouldShow = !panelIsVisible("#dualLabSuggestionPanel");
  setSuggestionPanelVisibility(
    "#dualLabSuggestionPanel",
    "#btnToggleLabSuggestions",
    shouldShow,
    "Show Suggested Lab Schedule",
    "Hide Suggested Lab Schedule"
  );

  if (shouldShow) {
    loadDualScheduleSuggestions();
  }
});

$("#scheduleModal").on("hidden.bs.modal", function () {
  resetSingleSuggestionPanel();
});

$("#dualScheduleModal").on("hidden.bs.modal", function () {
  resetDualSuggestionPanels();
});

$(document).on("input change", "#scheduleModal .sched-day, #scheduleModal #sched_time_start, #scheduleModal #sched_time_end, #scheduleModal #sched_room_id", function () {
  if ($("#scheduleModal").hasClass("show")) {
    queueSingleSuggestionRefresh();
  }
});

$(document).on("input change", "#dualScheduleModal .lec-day, #dualScheduleModal .lab-day, #dualScheduleModal #lec_time_start, #dualScheduleModal #lec_time_end, #dualScheduleModal #lec_room_id, #dualScheduleModal #lab_time_start, #dualScheduleModal #lab_time_end, #dualScheduleModal #lab_room_id", function () {
  if ($("#dualScheduleModal").hasClass("show")) {
    queueDualSuggestionRefresh();
  }
});

$(document).on("click", ".btn-use-suggestion", function () {
  const button = $(this);
  let days = [];

  try {
    days = JSON.parse(button.attr("data-days") || "[]");
  } catch (error) {
    days = [];
  }

  const item = {
    room_id: button.data("roomId"),
    time_start: button.data("timeStart"),
    time_end: button.data("timeEnd"),
    days: normalizeSuggestionDays(days)
  };

  if (button.data("targetMode") === "single") {
    applySingleSuggestion(item);
    return;
  }

  applyDualSuggestion(String(button.data("scheduleType") || "LEC").toUpperCase(), item);
});

scheduleAutoLoad(true);


$("#btnShowMatrix").on("click", function () {

  const ay  = $("#ay_id").val();
  const sem = $("#semester").val();

  if (!ay || !sem) {
    Swal.fire(
      "Missing Filters",
      "Please select Academic Year and Semester first.",
      "warning"
    );
    return;
  }

  $("#matrixModal").modal("show");

  $("#matrixContainer").html(`
    <div class="text-center text-muted py-5">
      Loading room utilization…
    </div>
  `);

  $.post(
    "../backend/load_room_time_matrix.php",
    {
      ay_id: ay,
      semester: sem
    },
    function (html) {
      $("#matrixContainer").html(html);
    }
  ).fail(function (xhr) {
    $("#matrixContainer").html(
      "<div class='text-danger text-center'>Failed to load matrix.</div>"
    );
    console.error(xhr.responseText);
  });

});




        // ============================
        // CLICK SCHEDULE / EDIT BUTTON
        // ============================
$(document).on("click", ".btn-schedule", function () {

    const btn = $(this);

    const offeringId = btn.data("offering-id");
    const labUnits   = parseFloat(btn.data("lab-units")) || 0;
    const isEditMode = btn.text().trim().toLowerCase() === "edit";

    const subCode = btn.data("sub-code");
    const subDesc = btn.data("sub-desc");
    const section = btn.data("section");

    // Shared labels
    const subjectLabel = subCode + " — " + subDesc;

    // ============================
    // CASE A — LECTURE ONLY
    // ============================
    if (labUnits === 0) {

        // Populate existing modal
        $("#sched_offering_id").val(offeringId);
        $("#sched_subject_label").text(subjectLabel);
        $("#sched_section_label").text("Section: " + section);
        $("#btnClearSchedule")
            .data("offering-id", offeringId)
            .data("subject-label", subjectLabel)
            .toggleClass("d-none", !isEditMode);
        $("#btnClearDualSchedule").addClass("d-none");

        // Reset fields
        $(".sched-day").prop("checked", false);
        $("#sched_time_start").val("");
        $("#sched_time_end").val("");
        $("#sched_room_id").val("").trigger("change");

        // Existing data (edit mode)
        const daysJson = btn.data("days-json");
        if (daysJson) {
            try {
                JSON.parse(daysJson).forEach(d => {
                    $("#day_" + d).prop("checked", true);
                });
            } catch(e){}
        }

        if (btn.data("time-start")) $("#sched_time_start").val(btn.data("time-start"));
        if (btn.data("time-end"))   $("#sched_time_end").val(btn.data("time-end"));

        const selectedRoomId = btn.data("room-id") ? String(btn.data("room-id")) : "";
        resetSingleSuggestionPanel();
        loadTermRoomOptions(false).done(function () {
            if (!applySingleScheduleRoomOptions(selectedRoomId)) {
                Swal.fire("Room Setup Issue", "No lecture-compatible rooms are available for selected AY and Semester.", "warning");
                return;
            }
            $("#scheduleModal").modal("show");
        }).fail(function (message) {
            Swal.fire("Room Setup Issue", message || "No room is available for selected AY and Semester.", "warning");
        });
        return;
    }

// ============================
// CASE B — LECTURE + LAB
// ============================
$("#dual_offering_id").val(offeringId);
$("#dual_subject_label").text(subjectLabel);
$("#dual_section_label").text("Section: " + section);
$("#btnClearDualSchedule")
    .data("offering-id", offeringId)
    .data("subject-label", subjectLabel)
    .toggleClass("d-none", !isEditMode);
$("#btnClearSchedule").addClass("d-none");
resetDualSuggestionPanels();

// Build day buttons first
buildDayButtons("lec_days", "lec");
buildDayButtons("lab_days", "lab");

// CLEAR fields (default)
$("#lec_time_start, #lec_time_end, #lab_time_start, #lab_time_end").val("");
$("#lec_room_id, #lab_room_id").val("");
$(".lec-day, .lab-day").prop("checked", false);

// ============================
// EDIT MODE → LOAD EXISTING
// ============================
if (isEditMode) {

    $.ajax({
        url: "../backend/query_class_schedule.php",
        type: "POST",
        dataType: "json",
        data: {
            load_dual_schedule: 1,
            offering_id: offeringId
        },
        success: function (res) {

            if (res.status !== "ok") {
                Swal.fire("Error", res.message, "error");
                return;
            }

            // -------- LECTURE --------
            if (res.LEC) {
                $("#lec_time_start").val(res.LEC.time_start);
                $("#lec_time_end").val(res.LEC.time_end);

                res.LEC.days.forEach(d => {
                    $("#lec_" + d).prop("checked", true);
                });
            }

            // -------- LAB --------
            if (res.LAB) {
                $("#lab_time_start").val(res.LAB.time_start);
                $("#lab_time_end").val(res.LAB.time_end);

                res.LAB.days.forEach(d => {
                    $("#lab_" + d).prop("checked", true);
                });
            }

            loadTermRoomOptions(false).done(function () {
                const roomCounts = applyDualScheduleRoomOptions(
                    res.LEC && res.LEC.room_id ? String(res.LEC.room_id) : "",
                    res.LAB && res.LAB.room_id ? String(res.LAB.room_id) : ""
                );

                if (roomCounts.lectureCount === 0) {
                    Swal.fire("Room Setup Issue", "No lecture-compatible rooms are available for selected AY and Semester.", "warning");
                    return;
                }

                if (roomCounts.laboratoryCount === 0) {
                    Swal.fire("Room Setup Issue", "No laboratory-compatible rooms are available for selected AY and Semester.", "warning");
                    return;
                }

                $("#dualScheduleModal").modal("show");
            }).fail(function (message) {
                Swal.fire("Room Setup Issue", message || "No room is available for selected AY and Semester.", "warning");
            });
        },
        error: function (xhr) {
            Swal.fire("Error", xhr.responseText, "error");
        }
    });

} else {
    // NEW ENTRY MODE
    loadTermRoomOptions(false).done(function () {
        const roomCounts = applyDualScheduleRoomOptions();
        if (roomCounts.lectureCount === 0) {
            Swal.fire("Room Setup Issue", "No lecture-compatible rooms are available for selected AY and Semester.", "warning");
            return;
        }
        if (roomCounts.laboratoryCount === 0) {
            Swal.fire("Room Setup Issue", "No laboratory-compatible rooms are available for selected AY and Semester.", "warning");
            return;
        }
        $("#dualScheduleModal").modal("show");
    }).fail(function (message) {
        Swal.fire("Room Setup Issue", message || "No room is available for selected AY and Semester.", "warning");
    });
}



});



$("#btnClearSchedule").on("click", function () {
    clearScheduleForOffering(
        $(this).data("offering-id"),
        $(this).data("subject-label")
    );
});

$("#btnClearDualSchedule").on("click", function () {
    clearScheduleForOffering(
        $(this).data("offering-id"),
        $(this).data("subject-label")
    );
});

const SUPPORTED_TIME_START = "07:30";
const SUPPORTED_TIME_END = "17:30";

function isWithinSupportedScheduleWindow(timeStart, timeEnd) {
    return Boolean(timeStart) &&
           Boolean(timeEnd) &&
           timeStart >= SUPPORTED_TIME_START &&
           timeEnd <= SUPPORTED_TIME_END;
}

// ============================
// SAVE CLASS SCHEDULE
// ============================
$("#btnSaveSchedule").on("click", function () {

    const offering_id = $("#sched_offering_id").val();
    const room_id     = $("#sched_room_id").val();
    const time_start  = $("#sched_time_start").val();
    const time_end    = $("#sched_time_end").val();

    let days = [];
    $(".sched-day:checked").each(function () {
        days.push($(this).val());
    });

    // ----------------------------
    // VALIDATION (Improved)
    // ----------------------------
    function showValidation(title, message) {
        // keep modal open but bring alert to front
        Swal.fire({
            icon: "warning",
            title: title,
            html: message,
            allowOutsideClick: false,
            customClass: {
                popup: 'swal-top'
            }
        });
    }

    if (!offering_id) {
        showValidation("Missing Data", "Offering reference is missing. Please reload the page.");
        return;
    }

    if (!room_id) {
        showValidation("Missing Room", "Please select a room.");
        return;
    }

    if (!time_start || !time_end) {
        showValidation("Missing Time", "Please provide both start and end time.");
        return;
    }

    if (time_end <= time_start) {
        showValidation(
            "Invalid Time Range",
            "End time must be later than start time."
        );
        return;
    }

    if (!isWithinSupportedScheduleWindow(time_start, time_end)) {
        showValidation(
            "Unsupported Time Window",
            "Class schedules must stay within 7:30 AM to 5:30 PM."
        );
        return;
    }

    if (days.length === 0) {
        showValidation(
            "Missing Days",
            "Please select at least one day for the class schedule."
        );
        return;
    }


        // ----------------------------
        // AJAX SAVE
        // ----------------------------
        $.ajax({
            url: "../backend/query_class_schedule.php",
            type: "POST",
            dataType: "json",
            data: {
                save_schedule: 1,
                offering_id: offering_id,
                room_id: room_id,
                time_start: time_start,
                time_end: time_end,
                days_json: JSON.stringify(days)
            },
            success: function (res) {

                if (res.status === "conflict") {
                    Swal.fire({
                        icon: "error",
                        title: "Schedule Conflict",
                        html: res.message,
                        allowOutsideClick: false,
                        customClass: { popup: 'swal-top' }
                    });
                    return;
                }

                if (res.status === "ok") {
                    Swal.fire({
                        icon: "success",
                        title: "Schedule Saved",
                        timer: 1200,
                        showConfirmButton: false
                    });

                    $("#scheduleModal").modal("hide");

                    setTimeout(function () {
                        loadScheduleTable();
                    }, 300);
                    return;
                }

                Swal.fire("Error", res.message || "Unknown error.", "error");
            },
            error: function (xhr) {
                Swal.fire("Error", xhr.responseText, "error");
            }
        });
    });

});


// =======================================================
// SAVE LECTURE + LAB SCHEDULE
// =======================================================
$("#btnSaveDualSchedule").on("click", function () {

    const offering_id = $("#dual_offering_id").val();

    if (!offering_id) {
        Swal.fire("Error", "Missing offering reference.", "error");
        return;
    }

    function collectDays(prefix) {
        let days = [];
        $("." + prefix + "-day:checked").each(function () {
            days.push($(this).val());
        });
        return days;
    }

    // -----------------------------
    // LECTURE DATA
    // -----------------------------
    const lec = {
        type: "LEC",
        room_id: $("#lec_room_id").val(),
        time_start: $("#lec_time_start").val(),
        time_end: $("#lec_time_end").val(),
        days: collectDays("lec")
    };

    // -----------------------------
    // LAB DATA
    // -----------------------------
    const lab = {
        type: "LAB",
        room_id: $("#lab_room_id").val(),
        time_start: $("#lab_time_start").val(),
        time_end: $("#lab_time_end").val(),
        days: collectDays("lab")
    };

    // -----------------------------
    // BASIC VALIDATION
    // -----------------------------
    function invalidBlock(title, msg) {
        Swal.fire({
            icon: "warning",
            title: title,
            html: msg,
            customClass: { popup: 'swal-top' }
        });
    }

    if (!lec.room_id || !lec.time_start || !lec.time_end || lec.days.length === 0) {
        invalidBlock("Lecture Incomplete", "Please complete lecture schedule.");
        return;
    }

    if (!lab.room_id || !lab.time_start || !lab.time_end || lab.days.length === 0) {
        invalidBlock("Laboratory Incomplete", "Please complete laboratory schedule.");
        return;
    }

    if (lec.time_end <= lec.time_start) {
        invalidBlock("Lecture Time Error", "Lecture end time must be later than start time.");
        return;
    }

    if (lab.time_end <= lab.time_start) {
        invalidBlock("Lab Time Error", "Lab end time must be later than start time.");
        return;
    }

    if (!isWithinSupportedScheduleWindow(lec.time_start, lec.time_end)) {
        invalidBlock("Lecture Time Error", "Lecture schedule must stay within 7:30 AM to 5:30 PM.");
        return;
    }

    if (!isWithinSupportedScheduleWindow(lab.time_start, lab.time_end)) {
        invalidBlock("Lab Time Error", "Laboratory schedule must stay within 7:30 AM to 5:30 PM.");
        return;
    }

    // -----------------------------
    // BUILD PAYLOAD
    // -----------------------------
    const payload = {
        save_dual_schedule: 1,
        offering_id: offering_id,
        schedules: [
            {
                type: "LEC",
                room_id: lec.room_id,
                time_start: lec.time_start,
                time_end: lec.time_end,
                days_json: JSON.stringify(lec.days)
            },
            {
                type: "LAB",
                room_id: lab.room_id,
                time_start: lab.time_start,
                time_end: lab.time_end,
                days_json: JSON.stringify(lab.days)
            }
        ]
    };

    // -----------------------------
    // AJAX SAVE
    // -----------------------------
    $.ajax({
        url: "../backend/query_class_schedule.php",
        type: "POST",
        dataType: "json",
        data: payload,
success: function (res) {

    if (res.status === "conflict") {
        Swal.fire({
            icon: "error",
            title: "Schedule Conflict",
            html: res.message,
            allowOutsideClick: false,
            customClass: { popup: 'swal-top' }
        });
        return;
    }

    if (res.status === "ok") {
        Swal.fire({
            icon: "success",
            title: "Schedule Saved",
            timer: 1200,
            showConfirmButton: false
        });

        $("#dualScheduleModal").modal("hide");

        setTimeout(function () {
            loadScheduleTable();
        }, 300);
        return;
    }

    Swal.fire("Error", res.message || "Unknown error.", "error");
},
        error: function (xhr) {
            Swal.fire("Error", xhr.responseText, "error");
        }
    });

});




</script>


</body>
</html>
