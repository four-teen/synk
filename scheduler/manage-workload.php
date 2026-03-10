<?php 
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';
require_once '../backend/schema_helper.php';

/* =====================================================
   WORKLOAD COMPUTATION CONFIG
   NOTE:
   - 1 LAB unit = 3 contact hours
   - Each contact hour × LAB_LOAD_MULTIPLIER
   - This is intentionally hardcoded for now
   - Future: move to settings table
===================================================== */

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$college_id   = $_SESSION['college_id'];
$college_name = $_SESSION['college_name'] ?? '';
$campus_name  = '';
$currentTerm = synk_fetch_current_academic_term($conn);
$defaultAyId = (int)$currentTerm['ay_id'];
$defaultAyLabel = (string)$currentTerm['ay_label'];
$defaultSemesterUi = '';

if ((int)$college_id > 0) {
    $campusStmt = $conn->prepare("
        SELECT cp.campus_name
        FROM tbl_college c
        LEFT JOIN tbl_campus cp ON cp.campus_id = c.campus_id
        WHERE c.college_id = ?
        LIMIT 1
    ");

    if ($campusStmt) {
        $campusStmt->bind_param("i", $college_id);
        $campusStmt->execute();
        $campusResult = $campusStmt->get_result();
        $campusRow = $campusResult ? $campusResult->fetch_assoc() : null;
        $campus_name = trim((string)($campusRow['campus_name'] ?? ''));
        $campusStmt->close();
    }
}

if ((int)$currentTerm['semester'] === 1) {
    $defaultSemesterUi = '1st';
} elseif ((int)$currentTerm['semester'] === 2) {
    $defaultSemesterUi = '2nd';
} elseif ((int)$currentTerm['semester'] === 3) {
    $defaultSemesterUi = 'Midyear';
}

// LOAD FACULTY ASSIGNED TO THIS COLLEGE
$faculty_options = "";
$assignmentHasAyId = synk_table_has_column($conn, 'tbl_college_faculty', 'ay_id');
$assignmentHasSemester = synk_table_has_column($conn, 'tbl_college_faculty', 'semester');
$facultySql = "
    SELECT DISTINCT f.faculty_id,
           CONCAT(f.last_name, ', ', f.first_name, ' ', COALESCE(f.ext_name,'')) AS full_name
    FROM tbl_college_faculty cf
    JOIN tbl_faculty f ON cf.faculty_id = f.faculty_id
    WHERE cf.college_id = ?
      AND cf.status = 'active'
      AND f.status  = 'active'
";

$facultyTypes = 'i';
$facultyParams = [(int)$college_id];

if ($assignmentHasAyId) {
    $facultySql .= " AND cf.ay_id = ?";
    $facultyTypes .= 'i';
    $facultyParams[] = (int)$defaultAyId;
}

if ($assignmentHasSemester) {
    $facultySql .= " AND cf.semester = ?";
    $facultyTypes .= 'i';
    $facultyParams[] = (int)$currentTerm['semester'];
}

$facultySql .= " ORDER BY f.last_name, f.first_name";

$facultyStmt = $conn->prepare($facultySql);
if ($facultyStmt instanceof mysqli_stmt) {
    synk_bind_dynamic_params($facultyStmt, $facultyTypes, $facultyParams);
    $facultyStmt->execute();
    $facultyResult = $facultyStmt->get_result();

    while ($f = $facultyResult->fetch_assoc()) {
        $fid = (int)$f['faculty_id'];
        $fname = htmlspecialchars((string)$f['full_name'], ENT_QUOTES, 'UTF-8');
        $faculty_options .= "<option value='{$fid}'>{$fname}</option>";
    }

    $facultyStmt->close();
}

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
    <title>Faculty Workload | Synk Scheduler</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"/>

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
        .fw-summary-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .table-sm td {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }

        #facultyAlert .alert {
            background: #eaf8ff;
            border-color: #cbeaf8;
            color: #1a7da8;
        }

        .workload-card {
            border: 1px solid #dbe5f1;
            box-shadow: 0 2px 8px rgba(18, 38, 63, 0.05);
        }

        .workload-table thead th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #5f728b;
            border-bottom: 1px solid #dbe4ef;
            border-top: 1px solid #dbe4ef;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), inset 0 -1px 0 rgba(204, 216, 229, 0.9);
            white-space: nowrap;
        }

        .workload-table tbody td {
            color: #5c6f88;
            border-color: #e7edf5;
            vertical-align: middle;
        }

        .workload-table tfoot th,
        .workload-table tfoot td {
            color: #5f728b;
            border-top: 2px solid #d7e1ec;
            background: #f9fbfd;
            vertical-align: middle;
        }

        .workload-code {
            font-weight: 700;
            color: #5b6f86;
            white-space: nowrap;
        }

        .workload-desc {
            color: #5f728b;
        }

        .workload-days,
        .workload-room {
            white-space: nowrap;
        }

        .workload-time {
            white-space: normal;
            line-height: 1.08;
            min-width: 88px;
        }

        .time-line {
            display: block;
            white-space: nowrap;
        }

        .merged-metric {
            vertical-align: middle !important;
            background: #fbfcfe;
            font-weight: 600;
        }

        .workload-summary-row th,
        .workload-summary-row td {
            background: #f9fbfd;
            font-size: inherit;
            border-top: 1px solid #d7e1ec;
            border-bottom: 1px solid #d7e1ec;
        }

        .workload-summary-label {
            color: #52657d;
            font-weight: 700;
            white-space: nowrap;
        }

        .workload-summary-value {
            color: #4f6279;
            font-weight: 600;
        }

        .screen-only {
            white-space: nowrap;
        }

        .summary-separator th,
        .summary-separator td {
            border-top: 2px solid #b9c8d9 !important;
        }

        .workload-total-row th,
        .workload-total-row td {
            border-top: 2px solid #b7c6d8 !important;
            font-size: inherit;
            background: #f6f9fc;
        }

        .load-status-inline {
            display: inline-block;
            margin-left: 0.45rem;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .total-load-value {
            font-size: inherit;
            font-weight: 700;
        }

        .load-status-inline.underload {
            background: #fff3cd;
            color: #7a5a00;
        }

        .load-status-inline.overload {
            background: #fde8ea;
            color: #a61c2d;
        }

        .print-sheet-header {
            display: none;
            padding-top: 0;
            padding-bottom: 0.12rem;
            margin-bottom: 0.4rem;
        }

        .print-sheet-title {
            font-family: Arial, sans-serif;
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            text-align: center;
            color: #000;
        }

        .print-sheet-subtitle {
            font-family: Arial, sans-serif;
            font-size: 0.78rem;
            text-align: center;
            color: #000;
        }

        .print-sheet-line {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 0.18rem;
            font-family: Arial, sans-serif;
            font-size: 0.76rem;
            color: #000;
        }

        .print-line-value {
            display: inline-block;
            min-width: 180px;
            border-bottom: 1px solid #bcc7d3;
            padding: 0 0.18rem 0.08rem;
            line-height: 1.2;
        }

        .print-line-value.is-filled {
            border-bottom-color: transparent;
        }

        .print-line-value.is-blank {
            border-bottom-color: #bcc7d3;
        }

        .print-sheet-line.single {
            grid-template-columns: 1fr;
        }

        .print-only-block {
            display: none;
        }

        .print-workload-footer {
            margin-top: 0.75rem;
            font-family: Arial, sans-serif;
            color: #000;
        }

        .print-signatory-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem 1.25rem;
            align-items: start;
        }

        .print-signatory {
            font-size: 0.8rem;
        }

        .print-signatory.full-width {
            grid-column: 1 / -1;
            width: 52%;
            margin: 0 auto;
        }

        .print-sign-label {
            margin-bottom: 0.18rem;
            font-weight: 400;
        }

        .print-sign-line {
            min-height: 1.1rem;
            border-bottom: 1px solid #bcc7d3;
            padding: 0 0.18rem 0.06rem;
            font-weight: 800;
            line-height: 1.15;
        }

        #printConforme {
            text-align: center;
        }

        .print-sign-line.is-filled {
            border-bottom-color: #bcc7d3;
        }

        .print-sign-line.short {
            width: 70%;
            margin: 0.4rem auto 0;
        }

        .print-sign-caption {
            margin-top: 0.08rem;
            text-align: center;
            font-size: 0.78rem;
            min-height: 0.9rem;
        }

        .print-sign-uppercase {
            text-transform: uppercase;
        }

        .print-meta-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 0.9rem 1rem;
            margin-top: 0.7rem;
            align-items: start;
        }

        .print-note-block,
        .print-cc-block,
        .print-ack-box {
            font-size: 0.67rem;
            line-height: 1.2;
        }

        .print-note-title,
        .print-cc-title,
        .print-ack-title {
            font-weight: 700;
            margin-bottom: 0.12rem;
        }

        .print-note-line,
        .print-cc-line,
        .print-ack-line {
            margin: 0.06rem 0;
        }

        .print-note-line.indent {
            padding-left: 1.5rem;
        }

        .print-ack-box {
            border: 1px dashed #8b97a6;
            padding: 0.35rem 0.45rem;
            margin-top: 0.45rem;
        }

        .pair-note {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.7rem;
            color: #7a8aa0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .paired-anchor {
            background: #fbfcfe;
        }

        .paired-row td {
            background-image: linear-gradient(to right, rgba(88, 116, 255, 0.04), rgba(88, 116, 255, 0));
        }

        .schedule-partner-note {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.72rem;
            color: #6f7f96;
        }

        .type-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .type-pill.lec {
            background: #e8e9ff;
            color: #5d68f4;
        }

        .type-pill.lab {
            background: #fff0cf;
            color: #c98900;
        }

        .btn-delete-workload {
            border-color: #ff5f4d;
            color: #ff5f4d;
            line-height: 1;
        }

        .btn-delete-workload:hover {
            background: #ff5f4d;
            color: #fff;
        }

        .table-loader-row td {
            padding-top: 1.1rem !important;
            padding-bottom: 1.1rem !important;
        }

        .table-loader {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            color: #6f7f95;
            font-weight: 600;
        }

        .table-loader .spinner-border {
            width: 1rem;
            height: 1rem;
        }

        .total-label {
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .load-pill {
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 7px;
            white-space: nowrap;
            display: inline-block;
        }

        .load-normal {
            background-color: #e7f5ef;
            color: #0f5132;
        }

        .load-high {
            background-color: #fff3cd;
            color: #664d03;
        }

        .load-over {
            background-color: #f8d7da;
            color: #842029;
        }

        /* Increase Select2 height to match normal inputs */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            padding: 6px 12px !important;
            display: flex;
            align-items: center;
            border: 1px solid #d9dee3;
            border-radius: 6px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
            right: 10px !important;
        }

        .select2-selection__rendered {
            line-height: 42px !important;
        }

        /* PRINT */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            html,
            body {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }

            body * {
                visibility: hidden;
            }

            #printHeader {
                display: block !important;
            }

            #workloadCard,
            #workloadCard * {
                visibility: visible;
            }

            #workloadCard {
                position: absolute;
                left: 0;
                top: 0;
                width: 210mm;
                min-height: 297mm;
                max-width: 210mm;
                padding: 16mm 6mm 7mm;
                box-sizing: border-box;
                box-shadow: none;
                border: none;
                margin: 0;
                page-break-after: avoid !important;
                break-after: avoid-page !important;
            }

            #workloadCard > .card-header {
                display: none !important;
            }

            #workloadCard .table-responsive {
                overflow: visible !important;
            }

            .print-sheet-header {
                display: block !important;
                margin-bottom: 0.45rem;
            }

            .workload-table,
            .workload-table th,
            .workload-table td {
                border: 1px solid #000 !important;
                color: #000 !important;
                background: #fff !important;
                font-family: Arial, sans-serif;
                font-size: 7.6pt !important;
                table-layout: fixed;
            }

            .workload-table th,
            .workload-table td {
                padding: 3px 4px !important;
                line-height: 1.08;
                word-break: break-word;
            }

            .workload-table thead th {
                font-size: 6.15pt !important;
                line-height: 1.02 !important;
                letter-spacing: 0.01em !important;
                white-space: normal !important;
                word-break: normal !important;
                padding: 2px 3px !important;
            }

            .workload-table thead th.course-head { width: 7.5% !important; }
            .workload-table thead th.day-head { width: 5.5% !important; }
            .workload-table thead th.time-head { width: 7.5% !important; }
            .workload-table thead th.room-head { width: 6.5% !important; }
            .workload-table thead th.unit-head { width: 4.5% !important; }
            .workload-table thead th.hours-group-head { width: 9% !important; }
            .workload-table thead th.load-head { width: 6% !important; }
            .workload-table thead th.students-head { width: 7.5% !important; }
            .workload-table thead th.hours-subhead {
                width: 4.5% !important;
                white-space: nowrap !important;
                word-break: keep-all !important;
                overflow-wrap: normal !important;
            }

            .workload-table th:nth-child(1),
            .workload-table td:nth-child(1) { width: 10%; }
            .workload-table th:nth-child(2),
            .workload-table td:nth-child(2) { width: 19%; }
            .workload-table th:nth-child(3),
            .workload-table td:nth-child(3) { width: 8%; }
            .workload-table th:nth-child(4),
            .workload-table td:nth-child(4) { width: 6%; }
            .workload-table th:nth-child(5),
            .workload-table td:nth-child(5) { width: 8%; }
            .workload-table th:nth-child(6),
            .workload-table td:nth-child(6) { width: 7%; }
            .workload-table th:nth-child(7),
            .workload-table td:nth-child(7) { width: 4.5%; }
            .workload-table th:nth-child(8),
            .workload-table td:nth-child(8) { width: 4.5%; }
            .workload-table th:nth-child(9),
            .workload-table td:nth-child(9) { width: 4.5%; }
            .workload-table th:nth-child(10),
            .workload-table td:nth-child(10) { width: 5.5%; }
            .workload-table th:nth-child(11),
            .workload-table td:nth-child(11) { width: 7%; }

            .workload-time {
                min-width: 0;
            }

            .workload-table {
                width: 100% !important;
                page-break-inside: avoid !important;
                break-inside: avoid-page !important;
            }

            .btn,
            .badge,
            .select2-container,
            .screen-only {
                display: none !important;
            }

            .load-status-inline {
                display: block;
                margin-left: 0;
                margin-top: 2px;
                padding: 0;
                border: 0;
                background: transparent !important;
                color: #000 !important;
                font-size: 6.8pt !important;
                letter-spacing: 0.02em;
            }

            .print-sheet-line,
            .print-sheet-subtitle {
                font-size: 7.2pt !important;
            }

            .print-only-block {
                display: block !important;
            }

            .print-workload-footer {
                page-break-inside: avoid;
                break-inside: avoid-page;
            }

            .print-signatory,
            .print-sign-caption {
                font-size: 7.8pt !important;
            }

            .print-note-block,
            .print-cc-block,
            .print-ack-box {
                font-size: 6.7pt !important;
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
        <i class="bx bx-task me-2"></i>
        Faculty Workload
        <small class="text-muted">(<?= htmlspecialchars($college_name) ?>)</small>
    </h4>

    <!-- SELECT FACULTY -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="m-0">Assign Workload</h5>
            <small class="text-muted">Assign scheduled lecture and laboratory entries to faculty.</small>
        </div>
        <div class="card-body">

            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Select Faculty</label>
                    <select id="faculty_id" class="form-select select2-single">
                        <option value="">Select Faculty</option>
                        <?= $faculty_options ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Semester</label>
                    <select id="fw_semester" class="form-select">
                        <option value="1st"<?= $defaultSemesterUi === '1st' ? ' selected' : '' ?>>1st Semester</option>
                        <option value="2nd"<?= $defaultSemesterUi === '2nd' ? ' selected' : '' ?>>2nd Semester</option>
                        <option value="Midyear"<?= $defaultSemesterUi === 'Midyear' ? ' selected' : '' ?>>Midyear</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">A.Y.</label>
                    <select id="fw_ay" class="form-select select2-single">
                        <option value="">Select A.Y.</option>
                        <?php
                            $ay = mysqli_query($conn, "SELECT ay_id, ay FROM tbl_academic_years ORDER BY ay ASC");
                            while ($r = mysqli_fetch_assoc($ay)) {
                                $ayId  = (int)$r['ay_id'];
                                $ayval = htmlspecialchars($r['ay']);
                                $selected = ($ayId === $defaultAyId || $r['ay'] === $defaultAyLabel) ? " selected" : "";
                                echo "<option value='{$ayval}' data-ay-id='{$ayId}'{$selected}>{$ayval}</option>";
                            }
                        ?>
                    </select>
                </div>
            </div>

            <div class="mt-3" id="facultyAlert" style="display:none;">
                <div class="alert alert-info mb-0">
                    <strong>Faculty Selected:</strong> <span id="facultyNameText"></span>
                    &nbsp;|&nbsp; <span class="fw-summary-label">Term:</span>
                    <span id="termSummary"></span>
                </div>
            </div>

        </div>
    </div>
<div class="card mt-4 workload-card" id="workloadCard" style="display:none;">
<div class="card-header d-flex justify-content-between align-items-center">
    <div>
        <h5 class="m-0">Current Faculty Workload</h5>
        <small class="text-muted">
            Classes already assigned for this term
        </small>
    </div>

    <!-- ✅ PRINT BUTTON (VISIBLE NOW) -->
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bx bx-printer me-1"></i> Print Workload
    </button>
</div>

<!-- PRINT HEADER (VISIBLE ONLY ON PRINT) -->
<div id="printHeader" class="print-sheet-header">
    <div class="print-sheet-title">INDIVIDUAL FACULTY WORKLOAD</div>
    <div class="print-sheet-subtitle"><span id="printCampus" class="print-line-value <?= trim($campus_name) !== '' ? 'is-filled' : 'is-blank' ?>" style="min-width:220px;"><?= htmlspecialchars($campus_name) ?></span></div>
    <div class="print-sheet-subtitle"><span id="printSemesterAy" class="print-line-value is-blank" style="min-width:240px;"></span></div>
    <div class="print-sheet-line">
        <div>Name: <span id="printFacultyName" class="print-line-value is-blank"></span></div>
        <div>College: <span id="printCollege" class="print-line-value <?= trim($college_name) !== '' ? 'is-filled' : 'is-blank' ?>"><?= htmlspecialchars($college_name) ?></span></div>
    </div>
    <div class="print-sheet-line">
        <div>Degree: <span class="print-line-value is-blank"></span></div>
        <div>Major: <span class="print-line-value is-blank"></span></div>
    </div>
    <div class="print-sheet-line single">
        <div>Designation: <span id="printDesignation" class="print-line-value is-blank" style="min-width:300px;"></span></div>
    </div>
</div>

    
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 workload-table">
            <thead class="table-light">
                <tr>
                    <th rowspan="2">Course No.</th>
                    <th rowspan="2">Course Description</th>
                    <th rowspan="2" class="course-head">Course</th>
                    <th rowspan="2" class="day-head">Day</th>
                    <th rowspan="2" class="time-head">Time</th>
                    <th rowspan="2" class="room-head">Room</th>
                    <th rowspan="2" class="text-center unit-head">Unit</th>
                    <th colspan="2" class="text-center hours-group-head">Unit Breakdown</th>
                    <th rowspan="2" class="text-center load-head">Load</th>
                    <th rowspan="2" class="text-center students-head"># of<br>Students</th>
                    <th rowspan="2" class="text-end screen-only">Action</th>
                </tr>
                <tr>
                    <th class="text-center hours-subhead">Lab</th>
                    <th class="text-center hours-subhead">Lec</th>
                </tr>
            </thead>

            <tbody id="workloadTbody"></tbody>
    <tfoot class="table-light">
        <tr class="workload-summary-row">
            <th colspan="2" class="text-start workload-summary-label">Designation:</th>
            <td colspan="4" class="workload-summary-value" id="designationText"></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-center fw-semibold" id="designationLOAD"></td>
            <td></td>
            <td class="screen-only"></td>
        </tr>
        <tr class="workload-summary-row summary-separator">
            <th colspan="2" class="text-start workload-summary-label">No. of Prep:</th>
            <td colspan="4" class="workload-summary-value" id="totalPreparations">0</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="screen-only"></td>
        </tr>
        <tr class="workload-summary-row workload-total-row">
            <th colspan="6" class="text-end fw-semibold total-label">Total Load</th>
            <th class="text-center" id="totalUNIT">0</th>
            <th class="text-center" id="totalLAB">0</th>
            <th class="text-center" id="totalLEC">0</th>
            <th class="text-center fw-semibold" id="totalLOADCell">0</th>
            <th class="text-center" id="totalStudents"></th>
            <th class="screen-only"></th>
        </tr>
    </tfoot>
        </table>
    </div>
    
    <div id="printFooter" class="print-only-block print-workload-footer">
    <div class="print-signatory-grid">
        <div class="print-signatory">
            <div class="print-sign-label">Conforme:</div>
            <div id="printConforme" class="print-sign-line print-sign-uppercase"></div>
            <div class="print-sign-caption">Name/Signature over Printed Name</div>
            <div class="print-sign-line short"></div>
            <div class="print-sign-caption">Date</div>
        </div>
        <div class="print-signatory">
            <div class="print-sign-label">Prepared by:</div>
            <div class="print-sign-line"></div>
            <div class="print-sign-caption">&nbsp;</div>
            <div class="print-sign-line short"></div>
            <div class="print-sign-caption">Date</div>
        </div>
        <div class="print-signatory full-width">
            <div class="print-sign-label">Approved:</div>
            <div class="print-sign-line"></div>
            <div class="print-sign-caption">&nbsp;</div>
        </div>
    </div>

    <div class="print-meta-grid">
        <div class="print-note-block">
            <div class="print-note-title">Note:</div>
            <div class="print-note-line">Regular number of students in the class:</div>
            <div class="print-note-line indent">Lecture: 40, Lab: 25</div>
            <div class="print-note-line">Load of teaching lab only 0.8 per contact hour/week/semester. Load of teaching lecture only 1.0 per contact hour/week/semester.</div>
            <div class="print-note-line">Thesis in final adviser:</div>
            <div class="print-note-line indent">0-15 students, 1 unit</div>
            <div class="print-note-line indent">16-30 students, 2 units</div>
            <div class="print-note-line indent">31-45 students, 3 units</div>
            <div class="print-note-line">Compute additional unit as:</div>
            <div class="print-note-line indent">0.33 unit/subject after the 2nd class preparation</div>
            <div class="print-note-line indent">0.02 unit/subject in excess of the set regular number</div>
        </div>

        <div>
            <div class="print-cc-block">
                <div class="print-cc-title">cc</div>
                <div class="print-cc-line">1 - VP ACA</div>
                <div class="print-cc-line">1 - Registrar</div>
                <div class="print-cc-line">1 - College Dean</div>
                <div class="print-cc-line">1 - Program Chairperson</div>
                <div class="print-cc-line">1 - Faculty Concerned</div>
            </div>

            <div class="print-ack-box">
                <div class="print-ack-title">ACKNOWLEDGEMENT RECEIPT:</div>
                <div class="print-ack-line">Date: ____________________</div>
                <div class="print-ack-line">Time: ____________________</div>
                <div class="print-ack-line">By:</div>
                <div class="print-sign-line" style="margin-top:0.16rem;"></div>
                <div class="print-sign-caption">Name &amp; Signature of Authorized Representative</div>
            </div>
        </div>
    </div>
</div>
</div>
<!-- SCHEDULED CLASSES CARD -->
<div class="card mb-4 mt-4" id="scheduledClassCard" style="display:none;">
    <div class="card-header">
        <h5 class="m-0">Scheduled Classes</h5>
        <small class="text-muted">
            Select scheduled lecture/laboratory entries for the chosen faculty, A.Y., and semester.
        </small>
    </div>

<!-- SEARCH & FILTER BAR -->
<div class="card-body py-2 border-bottom">
    <div class="row g-2 align-items-center">
        <div class="col-md-8">
            <input type="text"
                   id="scheduleSearch"
                   class="form-control"
                   placeholder="Search by course no, description, section, type, room, or days...">
        </div>

        <div class="col-md-4">
            <select id="scheduleFilter" class="form-select">
                <option value="all">Filter by: All</option>
                <option value="course">Course No.</option>
                <option value="desc">Description</option>
                <option value="section">Section</option>
                <option value="type">Type</option>
                <option value="room">Room</option>
                <option value="days">Days</option>
            </select>
        </div>
    </div>
</div>

    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:40px;">
                        <input type="checkbox" id="checkAllSchedules">
                    </th>
                    <th>Course No.</th>
                    <th>Course Description</th>
                    <th>Section</th>
                    <th>Type</th>
                    <th>Days</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th class="text-center">Units</th>
                    <th class="text-center">Lab</th>
                    <th class="text-center">Lec</th>
                </tr>
            </thead>
            <tbody id="scheduledClassTbody">
                <!-- populated via AJAX -->
            </tbody>
        </table>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Lecture and laboratory entries pulled from <strong>Class Scheduling</strong>
        </small>

        <button class="btn btn-primary" id="btnApplyToWorkload">
            <i class="bx bx-check"></i> Apply to Workload
        </button>
    </div>
</div>
</div>

<?php include '../footer.php'; ?>

</div>
</div>
</div>

<!-- JS -->
    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
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
/* =========================================================
   GLOBAL STATE
========================================================= */
let currentAyId = null;
let currentSemesterNum = null;
let scheduledClassesRequest = null;
let workloadListRequest = null;
let selectionRequestToken = 0;

$(document).ready(function () {

    /* =========================================================
       INIT SELECT2 (ONCE ONLY)
    ========================================================= */
    $('.select2-single').select2({
        width: '100%',
        placeholder: "Select...",
        allowClear: true
    });

    /* =========================================================
       SEMESTER MAP
    ========================================================= */
    const SEMESTER_MAP = {
        "1st": 1,
        "2nd": 2,
        "Midyear": 3
    };
    const WORKLOAD_COLLEGE_NAME = <?= json_encode($college_name) ?>;

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function toNumber(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

    function formatNumber(value) {
        const n = toNumber(value);
        return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(2)));
    }

    function formatStudentCount(value) {
        const n = Math.round(toNumber(value));
        return n > 0 ? String(n) : "";
    }

    function getLoadStatus(loadValue) {
        const numericLoad = toNumber(loadValue);

        if (numericLoad > 21) {
            return { label: "Overload", className: "overload" };
        }

        if (numericLoad >= 18) {
            return { label: "", className: "normal" };
        }

        return { label: "Underload", className: "underload" };
    }

    function formatCompactTime(value) {
        const raw = String(value ?? "").trim();
        if (raw === "") {
            return "";
        }

        const parts = raw.split("-");
        if (parts.length !== 2) {
            return escapeHtml(raw);
        }

        return `
            <span class="time-line">${escapeHtml(parts[0].trim())}</span>
            <span class="time-line">${escapeHtml(parts[1].trim())}</span>
        `;
    }

    function formatDesignationDisplay(meta) {
        const name = String(meta?.designation_name || meta?.designation_label || "").trim();
        const label = String(meta?.designation_label || name).trim();

        if (!label) {
            return "";
        }

        if (name.toUpperCase() === "DEAN" && String(WORKLOAD_COLLEGE_NAME || "").trim() !== "") {
            return `${label}, ${WORKLOAD_COLLEGE_NAME}`;
        }

        return label;
    }

    function setPrintField(selector, value, options = {}) {
        const $field = $(selector);
        const normalized = String(value ?? "").trim();
        const rendered = normalized !== ""
            ? (options.uppercase ? normalized.toUpperCase() : normalized)
            : "";

        $field.text(rendered);
        $field.toggleClass("is-filled", normalized !== "");
        $field.toggleClass("is-blank", normalized === "");
    }

    function normalizeWorkloadResponse(payload) {
        if (Array.isArray(payload)) {
            return {
                rows: payload,
                meta: {
                    designation_name: "",
                    designation_label: "",
                    designation_units: 0,
                    total_preparations: 0
                }
            };
        }

        if (!payload || !Array.isArray(payload.rows)) {
            return null;
        }

        return {
            rows: payload.rows,
            meta: payload.meta || {}
        };
    }

    function buildLoadingRow(colspan, message) {
        return `
            <tr class="table-loader-row">
                <td colspan="${colspan}" class="text-center text-muted">
                    <div class="table-loader">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span>${escapeHtml(message)}</span>
                    </div>
                </td>
            </tr>
        `;
    }

    function setScheduledClassesLoadingState(message = "Loading scheduled classes...") {
        $("#scheduledClassCard").show();
        $("#checkAllSchedules").prop("checked", false);
        $("#btnApplyToWorkload").prop("disabled", true);
        $("#scheduledClassTbody").html(buildLoadingRow(11, message));
    }

    function setWorkloadLoadingState(message = "Loading faculty workload...") {
        $("#workloadCard").show();
        $("#workloadTbody").html(buildLoadingRow(12, message));
        $("#designationText").text("");
        $("#designationLOAD").text("");
        $("#totalPreparations").text("0");
        $("#totalUNIT").text("0");
        $("#totalLEC").text("0");
        $("#totalLAB").text("0");
        $("#totalLOADCell").text("0");
        $("#totalStudents").text("");
    }

    function buildWorkloadDescription(row) {
        return escapeHtml(row?.desc || "");
    }

    function getDisplayUnits(row) {
        if (row && Object.prototype.hasOwnProperty.call(row, "subject_units")) {
            return toNumber(row.subject_units);
        }

        return toNumber(row?.units);
    }

    function getDisplayLabUnits(row) {
        if (row && Object.prototype.hasOwnProperty.call(row, "lab_units")) {
            return toNumber(row.lab_units);
        }

        return toNumber(row?.hours_lab);
    }

    function getDisplayLecUnits(row) {
        if (row && Object.prototype.hasOwnProperty.call(row, "lec_units")) {
            return toNumber(row.lec_units);
        }

        return toNumber(row?.hours_lec);
    }

    function getUnitTotalKey(row) {
        const offeringId = Number(row?.offering_id) || 0;
        if (offeringId > 0) {
            return `offering:${offeringId}`;
        }

        const groupId = Number(row?.group_id) || 0;
        if (groupId > 0) {
            return `group:${groupId}`;
        }

        const subjectCode = String(row?.sub_code || row?.subject_code || "").trim();
        const courseLabel = String(row?.course || row?.section || row?.section_name || "").trim();
        return `${subjectCode}|${courseLabel}`;
    }

    function getSelectedContext() {
        const facultyId = $("#faculty_id").val();
        const ayText = $("#fw_ay").val();
        const semesterUi = $("#fw_semester").val();
        const ayId = Number($("#fw_ay option:selected").data("ay-id")) || 0;
        const semesterNum = SEMESTER_MAP[semesterUi] || 0;

        if (!facultyId || !ayText || !semesterUi || !ayId || !semesterNum) {
            return null;
        }

        return {
            facultyId,
            facultyName: $("#faculty_id option:selected").text(),
            ayId,
            ayText,
            semesterUi,
            semesterNum
        };
    }

    function abortPendingRequest(request) {
        if (request && request.readyState !== 4) {
            request.abort();
        }
    }

    function hideSelectionPanels() {
        abortPendingRequest(scheduledClassesRequest);
        abortPendingRequest(workloadListRequest);
        currentAyId = null;
        currentSemesterNum = null;
        $("#facultyAlert").hide();
        $("#scheduledClassCard").hide();
        $("#workloadCard").hide();
        $("#checkAllSchedules").prop("checked", false);
        $("#btnApplyToWorkload").prop("disabled", true);
        $("#workloadTbody").html("");
        $("#designationText").text("");
        $("#designationLOAD").text("");
        $("#totalPreparations").text("0");
        $("#totalUNIT").text("0");
        $("#totalLEC").text("0");
        $("#totalLAB").text("0");
        $("#totalLOADCell").text("0");
        $("#totalStudents").text("");
        setPrintField("#printFacultyName", "");
        setPrintField("#printSemesterAy", "");
        setPrintField("#printDesignation", "");
        setPrintField("#printConforme", "");
    }

    function refreshWorkloadPanels() {
        const context = getSelectedContext();

        if (!context) {
            hideSelectionPanels();
            return null;
        }

        currentAyId = context.ayId;
        currentSemesterNum = context.semesterNum;

        const requestToken = ++selectionRequestToken;
        loadScheduledClasses(context, requestToken);
        loadWorkloadList(context, requestToken);

        return context;
    }

    /* =========================================================
       LOAD SCHEDULED CLASSES
    ========================================================= */
    function loadScheduledClasses(context = getSelectedContext(), requestToken = selectionRequestToken) {
        if (!context) return;

        abortPendingRequest(scheduledClassesRequest);
        setScheduledClassesLoadingState();

        scheduledClassesRequest = $.ajax({
            url: "../backend/query_class_schedule_loader.php",
            type: "POST",
            dataType: "json",
            data: {
                faculty_id: context.facultyId,
                ay_id: context.ayId,
                ay: context.ayText,
                semester_num: context.semesterNum,
                semester: context.semesterUi
            }
        }).done(function (data) {
            if (requestToken !== selectionRequestToken) {
                return;
            }

            if (!Array.isArray(data)) {
                showInvalid();
                return;
            }

            if (data.length === 0) {
                $("#checkAllSchedules").prop("checked", false);
                $("#scheduledClassTbody").html(`
                    <tr>
                        <td colspan="11" class="text-center text-muted">
                            No scheduled classes found.
                        </td>
                    </tr>
                `);
                $("#btnApplyToWorkload").prop("disabled", true);
                return;
            }

            let rows = "";
            data.forEach(item => {
                const type = String(item.schedule_type || "").toUpperCase();
                const displayUnits = getDisplayUnits(item);
                const displayLabUnits = getDisplayLabUnits(item);
                const displayLecUnits = getDisplayLecUnits(item);
                const typeBadge = type === "LAB"
                    ? '<span class="type-pill lab">LAB</span>'
                    : '<span class="type-pill lec">LEC</span>';
                rows += `
                    <tr>
                        <td>
                            <input type="checkbox"
                                   class="chkSchedule"
                                   value="${escapeHtml(item.schedule_id)}">
                        </td>
                        <td>${escapeHtml(item.subject_code)}</td>
                        <td>${escapeHtml(item.subject_description)}</td>
                        <td>${escapeHtml(item.section_name)}</td>
                        <td class="text-center">${typeBadge}</td>
                        <td>${escapeHtml(item.days)}</td>
                        <td>${escapeHtml(item.time)}</td>
                        <td>${escapeHtml(item.room_code)}</td>
                        <td class="text-center">${formatNumber(displayUnits)}</td>
                        <td class="text-center">${formatNumber(displayLabUnits)}</td>
                        <td class="text-center">${formatNumber(displayLecUnits)}</td>
                    </tr>
                `;
            });
            $("#scheduledClassTbody").html(rows);
            $("#btnApplyToWorkload").prop("disabled", false);
        }).fail(function (xhr, status) {
            if (status === "abort" || requestToken !== selectionRequestToken) {
                return;
            }

            showInvalid();
            $("#btnApplyToWorkload").prop("disabled", true);
        });
    }

    function showInvalid() {
        $("#checkAllSchedules").prop("checked", false);
        $("#scheduledClassTbody").html(`
            <tr>
                <td colspan="11" class="text-danger text-center">
                    Invalid response from server
                </td>
            </tr>
        `);
    }

    $(document).on("change", "#checkAllSchedules", function () {
        $(".chkSchedule").prop("checked", $(this).is(":checked"));
    });

    $(document).on("change", ".chkSchedule", function () {
        const total = $(".chkSchedule").length;
        const checked = $(".chkSchedule:checked").length;
        $("#checkAllSchedules").prop("checked", total > 0 && total === checked);
    });

    /* =========================================================
       LOAD FACULTY WORKLOAD LIST
    ========================================================= */
    function loadWorkloadList(context = getSelectedContext(), requestToken = selectionRequestToken) {
        if (!context) return;

        abortPendingRequest(workloadListRequest);
        setWorkloadLoadingState();

        workloadListRequest = $.ajax({
            url: "../backend/query_load_faculty_workload.php",
            type: "POST",
            dataType: "json",
            data: {
                faculty_id: context.facultyId,
                ay_id: context.ayId,
                semester: context.semesterNum
            }
        }).done(function (data) {
            if (requestToken !== selectionRequestToken) {
                return;
            }

            const payload = normalizeWorkloadResponse(data);
            if (!payload) {
                $("#workloadTbody").html(`
                    <tr>
                        <td colspan="12" class="text-danger text-center">
                            Invalid response from server
                        </td>
                    </tr>
                `);
                $("#designationText").text("");
                $("#designationLOAD").text("");
                $("#totalPreparations").text("0");
                $("#totalUNIT").text("0");
                $("#totalLEC").text("0");
                $("#totalLAB").text("0");
                $("#totalLOADCell").text("0");
                $("#totalStudents").text("");
                setPrintField("#printFacultyName", "");
                setPrintField("#printSemesterAy", "");
                setPrintField("#printDesignation", "");
                setPrintField("#printConforme", "");
                $("#workloadCard").show();
                return;
            }

            const rowsData = payload.rows;
            const meta = payload.meta || {};
            const preparationSet = new Set();
            const unitTotalKeys = new Set();
            let totalLEC = 0;
            let totalLAB = 0;
            let totalUNIT = 0;
            let totalLOAD = 0;
            let rows = "";

            for (let i = 0; i < rowsData.length; i++) {
                const row = rowsData[i];
                const displayUnits = getDisplayUnits(row);
                const displayLabUnits = getDisplayLabUnits(row);
                const displayLecUnits = getDisplayLecUnits(row);
                const unitTotalKey = getUnitTotalKey(row);
                totalLOAD += toNumber(row.faculty_load);

                if (!unitTotalKeys.has(unitTotalKey)) {
                    totalUNIT += displayUnits;
                    totalLAB += displayLabUnits;
                    totalLEC += displayLecUnits;
                    unitTotalKeys.add(unitTotalKey);
                }

                const preparationKey = String(row.sub_code || "").trim();
                if (preparationKey !== "") {
                    preparationSet.add(preparationKey);
                }

                rows += `
                    <tr>
                        <td class="workload-code">${escapeHtml(row.sub_code)}</td>
                        <td class="workload-desc">${buildWorkloadDescription(row)}</td>
                        <td>${escapeHtml(row.course || row.section)}</td>
                        <td class="workload-days">${escapeHtml(row.days)}</td>
                        <td class="workload-time">${formatCompactTime(row.time)}</td>
                        <td class="workload-room">${escapeHtml(row.room)}</td>
                        <td class="text-center">${formatNumber(displayUnits)}</td>
                        <td class="text-center">${formatNumber(displayLabUnits)}</td>
                        <td class="text-center">${formatNumber(displayLecUnits)}</td>
                        <td class="text-center fw-semibold">${formatNumber(row.faculty_load)}</td>
                        <td class="text-center">${formatStudentCount(row.student_count)}</td>
                        <td class="text-end screen-only">
                            <button class="btn btn-sm btn-delete-workload btnRemoveWL"
                                    data-id="${row.workload_id}">
                                <i class="bx bx-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }

            if (!rows) {
                rows = `
                    <tr>
                        <td colspan="12" class="text-center text-muted">
                            No workload assigned yet.
                        </td>
                    </tr>
                `;
            }

            const designationUnits = toNumber(meta.designation_units);
            const totalPreparations = Math.max(
                Number(meta.total_preparations) || 0,
                preparationSet.size
            );
            const grandTotalLoad = totalLOAD + designationUnits;
            const loadStatus = getLoadStatus(grandTotalLoad);

            $("#workloadTbody").html(rows);
            $("#designationText").text(formatDesignationDisplay(meta));
            $("#designationLOAD").text(designationUnits > 0 ? formatNumber(designationUnits) : "");
            $("#totalPreparations").text(formatNumber(totalPreparations));
            $("#totalLAB").text(formatNumber(totalLAB));
            $("#totalLEC").text(formatNumber(totalLEC));
            $("#totalUNIT").text(formatNumber(totalUNIT));
            $("#totalLOADCell").html(`
                <span class="total-load-value">${escapeHtml(formatNumber(grandTotalLoad))}</span>
                ${loadStatus.label ? `<span class="load-status-inline ${escapeHtml(loadStatus.className)}">${escapeHtml(loadStatus.label)}</span>` : ""}
            `);
            $("#totalStudents").text("");

            setPrintField("#printFacultyName", context.facultyName);
            setPrintField("#printSemesterAy", context.semesterUi + " Semester | AY " + context.ayText);
            setPrintField("#printDesignation", formatDesignationDisplay(meta));
            setPrintField("#printConforme", context.facultyName, { uppercase: true });

            $("#workloadCard").show();
        }).fail(function (xhr, status) {
            if (status === "abort" || requestToken !== selectionRequestToken) {
                return;
            }

            $("#workloadTbody").html(`
                <tr>
                    <td colspan="12" class="text-danger text-center">
                        Failed to load faculty workload.
                    </td>
                </tr>
            `);
            setPrintField("#printFacultyName", "");
            setPrintField("#printSemesterAy", "");
            setPrintField("#printDesignation", "");
            setPrintField("#printConforme", "");
            $("#workloadCard").show();
        });
    }

    /* =========================================================
       REMOVE WORKLOAD
    ========================================================= */
    $(document).on("click", ".btnRemoveWL", function () {

        let id = $(this).data("id");

        Swal.fire({
            title: "Remove this class?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            confirmButtonText: "Remove"
        }).then(res => {

            if (!res.isConfirmed) return;

            $.post(
                "../backend/query_remove_workload.php",
                { workload_id: id },
                function () {
                    refreshWorkloadPanels();
                }
            );
        });
    });

    /* =========================================================
       AUTO LOAD ON SELECTION CHANGE
    ========================================================= */
    $("#faculty_id, #fw_ay, #fw_semester").on("change", function () {

        const context = getSelectedContext();
        if (!context) {
            hideSelectionPanels();
            return;
        }

        $("#facultyNameText").text(context.facultyName);
        $("#termSummary").text(context.semesterUi + " A.Y. " + context.ayText);

        $("#facultyAlert").stop(true, true).slideDown();
        $("#scheduledClassCard").stop(true, true).slideDown();

        refreshWorkloadPanels();
    });

    /* =========================================================
       APPLY TO WORKLOAD
    ========================================================= */
    $("#btnApplyToWorkload").on("click", function () {

        let faculty_id = $("#faculty_id").val();
        let ay_text    = $("#fw_ay").val();
        let semester_ui = $("#fw_semester").val();

        if (!faculty_id || !ay_text || !semester_ui) {
            Swal.fire("Missing Data", "Please select Faculty, A.Y., and Semester.", "warning");
            return;
        }

        let schedule_ids = [];
        $(".chkSchedule:checked").each(function () {
            schedule_ids.push($(this).val());
        });

        if (schedule_ids.length === 0) {
            Swal.fire("No Selection", "Please select at least one class.", "warning");
            return;
        }

        $.post(
            "../backend/query_apply_workload.php",
            {
                faculty_id: faculty_id,
                ay_id: currentAyId,
                semester: currentSemesterNum,
                schedule_ids: schedule_ids
            },
            function (res) {
                if (!res || typeof res !== "object") {
                    Swal.fire("Error", "Invalid response from server.", "error");
                    return;
                }

                if (res.status === "success") {
                    Swal.fire(
                        "Applied",
                        res.message || (res.inserted + " class(es) added to workload."),
                        "success"
                    );
                    refreshWorkloadPanels();
                    return;
                }

                if (res.status === "partial" || res.status === "conflict") {
                    Swal.fire({
                        icon: res.status === "partial" ? "warning" : "error",
                        title: res.status === "partial" ? "Partially Applied" : "Workload Conflict",
                        html: res.message || "Selected classes conflict with this faculty's current workload."
                    });
                    refreshWorkloadPanels();
                    return;
                }

                Swal.fire("Error", res.message || "Failed to apply workload.", "error");
            },
            "json"
        );
    });


/* =========================================================
   SCHEDULED CLASSES SEARCH & FILTER (UI ONLY)
========================================================= */
    $("#scheduleSearch, #scheduleFilter").on("keyup change", function () {

    let keyword = $("#scheduleSearch").val().toLowerCase();
    let filter  = $("#scheduleFilter").val();

    $("#scheduledClassTbody tr").each(function () {

        let row = $(this);

        let course  = row.find("td:eq(1)").text().toLowerCase();
        let desc    = row.find("td:eq(2)").text().toLowerCase();
        let section = row.find("td:eq(3)").text().toLowerCase();
        let type    = row.find("td:eq(4)").text().toLowerCase();
        let days    = row.find("td:eq(5)").text().toLowerCase();
        let room    = row.find("td:eq(7)").text().toLowerCase();

        let match = false;

        switch (filter) {
            case "course":
                match = course.includes(keyword);
                break;
            case "desc":
                match = desc.includes(keyword);
                break;
            case "section":
                match = section.includes(keyword);
                break;
            case "type":
                match = type.includes(keyword);
                break;
            case "room":
                match = room.includes(keyword);
                break;
            case "days":
                match = days.includes(keyword);
                break;
            default:
                match =
                    course.includes(keyword) ||
                    desc.includes(keyword) ||
                    section.includes(keyword) ||
                    type.includes(keyword) ||
                    days.includes(keyword) ||
                    room.includes(keyword);
        }

        row.toggle(match);
    });
});


    

});
</script>



</body>
</html>
