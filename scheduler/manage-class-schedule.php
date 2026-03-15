<?php
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';
require_once '../backend/academic_schedule_policy_helper.php';

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
$schedulerCollegeId = (int)($_SESSION['college_id'] ?? 0);
$schedulePolicy = synk_fetch_effective_schedule_policy($conn, $schedulerCollegeId);

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
    min-width: 88px;
    vertical-align: middle;
}

.matrix-room {
    background: #f8f9fa;
    white-space: normal;
    word-break: break-word;
    line-height: 1.15;
}

.matrix-cell {
    border-radius: 6px;
    padding: 4px;
    text-align: center;
    font-size: 0.76rem;
    min-height: 34px;
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

.schedule-pan-shell {
    overflow-x: auto;
    cursor: grab;
    touch-action: pan-y;
    scrollbar-width: thin;
}

.schedule-pan-shell.is-pan-active {
    cursor: grabbing;
}

.schedule-pan-shell.is-pan-active,
.schedule-pan-shell.is-pan-active * {
    user-select: none;
}

.schedule-offerings-table {
    min-width: 1220px;
}

.schedule-offerings-table th,
.schedule-offerings-table td {
    vertical-align: middle;
}

.schedule-hours-col {
    width: 68px;
    min-width: 68px;
}

.schedule-action-col {
    min-width: 92px;
    white-space: nowrap;
}

.schedule-stack-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0;
}

.schedule-stack-item + .schedule-stack-item {
    margin-top: 0.35rem;
}

.schedule-stack-item.empty {
    min-height: 2.4rem;
    justify-content: center;
}

.schedule-stack-badge {
    display: inline-flex;
    justify-content: center;
}

.schedule-stack-value {
    display: block;
    font-size: 0.85rem;
    line-height: 1.2;
    text-align: center;
    white-space: nowrap;
}

.schedule-stack-value.is-room {
    max-width: 150px;
    white-space: normal;
    word-break: break-word;
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

.schedule-block-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.schedule-block-card {
    border: 1px solid #d7e1f2;
    border-radius: 14px;
    padding: 1rem;
    background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    box-shadow: 0 10px 24px rgba(31, 42, 68, 0.06);
}

.schedule-block-card + .schedule-block-card {
    margin-top: 1rem;
}

.schedule-block-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}

.schedule-block-meta {
    font-size: 0.82rem;
    color: #5f6f8d;
}

.schedule-block-days {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

#blockScheduleModal .schedule-block-day + .schedule-block-day-chip,
#blockScheduleModal .schedule-block-day + .schedule-block-day-chip:hover,
#blockScheduleModal .schedule-block-day + .schedule-block-day-chip:focus,
#blockScheduleModal .schedule-block-day + .schedule-block-day-chip:active {
    min-width: 2.75rem;
    border-radius: 0.55rem;
    background: #fff;
    border-color: #d0d7e2;
    color: #6b7280;
    box-shadow: none !important;
}

#blockScheduleModal .schedule-block-day:checked + .schedule-block-day-chip,
#blockScheduleModal .schedule-block-day:checked + .schedule-block-day-chip:hover,
#blockScheduleModal .schedule-block-day:checked + .schedule-block-day-chip:focus,
#blockScheduleModal .schedule-block-day:checked + .schedule-block-day-chip:active {
    background: #696cff;
    border-color: #696cff;
    color: #fff;
    box-shadow: none !important;
}

#blockScheduleModal .schedule-block-day:disabled + .schedule-block-day-chip,
#blockScheduleModal .schedule-block-day:disabled + .schedule-block-day-chip:hover,
#blockScheduleModal .schedule-block-day:disabled + .schedule-block-day-chip:focus,
#blockScheduleModal .schedule-block-day:disabled + .schedule-block-day-chip:active {
    background: #fff;
    border-color: #d0d7e2;
    color: #9aa5b5;
    opacity: 0.65;
    cursor: not-allowed;
    box-shadow: none !important;
}

.schedule-block-empty {
    border: 1px dashed #c7d4ee;
    border-radius: 12px;
    background: rgba(249, 251, 255, 0.8);
    color: #5f6f8d;
    padding: 1rem;
    text-align: center;
}

.coverage-summary {
    border: 1px solid #d7e1f2;
    border-radius: 12px;
    background: #f8fbff;
    padding: 0.85rem 1rem;
}

.coverage-summary strong {
    color: #1f2a44;
}

.coverage-detail {
    font-size: 0.82rem;
    color: #5f6f8d;
}

.auto-draft-summary {
    border: 1px solid #d7e1f2;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    padding: 1rem;
    box-shadow: 0 10px 24px rgba(31, 42, 68, 0.05);
}

.auto-draft-rules {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.auto-draft-rule {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    background: #eef3ff;
    color: #3d5aa8;
    font-size: 0.78rem;
    font-weight: 600;
}

.auto-draft-list {
    display: grid;
    gap: 0.85rem;
}

.auto-draft-card {
    border: 1px solid #d6e0f3;
    border-radius: 14px;
    background: #fff;
    padding: 0.95rem;
    box-shadow: 0 10px 24px rgba(31, 42, 68, 0.05);
}

.auto-draft-card.manual {
    border-color: #f0d2b4;
    background: linear-gradient(180deg, #fffefb 0%, #fff8f0 100%);
}

.auto-draft-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2a44;
}

.auto-draft-subtitle {
    font-size: 0.84rem;
    color: #61708f;
}

.auto-draft-blocks {
    display: grid;
    gap: 0.45rem;
    margin-top: 0.8rem;
}

.auto-draft-block {
    border: 1px solid #dbe4f4;
    border-radius: 12px;
    background: #f8fbff;
    padding: 0.75rem;
}

.auto-draft-block-meta {
    font-size: 0.82rem;
    color: #5f6f8d;
}

.auto-draft-empty {
    border: 1px dashed #c7d4ee;
    border-radius: 12px;
    background: rgba(249, 251, 255, 0.8);
    color: #5f6f8d;
    padding: 1rem;
    text-align: center;
}

.auto-draft-reason {
    font-size: 0.9rem;
    color: #6a4b2d;
}

.auto-draft-status-group {
    border: 1px solid #d7e1f2;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
    padding: 0.95rem;
}

.auto-draft-loader {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #5f6f8d;
    font-size: 0.84rem;
    font-weight: 600;
}

.matrix-vacant {
    background: #eef2f6;
    color: #8b97a8;
}

.matrix-occupied {
    background: #f6f9fc;
    color: #17324d;
    border: 1px solid #d8e4f1;
    font-weight: 600;
}

.matrix-entry {
    border-radius: 5px;
    color: #fff;
    padding: 3px 5px;
    margin-bottom: 3px;
    line-height: 1.08;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
}

.matrix-entry:last-child {
    margin-bottom: 0;
}

.matrix-conflict .matrix-entry {
    color: #fff;
}

.matrix-entry strong {
    display: block;
    font-size: 0.68rem;
    letter-spacing: 0.01em;
}

.matrix-entry small {
    display: block;
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.58rem;
}

.matrix-conflict {
    background: #fff5f5;
    border: 1px solid #ffc9c9;
}

.matrix-day {
    background: #f8f9fa;
    font-weight: 700;
    white-space: nowrap;
    min-width: 52px;
    width: 52px;
    max-width: 52px;
    letter-spacing: 0.02em;
}

/* SUBJECT COLORS (auto-rotated) */
.sub-0 { background: #2563eb; }
.sub-1 { background: #059669; }
.sub-2 { background: #d97706; }
.sub-3 { background: #7c3aed; }
.sub-4 { background: #0f766e; }
.sub-5 { background: #dc2626; }
.sub-6 { background: #db2777; }
.sub-7 { background: #4f46e5; }
.sub-8 { background: #0891b2; }
.sub-9 { background: #65a30d; }
.sub-10 { background: #b45309; }
.sub-11 { background: #475569; }

/* =====================================================
   ROOM–TIME MATRIX (ENHANCEMENTS – SCOPED)
   👉 Scoped to #matrixModal ONLY to avoid conflicts
===================================================== */

#matrixModal {
    --matrix-room-col-width: 118px;
    --matrix-day-col-width: 50px;
    --matrix-slot-col-width: 58px;
}

#matrixModal .matrix-table {
    font-size: 0.68rem;
}

#matrixModal .matrix-room-col {
    width: var(--matrix-room-col-width);
}

#matrixModal .matrix-day-col {
    width: var(--matrix-day-col-width);
}

#matrixModal .matrix-slot-col {
    width: var(--matrix-slot-col-width);
}

#matrixModal .matrix-table th {
    font-size: 0.62rem;
    line-height: 1.05;
    white-space: nowrap;
}

#matrixModal .matrix-table th,
#matrixModal .matrix-table td {
    padding: 4px !important;
    min-width: var(--matrix-slot-col-width);
}

#matrixModal .matrix-room {
    min-width: var(--matrix-room-col-width);
    width: var(--matrix-room-col-width);
    max-width: var(--matrix-room-col-width);
    font-size: 0.69rem;
    padding: 7px 6px !important;
}

#matrixModal .matrix-day {
    min-width: var(--matrix-day-col-width);
    width: var(--matrix-day-col-width);
    max-width: var(--matrix-day-col-width);
    font-size: 0.64rem;
    padding: 4px 3px !important;
}

#matrixModal .matrix-cell {
    padding: 3px;
    min-height: 28px;
    font-size: 0.64rem;
    line-height: 1.02;
}

#matrixModal .matrix-cell strong {
    font-size: 0.64rem;
}

#matrixModal .matrix-cell small {
    font-size: 0.56rem;
    opacity: 0.94;
}

#matrixModal .matrix-time-slot {
    display: grid;
    gap: 1px;
    justify-items: center;
    line-height: 1.02;
    font-variant-numeric: tabular-nums;
}

