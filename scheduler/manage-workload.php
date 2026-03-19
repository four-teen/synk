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

        #scheduledClassTbody tr.linked-schedule-row td {
            background-image: linear-gradient(to right, rgba(93, 104, 244, 0.045), rgba(93, 104, 244, 0));
        }

        #scheduledClassTbody tr.linked-schedule-row td:first-child {
            position: relative;
            padding-left: 1.35rem;
        }

        #scheduledClassTbody tr.linked-schedule-row td:first-child::before {
            content: "";
            position: absolute;
            left: 0.45rem;
            width: 4px;
            background: var(--schedule-pair-color, #5d68f4);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.85);
        }

        #scheduledClassTbody tr.linked-schedule-start td:first-child::before {
            top: 22%;
            bottom: -1px;
            border-radius: 999px 999px 0 0;
        }

        #scheduledClassTbody tr.linked-schedule-middle td:first-child::before {
            top: -1px;
            bottom: -1px;
            border-radius: 0;
        }

        #scheduledClassTbody tr.linked-schedule-end td:first-child::before {
            top: -1px;
            bottom: 22%;
            border-radius: 0 0 999px 999px;
        }

        .scheduled-pair-note {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.35rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: #5f728b;
        }

        .scheduled-pair-note::before {
            content: "";
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            background: var(--schedule-pair-color, #5d68f4);
            box-shadow: 0 0 0 2px rgba(93, 104, 244, 0.12);
        }

        .floating-apply-bar {
            position: fixed;
            right: 1.25rem;
            bottom: 1rem;
            z-index: 1030;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            min-width: 320px;
            max-width: min(480px, calc(100vw - 2rem));
            padding: 0.85rem 1rem;
            border: 1px solid #d9e4f2;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 18px 40px rgba(18, 38, 63, 0.16);
            backdrop-filter: blur(14px);
            opacity: 0;
            transform: translateY(18px);
            pointer-events: none;
            transition: opacity 0.22s ease, transform 0.22s ease;
        }

        .floating-apply-bar.is-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .faculty-browser-launcher {
            position: fixed;
            right: 0.9rem;
            top: 52%;
            transform: translateY(-50%);
            z-index: 1031;
            border: 0;
            border-radius: 22px;
            background: linear-gradient(180deg, #eef3ff 0%, #dfe8ff 100%);
            color: #4154d8;
            box-shadow: 0 20px 42px rgba(65, 84, 216, 0.2);
            padding: 0.95rem 0.65rem;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 0.55rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, right 0.2s ease;
        }

        .faculty-browser-launcher:hover,
        .faculty-browser-launcher:focus-visible {
            right: 1.1rem;
            transform: translateY(-50%) scale(1.01);
            box-shadow: 0 24px 48px rgba(65, 84, 216, 0.24);
        }

        .faculty-browser-launcher:focus-visible {
            outline: 3px solid rgba(65, 84, 216, 0.2);
            outline-offset: 2px;
        }

        .faculty-browser-launcher-icon {
            width: 2.2rem;
            height: 2.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: inset 0 0 0 1px rgba(91, 108, 255, 0.12);
            font-size: 1.15rem;
        }

        .faculty-browser-launcher-label {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            font-size: 0.7rem;
            font-weight: 800;
        }

        #facultyBrowserDrawer {
            width: min(430px, calc(100vw - 1rem));
            border-left: 1px solid #dbe3f6;
            box-shadow: -24px 0 48px rgba(25, 40, 90, 0.14);
        }

        #facultyBrowserDrawer .offcanvas-header {
            padding: 1.2rem 1.2rem 1rem;
            background:
                radial-gradient(circle at top right, rgba(120, 196, 255, 0.18), transparent 44%),
                linear-gradient(180deg, #edf4ff 0%, #f8fbff 100%);
            border-bottom: 1px solid #dbe3f6;
        }

        .faculty-browser-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .faculty-browser-title-icon {
            width: 2.7rem;
            height: 2.7rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            color: #4154d8;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: inset 0 0 0 1px rgba(91, 108, 255, 0.12);
            font-size: 1.3rem;
        }

        .faculty-browser-title h5 {
            margin: 0;
            font-weight: 700;
            color: #22304c;
        }

        .faculty-browser-title p {
            margin: 0.2rem 0 0;
            color: #687796;
            font-size: 0.86rem;
        }

        #facultyBrowserDrawer .offcanvas-body {
            padding: 1.2rem;
            background:
                radial-gradient(circle at top left, rgba(227, 237, 255, 0.62), transparent 32%),
                linear-gradient(180deg, #fbfcff 0%, #f6f9ff 100%);
        }

        .faculty-browser-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .faculty-browser-metric {
            border: 1px solid #dbe4f6;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.88);
            padding: 0.85rem;
            box-shadow: 0 12px 28px rgba(29, 43, 88, 0.05);
        }

        .faculty-browser-metric-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #7887a4;
        }

        .faculty-browser-metric-value {
            display: block;
            margin-top: 0.3rem;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1;
            color: #22304c;
        }

        .faculty-browser-note {
            margin-bottom: 1rem;
            color: #6d7c98;
            font-size: 0.84rem;
        }

        .faculty-browser-section {
            border: 1px solid #dfe7f7;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 14px 30px rgba(29, 43, 88, 0.05);
            padding: 1rem;
        }

        .faculty-browser-section + .faculty-browser-section {
            margin-top: 1rem;
        }

        .faculty-browser-section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.9rem;
        }

        .faculty-browser-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.77rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .faculty-browser-status-chip.is-overload {
            background: #fde8ea;
            color: #a61c2d;
        }

        .faculty-browser-status-chip.is-normal {
            background: #e7f5ef;
            color: #0f6a42;
        }

        .faculty-browser-status-chip.is-underload {
            background: #fff3cd;
            color: #7a5a00;
        }

        .faculty-browser-section-subtitle {
            margin-top: 0.2rem;
            color: #74839e;
            font-size: 0.8rem;
        }

        .faculty-browser-section-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.28rem 0.65rem;
            background: #f1f5ff;
            color: #51617f;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .faculty-browser-list {
            display: grid;
            gap: 0.8rem;
        }

        .faculty-browser-card {
            width: 100%;
            border: 1px solid #e3eaf8;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            padding: 0.9rem 0.95rem;
            display: flex;
            justify-content: space-between;
            gap: 0.9rem;
            align-items: flex-start;
            text-align: left;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(29, 43, 88, 0.04);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .faculty-browser-card:hover,
        .faculty-browser-card:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(29, 43, 88, 0.08);
        }

        .faculty-browser-card:focus-visible {
            outline: 3px solid rgba(65, 84, 216, 0.18);
            outline-offset: 2px;
        }

        .faculty-browser-card.is-current {
            border-color: #7d8bff;
            box-shadow: 0 16px 34px rgba(67, 86, 219, 0.12);
        }

        .faculty-browser-card.is-overload {
            border-color: rgba(166, 28, 45, 0.18);
        }

        .faculty-browser-card.is-normal {
            border-color: rgba(15, 106, 66, 0.18);
        }

        .faculty-browser-card.is-underload {
            border-color: rgba(122, 90, 0, 0.18);
        }

        .faculty-browser-name {
            font-size: 0.96rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .faculty-browser-name.is-overload {
            color: #a61c2d;
        }

        .faculty-browser-name.is-normal {
            color: #0f6a42;
        }

        .faculty-browser-name.is-underload {
            color: #7a5a00;
        }

        .faculty-browser-subtext {
            margin-top: 0.28rem;
            color: #73829d;
            font-size: 0.81rem;
        }

        .faculty-browser-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.42rem;
            margin-top: 0.65rem;
        }

        .faculty-browser-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.24rem 0.55rem;
            font-size: 0.72rem;
            font-weight: 700;
            background: #eef3ff;
            color: #50617e;
        }

        .faculty-browser-tag.is-overload {
            background: #fde8ea;
            color: #a61c2d;
        }

        .faculty-browser-tag.is-normal {
            background: #e7f5ef;
            color: #0f6a42;
        }

        .faculty-browser-tag.is-underload {
            background: #fff3cd;
            color: #7a5a00;
        }

        .faculty-browser-load {
            min-width: 88px;
            text-align: right;
        }

        .faculty-browser-load-number {
            display: block;
            font-size: 1.35rem;
            line-height: 1;
            font-weight: 800;
            color: #22304c;
        }

        .faculty-browser-load-label {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #7b89a4;
        }

        .faculty-browser-empty {
            border: 1px dashed #cedaf4;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            color: #60708f;
            text-align: center;
            padding: 1.15rem;
            font-size: 0.9rem;
        }

        .faculty-browser-empty .spinner-border {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
            vertical-align: text-bottom;
        }

        .floating-apply-meta {
            min-width: 0;
        }

        .floating-apply-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: #4f6279;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .floating-apply-count {
            display: block;
            margin-top: 0.12rem;
            font-size: 0.82rem;
            color: #6b7e96;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .floating-apply-bar {
                left: 1rem;
                right: 1rem;
                bottom: 0.85rem;
                min-width: 0;
                flex-wrap: wrap;
                gap: 0.75rem;
                padding: 0.8rem 0.9rem;
            }

            .floating-apply-bar .btn {
                width: 100%;
            }

            .faculty-browser-launcher {
                top: auto;
                bottom: 1rem;
                right: 1rem;
                transform: none;
                flex-direction: row;
                padding: 0.8rem 1rem;
                border-radius: 999px;
            }

            .faculty-browser-launcher:hover,
            .faculty-browser-launcher:focus-visible {
                right: 1rem;
                transform: translateY(-2px);
            }

            .faculty-browser-launcher-label {
                writing-mode: initial;
                transform: none;
                letter-spacing: 0.08em;
            }

            .faculty-browser-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            #facultyBrowserDrawer {
                width: 100vw;
            }

            .faculty-browser-summary {
                grid-template-columns: 1fr;
            }

            .faculty-browser-section-header,
            .faculty-browser-card {
                flex-direction: column;
            }

            .faculty-browser-load {
                min-width: 0;
                text-align: left;
            }
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
                    <th colspan="2" class="text-center hours-group-head">No. of Hours</th>
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
            <div class="print-note-line">Load of teaching lab only 0.75 per contact hour/week/semester. Load of teaching lecture only 1.0 per contact hour/week/semester.</div>
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
                    <th class="text-center">Lab Hrs</th>
                    <th class="text-center">Lec Hrs</th>
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

        <button class="btn btn-primary btnApplyToWorkloadTrigger" id="btnApplyToWorkload" disabled>
            <i class="bx bx-check"></i> Apply to Workload
        </button>
    </div>