#matrixModal .matrix-time-slot span {
    display: block;
}

#matrixModal .matrix-meta-note {
    line-height: 1.35;
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

#matrixModal .matrix-day {
    position: sticky;
    left: var(--matrix-room-col-width);
    z-index: 4;
    box-shadow: 2px 0 4px rgba(0,0,0,0.04);
}

#matrixModal .matrix-table thead .matrix-room {
    z-index: 9;
}

#matrixModal .matrix-table thead .matrix-day {
    z-index: 8;
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
    max-width: min(99vw, 2200px) !important;
    width: min(99vw, 2200px);
    height: 95vh;
    margin: 0.75rem auto;
}

#matrixModal .modal-content {
    height: 100%;
}

#matrixModal .modal-body {
    overflow: hidden;
}

@media (min-width: 1200px) {
    #matrixModal {
        --matrix-room-col-width: 104px;
        --matrix-day-col-width: 46px;
        --matrix-slot-col-width: 50px;
    }

    #matrixModal .matrix-table {
        width: 100%;
        table-layout: fixed;
    }
}

@media (min-width: 1600px) {
    #matrixModal {
        --matrix-room-col-width: 100px;
        --matrix-day-col-width: 44px;
        --matrix-slot-col-width: 48px;
    }
}

@media (max-width: 992px) {
    #matrixModal {
        --matrix-room-col-width: 126px;
        --matrix-day-col-width: 52px;
        --matrix-slot-col-width: 66px;
    }

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

    #matrixModal .matrix-table {
        width: max-content;
        table-layout: auto;
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

<div class="alert alert-info mb-4">
  <strong>Scheduling Policy:</strong>
  <?= htmlspecialchars($schedulePolicy['source_label'] ?? 'Global default') ?> |
  <?= htmlspecialchars($schedulePolicy['window_label']) ?>
  <?php if (!empty($schedulePolicy['blocked_days'])): ?>
    | Blocked days: <?= htmlspecialchars($schedulePolicy['blocked_days_label']) ?>
  <?php endif; ?>
  <?php if (!empty($schedulePolicy['blocked_times'])): ?>
    | Blocked times: <?= htmlspecialchars($schedulePolicy['blocked_times_label']) ?>
  <?php endif; ?>
</div>

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

          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-success btn-sm" id="btnPreviewAutoDraft">
              <i class="bx bx-magic-wand me-1"></i> Start Draft
            </button>
            <button class="btn btn-outline-danger btn-sm" id="btnClearAllSchedules">
              <i class="bx bx-trash me-1"></i> Clear All Schedules
            </button>
            <button class="btn btn-outline-primary btn-sm" id="btnShowMatrix">
              <i class="bx bx-grid-alt me-1"></i> Room-Time Matrix
            </button>
          </div>
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
    <input
      type="time"
      id="sched_time_start"
      class="form-control"
      min="<?= htmlspecialchars($schedulePolicy['day_start_input']) ?>"
      max="<?= htmlspecialchars($schedulePolicy['day_end_input']) ?>"
      step="1800"
    >
    </div>

    <div class="col-md-3">
    <label class="form-label">End</label>
    <input
      type="time"
      id="sched_time_end"
      class="form-control"
      min="<?= htmlspecialchars($schedulePolicy['day_start_input']) ?>"
      max="<?= htmlspecialchars($schedulePolicy['day_end_input']) ?>"
      step="1800"
    >
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
    <button type="button" class="btn btn-primary" id="btnSaveSchedule">
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
                  <input
                    type="time"
                    id="lec_time_start"
                    class="form-control"
                    min="<?= htmlspecialchars($schedulePolicy['day_start_input']) ?>"
                    max="<?= htmlspecialchars($schedulePolicy['day_end_input']) ?>"
                    step="1800"
                  >
                  <input
                    type="time"
                    id="lec_time_end"
                    class="form-control"
                    min="<?= htmlspecialchars($schedulePolicy['day_start_input']) ?>"
                    max="<?= htmlspecialchars($schedulePolicy['day_end_input']) ?>"
                    step="1800"
                  >
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
                  <input
                    type="time"
                    id="lab_time_start"
                    class="form-control"
                    min="<?= htmlspecialchars($schedulePolicy['day_start_input']) ?>"
                    max="<?= htmlspecialchars($schedulePolicy['day_end_input']) ?>"
                    step="1800"
                  >
                  <input
                    type="time"
                    id="lab_time_end"
                    class="form-control"
                    min="<?= htmlspecialchars($schedulePolicy['day_start_input']) ?>"
                    max="<?= htmlspecialchars($schedulePolicy['day_end_input']) ?>"
                    step="1800"
                  >
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
        <button type="button" class="btn btn-primary" id="btnSaveDualSchedule">
          <i class="bx bx-save me-1"></i> Save Lecture & Lab
        </button>
      </div>

    </div>
  </div>
</div>


<!-- DYNAMIC SCHEDULE BLOCK MODAL -->
<div class="modal fade" id="blockScheduleModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Define Class Schedule Blocks</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="block_sched_offering_id">
        <input type="hidden" id="block_sched_lec_units">
        <input type="hidden" id="block_sched_lab_units">
        <input type="hidden" id="block_sched_total_units">

        <div class="mb-3">
          <strong id="block_sched_subject_label"></strong><br>
          <small class="text-muted" id="block_sched_section_label"></small>
        </div>

        <div class="coverage-summary mb-3" id="scheduleBlockCoverageSummary">
          <strong>Schedule coverage will appear here.</strong>
        </div>

        <div class="schedule-block-toolbar mb-3">
          <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddLectureBlock">
            <i class="bx bx-plus me-1"></i> Add Lecture Schedule
          </button>
          <button type="button" class="btn btn-outline-success btn-sm" id="btnAddLabBlock">
            <i class="bx bx-plus me-1"></i> Add Lab Schedule
          </button>
        </div>

        <div id="scheduleBlockList"></div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-danger me-auto d-none" id="btnClearBlockSchedule">
          <i class="bx bx-trash me-1"></i> Clear Schedule
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnSaveScheduleBlocks">
          <i class="bx bx-save me-1"></i> Save Schedule Blocks
        </button>
      </div>
    </div>
  </div>
</div>


<!-- AUTO DRAFT MODAL -->
<div class="modal fade" id="autoDraftModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Auto Draft Schedule</h5>
          <div class="small text-muted mt-1">
            Preview conflict-free drafts before applying them to the selected prospectus and term.
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="auto-draft-summary" id="autoDraftSummary">
          <strong>Draft summary will appear here.</strong>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-3 mt-3 flex-wrap">
          <div class="auto-draft-rules" id="autoDraftRuleList">
            <span class="auto-draft-rule">Rules will load with the preview.</span>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="auto-draft-loader d-none" id="autoDraftLoader">
              <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
              <span id="autoDraftLoaderText">Drafting... 0.0s</span>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefreshAutoDraft">
              Start Draft
            </button>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="step-label mb-1">Ready To Apply</div>
                <div class="schedule-hint mb-0">These offerings have complete lecture and laboratory drafts that passed the preview checks.</div>
              </div>
              <span class="badge bg-label-success text-success" id="autoDraftReadyCount">0</span>
            </div>
            <div class="auto-draft-list" id="autoDraftReadyList">
              <div class="auto-draft-empty">Generate a preview to see draft schedules.</div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="step-label mb-1">Manual Review By Status</div>
                <div class="schedule-hint mb-0">Offerings that could not be completed are grouped by their current scheduling status.</div>
              </div>
              <span class="badge bg-label-warning text-warning" id="autoDraftManualCount">0</span>
            </div>
            <div class="auto-draft-list" id="autoDraftStatusGroupList">
              <div class="auto-draft-empty">Subjects that cannot be auto-scheduled will appear here by status.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="btnApplyAutoDraft" disabled>
          <i class="bx bx-save me-1"></i> Apply Draft
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
        <div>
          <h5 class="modal-title mb-0">
            <i class="bx bx-building me-1"></i> Room-Time Matrix
          </h5>
          <div class="small text-muted mt-1">
            Follows <?= htmlspecialchars($schedulePolicy['source_label'] ?? 'Scheduling policy') ?>:
            <?= htmlspecialchars($schedulePolicy['window_label'] ?? '') ?> | 12-hour time labels
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div id="matrixContainer">
          <div class="text-center text-muted py-5">
            Loading room utilization...
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
    const SCHEDULE_POLICY = <?= json_encode($schedulePolicy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const SCHEDULE_DAY_ORDER = ["M", "T", "W", "Th", "F", "S"];
    const SUPPORTED_TIME_START = String(SCHEDULE_POLICY.day_start_input || "07:30");
    const SUPPORTED_TIME_END = String(SCHEDULE_POLICY.day_end_input || "17:30");
    const BLOCKED_SCHEDULE_DAYS = Array.isArray(SCHEDULE_POLICY.blocked_days)
        ? SCHEDULE_POLICY.blocked_days.filter(day => SCHEDULE_DAY_ORDER.includes(day))
        : [];
    const BLOCKED_SCHEDULE_DAY_SET = new Set(BLOCKED_SCHEDULE_DAYS);
    const BLOCKED_SCHEDULE_TIMES = Array.isArray(SCHEDULE_POLICY.blocked_times)
        ? SCHEDULE_POLICY.blocked_times
              .map(range => ({
                  start: String(range.start_input || range.start || ""),
                  end: String(range.end_input || range.end || ""),
                  label: String(range.label || "")
              }))
              .filter(range => range.start !== "" && range.end !== "")
        : [];

    function formatPolicyTimeLabel(timeValue) {
        const value = String(timeValue || "").trim();
        if (!value) {
            return "";
        }

        const [hourText, minuteText] = value.split(":");
        const hour = Number(hourText);
        const minute = Number(minuteText);
        if (!Number.isFinite(hour) || !Number.isFinite(minute)) {
            return value;
        }

        const suffix = hour >= 12 ? "PM" : "AM";
        const normalizedHour = hour % 12 || 12;
        return `${normalizedHour}:${String(minute).padStart(2, "0")} ${suffix}`;
    }

    function scheduleWindowLabel() {
        return String(SCHEDULE_POLICY.window_label || `${formatPolicyTimeLabel(SUPPORTED_TIME_START)} to ${formatPolicyTimeLabel(SUPPORTED_TIME_END)}`);
    }

    function blockedTimeRangeLabel(range) {
        if (!range) {
            return "";
        }

        return String(range.label || `${formatPolicyTimeLabel(range.start)} to ${formatPolicyTimeLabel(range.end)}`);
    }

    function normalizeScheduleDays(days) {
        const list = Array.isArray(days) ? days : [];
        return SCHEDULE_DAY_ORDER.filter(day => list.includes(day));
    }

    function getDisallowedScheduleDays(days) {
        return normalizeScheduleDays(days).filter(day => BLOCKED_SCHEDULE_DAY_SET.has(day));
    }

    function getBlockedTimeOverlap(timeStart, timeEnd) {
        if (!timeStart || !timeEnd) {
            return null;
        }

        for (const range of BLOCKED_SCHEDULE_TIMES) {
            if (timeStart < range.end && timeEnd > range.start) {
                return range;
            }
        }

        return null;
    }

    function isWithinSupportedScheduleWindow(timeStart, timeEnd) {
        return Boolean(timeStart) &&
               Boolean(timeEnd) &&
               timeStart >= SUPPORTED_TIME_START &&
               timeEnd <= SUPPORTED_TIME_END;
    }

    function validateSchedulePolicy(days, timeStart, timeEnd, label) {
        const subjectLabel = String(label || "Schedule");
        const disallowedDays = getDisallowedScheduleDays(days);
        if (disallowedDays.length > 0) {
            return {
                title: "Blocked Day",
                message: `${subjectLabel} uses blocked day(s): ${escapeHtml(disallowedDays.join(", "))}.`
            };
        }

        if (!isWithinSupportedScheduleWindow(timeStart, timeEnd)) {
            return {
                title: "Unsupported Time Window",
                message: `${subjectLabel} must stay within ${scheduleWindowLabel()}.`
            };
        }

        const blockedTime = getBlockedTimeOverlap(timeStart, timeEnd);
        if (blockedTime) {
            return {
                title: "Blocked Time Window",
                message: `${subjectLabel} overlaps the blocked time range of ${blockedTimeRangeLabel(blockedTime)}.`
            };
        }

        return null;
    }

    function applyTimeBoundsToInputs(selector) {
        $(selector).each(function () {
            $(this)
                .attr("min", SUPPORTED_TIME_START)
                .attr("max", SUPPORTED_TIME_END)
                .attr("step", "1800");
        });
    }

    function applyDayRestrictionState(selector) {
        $(selector).each(function () {
            const input = $(this);
            const isBlocked = BLOCKED_SCHEDULE_DAY_SET.has(String(input.val() || ""));
            const shouldDisable = isBlocked && !input.is(":checked");
            input.prop("disabled", shouldDisable);

            const label = $(`label[for="${input.attr("id")}"]`);
            label.toggleClass("disabled", shouldDisable);
            if (shouldDisable) {
                label.attr("title", "Blocked in Academic Settings");
            } else {
                label.removeAttr("title");
            }
        });
    }

    function applySingleScheduleRestrictions() {
        applyDayRestrictionState(".sched-day");
        applyTimeBoundsToInputs("#sched_time_start, #sched_time_end");
    }

    function applyDualScheduleRestrictions() {
        applyDayRestrictionState(".lec-day, .lab-day");
        applyTimeBoundsToInputs("#lec_time_start, #lec_time_end, #lab_time_start, #lab_time_end");
    }

    applySingleScheduleRestrictions();
    applyTimeBoundsToInputs(".schedule-block-time-start, .schedule-block-time-end");

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
      const days = SCHEDULE_DAY_ORDER;
      let html = '';

      days.forEach(d => {
        const isBlocked = BLOCKED_SCHEDULE_DAY_SET.has(d);
        html += `
          <input type="checkbox" class="btn-check ${prefix}-day" id="${prefix}_${d}" value="${d}" ${isBlocked ? "disabled" : ""}>
          <label class="btn btn-outline-secondary btn-sm me-1 ${isBlocked ? "disabled" : ""}" for="${prefix}_${d}" ${isBlocked ? 'title="Blocked in Academic Settings"' : ""}>
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

    let scheduleBlockState = null;
    let scheduleBlockCounter = 0;

    function createScheduleBlockKey() {
        scheduleBlockCounter += 1;
        return `draft_${scheduleBlockCounter}`;
    }

    function normalizeScheduleBlockType(type) {
        return String(type || "").toUpperCase() === "LAB" ? "LAB" : "LEC";
    }

    function isLabCreditSchedule(lecUnits, labUnits, totalUnits) {
        return Number(labUnits) > 0 && Math.abs((Number(lecUnits) + Number(labUnits)) - Number(totalUnits)) < 0.0001;
    }

    function getLabContactHours(lecUnits, labUnits, totalUnits) {
        if (Number(labUnits) <= 0) {
            return 0;
        }
        return isLabCreditSchedule(lecUnits, labUnits, totalUnits)
            ? Number(labUnits) * 3
            : Number(labUnits);
    }

    function getRequiredMinutesByTypeClient() {
        const lecUnits = Number($("#block_sched_lec_units").val() || 0);
        const labUnits = Number($("#block_sched_lab_units").val() || 0);
        const totalUnits = Number($("#block_sched_total_units").val() || 0);

        return {
            LEC: Math.max(0, Math.round(lecUnits * 60)),
            LAB: Math.max(0, Math.round(getLabContactHours(lecUnits, labUnits, totalUnits) * 60))
        };
    }

    function getBlockWeeklyMinutes(block) {
        const days = Array.isArray(block?.days) ? block.days : [];
        const timeStart = String(block?.time_start || "");
        const timeEnd = String(block?.time_end || "");
        if (!timeStart || !timeEnd || timeEnd <= timeStart || days.length === 0) {
            return 0;
        }

        const [startHour, startMinute] = timeStart.split(":").map(Number);
        const [endHour, endMinute] = timeEnd.split(":").map(Number);
        const minutes = ((endHour * 60) + endMinute) - ((startHour * 60) + startMinute);
        return minutes > 0 ? minutes * days.length : 0;
    }

    function getScheduledMinutesByTypeClient(blocks) {
        const totals = { LEC: 0, LAB: 0 };

        (blocks || []).forEach(block => {
            const type = normalizeScheduleBlockType(block?.type);
            totals[type] += getBlockWeeklyMinutes(block);
        });

        return totals;
    }

    function formatCoverageMinutes(minutes) {
        const hours = Number(minutes || 0) / 60;
        return `${hours % 1 === 0 ? hours.toFixed(0) : hours.toFixed(1)} hrs`;
    }

    function createScheduleBlock(type, source = {}) {
        return {
            block_key: String(source.block_key || (source.schedule_id ? `existing_${source.schedule_id}` : createScheduleBlockKey())),
            schedule_id: parseInt(source.schedule_id, 10) || 0,
            type: normalizeScheduleBlockType(source.type || type),
            room_id: source.room_id ? String(source.room_id) : "",
            time_start: String(source.time_start || ""),
            time_end: String(source.time_end || ""),
            days: Array.isArray(source.days) ? source.days.filter(day => SCHEDULE_DAY_ORDER.includes(day)) : [],
            suggestions: Array.isArray(source.suggestions) ? source.suggestions : [],
            suggestionsVisible: Boolean(source.suggestionsVisible)
        };
    }

    function getRequiredScheduleTypesClient() {
        return Number($("#block_sched_lab_units").val() || 0) > 0 ? ["LEC", "LAB"] : ["LEC"];
    }

    function getScheduleBlockLabel(block, indexMap) {
        const type = normalizeScheduleBlockType(block.type);
        indexMap[type] = (indexMap[type] || 0) + 1;
        return type === "LAB" ? `Laboratory ${indexMap[type]}` : `Lecture ${indexMap[type]}`;
    }

    function renderScheduleBlockCoverageSummary() {
        if (!scheduleBlockState) {
            $("#scheduleBlockCoverageSummary").html("<strong>Schedule coverage will appear here.</strong>");
            return;
        }

        const required = getRequiredMinutesByTypeClient();
        const requiredTypes = getRequiredScheduleTypesClient();
        const scheduled = getScheduledMinutesByTypeClient(scheduleBlockState.blocks);

        let statusTitle = "Not Scheduled";
        let statusClass = "bg-secondary";
        const hasAny = requiredTypes.some(type => (scheduled[type] || 0) > 0);
        const complete = requiredTypes.every(type => {
            const requiredMinutes = Number(required[type] || 0);
            return requiredMinutes <= 0 || Number(scheduled[type] || 0) >= requiredMinutes;
        });

        if (hasAny && complete) {
            statusTitle = "Fully Scheduled";
            statusClass = "bg-success";
        } else if (hasAny) {
            statusTitle = "Partially Scheduled";
            statusClass = "bg-warning text-dark";
        }

        const details = requiredTypes.map(type => {
            const label = type === "LAB" ? "Laboratory" : "Lecture";
            return `
                <div class="coverage-detail">
                    <span class="badge ${type === "LAB" ? "bg-label-success text-success" : "bg-label-primary text-primary"} me-1">${escapeHtml(label)}</span>
                    ${escapeHtml(formatCoverageMinutes(scheduled[type] || 0))} / ${escapeHtml(formatCoverageMinutes(required[type] || 0))}
                </div>
            `;
        }).join("");

        $("#scheduleBlockCoverageSummary").html(`
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <strong>${escapeHtml(statusTitle)}</strong>
                    <div class="coverage-detail mt-1">Coverage is based on total scheduled weekly minutes per lecture/lab type.</div>
                </div>
                <span class="badge ${statusClass}">${escapeHtml(statusTitle)}</span>
            </div>
            <div class="mt-2">${details}</div>
        `);
    }

    function renderBlockSuggestionCards(block) {
        if (!Array.isArray(block.suggestions) || block.suggestions.length === 0) {
            return `<div class="suggestion-empty">No conflict-free suggestions found for this block.</div>`;
        }

        return block.suggestions.map(item => `
            <div class="suggestion-card ${escapeHtml(item.fit_class || "valid")}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="suggestion-fit ${escapeHtml(item.fit_class || "valid")}">${escapeHtml(item.fit_label || "Valid Slot")}</span>
                        <span class="suggestion-chip">${escapeHtml(item.pattern_label || "Suggested Slot")}</span>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary btn-use-block-suggestion"
                        data-block-key="${escapeHtml(block.block_key)}"
                        data-room-id="${escapeHtml(item.room_id || "")}"
                        data-time-start="${escapeHtml(item.time_start || "")}"
                        data-time-end="${escapeHtml(item.time_end || "")}"
                        data-days='${escapeHtml(JSON.stringify(item.days || []))}'
                    >
                        Use
                    </button>
                </div>
                <div class="suggestion-slot mt-2">${escapeHtml(item.days_label || "")} • ${escapeHtml(item.time_label || "")}</div>
                <div class="suggestion-meta mt-1">${escapeHtml(item.room_label || "")}</div>
                <div class="suggestion-reasons mt-2">
                    ${(item.reasons || []).map(reason => `<span class="suggestion-reason">${escapeHtml(reason)}</span>`).join("")}
                </div>
            </div>
        `).join("");
    }

    function renderScheduleBlocks() {
        const list = $("#scheduleBlockList");

        if (!scheduleBlockState || !Array.isArray(scheduleBlockState.blocks) || scheduleBlockState.blocks.length === 0) {
            list.html(`
                <div class="schedule-block-empty">
                    No schedule blocks yet. Add a lecture or laboratory block to begin.
                </div>
            `);
            renderScheduleBlockCoverageSummary();
            return;
        }

        const indexMap = { LEC: 0, LAB: 0 };
        const html = scheduleBlockState.blocks.map(block => {
            const label = getScheduleBlockLabel(block, indexMap);
            const type = normalizeScheduleBlockType(block.type);
            const typeBadgeClass = type === "LAB" ? "bg-label-success text-success" : "bg-label-primary text-primary";
            const roomPlaceholder = type === "LAB" ? "Select laboratory room..." : "Select lecture room...";
            const roomOptionsHtml = buildRoomOptionsHtml(filterRoomsForSchedule(type), roomPlaceholder);
            const selectedDays = normalizeScheduleDays(block.days);
            const dayButtons = SCHEDULE_DAY_ORDER.map(day => {
                const isBlocked = BLOCKED_SCHEDULE_DAY_SET.has(day);
                const shouldDisable = isBlocked && !selectedDays.includes(day);
                return `
                <input
                    type="checkbox"
                    class="btn-check schedule-block-day"
                    id="sched_${escapeHtml(block.block_key)}_${day}"
                    data-block-key="${escapeHtml(block.block_key)}"
                    value="${day}"
                    ${shouldDisable ? "disabled" : ""}
                >
                <label
                    class="btn btn-sm me-1 schedule-block-day-chip ${shouldDisable ? "disabled" : ""}"
                    for="sched_${escapeHtml(block.block_key)}_${day}"
                    ${shouldDisable ? 'title="Blocked in Academic Settings"' : ""}
                >${day}</label>
            `;
            }).join("");

            return `
                <div class="schedule-block-card" data-schedule-block="${escapeHtml(block.block_key)}">
                    <div class="schedule-block-header">
                        <div>
                            <span class="badge ${typeBadgeClass} mb-2">${escapeHtml(label)}</span>
                            <div class="schedule-block-meta">This block can be assigned to a different faculty later.</div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-outline-danger btn-sm btn-remove-schedule-block"
                            data-block-key="${escapeHtml(block.block_key)}"
                        >
                            Remove
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label">Days</label>
                            <div class="schedule-block-days">${dayButtons}</div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Time</label>
                            <div class="d-flex gap-2">
                                <input type="time" class="form-control schedule-block-time-start" data-block-key="${escapeHtml(block.block_key)}" min="${escapeHtml(SUPPORTED_TIME_START)}" max="${escapeHtml(SUPPORTED_TIME_END)}" step="1800" value="${escapeHtml(block.time_start || "")}">
                                <input type="time" class="form-control schedule-block-time-end" data-block-key="${escapeHtml(block.block_key)}" min="${escapeHtml(SUPPORTED_TIME_START)}" max="${escapeHtml(SUPPORTED_TIME_END)}" step="1800" value="${escapeHtml(block.time_end || "")}">
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Room</label>
                            <select class="form-select schedule-block-room" data-block-key="${escapeHtml(block.block_key)}">
                                ${roomOptionsHtml}
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm btn-block-suggestions"
                            data-block-key="${escapeHtml(block.block_key)}"
                        >
                            <i class="bx bx-bulb me-1"></i>${block.suggestionsVisible ? "Hide Suggested Schedule" : "Show Suggested Schedule"}
                        </button>
                    </div>

                    <div class="suggestion-panel mt-3 ${block.suggestionsVisible ? "" : "d-none"}" id="blockSuggestionPanel_${escapeHtml(block.block_key)}">
                        <div class="suggestion-board">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="step-label mb-1">Suggested Slots</div>
                                    <div class="schedule-hint mb-0">
                                        Ranked conflict-free options for this ${type === "LAB" ? "laboratory" : "lecture"} block.
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm btn-refresh-block-suggestions" data-block-key="${escapeHtml(block.block_key)}">
                                    Refresh
                                </button>
                            </div>
                            <div class="suggestion-list mt-3" id="blockSuggestionBoard_${escapeHtml(block.block_key)}">
                                ${block.suggestionsVisible ? renderBlockSuggestionCards(block) : '<div class="suggestion-empty">Suggestions will load when you open this panel.</div>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join("");

        list.html(html);

        scheduleBlockState.blocks.forEach(block => {
            const root = $(`[data-schedule-block="${block.block_key}"]`);
            root.find(".schedule-block-room").val(String(block.room_id || ""));
            (block.days || []).forEach(day => {
                root.find(`.schedule-block-day[value="${day}"]`).prop("checked", true);
            });
        });

        $("#btnAddLabBlock").prop("disabled", !getRequiredScheduleTypesClient().includes("LAB"));
        renderScheduleBlockCoverageSummary();
    }

    function syncBlocksFromDom() {
        if (!scheduleBlockState) {
            return;
        }

        scheduleBlockState.blocks = scheduleBlockState.blocks.map(block => {
            const root = $(`[data-schedule-block="${block.block_key}"]`);
            if (!root.length) {
                return block;
            }

            const days = [];
            root.find(".schedule-block-day:checked").each(function () {
                days.push($(this).val());
            });

            return {
                ...block,
                room_id: root.find(".schedule-block-room").val() || "",
                time_start: root.find(".schedule-block-time-start").val() || "",
                time_end: root.find(".schedule-block-time-end").val() || "",
                days
            };
        });
    }

    function collectScheduleBlockDrafts() {
        syncBlocksFromDom();
        return (scheduleBlockState?.blocks || []).map(block => ({
            block_key: block.block_key,
            schedule_id: block.schedule_id || 0,
            type: block.type,
            room_id: block.room_id || "",
            time_start: block.time_start || "",
            time_end: block.time_end || "",
            days: Array.isArray(block.days) ? block.days : []
        }));
    }

    function addScheduleBlock(type) {
        if (!scheduleBlockState) {
            return;
        }

        scheduleBlockState.blocks.push(createScheduleBlock(type));
        renderScheduleBlocks();
    }

    function removeScheduleBlock(blockKey) {
        if (!scheduleBlockState) {
            return;
        }

        scheduleBlockState.blocks = scheduleBlockState.blocks.filter(block => block.block_key !== blockKey);
        renderScheduleBlocks();
    }

    function openScheduleBlockModal(button) {
        const offeringId = parseInt(button.data("offeringId"), 10) || 0;
        const labUnits = Number(button.data("labUnits")) || 0;
        const lecUnits = Number(button.data("lecUnits")) || 0;
        const totalUnits = Number(button.data("totalUnits")) || 0;
        const subjectLabel = `${button.data("subCode")} — ${button.data("subDesc")}`;
        const sectionLabel = `Section: ${button.data("section")}`;

        if (!offeringId) {
            Swal.fire("Error", "Missing offering reference.", "error");
            return;
        }

        loadTermRoomOptions(false).done(function () {
            if (filterRoomsForSchedule("LEC").length === 0) {
                Swal.fire("Room Setup Issue", "No lecture-compatible rooms are available for the selected AY and Semester.", "warning");
                return;
            }

            if (labUnits > 0 && filterRoomsForSchedule("LAB").length === 0) {
                Swal.fire("Room Setup Issue", "No laboratory-compatible rooms are available for the selected AY and Semester.", "warning");
                return;
            }

            $.ajax({
                url: "../backend/query_class_schedule.php",
                type: "POST",
                dataType: "json",
                data: {
                    load_schedule_blocks: 1,
                    offering_id: offeringId
                },
                success: function (res) {
                    if (!res || res.status !== "ok") {
                        Swal.fire("Error", (res && res.message) ? res.message : "Failed to load schedule blocks.", "error");
                        return;
                    }

                    const requiredTypes = Array.isArray(res.required_types) && res.required_types.length > 0
                        ? res.required_types
                        : (labUnits > 0 ? ["LEC", "LAB"] : ["LEC"]);
                    const blocks = Array.isArray(res.blocks) && res.blocks.length > 0
                        ? res.blocks.map(block => createScheduleBlock(block.type, block))
                        : requiredTypes.map(type => createScheduleBlock(type));

                    scheduleBlockState = {
                        offeringId,
                        subjectLabel,
                        sectionLabel,
                        blocks
                    };

                    $("#block_sched_offering_id").val(String(offeringId));
                    $("#block_sched_subject_label").text(subjectLabel);
                    $("#block_sched_section_label").text(sectionLabel);
                    $("#block_sched_lec_units").val(String(lecUnits));
                    $("#block_sched_lab_units").val(String(labUnits));
                    $("#block_sched_total_units").val(String(totalUnits));
                    $("#btnClearBlockSchedule")
                        .data("offering-id", offeringId)
                        .data("subject-label", subjectLabel)
                        .toggleClass("d-none", !(Array.isArray(res.blocks) && res.blocks.length > 0));

                    renderScheduleBlocks();
                    $("#blockScheduleModal").modal("show");
                },
                error: function (xhr) {
                    Swal.fire("Error", xhr.responseText || "Failed to load schedule blocks.", "error");
                }
            });
        }).fail(function (message) {
            Swal.fire("Room Setup Issue", message || "No room is available for selected AY and Semester.", "warning");
        });
    }

    function loadSuggestionsForBlock(blockKey) {
        if (!scheduleBlockState) {
            return;
        }

        syncBlocksFromDom();
        const block = scheduleBlockState.blocks.find(item => item.block_key === blockKey);
        if (!block) {
            return;
        }

        const board = $(`#blockSuggestionBoard_${blockKey}`);
        board.html(`<div class="suggestion-empty">Loading suggestions...</div>`);

        $.ajax({
            url: "../backend/load_schedule_suggestions.php",
            type: "POST",
            dataType: "json",
            data: {
                offering_id: scheduleBlockState.offeringId,
                target_type: block.type,
                target_key: block.block_key,
                drafts_json: JSON.stringify(collectScheduleBlockDrafts())
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    board.html(`<div class="suggestion-empty">${escapeHtml((res && res.message) ? res.message : "Failed to load suggestions.")}</div>`);
                    return;
                }

                block.suggestions = Array.isArray(res.suggestions) ? res.suggestions : [];
                block.suggestionsVisible = true;
                renderScheduleBlocks();
            },
            error: function () {
                board.html(`<div class="suggestion-empty">Failed to load suggestions.</div>`);
            }
        });
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
        return normalizeScheduleDays(days);
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
        applySingleScheduleRestrictions();
    }

    function setDualScheduleDays(prefix, days) {
        $("." + prefix + "-day").prop("checked", false);
        normalizeSuggestionDays(days).forEach(function (day) {
            $("#" + prefix + "_" + day).prop("checked", true);
        });
        applyDualScheduleRestrictions();
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
                        initializeScheduleTablePan();
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

    function initializeScheduleTablePan() {
        $("#scheduleListContainer .schedule-pan-shell").each(function () {
            const shell = this;
            if (shell.dataset.panReady === "1") {
                return;
            }

            shell.dataset.panReady = "1";

            let activePointerId = null;
            let startX = 0;
            let startScrollLeft = 0;
            let isDragging = false;
            let suppressClick = false;

            function finishPan(event) {
                if (activePointerId === null) {
                    return;
                }

                if (event && typeof event.pointerId !== "undefined" && event.pointerId !== activePointerId) {
                    return;
                }

                const wasDragging = isDragging;
                if (shell.releasePointerCapture && shell.hasPointerCapture && shell.hasPointerCapture(activePointerId)) {
                    try {
                        shell.releasePointerCapture(activePointerId);
                    } catch (error) {
                        // Ignore release failures for browsers that auto-release capture.
                    }
                }

                activePointerId = null;
                isDragging = false;
                shell.classList.remove("is-pan-active");

                if (!wasDragging) {
                    suppressClick = false;
                }
            }

            shell.addEventListener("pointerdown", function (event) {
                if (typeof event.button !== "undefined" && event.button !== 0) {
                    return;
                }

                if (shell.scrollWidth <= shell.clientWidth + 4) {
                    return;
                }

                activePointerId = event.pointerId;
                startX = event.clientX;
                startScrollLeft = shell.scrollLeft;
                isDragging = false;
                suppressClick = false;

                if (shell.setPointerCapture) {
                    try {
                        shell.setPointerCapture(activePointerId);
                    } catch (error) {
                        // Ignore browsers that do not support pointer capture in this context.
                    }
                }
            });

            shell.addEventListener("pointermove", function (event) {
                if (activePointerId === null || event.pointerId !== activePointerId) {
                    return;
                }

                const deltaX = event.clientX - startX;
                if (!isDragging && Math.abs(deltaX) > 6) {
                    isDragging = true;
                    shell.classList.add("is-pan-active");
                }

                if (!isDragging) {
                    return;
                }

                shell.scrollLeft = startScrollLeft - deltaX;
                suppressClick = true;
                event.preventDefault();
            });

            shell.addEventListener("pointerup", finishPan);
            shell.addEventListener("pointercancel", finishPan);
            shell.addEventListener("lostpointercapture", finishPan);

            shell.addEventListener("click", function (event) {
                if (!suppressClick) {
                    return;
                }

                suppressClick = false;
                event.preventDefault();
                event.stopPropagation();
            }, true);
        });
    }

    let autoDraftState = null;
    let autoDraftRequest = null;
    let autoDraftHasPreview = false;
    let autoDraftIsLoading = false;
    let autoDraftStartedAt = 0;
    let autoDraftLastDurationMs = 0;
    let autoDraftLoaderTimer = null;

    function formatAutoDraftElapsed(ms) {
        const safeMs = Math.max(0, Number(ms || 0));
        const seconds = safeMs / 1000;
        if (seconds < 60) {
            return `${seconds < 10 ? seconds.toFixed(1) : seconds.toFixed(0)}s`;
        }

        const minutes = Math.floor(seconds / 60);
        const remainder = Math.round(seconds % 60);
        return `${minutes}m ${String(remainder)}s`;
    }

    function updateAutoDraftButtonState() {
        const label = autoDraftIsLoading
            ? `Drafting... ${formatAutoDraftElapsed(Date.now() - autoDraftStartedAt)}`
            : (autoDraftHasPreview ? "Refresh Draft" : "Start Draft");

        $("#btnPreviewAutoDraft").html(`<i class="bx bx-magic-wand me-1"></i> ${escapeHtml(label)}`);
        $("#btnRefreshAutoDraft").text(label);
        $("#btnPreviewAutoDraft, #btnRefreshAutoDraft").prop("disabled", autoDraftIsLoading);
    }

    function stopAutoDraftLoader() {
        autoDraftIsLoading = false;
        autoDraftStartedAt = 0;
        if (autoDraftLoaderTimer) {
            clearInterval(autoDraftLoaderTimer);
            autoDraftLoaderTimer = null;
        }
        $("#autoDraftLoader").addClass("d-none");
        $("#autoDraftLoaderText").text("Drafting... 0.0s");
        updateAutoDraftButtonState();
    }

    function startAutoDraftLoader() {
        stopAutoDraftLoader();
        autoDraftIsLoading = true;
        autoDraftStartedAt = Date.now();
        $("#autoDraftLoader").removeClass("d-none");
        $("#autoDraftLoaderText").text(`Drafting... ${formatAutoDraftElapsed(0)}`);
        updateAutoDraftButtonState();

        autoDraftLoaderTimer = window.setInterval(function () {
            const elapsed = Date.now() - autoDraftStartedAt;
            $("#autoDraftLoaderText").text(`Drafting... ${formatAutoDraftElapsed(elapsed)}`);
            $("#autoDraftSummaryTimer").text(`Elapsed: ${formatAutoDraftElapsed(elapsed)}`);
            updateAutoDraftButtonState();
        }, 200);
    }

    function resetAutoDraftPreview(message = "Draft summary will appear here.") {
        autoDraftState = null;
        autoDraftHasPreview = false;
        autoDraftLastDurationMs = 0;
        stopAutoDraftLoader();
        $("#autoDraftSummary").html(`<strong>${escapeHtml(message)}</strong>`);
        $("#autoDraftRuleList").html(`<span class="auto-draft-rule">${escapeHtml("Rules will load with the preview.")}</span>`);
        $("#autoDraftReadyCount").text("0");
        $("#autoDraftManualCount").text("0");
        $("#autoDraftReadyList").html(`<div class="auto-draft-empty">Generate a preview to see draft schedules.</div>`);
        $("#autoDraftStatusGroupList").html(`<div class="auto-draft-empty">Subjects that cannot be auto-scheduled will appear here by status.</div>`);
        $("#btnApplyAutoDraft").prop("disabled", true);
        updateAutoDraftButtonState();
    }

    function renderAutoDraftErrorState(message) {
        autoDraftState = null;
        autoDraftHasPreview = false;
        autoDraftLastDurationMs = 0;
        stopAutoDraftLoader();
        $("#autoDraftSummary").html(`<strong>${escapeHtml(message || "Failed to generate the draft preview.")}</strong>`);
        $("#autoDraftRuleList").html(`<span class="auto-draft-rule">${escapeHtml("Rules were not loaded because the draft did not finish.")}</span>`);
        $("#autoDraftReadyCount").text("0");
        $("#autoDraftManualCount").text("0");
        $("#autoDraftReadyList").html(`<div class="auto-draft-empty">${escapeHtml(message || "Failed to generate the draft preview.")}</div>`);
        $("#autoDraftStatusGroupList").html(`<div class="auto-draft-empty">Try starting the draft again after checking the current term filters.</div>`);
        $("#btnApplyAutoDraft").prop("disabled", true);
        updateAutoDraftButtonState();
    }

    function getCurrentScheduleFilters() {
        return {
            prospectus_id: $("#prospectus_id").val(),
            ay_id: $("#ay_id").val(),
            semester: $("#semester").val()
        };
    }

    function autoDraftStatusBadge(statusKey, statusLabel) {
        const key = String(statusKey || "").toLowerCase();
        let badgeClass = "bg-secondary";
        if (key === "incomplete") {
            badgeClass = "bg-warning text-dark";
        } else if (key === "scheduled") {
            badgeClass = "bg-success";
        }

        return `<span class="badge ${badgeClass}">${escapeHtml(statusLabel || "Pending")}</span>`;
    }

    function setAutoDraftLoadingState(message = "Preparing draft preview...") {
        autoDraftState = null;
        autoDraftLastDurationMs = 0;
        startAutoDraftLoader();
        $("#autoDraftSummary").html(`
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                <strong>${escapeHtml(message)}</strong>
            </div>
            <div class="coverage-detail mt-2" id="autoDraftSummaryTimer">Elapsed: ${escapeHtml(formatAutoDraftElapsed(0))}</div>
        `);
        $("#autoDraftRuleList").html(`<span class="auto-draft-rule">${escapeHtml("Rules will load with the preview.")}</span>`);
        $("#autoDraftReadyCount").text("0");
        $("#autoDraftManualCount").text("0");
        $("#autoDraftReadyList").html(`<div class="auto-draft-empty">${escapeHtml(message)}</div>`);
        $("#autoDraftStatusGroupList").html(`<div class="auto-draft-empty">Subjects that cannot be auto-scheduled will appear here by status.</div>`);
        $("#btnApplyAutoDraft").prop("disabled", true);
        updateAutoDraftButtonState();
    }

    function renderAutoDraftRules(rules) {
        const list = Array.isArray(rules) ? rules.filter(Boolean) : [];
        if (list.length === 0) {
            $("#autoDraftRuleList").html(`<span class="auto-draft-rule">No rule notes were returned.</span>`);
            return;
        }

        $("#autoDraftRuleList").html(
            list.map(rule => `<span class="auto-draft-rule">${escapeHtml(rule)}</span>`).join("")
        );
    }

    function renderAutoDraftReadyList(items) {
        const list = Array.isArray(items) ? items : [];
        if (list.length === 0) {
            $("#autoDraftReadyList").html(`<div class="auto-draft-empty">No complete draft could be produced for the current filters.</div>`);
            return;
        }

        const html = list.map(item => {
            const blocksHtml = (Array.isArray(item.blocks) ? item.blocks : []).map(block => `
                <div class="auto-draft-block">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge ${block.type === "LAB" ? "bg-label-success text-success" : "bg-label-primary text-primary"}">${escapeHtml(block.type_label || block.type || "Block")}</span>
                            <span class="suggestion-chip">${escapeHtml(block.pattern_label || "Draft Pattern")}</span>
                        </div>
                        <div class="auto-draft-block-meta">${escapeHtml(block.days_label || "")}</div>
                    </div>
                    <div class="suggestion-slot mt-2">${escapeHtml(block.days_label || "")} • ${escapeHtml(block.time_label || "")}</div>
                    <div class="auto-draft-block-meta mt-1">${escapeHtml(block.room_label || "")}</div>
                </div>
            `).join("");

            return `
                <div class="auto-draft-card">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="auto-draft-title">${escapeHtml(item.sub_code || "")} - ${escapeHtml(item.sub_description || "")}</div>
                            <div class="auto-draft-subtitle mt-1">${escapeHtml(item.section_name || "")}</div>
                        </div>
                        ${autoDraftStatusBadge(item.current_status_key, item.current_status_label)}
                    </div>
                    <div class="auto-draft-blocks">${blocksHtml}</div>
                </div>
            `;
        }).join("");

        $("#autoDraftReadyList").html(html);
    }

    function renderAutoDraftManualCards(items) {
        return (Array.isArray(items) ? items : []).map(item => `
            <div class="auto-draft-card manual">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="auto-draft-title">${escapeHtml(item.sub_code || "")} - ${escapeHtml(item.sub_description || "")}</div>
                        <div class="auto-draft-subtitle mt-1">${escapeHtml(item.section_name || "")}</div>
                    </div>
                    ${autoDraftStatusBadge(item.current_status_key, item.current_status_label)}
                </div>
                <div class="auto-draft-reason mt-3">${escapeHtml(item.reason || "Manual scheduling is still required.")}</div>
            </div>
        `).join("");
    }

    function groupAutoDraftManualItems(items) {
        const source = Array.isArray(items) ? items : [];
        const buckets = {
            not_scheduled: {
                key: "not_scheduled",
                label: "Not Scheduled",
                items: []
            },
            incomplete: {
                key: "incomplete",
                label: "Incomplete",
                items: []
            }
        };
        const otherBuckets = {};

        source.forEach(item => {
            const key = String(item?.current_status_key || "").toLowerCase();
            const label = String(item?.current_status_label || "Other Status").trim() || "Other Status";
            if (key === "not_scheduled") {
                buckets.not_scheduled.items.push(item);
                return;
            }

            if (key === "incomplete") {
                buckets.incomplete.items.push(item);
                return;
            }

            if (!otherBuckets[label]) {
                otherBuckets[label] = {
                    key: `other_${label.toLowerCase().replace(/[^a-z0-9]+/g, "_")}`,
                    label,
                    items: []
                };
            }
            otherBuckets[label].items.push(item);
        });

        const groups = [];
        if (buckets.not_scheduled.items.length > 0) {
            groups.push(buckets.not_scheduled);
        }
        if (buckets.incomplete.items.length > 0) {
            groups.push(buckets.incomplete);
        }
        Object.keys(otherBuckets).sort().forEach(label => {
            groups.push(otherBuckets[label]);
        });

        return groups;
    }

    function renderAutoDraftStatusGroups(items) {
        const groups = groupAutoDraftManualItems(items);
        if (groups.length === 0) {
            $("#autoDraftStatusGroupList").html(`<div class="auto-draft-empty">Everything targeted by the preview found a complete draft.</div>`);
            return;
        }

        const html = groups.map(group => `
            <div class="auto-draft-status-group">
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <div class="step-label mb-1">${escapeHtml(group.label)}</div>
                        <div class="schedule-hint mb-0">Current status bucket for offerings that still need manual scheduling.</div>
                    </div>
                    <span class="badge bg-label-warning text-warning">${escapeHtml(String(group.items.length))}</span>
                </div>
                <div class="auto-draft-list mt-3">
                    ${renderAutoDraftManualCards(group.items)}
                </div>
            </div>
        `).join("");

        $("#autoDraftStatusGroupList").html(html);
    }

    function renderAutoDraftSummary(summary, durationMs = 0) {
        const targetCount = Number(summary?.target_count || 0);
        const readyCount = Number(summary?.ready_count || 0);
        const manualCount = Number(summary?.manual_count || 0);
        const skippedCount = Number(summary?.skipped_complete_count || 0);
        const generatedIn = Number(durationMs || 0) > 0
            ? `Generated in ${formatAutoDraftElapsed(durationMs)}.`
            : "";

        let lead = `${readyCount} offering(s) are ready to apply.`;
        if (targetCount === 0 && skippedCount > 0) {
            lead = `All ${skippedCount} offering(s) under the current filters are already fully scheduled.`;
        } else if (targetCount === 0) {
            lead = "No incomplete or unscheduled offering was found for the current filters.";
        }

        $("#autoDraftSummary").html(`
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <strong>${escapeHtml(lead)}</strong>
                    <div class="coverage-detail mt-1">
                        Preview checked ${escapeHtml(String(targetCount))} incomplete or unscheduled offering(s) and skipped ${escapeHtml(String(skippedCount))} complete offering(s).
                    </div>
                    ${generatedIn ? `<div class="coverage-detail mt-1">${escapeHtml(generatedIn)}</div>` : ""}
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-label-success text-success">Ready ${escapeHtml(String(readyCount))}</span>
                    <span class="badge bg-label-warning text-warning">Manual ${escapeHtml(String(manualCount))}</span>
                    <span class="badge bg-label-secondary text-secondary">Skipped ${escapeHtml(String(skippedCount))}</span>
                </div>
            </div>
        `);

        $("#autoDraftReadyCount").text(String(readyCount));
        $("#autoDraftManualCount").text(String(manualCount));
    }

    function loadAutoDraftPreview() {
        const filters = getCurrentScheduleFilters();
        if (!filters.prospectus_id || !filters.ay_id || !filters.semester) {
            Swal.fire("Missing Filters", "Select Prospectus, Academic Year, and Semester first.", "warning");
            return;
        }

        if (autoDraftRequest && autoDraftRequest.readyState !== 4) {
            autoDraftRequest.abort();
        }

        setAutoDraftLoadingState();
        $("#autoDraftModal").modal("show");

        autoDraftRequest = $.ajax({
            url: "../backend/auto_schedule_draft.php",
            type: "POST",
            dataType: "json",
            data: {
                preview_auto_schedule_draft: 1,
                prospectus_id: filters.prospectus_id,
                ay_id: filters.ay_id,
                semester: filters.semester
            },
            success: function (res) {
                const durationMs = autoDraftStartedAt ? (Date.now() - autoDraftStartedAt) : 0;
                stopAutoDraftLoader();
                if (!res || res.status !== "ok") {
                    renderAutoDraftErrorState((res && res.message) ? res.message : "Failed to generate the draft preview.");
                    return;
                }

                autoDraftHasPreview = true;
                autoDraftLastDurationMs = durationMs;
                autoDraftState = {
                    filters,
                    ready: Array.isArray(res.ready) ? res.ready : [],
                    manual: Array.isArray(res.manual) ? res.manual : [],
                    summary: res.summary || {},
                    rules: Array.isArray(res.rules) ? res.rules : []
                };

                updateAutoDraftButtonState();
                renderAutoDraftSummary(autoDraftState.summary, durationMs);
                renderAutoDraftRules(autoDraftState.rules);
                renderAutoDraftReadyList(autoDraftState.ready);
                renderAutoDraftStatusGroups(autoDraftState.manual);
                $("#btnApplyAutoDraft").prop("disabled", autoDraftState.ready.length === 0);
            },
            error: function (xhr) {
                stopAutoDraftLoader();
                if (xhr.statusText === "abort") {
                    return;
                }
                renderAutoDraftErrorState("Failed to generate the draft preview.");
            }
        });
    }

    function applyAutoDraft() {
        if (!autoDraftState || !Array.isArray(autoDraftState.ready) || autoDraftState.ready.length === 0) {
            Swal.fire("No Draft", "Generate a draft preview first.", "warning");
            return;
        }

        const filters = autoDraftState.filters || getCurrentScheduleFilters();
        const draftPayload = autoDraftState.ready.map(item => ({
            offering_id: item.offering_id,
            blocks: item.blocks || []
        }));

        Swal.fire({
            icon: "question",
            title: "Apply Auto Draft?",
            html: `This will save <b>${escapeHtml(String(draftPayload.length))}</b> generated schedule draft(s). Incomplete drafts are not included.`,
            showCancelButton: true,
            confirmButtonText: "Apply Draft",
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $("#btnApplyAutoDraft").prop("disabled", true);

            $.ajax({
                url: "../backend/auto_schedule_draft.php",
                type: "POST",
                dataType: "json",
                data: {
                    apply_auto_schedule_draft: 1,
                    prospectus_id: filters.prospectus_id,
                    ay_id: filters.ay_id,
                    semester: filters.semester,
                    draft_items_json: JSON.stringify(draftPayload)
                },
                success: function (res) {
                    const appliedCount = Number(res?.applied_count || 0);
                    const failedCount = Number(res?.failed_count || 0);

                    if (res && (res.status === "ok" || res.status === "partial")) {
                        const failureLines = Array.isArray(res.failed)
                            ? res.failed.slice(0, 6).map(item => `${escapeHtml(item.sub_code || "")} ${escapeHtml(item.section_name || "")}: ${escapeHtml(item.reason || "")}`)
                            : [];

                        let html = `${escapeHtml(String(appliedCount))} draft(s) applied.`;
                        if (failedCount > 0) {
                            html += `<br><br>${escapeHtml(String(failedCount))} draft(s) still need manual review.`;
                            if (failureLines.length > 0) {
                                html += `<br><br>${failureLines.join("<br>")}`;
                            }
                        }

                        Swal.fire({
                            icon: res.status === "ok" ? "success" : "warning",
                            title: res.status === "ok" ? "Draft Applied" : "Draft Applied With Notes",
                            html: html,
                            allowOutsideClick: false,
                            customClass: { popup: "swal-top" }
                        });

                        $("#autoDraftModal").modal("hide");
                        resetAutoDraftPreview();
                        loadScheduleTable();
                        return;
                    }

                    Swal.fire("Error", (res && res.message) ? res.message : "Failed to apply the generated draft.", "error");
                    $("#btnApplyAutoDraft").prop("disabled", false);
                },
                error: function (xhr) {
                    Swal.fire("Error", xhr.responseText || "Failed to apply the generated draft.", "error");
                    $("#btnApplyAutoDraft").prop("disabled", false);
                }
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
                        $("#blockScheduleModal").modal("hide");

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

    function clearAllSchedulesInScope() {
        const filters = getCurrentScheduleFilters();
        if (!filters.prospectus_id || !filters.ay_id || !filters.semester) {
            Swal.fire("Missing Filters", "Select Prospectus, Academic Year, and Semester first.", "warning");
            return;
        }

        const $button = $("#btnClearAllSchedules");
        const originalHtml = $button.html();

        Swal.fire({
            icon: "warning",
            title: "Clear All Schedules?",
            html: [
                "This will remove all saved schedules for the current <b>prospectus, academic year, and semester</b> within your college scope.",
                "Locked offerings and workload-assigned offerings will be skipped."
            ].join("<br><br>"),
            showCancelButton: true,
            confirmButtonText: "Yes, clear all",
            confirmButtonColor: "#dc3545",
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $button
                .prop("disabled", true)
                .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Clearing...');

            Swal.fire({
                title: "Clearing Schedules...",
                html: "Please wait while the selected college offering schedules are being reset.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: { popup: "swal-top" },
                didOpen: function () {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: "../backend/query_class_schedule.php",
                type: "POST",
                dataType: "json",
                data: {
                    clear_all_college_schedules: 1,
                    prospectus_id: filters.prospectus_id,
                    ay_id: filters.ay_id,
                    semester: filters.semester
                },
                success: function (res) {
                    Swal.close();

                    if (!res || (res.status !== "ok" && res.status !== "partial")) {
                        Swal.fire("Error", (res && res.message) ? res.message : "Failed to clear schedules.", "error");
                        return;
                    }

                    const scopedCount = Number(res?.scoped_offering_count || 0);
                    const clearableCount = Number(res?.clearable_offering_count || 0);
                    const clearedOfferingCount = Number(res?.cleared_offering_count || 0);
                    const deletedRowCount = Number(res?.deleted_schedule_row_count || 0);
                    const resetOfferingCount = Number(res?.reset_offering_count || 0);
                    const skippedCount = Number(res?.skipped_count || 0);
                    const skippedLines = Array.isArray(res?.skipped)
                        ? res.skipped.slice(0, 8).map(item => {
                            const parts = [
                                escapeHtml(item?.sub_code || ""),
                                escapeHtml(item?.section_name || "")
                            ].filter(Boolean);
                            const label = parts.join(" ");
                            return `${label || "Offering"}: ${escapeHtml(item?.reason || "Skipped.")}`;
                        })
                        : [];

                    let html = `Checked <b>${escapeHtml(String(scopedCount))}</b> offering(s) in the current scope.`;
                    html += `<br><br>Eligible for clearing: <b>${escapeHtml(String(clearableCount))}</b>`;
                    html += `<br>Offerings with saved schedules removed: <b>${escapeHtml(String(clearedOfferingCount))}</b>`;
                    html += `<br>Schedule rows deleted: <b>${escapeHtml(String(deletedRowCount))}</b>`;
                    html += `<br>Offerings reset to pending: <b>${escapeHtml(String(resetOfferingCount))}</b>`;

                    if (skippedCount > 0) {
                        html += `<br><br>Skipped offering(s): <b>${escapeHtml(String(skippedCount))}</b>`;
                        if (skippedLines.length > 0) {
                            html += `<br><br>${skippedLines.join("<br>")}`;
                        }
                    }

                    let resultIcon = res.status === "ok" ? "success" : "warning";
                    let resultTitle = res.status === "ok" ? "Schedules Cleared" : "Schedules Cleared With Notes";

                    if (scopedCount === 0) {
                        resultIcon = "info";
                        resultTitle = "No Offerings Found";
                    } else if (deletedRowCount === 0 && resetOfferingCount === 0 && skippedCount === 0) {
                        resultIcon = "info";
                        resultTitle = "Nothing To Clear";
                    }

                    Swal.fire({
                        icon: resultIcon,
                        title: resultTitle,
                        html: html,
                        allowOutsideClick: false,
                        customClass: { popup: "swal-top" }
                    });

                    $("#scheduleModal").modal("hide");
                    $("#dualScheduleModal").modal("hide");
                    $("#blockScheduleModal").modal("hide");
                    $("#autoDraftModal").modal("hide");

                    resetAutoDraftPreview();
                    loadScheduleTable();
                },
                error: function (xhr) {
                    Swal.close();
                    Swal.fire("Error", xhr.responseText || "Failed to clear schedules.", "error");
                },
                complete: function () {
                    $button.prop("disabled", false).html(originalHtml);
                }
            });
        });
    }

    // ===============================================================
$(document).ready(function () {

ensureDefaultProspectus();
resetSingleSuggestionPanel();
resetDualSuggestionPanels();
resetAutoDraftPreview();

$("#prospectus_id, #ay_id, #semester").on("change", function () {
  if (this.id === "ay_id" || this.id === "semester") {
    clearTermRoomOptions();
  }
  resetAutoDraftPreview();
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

$("#btnPreviewAutoDraft, #btnRefreshAutoDraft").on("click", function () {
  loadAutoDraftPreview();
});

$("#btnApplyAutoDraft").on("click", function () {
  applyAutoDraft();
});

$("#btnClearAllSchedules").on("click", function () {
  clearAllSchedulesInScope();
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

$("#autoDraftModal").on("hidden.bs.modal", function () {
  if (autoDraftRequest && autoDraftRequest.readyState !== 4) {
    autoDraftRequest.abort();
  }
  stopAutoDraftLoader();
});

$(document).on("input change", "#scheduleModal .sched-day, #scheduleModal #sched_time_start, #scheduleModal #sched_time_end, #scheduleModal #sched_room_id", function () {
  applySingleScheduleRestrictions();
  if ($("#scheduleModal").hasClass("show")) {
    queueSingleSuggestionRefresh();
  }
});

$(document).on("input change", "#dualScheduleModal .lec-day, #dualScheduleModal .lab-day, #dualScheduleModal #lec_time_start, #dualScheduleModal #lec_time_end, #dualScheduleModal #lec_room_id, #dualScheduleModal #lab_time_start, #dualScheduleModal #lab_time_end, #dualScheduleModal #lab_room_id", function () {
  applyDualScheduleRestrictions();
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
      Loading room utilization for ${escapeHtml(scheduleWindowLabel())}...
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
        applySingleScheduleRestrictions();

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
applyDualScheduleRestrictions();

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
            applyDualScheduleRestrictions();

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

// ============================
// SAVE CLASS SCHEDULE
// ============================
$(document).off("click", "#btnSaveSchedule").on("click", "#btnSaveSchedule", function (event) {
    event.preventDefault();
    event.stopPropagation();

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

    const singlePolicyIssue = validateSchedulePolicy(days, time_start, time_end, "Class schedule");
    if (singlePolicyIssue) {
        showValidation(
            singlePolicyIssue.title,
            singlePolicyIssue.message
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

// =======================================================
// SAVE LECTURE + LAB SCHEDULE
// =======================================================
$(document).off("click", "#btnSaveDualSchedule").on("click", "#btnSaveDualSchedule", function (event) {
    event.preventDefault();
    event.stopPropagation();

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

    const lecturePolicyIssue = validateSchedulePolicy(lec.days, lec.time_start, lec.time_end, "Lecture schedule");
    if (lecturePolicyIssue) {
        invalidBlock(lecturePolicyIssue.title, lecturePolicyIssue.message);
        return;
    }

    const labPolicyIssue = validateSchedulePolicy(lab.days, lab.time_start, lab.time_end, "Laboratory schedule");
    if (labPolicyIssue) {
        invalidBlock(labPolicyIssue.title, labPolicyIssue.message);
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

$("#blockScheduleModal").on("hidden.bs.modal", function () {
    scheduleBlockState = null;
    $("#scheduleBlockList").empty();
    $("#scheduleBlockCoverageSummary").html("<strong>Schedule coverage will appear here.</strong>");
});

$("#btnAddLectureBlock").on("click", function () {
    addScheduleBlock("LEC");
});

$("#btnAddLabBlock").on("click", function () {
    if (!getRequiredScheduleTypesClient().includes("LAB")) {
        Swal.fire("Lecture Only Subject", "This subject does not allow laboratory schedule blocks.", "warning");
        return;
    }
    addScheduleBlock("LAB");
});

$("#btnClearBlockSchedule").on("click", function () {
    clearScheduleForOffering(
        $(this).data("offering-id"),
        $(this).data("subject-label")
    );
});

$(document).on("input change", "#blockScheduleModal .schedule-block-day, #blockScheduleModal .schedule-block-time-start, #blockScheduleModal .schedule-block-time-end, #blockScheduleModal .schedule-block-room", function () {
    syncBlocksFromDom();
    renderScheduleBlockCoverageSummary();
});

$(document).on("click", "#blockScheduleModal .btn-remove-schedule-block", function () {
    removeScheduleBlock(String($(this).data("blockKey") || ""));
});

$(document).on("click", "#blockScheduleModal .btn-block-suggestions", function () {
    const blockKey = String($(this).data("blockKey") || "");
    if (!scheduleBlockState) {
        return;
    }

    const block = scheduleBlockState.blocks.find(item => item.block_key === blockKey);
    if (!block) {
        return;
    }

    if (block.suggestionsVisible) {
        block.suggestionsVisible = false;
        renderScheduleBlocks();
        return;
    }

    block.suggestionsVisible = true;
    renderScheduleBlocks();
    loadSuggestionsForBlock(blockKey);
});

$(document).on("click", "#blockScheduleModal .btn-refresh-block-suggestions", function () {
    loadSuggestionsForBlock(String($(this).data("blockKey") || ""));
});

$(document).on("click", "#blockScheduleModal .btn-use-block-suggestion", function () {
    if (!scheduleBlockState) {
        return;
    }

    const blockKey = String($(this).data("blockKey") || "");
    const block = scheduleBlockState.blocks.find(item => item.block_key === blockKey);
    if (!block) {
        return;
    }

    let days = [];
    try {
        days = JSON.parse($(this).attr("data-days") || "[]");
    } catch (error) {
        days = [];
    }

    block.room_id = String($(this).data("roomId") || "");
    block.time_start = String($(this).data("timeStart") || "");
    block.time_end = String($(this).data("timeEnd") || "");
    block.days = Array.isArray(days) ? days.filter(day => SCHEDULE_DAY_ORDER.includes(day)) : [];
    block.suggestionsVisible = false;
    renderScheduleBlocks();
});

$(document).off("click", ".btn-schedule").on("click", ".btn-schedule", function () {
    openScheduleBlockModal($(this));
});

$(document).off("click", "#btnSaveScheduleBlocks").on("click", "#btnSaveScheduleBlocks", function (event) {
    event.preventDefault();
    event.stopPropagation();

    if (!scheduleBlockState) {
        Swal.fire("Error", "No schedule block context is loaded.", "error");
        return;
    }

    syncBlocksFromDom();

    if (!Array.isArray(scheduleBlockState.blocks) || scheduleBlockState.blocks.length === 0) {
        Swal.fire("Missing Blocks", "Add at least one lecture or laboratory block before saving.", "warning");
        return;
    }

    const labels = [];
    const labelIndexMap = { LEC: 0, LAB: 0 };
    scheduleBlockState.blocks.forEach(block => {
        labels.push(getScheduleBlockLabel(block, labelIndexMap));
    });

    for (let i = 0; i < scheduleBlockState.blocks.length; i++) {
        const block = scheduleBlockState.blocks[i];
        const label = labels[i];

        if (!block.room_id || !block.time_start || !block.time_end || !Array.isArray(block.days) || block.days.length === 0) {
            Swal.fire("Incomplete Block", `${label} is incomplete.`, "warning");
            return;
        }

        if (block.time_end <= block.time_start) {
            Swal.fire("Invalid Time Range", `${label} must end later than it starts.`, "warning");
            return;
        }

        const blockPolicyIssue = validateSchedulePolicy(block.days, block.time_start, block.time_end, label);
        if (blockPolicyIssue) {
            Swal.fire(blockPolicyIssue.title, blockPolicyIssue.message, "warning");
            return;
        }
    }

    for (let i = 0; i < scheduleBlockState.blocks.length; i++) {
        for (let j = i + 1; j < scheduleBlockState.blocks.length; j++) {
            const left = scheduleBlockState.blocks[i];
            const right = scheduleBlockState.blocks[j];

            const overlapDays = (left.days || []).filter(day => (right.days || []).includes(day));
            if (overlapDays.length === 0) {
                continue;
            }

            if (!(left.time_start < right.time_end && left.time_end > right.time_start)) {
                continue;
            }

            Swal.fire(
                "Internal Schedule Conflict",
                `${labels[i]} overlaps with ${labels[j]}.`,
                "error"
            );
            return;
        }
    }

    $.ajax({
        url: "../backend/query_class_schedule.php",
        type: "POST",
        dataType: "json",
        data: {
            save_schedule_blocks: 1,
            offering_id: $("#block_sched_offering_id").val(),
            blocks: scheduleBlockState.blocks.map(block => ({
                schedule_id: block.schedule_id || 0,
                type: block.type,
                room_id: block.room_id,
                time_start: block.time_start,
                time_end: block.time_end,
                days_json: JSON.stringify(block.days || [])
            }))
        },
        success: function (res) {
            if (res.status === "conflict") {
                Swal.fire({
                    icon: "error",
                    title: "Schedule Conflict",
                    html: res.message,
                    allowOutsideClick: false,
                    customClass: { popup: "swal-top" }
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

                $("#blockScheduleModal").modal("hide");
                setTimeout(function () {
                    loadScheduleTable();
                }, 300);
                return;
            }

            Swal.fire("Error", res.message || "Unknown error.", "error");
        },
        error: function (xhr) {
            Swal.fire("Error", xhr.responseText || "Failed to save schedule blocks.", "error");
        }
    });
});

});




</script>


</body>
</html>