</div>
</div>

<div class="floating-apply-bar screen-only" id="floatingApplyBar">
    <div class="floating-apply-meta">
        <span class="floating-apply-label">Apply To Workload</span>
        <span class="floating-apply-count" id="floatingApplyCount">Select scheduled classes to apply.</span>
    </div>
    <button class="btn btn-primary btnApplyToWorkloadTrigger" id="btnApplyToWorkloadFloating" disabled>
        <i class="bx bx-check me-1"></i> Apply to Workload
    </button>
</div>

<button
    type="button"
    class="faculty-browser-launcher screen-only"
    id="btnOpenFacultyBrowser"
    aria-controls="facultyBrowserDrawer"
    aria-label="Open faculty workload overview"
>
    <span class="faculty-browser-launcher-icon">
        <i class="bx bx-user-pin"></i>
    </span>
    <span class="faculty-browser-launcher-label">Faculty Load</span>
</button>

<?php include '../footer.php'; ?>

</div>
</div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="facultyBrowserDrawer" aria-labelledby="facultyBrowserDrawerLabel">
    <div class="offcanvas-header">
        <div class="faculty-browser-title">
            <span class="faculty-browser-title-icon">
                <i class="bx bx-group"></i>
            </span>
            <div>
                <h5 id="facultyBrowserDrawerLabel">Faculty Load Overview</h5>
                <p>Browse faculty totals for the selected term and jump straight into any workload profile.</p>
            </div>
        </div>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="faculty-browser-summary" id="facultyBrowserSummary"></div>
        <div class="faculty-browser-note">
            Uses the current A.Y. and semester on this page. Click a faculty card to load that faculty into the main workload panels.
        </div>
        <div id="facultyBrowserList">
            <div class="faculty-browser-empty">Loading faculty workload overview...</div>
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
let facultyOverviewRequest = null;
let selectionRequestToken = 0;
let facultyOverviewCacheKey = "";
let facultyOverviewCache = [];
let facultyBrowserDrawerInstance = null;
const SCHEDULE_PAIR_COLORS = ["#22a06b", "#e85d75", "#5d68f4", "#d39c0f", "#2e8de4", "#8a63f7"];

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
    const facultyBrowserElement = document.getElementById("facultyBrowserDrawer");
    if (facultyBrowserElement) {
        facultyBrowserDrawerInstance = bootstrap.Offcanvas.getOrCreateInstance(facultyBrowserElement);
    }

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
            return { label: "Overload", className: "overload", drawerLabel: "Overload" };
        }

        if (numericLoad >= 18) {
            return { label: "", className: "normal", drawerLabel: "Normal Load" };
        }

        return { label: "Underload", className: "underload", drawerLabel: "Underload" };
    }

    function getFacultyBrowserTermContext() {
        const ayText = $("#fw_ay").val();
        const ayId = Number($("#fw_ay option:selected").data("ay-id")) || 0;
        const semesterUi = $("#fw_semester").val();
        const semesterNum = SEMESTER_MAP[semesterUi] || 0;

        if (!ayText || !ayId || !semesterUi || !semesterNum) {
            return null;
        }

        return {
            ayId,
            ayText,
            semesterUi,
            semesterNum
        };
    }

    function getFacultyOptionList() {
        const items = [];
        $("#faculty_id option").each(function () {
            const option = $(this);
            const facultyId = parseInt(option.val(), 10) || 0;
            const fullName = String(option.text() || "").trim();

            if (!facultyId || !fullName) {
                return;
            }

            items.push({
                faculty_id: facultyId,
                full_name: fullName
            });
        });

        return items;
    }

    function clearFacultyOverviewCache() {
        abortPendingRequest(facultyOverviewRequest);
        facultyOverviewRequest = null;
        facultyOverviewCacheKey = "";
        facultyOverviewCache = [];
    }

    function buildFacultyBrowserMetric(label, value) {
        return `
            <div class="faculty-browser-metric">
                <span class="faculty-browser-metric-label">${escapeHtml(label)}</span>
                <span class="faculty-browser-metric-value">${escapeHtml(String(value))}</span>
            </div>
        `;
    }

    function setFacultyBrowserMessage(message, isLoading = false) {
        $("#facultyBrowserSummary").html("");
        $("#facultyBrowserList").html(`
            <div class="faculty-browser-empty">
                ${isLoading ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' : ''}
                ${escapeHtml(message)}
            </div>
        `);
    }

    function renderFacultyBrowser() {
        const summary = $("#facultyBrowserSummary");
        const list = $("#facultyBrowserList");
        if (summary.length === 0 || list.length === 0) {
            return;
        }

        const termContext = getFacultyBrowserTermContext();
        const facultyOptions = getFacultyOptionList();
        if (!termContext) {
            setFacultyBrowserMessage("Select A.Y. and semester to browse faculty loads.");
            return;
        }

        if (facultyOptions.length === 0) {
            setFacultyBrowserMessage("No faculty options are available on this page.");
            return;
        }

        if (facultyOverviewCacheKey === "" && facultyOverviewCache.length === 0) {
            if (facultyOverviewRequest && facultyOverviewRequest.readyState !== 4) {
                setFacultyBrowserMessage("Loading faculty workload overview...", true);
                return;
            }

            setFacultyBrowserMessage("Open the drawer to load faculty workload overview.");
            return;
        }

        const overviewMap = new Map(
            (Array.isArray(facultyOverviewCache) ? facultyOverviewCache : []).map(item => [Number(item.faculty_id), item])
        );
        const currentFacultyId = String($("#faculty_id").val() || "");
        const merged = facultyOptions.map(option => {
            const summaryRow = overviewMap.get(option.faculty_id) || {};
            const totalLoad = toNumber(summaryRow.total_load);
            const workloadLoad = toNumber(summaryRow.workload_load);
            const designationUnits = toNumber(summaryRow.designation_units);
            const totalPreparations = Math.max(0, Number(summaryRow.total_preparations) || 0);
            const loadStatus = getLoadStatus(totalLoad);

            return {
                faculty_id: option.faculty_id,
                full_name: option.full_name,
                total_load: totalLoad,
                workload_load: workloadLoad,
                designation_units: designationUnits,
                total_preparations: totalPreparations,
                designation_label: String(summaryRow.designation_label || "").trim(),
                load_status: loadStatus
            };
        });

        const sections = [
            {
                key: "overload",
                title: "Overload",
                subtitle: "Faculty above 21 teaching-load units for the selected term."
            },
            {
                key: "normal",
                title: "Normal Load",
                subtitle: "Faculty within the regular workload band for the selected term."
            },
            {
                key: "underload",
                title: "Underload",
                subtitle: "Faculty below the regular workload band for the selected term."
            }
        ];

        const counts = {
            overload: merged.filter(item => item.load_status.className === "overload").length,
            normal: merged.filter(item => item.load_status.className === "normal").length,
            underload: merged.filter(item => item.load_status.className === "underload").length
        };

        summary.html(
            buildFacultyBrowserMetric("Overload", counts.overload) +
            buildFacultyBrowserMetric("Normal", counts.normal) +
            buildFacultyBrowserMetric("Underload", counts.underload)
        );

        const sectionsHtml = sections.map(section => {
            const rows = merged
                .filter(item => item.load_status.className === section.key)
                .sort((left, right) => String(left.full_name).localeCompare(String(right.full_name)));

            if (rows.length === 0) {
                return "";
            }

            const cards = rows.map(item => {
                const statusClass = item.load_status.className;
                const prepLabel = `${item.total_preparations} prep${item.total_preparations === 1 ? "" : "s"}`;
                const noteParts = [];

                if (item.designation_label) {
                    noteParts.push(item.designation_label);
                }

                if (item.designation_units > 0) {
                    noteParts.push(`Designation ${formatNumber(item.designation_units)}`);
                }

                if (item.workload_load > 0) {
                    noteParts.push(`Teaching ${formatNumber(item.workload_load)}`);
                }

                if (noteParts.length === 0) {
                    noteParts.push("No assigned workload yet");
                }

                return `
                    <button
                        type="button"
                        class="faculty-browser-card ${statusClass === "normal" ? "is-normal" : statusClass === "overload" ? "is-overload" : "is-underload"} ${currentFacultyId === String(item.faculty_id) ? "is-current" : ""}"
                        data-faculty-id="${escapeHtml(item.faculty_id)}"
                    >
                        <div>
                            <div class="faculty-browser-name ${statusClass === "normal" ? "is-normal" : statusClass === "overload" ? "is-overload" : "is-underload"}">
                                ${escapeHtml(item.full_name)}
                            </div>
                            <div class="faculty-browser-subtext">${escapeHtml(noteParts.join(" | "))}</div>
                            <div class="faculty-browser-tags">
                                <span class="faculty-browser-tag ${statusClass === "normal" ? "is-normal" : statusClass === "overload" ? "is-overload" : "is-underload"}">${escapeHtml(item.load_status.drawerLabel)}</span>
                                <span class="faculty-browser-tag">${escapeHtml(prepLabel)}</span>
                                ${currentFacultyId === String(item.faculty_id) ? '<span class="faculty-browser-tag">Selected</span>' : ""}
                            </div>
                        </div>
                        <div class="faculty-browser-load">
                            <span class="faculty-browser-load-number">${escapeHtml(formatNumber(item.total_load))}</span>
                            <span class="faculty-browser-load-label">Total Load</span>
                        </div>
                    </button>
                `;
            }).join("");

            return `
                <section class="faculty-browser-section">
                    <div class="faculty-browser-section-header">
                        <div>
                            <span class="faculty-browser-status-chip is-${escapeHtml(section.key)}">
                                <i class="bx bx-user"></i>
                                ${escapeHtml(section.title)}
                            </span>
                            <div class="faculty-browser-section-subtitle">${escapeHtml(section.subtitle)}</div>
                        </div>
                        <span class="faculty-browser-section-badge">${escapeHtml(String(rows.length))}</span>
                    </div>
                    <div class="faculty-browser-list">${cards}</div>
                </section>
            `;
        }).join("");

        list.html(
            sectionsHtml !== ""
                ? sectionsHtml
                : "<div class='faculty-browser-empty'>No faculty load summary is available for the selected term.</div>"
        );
    }

    function loadFacultyOverview(forceReload = false) {
        const dfd = $.Deferred();
        const termContext = getFacultyBrowserTermContext();
        const facultyOptions = getFacultyOptionList();

        if (!termContext) {
            clearFacultyOverviewCache();
            renderFacultyBrowser();
            dfd.reject("missing_term");
            return dfd.promise();
        }

        if (facultyOptions.length === 0) {
            clearFacultyOverviewCache();
            renderFacultyBrowser();
            dfd.reject("missing_faculty");
            return dfd.promise();
        }

        const facultyIds = facultyOptions.map(item => item.faculty_id);
        const cacheKey = `${termContext.ayId}-${termContext.semesterNum}-${facultyIds.join(",")}`;

        if (!forceReload && cacheKey === facultyOverviewCacheKey && facultyOverviewCache.length > 0) {
            renderFacultyBrowser();
            dfd.resolve(facultyOverviewCache);
            return dfd.promise();
        }

        abortPendingRequest(facultyOverviewRequest);
        setFacultyBrowserMessage("Loading faculty workload overview...", true);

        facultyOverviewRequest = $.ajax({
            url: "../backend/query_workload_faculty_overview.php",
            type: "POST",
            dataType: "json",
            data: {
                ay_id: termContext.ayId,
                semester: termContext.semesterNum,
                faculty_ids: facultyIds
            }
        }).done(function (response) {
            if (!response || response.status !== "ok" || !Array.isArray(response.faculty)) {
                clearFacultyOverviewCache();
                setFacultyBrowserMessage((response && response.message) ? response.message : "Failed to load faculty workload overview.");
                dfd.reject("invalid");
                return;
            }

            facultyOverviewCacheKey = cacheKey;
            facultyOverviewCache = response.faculty;
            renderFacultyBrowser();
            dfd.resolve(facultyOverviewCache);
        }).fail(function (xhr, status) {
            if (status === "abort") {
                dfd.reject("abort");
                return;
            }

            clearFacultyOverviewCache();
            setFacultyBrowserMessage("Failed to load faculty workload overview.");
            dfd.reject(xhr);
        });

        return dfd.promise();
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

    function setApplyWorkloadButtonsDisabled(disabled) {
        $("#btnApplyToWorkload, #btnApplyToWorkloadFloating").prop("disabled", Boolean(disabled));
    }

    function updateApplyWorkloadControls() {
        const cardVisible = $("#scheduledClassCard").is(":visible");
        const total = $(".chkSchedule").length;
        const checked = $(".chkSchedule:checked").length;

        if (!cardVisible) {
            $("#floatingApplyBar").removeClass("is-visible");
            $("#floatingApplyCount").text("Select scheduled classes to apply.");
            $("#checkAllSchedules").prop("disabled", true).prop("checked", false);
            setApplyWorkloadButtonsDisabled(true);
            return;
        }

        $("#floatingApplyBar").addClass("is-visible");
        $("#checkAllSchedules").prop("disabled", total === 0);
        setApplyWorkloadButtonsDisabled(!(total > 0 && checked > 0));

        if (total === 0) {
            $("#floatingApplyCount").text("No scheduled classes available.");
            return;
        }

        $("#floatingApplyCount").text(`${checked} of ${total} class${total === 1 ? "" : "es"} selected`);
    }

    function setScheduledClassesLoadingState(message = "Loading scheduled classes...") {
        $("#scheduledClassCard").show();
        $("#checkAllSchedules").prop("checked", false);
        setApplyWorkloadButtonsDisabled(true);
        $("#scheduledClassTbody").html(buildLoadingRow(11, message));
        updateApplyWorkloadControls();
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

    function buildWorkloadDescription(row, isPaired = false) {
        const description = escapeHtml(row?.desc || "");
        if (!isPaired) {
            return description;
        }

        const type = String(row?.type || "LEC").toUpperCase() === "LAB" ? "LAB" : "LEC";
        return `
            ${description}
            <span class="schedule-partner-note">
                <span class="type-pill ${type.toLowerCase()}">${type}</span>
            </span>
        `;
    }

    function getDisplayUnits(row) {
        if (row && Object.prototype.hasOwnProperty.call(row, "units")) {
            return toNumber(row.units);
        }

        if (row && Object.prototype.hasOwnProperty.call(row, "subject_units")) {
            return toNumber(row.subject_units);
        }

        return toNumber(row?.units);
    }

    function getDisplayLabUnits(row) {
        if (row && Object.prototype.hasOwnProperty.call(row, "hours_lab")) {
            return toNumber(row.hours_lab);
        }

        if (row && Object.prototype.hasOwnProperty.call(row, "lab_units")) {
            return toNumber(row.lab_units);
        }

        return toNumber(row?.hours_lab);
    }

    function getDisplayLecUnits(row) {
        if (row && Object.prototype.hasOwnProperty.call(row, "hours_lec")) {
            return toNumber(row.hours_lec);
        }

        if (row && Object.prototype.hasOwnProperty.call(row, "lec_units")) {
            return toNumber(row.lec_units);
        }

        return toNumber(row?.hours_lec);
    }

    function getWorkloadGroupKey(row) {
        const groupId = Number(row?.group_id) || 0;
        if (groupId > 0) {
            return `group:${groupId}`;
        }

        const offeringId = Number(row?.offering_id) || 0;
        if (offeringId > 0) {
            return `offering:${offeringId}`;
        }

        return `workload:${Number(row?.workload_id) || 0}`;
    }

    function getScheduledClassGroupKey(row) {
        const groupId = Number(row?.group_id) || 0;
        if (groupId > 0) {
            return `group:${groupId}`;
        }

        const offeringId = Number(row?.offering_id) || 0;
        if (offeringId > 0) {
            return `offering:${offeringId}`;
        }

        return `schedule:${Number(row?.schedule_id) || 0}`;
    }

    function getSchedulePairColor(groupKey) {
        const text = String(groupKey || "");
        let hash = 0;

        for (let i = 0; i < text.length; i++) {
            hash = ((hash << 5) - hash) + text.charCodeAt(i);
            hash |= 0;
        }

        return SCHEDULE_PAIR_COLORS[Math.abs(hash) % SCHEDULE_PAIR_COLORS.length];
    }

    function getSchedulePairRowClass(index, total) {
        if (total <= 1) {
            return "";
        }

        if (index === 0) {
            return "linked-schedule-start";
        }

        if (index === (total - 1)) {
            return "linked-schedule-end";
        }

        return "linked-schedule-middle";
    }

    function buildRemoveWorkloadButton(row) {
        return `
            <button class="btn btn-sm btn-delete-workload btnRemoveWL"
                    data-id="${escapeHtml(row.workload_id)}">
                <i class="bx bx-trash"></i>
            </button>
        `;
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
        setApplyWorkloadButtonsDisabled(true);
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
        updateApplyWorkloadControls();
        renderFacultyBrowser();
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
                updateApplyWorkloadControls();
                return;
            }

            let rows = "";
            for (let i = 0; i < data.length; i++) {
                const item = data[i];
                const groupKey = getScheduledClassGroupKey(item);
                const groupRows = [item];

                while ((i + groupRows.length) < data.length) {
                    const candidateRow = data[i + groupRows.length];
                    if (getScheduledClassGroupKey(candidateRow) !== groupKey) {
                        break;
                    }
                    groupRows.push(candidateRow);
                }

                const pairColor = groupRows.length > 1 ? getSchedulePairColor(groupKey) : "";

                groupRows.forEach(function (groupRow, groupIndex) {
                    const type = String(groupRow.schedule_type || "").toUpperCase();
                    const displayUnits = getDisplayUnits(groupRow);
                    const displayLabUnits = getDisplayLabUnits(groupRow);
                    const displayLecUnits = getDisplayLecUnits(groupRow);
                    const typeBadge = type === "LAB"
                        ? '<span class="type-pill lab">LAB</span>'
                        : '<span class="type-pill lec">LEC</span>';
                    const rowClass = groupRows.length > 1
                        ? `linked-schedule-row ${getSchedulePairRowClass(groupIndex, groupRows.length)}`
                        : "";
                    const rowStyle = groupRows.length > 1
                        ? ` style="--schedule-pair-color: ${pairColor};"`
                        : "";
                    const partnerNote = (groupRows.length > 1 && groupIndex === 0)
                        ? `<span class="scheduled-pair-note" style="--schedule-pair-color: ${pairColor};">Linked lecture/lab partner</span>`
                        : "";

                    rows += `
                        <tr class="${rowClass}"${rowStyle}>
                            <td>
                                <input type="checkbox"
                                       class="chkSchedule"
                                       value="${escapeHtml(groupRow.schedule_id)}">
                            </td>
                            <td>${escapeHtml(groupRow.subject_code)}</td>
                            <td>${escapeHtml(groupRow.subject_description)}${partnerNote}</td>
                            <td>${escapeHtml(groupRow.section_name)}</td>
                            <td class="text-center">${typeBadge}</td>
                            <td>${escapeHtml(groupRow.days)}</td>
                            <td>${escapeHtml(groupRow.time)}</td>
                            <td>${escapeHtml(groupRow.room_code)}</td>
                            <td class="text-center">${formatNumber(displayUnits)}</td>
                            <td class="text-center">${formatNumber(displayLabUnits)}</td>
                            <td class="text-center">${formatNumber(displayLecUnits)}</td>
                        </tr>
                    `;
                });

                i += groupRows.length - 1;
            }
            $("#scheduledClassTbody").html(rows);
            updateApplyWorkloadControls();
        }).fail(function (xhr, status) {
            if (status === "abort" || requestToken !== selectionRequestToken) {
                return;
            }

            showInvalid();
            updateApplyWorkloadControls();
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
        updateApplyWorkloadControls();
    }

    $(document).on("change", "#checkAllSchedules", function () {
        $(".chkSchedule").prop("checked", $(this).is(":checked"));
        updateApplyWorkloadControls();
    });

    $(document).on("change", ".chkSchedule", function () {
        const total = $(".chkSchedule").length;
        const checked = $(".chkSchedule:checked").length;
        $("#checkAllSchedules").prop("checked", total > 0 && total === checked);
        updateApplyWorkloadControls();
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
            const countedGroups = new Set();
            let totalLEC = 0;
            let totalLAB = 0;
            let totalUNIT = 0;
            let totalLOAD = 0;
            let rows = "";

            for (let i = 0; i < rowsData.length; i++) {
                const row = rowsData[i];
                const groupKey = getWorkloadGroupKey(row);
                const groupRows = [row];

                while ((i + groupRows.length) < rowsData.length) {
                    const candidateRow = rowsData[i + groupRows.length];
                    if (getWorkloadGroupKey(candidateRow) !== groupKey) {
                        break;
                    }
                    groupRows.push(candidateRow);
                }

                const displayUnits = toNumber(row.units);
                const displayLabUnits = toNumber(row.lab);
                const displayLecUnits = toNumber(row.lec);
                const isMergedGroup = groupRows.length > 1;

                if (!countedGroups.has(groupKey)) {
                    countedGroups.add(groupKey);
                    totalUNIT += displayUnits;
                    totalLAB += displayLabUnits;
                    totalLEC += displayLecUnits;
                    totalLOAD += toNumber(row.faculty_load);
                }

                const preparationKey = String(row.sub_code || "").trim();
                if (preparationKey !== "") {
                    preparationSet.add(preparationKey);
                }

                if (isMergedGroup) {
                    const mergedStudents = groupRows.reduce(function (maxValue, groupRow) {
                        return Math.max(maxValue, toNumber(groupRow.student_count));
                    }, 0);

                    groupRows.forEach(function (groupRow, groupIndex) {
                        rows += `
                            <tr class="${groupIndex === 0 ? "paired-row paired-anchor" : "paired-row"}">
                                <td class="workload-code">${escapeHtml(groupRow.sub_code)}</td>
                                <td class="workload-desc">${buildWorkloadDescription(groupRow, true)}</td>
                                <td>${escapeHtml(groupRow.course || groupRow.section)}</td>
                                <td class="workload-days">${escapeHtml(groupRow.days)}</td>
                                <td class="workload-time">${formatCompactTime(groupRow.time)}</td>
                                <td class="workload-room">${escapeHtml(groupRow.room)}</td>
                                ${groupIndex === 0 ? `
                                    <td class="text-center merged-metric" rowspan="${groupRows.length}">${formatNumber(displayUnits)}</td>
                                    <td class="text-center merged-metric" rowspan="${groupRows.length}">${formatNumber(displayLabUnits)}</td>
                                    <td class="text-center merged-metric" rowspan="${groupRows.length}">${formatNumber(displayLecUnits)}</td>
                                    <td class="text-center merged-metric" rowspan="${groupRows.length}">${formatNumber(row.faculty_load)}</td>
                                    <td class="text-center merged-metric" rowspan="${groupRows.length}">${formatStudentCount(mergedStudents)}</td>
                                ` : ""}
                                <td class="text-end screen-only">
                                    ${buildRemoveWorkloadButton(groupRow)}
                                </td>
                            </tr>
                        `;
                    });

                    i += groupRows.length - 1;
                    continue;
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
                            ${buildRemoveWorkloadButton(row)}
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
                    loadFacultyOverview(true);
                }
            );
        });
    });

    /* =========================================================
       AUTO LOAD ON SELECTION CHANGE
    ========================================================= */
    $("#faculty_id, #fw_ay, #fw_semester").on("change", function () {
        const termChanged = this.id === "fw_ay" || this.id === "fw_semester";
        if (termChanged) {
            clearFacultyOverviewCache();
            loadFacultyOverview(true);
        } else {
            renderFacultyBrowser();
        }

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

    $("#btnOpenFacultyBrowser").on("click", function () {
        loadFacultyOverview(false).always(function () {
            renderFacultyBrowser();
            if (facultyBrowserDrawerInstance) {
                facultyBrowserDrawerInstance.show();
            }
        });
    });

    $(document).on("click", ".faculty-browser-card", function () {
        const facultyId = String($(this).data("facultyId") || "").trim();
        if (!facultyId) {
            return;
        }

        if ($("#faculty_id option[value='" + facultyId.replace(/'/g, "\\'") + "']").length === 0) {
            return;
        }

        if (String($("#faculty_id").val() || "") !== facultyId) {
            $("#faculty_id").val(facultyId).trigger("change");
            return;
        }

        renderFacultyBrowser();
    });

    /* =========================================================
       APPLY TO WORKLOAD
    ========================================================= */
    $(document).on("click", ".btnApplyToWorkloadTrigger", function () {

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
                    loadFacultyOverview(true);
                    return;
                }

                if (res.status === "partial" || res.status === "conflict") {
                    Swal.fire({
                        icon: res.status === "partial" ? "warning" : "error",
                        title: res.status === "partial" ? "Partially Applied" : "Workload Conflict",
                        html: res.message || "Selected classes conflict with this faculty's current workload."
                    });
                    refreshWorkloadPanels();
                    loadFacultyOverview(true);
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

loadFacultyOverview(true);


    

});
</script>



</body>
</html>
