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
   ROOM-TIME MATRIX (BASE - KEPT)
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
    min-width: 1120px;
    table-layout: auto;
}

.schedule-offerings-table th,
.schedule-offerings-table td {
    vertical-align: middle;
}

.schedule-section-col {
    width: 104px;
    min-width: 104px;
}

.schedule-subject-col {
    width: 112px;
    min-width: 112px;
}

.schedule-description-col {
    min-width: 260px;
}

.schedule-hours-col {
    width: 1%;
    min-width: 52px;
    white-space: nowrap;
}

.schedule-days-col {
    width: 98px;
    min-width: 98px;
}

.schedule-time-col {
    width: 146px;
    min-width: 146px;
}

.schedule-room-col {
    width: 138px;
    min-width: 138px;
}

.schedule-status-col {
    width: 1%;
    min-width: 1px;
    white-space: nowrap;
}

.schedule-action-col {
    width: 1%;
    min-width: 1px;
    white-space: nowrap;
}

.schedule-action-col .btn-schedule {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 90px;
    touch-action: manipulation;
}

.schedule-action-stack {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    align-items: stretch;
}

.schedule-action-stack .btn {
    min-width: 110px;
}

.schedule-status-col .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.schedule-status-stack {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    align-items: center;
}

.schedule-status-item {
    display: flex;
    justify-content: center;
}

.schedule-status-faculty {
    max-width: 132px;
    font-size: 0.74rem;
    line-height: 1.3;
    color: #6c757d;
    text-align: center;
    white-space: normal;
    word-break: break-word;
}

.schedule-section-name {
    display: block;
    font-weight: 600;
    line-height: 1.15;
}

.schedule-section-meta {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.72rem;
    line-height: 1.2;
    color: #6c757d;
}

.schedule-section-merge {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.72rem;
    line-height: 1.3;
    color: #0d6efd;
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
    max-width: none;
    margin-left: 0;
}

.schedule-merge-owner-shell {
    border: 1px solid #cfd8f1;
    border-radius: 12px;
    background: linear-gradient(135deg, #f7f9ff 0%, #eef3ff 100%);
    padding: 1rem 1.1rem;
}

.schedule-merge-owner-eyebrow {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #5b6b93;
}

.schedule-merge-owner-title {
    margin-top: 0.3rem;
    font-size: 1rem;
    font-weight: 700;
    color: #233255;
}

.schedule-merge-owner-meta {
    margin-top: 0.25rem;
    font-size: 0.86rem;
    color: #5f6b7a;
}

.schedule-merge-hint {
    margin-top: 0.7rem;
    font-size: 0.82rem;
    color: #5f6b7a;
}

.schedule-merge-candidate-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 420px;
    overflow-y: auto;
}

.schedule-merge-candidate {
    border: 1px solid #d7deea;
    border-radius: 12px;
    background: #fff;
    padding: 0.9rem 1rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.schedule-merge-candidate:hover {
    border-color: #aebee8;
    box-shadow: 0 10px 20px rgba(39, 55, 112, 0.06);
}

.schedule-merge-candidate.is-disabled {
    background: #f8f9fb;
    border-style: dashed;
    opacity: 0.82;
}

.schedule-merge-candidate-title {
    font-weight: 700;
    color: #27324f;
}

.schedule-merge-candidate-meta {
    margin-top: 0.2rem;
    font-size: 0.8rem;
    color: #6a778c;
}

.schedule-merge-candidate-reason {
    margin-top: 0.45rem;
    font-size: 0.8rem;
    color: #b04a32;
}

.schedule-merge-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.schedule-merge-empty {
    border: 1px dashed #cfd8e3;
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    color: #6c757d;
    background: #fbfcfe;
}

.schedule-sort-shell {
    min-width: 0;
}

.schedule-controls-note {
    font-size: 0.8rem;
    color: #6c757d;
    line-height: 1.55;
}

.schedule-workspace-card {
    border: 1px solid #e0e7f5;
    box-shadow: 0 16px 40px rgba(31, 42, 68, 0.08);
    overflow: hidden;
}

.schedule-workspace-shell {
    padding: 1.45rem 1.5rem 1.35rem;
    border-bottom: 1px solid #e3eaf8;
    background:
        radial-gradient(circle at top right, rgba(105, 108, 255, 0.1), transparent 28%),
        linear-gradient(180deg, #fbfcff 0%, #f5f8ff 100%);
}

.schedule-workspace-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.2rem;
    margin-bottom: 1.15rem;
}

.schedule-workspace-intro {
    max-width: 760px;
}

.schedule-workspace-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.62rem;
    border-radius: 999px;
    background: #edf3ff;
    color: #5267d8;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.schedule-workspace-title {
    margin: 0.75rem 0 0.45rem;
    font-size: 1.35rem;
    font-weight: 700;
    color: #23324d;
}

.schedule-workspace-subtitle {
    margin: 0;
    max-width: 760px;
    color: #5f6f8d;
    font-size: 0.95rem;
    line-height: 1.6;
}

.schedule-workspace-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.65rem;
}

.schedule-workspace-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 2.6rem;
    padding: 0.55rem 0.95rem;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 8px 20px rgba(31, 42, 68, 0.06);
}

.schedule-workspace-actions .btn i {
    font-size: 1rem;
}

.schedule-toolbar-grid {
    display: grid;
    grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
    gap: 1rem;
    align-items: stretch;
}

.schedule-toolbar-panel {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 100%;
    padding: 1rem 1rem 0.95rem;
    border: 1px solid #dbe5f6;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.88);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}

.schedule-toolbar-label {
    display: block;
    margin-bottom: 0.65rem;
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #627392;
}

.schedule-toolbar-panel .form-select,
.schedule-toolbar-panel .form-control {
    min-height: 2.85rem;
    border-color: #cfdaf0;
    border-radius: 12px;
}

.schedule-toolbar-panel .form-select:focus,
.schedule-toolbar-panel .form-control:focus {
    border-color: #7d8bff;
    box-shadow: 0 0 0 0.18rem rgba(82, 103, 216, 0.12);
}

.schedule-toolbar-panel.search-panel {
    justify-content: flex-start;
}

.schedule-toolbar-panel.schedule-set-panel {
    grid-column: 1 / -1;
}

.schedule-set-shell {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
}

.schedule-set-select {
    flex: 1 1 300px;
    min-width: 240px;
}

.schedule-set-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.schedule-set-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 2.65rem;
    border-radius: 12px;
    font-weight: 600;
}

.schedule-set-meta {
    min-height: 1.35rem;
    color: #5f6f8d;
    font-size: 0.84rem;
    line-height: 1.45;
}

.schedule-set-meta strong {
    color: #1f2a44;
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

.suggestion-card.review {
    border-color: #f2c56b;
    background: #fffaf0;
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

.suggestion-fit.review {
    background: #fff1cf;
    color: #9a5a00;
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

.availability-shell {
    display: grid;
    grid-template-columns: minmax(220px, 0.95fr) minmax(0, 1.35fr);
    gap: 1rem;
}

.availability-list {
    display: grid;
    gap: 0.7rem;
}

.availability-slot-card {
    width: 100%;
    border: 1px solid #d6e0f3;
    border-radius: 12px;
    background: #fff;
    padding: 0.85rem;
    text-align: left;
    box-shadow: 0 8px 20px rgba(31, 42, 68, 0.06);
    transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.availability-slot-card:hover,
.availability-slot-card:focus {
    border-color: #7d8bff;
    box-shadow: 0 10px 24px rgba(74, 88, 232, 0.12);
    transform: translateY(-1px);
}

.availability-slot-card.is-selected {
    border-color: #4a58e8;
    background: #eef2ff;
    box-shadow: 0 12px 26px rgba(74, 88, 232, 0.16);
}

.availability-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    border-radius: 999px;
    padding: 0.18rem 0.65rem;
    background: #e8f7ee;
    color: #167c49;
    font-size: 0.72rem;
    font-weight: 700;
}

@media (max-width: 991.98px) {
    .availability-shell {
        grid-template-columns: 1fr;
    }
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

.block-schedule-overview {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.95fr);
    gap: 0.85rem;
    align-items: stretch;
}

.block-schedule-overview .coverage-summary,
.block-schedule-overview .modal-assist-strip {
    margin-bottom: 0;
    height: 100%;
}

.block-schedule-overview .modal-assist-strip {
    min-height: 100%;
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

.matrix-entry-actionable {
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
}

.matrix-entry-actionable:hover,
.matrix-entry-actionable:focus {
    transform: translateY(-1px);
    box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, 0.2),
        0 6px 14px rgba(15, 23, 42, 0.18);
    opacity: 0.98;
    outline: none;
}

.matrix-entry-readonly {
    cursor: help;
    opacity: 0.88;
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

.matrix-entry-faculty {
    display: block;
    color: rgba(255, 255, 255, 0.94);
    font-size: 0.54rem;
    line-height: 1.05;
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
   ROOM-TIME MATRIX (ENHANCEMENTS - SCOPED)
   Scoped to #matrixModal ONLY to avoid conflicts
===================================================== */

#matrixModal {
    --matrix-room-col-width: 118px;
    --matrix-day-col-width: 50px;
    --matrix-slot-col-width: 58px;
    z-index: 1090;
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

#matrixModal .matrix-entry-faculty {
    font-size: 0.5rem;
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
   FORCE ROOM-TIME MATRIX TO USE SCREEN WIDTH
   (Bootstrap modal override - SAFE & SCOPED)
===================================================== */

#matrixModal .modal-dialog {
    max-width: 95vw !important;   /* <- THIS IS THE KEY */
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

#sectionScheduleMatrixModal {
    z-index: 1110;
}

#sectionScheduleMatrixModal .modal-dialog {
    max-width: min(1400px, calc(100vw - 2rem));
}

#sectionScheduleMatrixModal .modal-body {
    background: linear-gradient(180deg, #f7f9ff 0%, #eef4ff 100%);
}

.section-matrix-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.section-matrix-day-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.section-matrix-shell {
    border: 1px solid #d8e1f2;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.92);
    overflow: hidden;
    box-shadow: 0 14px 36px rgba(31, 42, 68, 0.08);
}

.section-matrix-scroll {
    overflow: auto;
    max-height: 68vh;
}

.section-matrix-table {
    width: 100%;
    min-width: 760px;
    border-collapse: separate;
    border-spacing: 0;
}

.section-matrix-table th,
.section-matrix-table td {
    border-right: 1px solid #e1e8f6;
    border-bottom: 1px solid #e1e8f6;
    padding: 0.55rem;
    vertical-align: top;
}

.section-matrix-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f5f8ff;
}

.section-matrix-time {
    position: sticky;
    left: 0;
    z-index: 3;
    width: 104px;
    min-width: 104px;
    background: #f8fbff;
    color: #44536e;
    font-weight: 700;
}

.section-matrix-time-label {
    min-height: 44px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.05rem;
    line-height: 1.15;
}

.section-matrix-time-label span:last-child {
    font-size: 0.76rem;
    color: #5f6f8c;
    font-weight: 600;
}

.section-matrix-section {
    min-width: 170px;
    text-align: center;
    background: #f5f8ff;
}

.section-matrix-section.is-current {
    background: #eef2ff;
    color: #3446c3;
}

.section-matrix-cell {
    min-width: 170px;
    background: rgba(255, 255, 255, 0.96);
}

.section-matrix-cell.is-vacant {
    background: rgba(245, 248, 255, 0.88);
}

.section-matrix-cell.is-occupied {
    background: rgba(239, 246, 255, 0.88);
}

.section-matrix-cell.is-conflict {
    background: rgba(255, 244, 228, 0.92);
}

.section-matrix-entry {
    border: 1px solid #d6e0f3;
    border-radius: 12px;
    background: #ffffff;
    padding: 0.45rem 0.55rem;
    box-shadow: 0 6px 18px rgba(31, 42, 68, 0.05);
}

.section-matrix-entry + .section-matrix-entry {
    margin-top: 0.45rem;
}

.section-matrix-entry.is-focus {
    border-color: #7d8bff;
    background: #eef2ff;
}

.section-matrix-entry-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-weight: 700;
    color: #1f2a44;
}

.section-matrix-entry-meta {
    margin-top: 0.3rem;
    font-size: 0.74rem;
    color: #596b89;
    line-height: 1.35;
}

.section-matrix-fill {
    min-height: 44px;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(99, 133, 255, 0.18) 0%, rgba(99, 133, 255, 0.08) 100%);
}

.section-matrix-vacant {
    min-height: 44px;
    border-radius: 10px;
    border: 1px dashed #d4dff3;
    background: rgba(250, 252, 255, 0.86);
}

.section-matrix-empty {
    border: 1px dashed #c8d5ef;
    border-radius: 14px;
    padding: 1rem;
    text-align: center;
    color: #5a6b89;
    background: rgba(255, 255, 255, 0.8);
}

.faculty-helper-shell {
    border: 1px solid #d7e1f2;
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
    padding: 1rem;
    box-shadow: 0 12px 28px rgba(31, 42, 68, 0.05);
}

.faculty-helper-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 0.9rem;
}

.faculty-helper-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.faculty-helper-summary {
    min-height: 100%;
    border: 1px solid #dbe4f5;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.96);
    padding: 0.95rem;
}

.faculty-helper-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.faculty-helper-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2a44;
}

.faculty-helper-note {
    margin-top: 0.2rem;
    font-size: 0.83rem;
    color: #60708f;
    line-height: 1.4;
}

.faculty-helper-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.faculty-helper-metric {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    background: #eef3ff;
    color: #3f58a7;
    font-size: 0.78rem;
    font-weight: 700;
}

.faculty-helper-section-title {
    margin-top: 0.95rem;
    margin-bottom: 0.55rem;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7584a0;
}

.faculty-helper-day-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.7rem;
}

.faculty-helper-day-card {
    border: 1px solid #dce4f5;
    border-radius: 12px;
    background: #f8fbff;
    padding: 0.75rem;
}

.faculty-helper-day-label {
    display: block;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #60708f;
}

.faculty-helper-day-value {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.84rem;
    font-weight: 700;
    color: #1f2a44;
    line-height: 1.4;
}

.faculty-helper-day-meta {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #6d7c98;
}

.faculty-helper-subject-list {
    display: grid;
    gap: 0.55rem;
}

.faculty-helper-subject-card {
    border: 1px solid #dbe4f5;
    border-radius: 12px;
    background: #ffffff;
    padding: 0.75rem 0.8rem;
}

.faculty-helper-subject-title {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-weight: 700;
    color: #1f2a44;
}

.faculty-helper-subject-meta {
    margin-top: 0.3rem;
    font-size: 0.8rem;
    color: #61708f;
    line-height: 1.45;
}

.faculty-helper-empty {
    border: 1px dashed #c7d4ee;
    border-radius: 12px;
    background: rgba(249, 251, 255, 0.8);
    color: #5f6f8d;
    padding: 1rem;
    text-align: center;
}

#facultyScheduleMatrixModal {
    z-index: 1115;
}

#facultyScheduleMatrixModal .modal-dialog {
    max-width: min(1450px, calc(100vw - 2rem));
}

#facultyScheduleMatrixModal .modal-body {
    background: linear-gradient(180deg, #f7f9ff 0%, #eef4ff 100%);
}

.faculty-matrix-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.faculty-matrix-shell {
    border: 1px solid #d8e1f2;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.94);
    overflow: hidden;
    box-shadow: 0 14px 36px rgba(31, 42, 68, 0.08);
}

.faculty-matrix-scroll {
    overflow: auto;
    max-height: 68vh;
}

.faculty-matrix-table {
    width: 100%;
    min-width: 880px;
    border-collapse: separate;
    border-spacing: 0;
}

.faculty-matrix-table th,
.faculty-matrix-table td {
    border-right: 1px solid #e1e8f6;
    border-bottom: 1px solid #e1e8f6;
    padding: 0.55rem;
    vertical-align: top;
}

.faculty-matrix-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f5f8ff;
}

.faculty-matrix-time {
    position: sticky;
    left: 0;
    z-index: 3;
    width: 104px;
    min-width: 104px;
    background: #f8fbff;
    color: #44536e;
    font-weight: 700;
}

.faculty-matrix-time-label {
    min-height: 44px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.05rem;
    line-height: 1.15;
}

.faculty-matrix-time-label span:last-child {
    font-size: 0.76rem;
    color: #5f6f8c;
    font-weight: 600;
}

.faculty-matrix-day {
    min-width: 178px;
    text-align: center;
    background: #f5f8ff;
}

.faculty-matrix-cell {
    min-width: 178px;
    background: rgba(255, 255, 255, 0.96);
}

.faculty-matrix-cell.is-vacant {
    background: rgba(245, 248, 255, 0.88);
}

.faculty-matrix-cell.is-occupied {
    background: rgba(239, 246, 255, 0.88);
}

.faculty-matrix-cell.is-conflict {
    background: rgba(255, 244, 228, 0.92);
}

.faculty-matrix-entry {
    border: 1px solid #d6e0f3;
    border-radius: 12px;
    background: #ffffff;
    padding: 0.45rem 0.55rem;
    box-shadow: 0 6px 18px rgba(31, 42, 68, 0.05);
}

.faculty-matrix-entry + .faculty-matrix-entry {
    margin-top: 0.45rem;
}

.faculty-matrix-entry.is-current {
    border-color: #7d8bff;
    background: #eef2ff;
}

.faculty-matrix-entry-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-weight: 700;
    color: #1f2a44;
}

.faculty-matrix-entry-meta {
    margin-top: 0.3rem;
    font-size: 0.74rem;
    color: #596b89;
    line-height: 1.35;
}

.faculty-matrix-vacant {
    min-height: 44px;
    border-radius: 10px;
    border: 1px dashed #d4dff3;
    background: rgba(250, 252, 255, 0.86);
}

.faculty-matrix-empty {
    border: 1px dashed #c8d5ef;
    border-radius: 14px;
    padding: 1rem;
    text-align: center;
    color: #5a6b89;
    background: rgba(255, 255, 255, 0.8);
}

.faculty-schedule-summary {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.faculty-schedule-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.76rem;
    color: #536881;
}

.faculty-schedule-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.faculty-schedule-legend-swatch {
    width: 0.95rem;
    height: 0.95rem;
    border-radius: 0.35rem;
    border: 1px solid #d9e2ec;
    background: #fbfdff;
}

.faculty-schedule-legend-swatch.is-current {
    border-color: #c7d4ff;
    background: #eef3ff;
}

.faculty-schedule-legend-swatch.is-draft {
    border-color: #c8e6d4;
    background: #eefaf2;
}

.faculty-schedule-legend-swatch.is-draft-conflict {
    border-color: #f0bcc3;
    background: #fff1f1;
}

.faculty-schedule-legend-swatch.is-occupied {
    border-color: #f0bcc3;
    background: #fff1f1;
}

.faculty-schedule-sheet {
    border: 1px solid #d9e2ec;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

.faculty-schedule-table {
    width: 100%;
    min-width: 960px;
    margin-bottom: 0;
    table-layout: fixed;
}

.faculty-schedule-table thead th {
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

.faculty-schedule-table tbody td {
    border-color: #d9e2ec;
    color: #576c85;
    vertical-align: top;
    background: #fff;
    padding: 0.55rem 0.6rem;
}

.faculty-schedule-table th:first-child,
.faculty-schedule-table td:first-child {
    width: 15%;
}

.faculty-schedule-table th:not(:first-child),
.faculty-schedule-table td:not(:first-child) {
    width: 14.16%;
}

.faculty-schedule-time-cell {
    white-space: nowrap;
    font-weight: 600;
    color: #334a63;
    font-size: 0.82rem;
    background: #fbfcfe;
}

.faculty-schedule-empty-cell {
    background: #fff;
    min-height: 48px;
}

.faculty-schedule-class-cell {
    background: #fbfdff !important;
}

.faculty-schedule-class-cell.is-current {
    background: #eef3ff !important;
}

.faculty-schedule-class-cell.is-preview {
    background: #eefaf2 !important;
}

.faculty-schedule-class-cell.is-preview-conflict {
    background: #fff1f1 !important;
}

.faculty-schedule-class-cell.is-occupied {
    background: #fff1f1 !important;
}

.faculty-schedule-class-block {
    min-height: 100%;
}

.faculty-schedule-class-block.is-occupied .faculty-schedule-subject-code {
    color: #8a2432;
}

.faculty-schedule-class-block.is-occupied .faculty-schedule-subject-description {
    color: #8b5a64;
}

.faculty-schedule-class-block.is-occupied .faculty-schedule-block-line {
    color: #6f4b52;
}

.faculty-schedule-class-block.is-preview .faculty-schedule-subject-code {
    color: #1f6b42;
}

.faculty-schedule-class-block.is-preview .faculty-schedule-subject-description,
.faculty-schedule-class-block.is-preview .faculty-schedule-block-line {
    color: #456a55;
}

.faculty-schedule-class-block.is-preview-conflict .faculty-schedule-subject-code {
    color: #8a2432;
}

.faculty-schedule-class-block.is-preview-conflict .faculty-schedule-subject-description,
.faculty-schedule-class-block.is-preview-conflict .faculty-schedule-block-line {
    color: #6f4b52;
}

.faculty-schedule-subject-code {
    font-size: 0.88rem;
    font-weight: 700;
    color: #253a53;
    line-height: 1.25;
}

.faculty-schedule-subject-description {
    margin-top: 0.2rem;
    font-size: 0.72rem;
    line-height: 1.25;
    color: #60768f;
}

.faculty-schedule-block-line {
    margin-top: 0.2rem;
    font-size: 0.72rem;
    line-height: 1.25;
    color: #3d546d;
}

.faculty-schedule-owner-line {
    margin-top: 0.28rem;
    font-size: 0.7rem;
    line-height: 1.3;
    font-weight: 600;
    color: #8a4b55;
}

.faculty-schedule-preview-label {
    margin-bottom: 0.28rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #3d546d;
}

.faculty-schedule-preview-details {
    margin-top: 0.3rem;
    display: grid;
    gap: 0.22rem;
}

.faculty-schedule-preview-detail {
    font-size: 0.68rem;
    line-height: 1.28;
    color: #7b4450;
}

.faculty-schedule-block-chip {
    display: inline-block;
    margin-top: 0.35rem;
    margin-right: 0.25rem;
    padding: 0.16rem 0.42rem;
    border-radius: 999px;
    background: #eef4ff;
    color: #3d63dd;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}

.faculty-schedule-block-chip.is-current {
    background: #e7f7ef;
    color: #157347;
}

.faculty-schedule-block-chip.is-preview {
    background: #e8f6ed;
    color: #1f7a46;
}

.faculty-schedule-block-chip.is-ready {
    background: #e7f7ef;
    color: #157347;
}

.faculty-schedule-block-chip.is-conflict {
    background: #fde7ea;
    color: #b42318;
}

.faculty-schedule-block-chip.is-occupied {
    background: #fde7ea;
    color: #b42318;
}

.faculty-schedule-warning {
    margin-bottom: 1rem;
    border: 1px solid #ffe3a6;
    border-radius: 10px;
    background: #fff8e6;
    color: #7a5a00;
    padding: 0.75rem 0.9rem;
    font-size: 0.84rem;
}

.faculty-awareness-panel {
    margin-bottom: 1rem;
    border: 1px solid #d9e2ec;
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(250, 252, 255, 0.96), rgba(255, 255, 255, 0.98));
    padding: 1rem;
}

.faculty-awareness-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
    flex-wrap: wrap;
}

.faculty-awareness-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 0.85rem;
}

.faculty-awareness-card {
    border: 1px solid #d9e2ec;
    border-radius: 12px;
    background: #fff;
    padding: 0.9rem;
    box-shadow: 0 8px 22px rgba(31, 42, 68, 0.05);
}

.faculty-awareness-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.65rem;
    margin-bottom: 0.75rem;
}

.faculty-awareness-card-title {
    font-size: 0.86rem;
    font-weight: 700;
    color: #253a53;
}

.faculty-awareness-card-meta {
    margin-top: 0.18rem;
    font-size: 0.72rem;
    color: #5f738d;
    line-height: 1.3;
}

.faculty-awareness-list {
    display: grid;
    gap: 0.65rem;
}

.faculty-awareness-item {
    border: 1px solid #d9e2ec;
    border-radius: 12px;
    background: #fbfdff;
    padding: 0.8rem;
}

.faculty-awareness-item.is-caution {
    border-color: #f2ccd2;
    background: #fff7f8;
}

.faculty-awareness-item.is-best {
    border-color: #cfe6d8;
    background: #f4fbf7;
}

.faculty-awareness-item-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.faculty-awareness-item-slot {
    margin-top: 0.45rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #253a53;
}

.faculty-awareness-item-meta {
    margin-top: 0.18rem;
    font-size: 0.72rem;
    color: #5c7088;
    line-height: 1.32;
}

.faculty-awareness-fit {
    display: inline-flex;
    align-items: center;
    padding: 0.16rem 0.5rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.faculty-awareness-fit.is-best {
    background: #e7f7ef;
    color: #157347;
}

.faculty-awareness-fit.is-caution {
    background: #fde7ea;
    color: #b42318;
}

.faculty-awareness-empty {
    border: 1px dashed #cfdae8;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.86);
    padding: 0.85rem;
    color: #5a6d87;
    font-size: 0.8rem;
    line-height: 1.4;
}

@media (max-width: 767.98px) {
    .section-matrix-table {
        min-width: 620px;
    }

    .section-matrix-time {
        min-width: 92px;
        width: 92px;
    }

    .section-matrix-section,
    .section-matrix-cell {
        min-width: 138px;
    }

    .faculty-helper-day-grid {
        grid-template-columns: 1fr;
    }

    .faculty-matrix-table {
        min-width: 700px;
    }

    .faculty-matrix-time {
        min-width: 92px;
        width: 92px;
    }

    .faculty-matrix-day,
    .faculty-matrix-cell {
        min-width: 150px;
    }

    .faculty-schedule-table {
        min-width: 760px;
    }

    .faculty-awareness-grid {
        grid-template-columns: 1fr;
    }
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

.room-browser-launcher {
    position: fixed;
    right: 0.9rem;
    top: 52%;
    transform: translateY(-50%);
    z-index: 1082;
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

.room-browser-launcher:hover,
.room-browser-launcher:focus-visible {
    right: 1.1rem;
    transform: translateY(-50%) scale(1.01);
    box-shadow: 0 24px 48px rgba(65, 84, 216, 0.24);
}

.room-browser-launcher:focus-visible {
    outline: 3px solid rgba(65, 84, 216, 0.2);
    outline-offset: 2px;
}

.room-browser-launcher-icon {
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

.room-browser-launcher-label {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 0.7rem;
    font-weight: 800;
}

.modal-assist-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    margin-bottom: 1rem;
    padding: 0.9rem 1rem;
    border: 1px solid #d9e2f6;
    border-radius: 16px;
    background: linear-gradient(180deg, #fbfcff 0%, #f3f7ff 100%);
}

.modal-assist-copy {
    min-width: 220px;
    flex: 1 1 220px;
}

.modal-assist-copy strong {
    display: block;
    margin-bottom: 0.2rem;
    color: #22304c;
}

.modal-assist-copy small {
    color: #687796;
    line-height: 1.45;
}

.modal-assist-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.55rem;
}

.modal-header-copy {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.modal-context-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.92rem;
    line-height: 1.35;
    color: #24324d;
}

.modal-context-inline .modal-context-separator,
.modal-context-inline .modal-context-section {
    color: #6d7890;
    font-weight: 500;
}

@media (max-width: 991.98px) {
    .block-schedule-overview {
        grid-template-columns: 1fr;
    }
}

#roomBrowserDrawer {
    width: min(430px, calc(100vw - 1rem));
    border-left: 1px solid #dbe3f6;
    box-shadow: -24px 0 48px rgba(25, 40, 90, 0.14);
    z-index: 1085;
}

#roomBrowserDrawer .offcanvas-header {
    padding: 1.2rem 1.2rem 1rem;
    background:
        radial-gradient(circle at top right, rgba(120, 196, 255, 0.18), transparent 44%),
        linear-gradient(180deg, #edf4ff 0%, #f8fbff 100%);
    border-bottom: 1px solid #dbe3f6;
}

.room-browser-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.room-browser-title-icon {
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

.room-browser-title h5 {
    margin: 0;
    font-weight: 700;
    color: #22304c;
}

.room-browser-title p {
    margin: 0.2rem 0 0;
    color: #687796;
    font-size: 0.86rem;
}

#roomBrowserDrawer .offcanvas-body {
    padding: 1.2rem;
    background:
        radial-gradient(circle at top left, rgba(227, 237, 255, 0.62), transparent 32%),
        linear-gradient(180deg, #fbfcff 0%, #f6f9ff 100%);
}

.room-browser-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.room-browser-metric {
    border: 1px solid #dbe4f6;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.88);
    padding: 0.85rem;
    box-shadow: 0 12px 28px rgba(29, 43, 88, 0.05);
}

.room-browser-metric-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #7887a4;
}

.room-browser-metric-value {
    display: block;
    margin-top: 0.3rem;
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1;
    color: #22304c;
}

.room-browser-note {
    margin-bottom: 1rem;
    color: #6d7c98;
    font-size: 0.84rem;
}

.room-browser-section {
    border: 1px solid #dfe7f7;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 14px 30px rgba(29, 43, 88, 0.05);
    padding: 1rem;
}

.room-browser-section + .room-browser-section {
    margin-top: 1rem;
}

.room-browser-section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}

.room-browser-section-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.room-browser-type-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.77rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.room-browser-type-chip.is-lecture {
    background: #ecefff;
    color: #5666ff;
}

.room-browser-type-chip.is-laboratory {
    background: #fff2cf;
    color: #b88004;
}

.room-browser-type-chip.is-flexible {
    background: #e6f6f1;
    color: #11806a;
}

.room-browser-section-title h6 {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 700;
    color: #22304c;
}

.room-browser-section-subtitle {
    margin-top: 0.2rem;
    color: #74839e;
    font-size: 0.8rem;
}

.room-browser-section-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.4rem;
}

.room-browser-section-badge {
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

.room-browser-room-list {
    display: grid;
    gap: 0.8rem;
}

.room-browser-room-card {
    border: 1px solid #e3eaf8;
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
    padding: 0.9rem 0.95rem;
    display: flex;
    justify-content: space-between;
    gap: 0.9rem;
    align-items: flex-start;
}

.room-browser-room-card.is-active {
    border-color: #bfd0ff;
    box-shadow: 0 16px 34px rgba(67, 86, 219, 0.08);
}

.room-browser-room-card.is-idle {
    opacity: 0.9;
}

.room-browser-room-name {
    font-size: 0.96rem;
    font-weight: 700;
    color: #22304c;
    line-height: 1.25;
}

.room-browser-room-note {
    margin-top: 0.28rem;
    color: #73829d;
    font-size: 0.81rem;
}

.room-browser-room-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.42rem;
    margin-top: 0.65rem;
}

.room-browser-room-tag {
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

.room-browser-room-tag.is-lec {
    background: #ecefff;
    color: #5666ff;
}

.room-browser-room-tag.is-lab {
    background: #fff2cf;
    color: #b88004;
}

.room-browser-room-tag.is-shared {
    background: #e8f6ff;
    color: #0d6a92;
}

.room-browser-room-tag.is-owned {
    background: #edf7ef;
    color: #17784b;
}

.room-browser-count {
    min-width: 76px;
    text-align: right;
}

.room-browser-count-number {
    display: block;
    font-size: 1.35rem;
    line-height: 1;
    font-weight: 800;
    color: #22304c;
}

.room-browser-count-label {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #7b89a4;
}

.room-browser-empty {
    border: 1px dashed #cedaf4;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.82);
    color: #60708f;
    text-align: center;
    padding: 1.15rem;
    font-size: 0.9rem;
}

@media (max-width: 991.98px) {
    .schedule-workspace-shell {
        padding: 1.2rem 1.15rem 1.1rem;
    }

    .schedule-workspace-top {
        flex-direction: column;
        align-items: stretch;
    }

    .schedule-workspace-actions {
        justify-content: flex-start;
    }

    .schedule-toolbar-grid {
        grid-template-columns: 1fr;
    }

    .schedule-offerings-table {
        min-width: 1040px;
    }

    .schedule-section-col {
        width: 96px;
        min-width: 96px;
    }

    .schedule-subject-col {
        width: 104px;
        min-width: 104px;
    }

    .schedule-description-col {
        min-width: 220px;
    }

    .schedule-days-col {
        width: 90px;
        min-width: 90px;
    }

    .schedule-time-col {
        width: 134px;
        min-width: 134px;
    }

    .schedule-room-col {
        width: 126px;
        min-width: 126px;
    }

    .schedule-status-col {
        width: 1%;
        min-width: 1px;
    }

    .room-browser-launcher {
        top: auto;
        bottom: 1rem;
        right: 1rem;
        transform: none;
        flex-direction: row;
        padding: 0.8rem 1rem;
        border-radius: 999px;
    }

    .room-browser-launcher:hover,
    .room-browser-launcher:focus-visible {
        right: 1rem;
        transform: translateY(-2px);
    }

    .room-browser-launcher-label {
        writing-mode: initial;
        transform: none;
        letter-spacing: 0.08em;
    }

    .room-browser-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    #scheduleListContainer {
        padding-bottom: 6rem !important;
    }

    .schedule-workspace-title {
        font-size: 1.2rem;
    }

    .schedule-workspace-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .schedule-workspace-actions .btn {
        width: 100%;
        justify-content: center;
    }

    .schedule-toolbar-panel {
        padding: 0.9rem 0.9rem 0.85rem;
        border-radius: 14px;
    }

    .schedule-offerings-table {
        min-width: 980px;
    }

    .schedule-description-col {
        min-width: 200px;
    }

    .schedule-pan-shell {
        padding-bottom: 0.35rem;
        -webkit-overflow-scrolling: touch;
    }

    .schedule-action-col {
        min-width: 112px;
    }

    .schedule-action-col .btn-schedule {
        width: 100%;
        min-height: 2.4rem;
    }

    #roomBrowserDrawer {
        width: 100vw;
    }

    .room-browser-summary {
        grid-template-columns: 1fr;
    }

    .room-browser-section-header,
    .room-browser-room-card {
        flex-direction: column;
    }

    .room-browser-count {
        min-width: 0;
        text-align: left;
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

      $label = $r['program_code'] . " - " . $r['program_name'];

      // Append major only if it exists
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
<div class="card schedule-workspace-card">
    <div class="schedule-workspace-shell">
      <div class="schedule-workspace-top">
        <div class="schedule-workspace-intro">
          <span class="schedule-workspace-eyebrow">Scheduler Workspace</span>
          <h5 class="schedule-workspace-title">Class Offerings</h5>
          <p class="schedule-workspace-subtitle">
            Review filtered offerings, compare sections with the same subject, and jump quickly into drafting, clearing, or checking room-time availability.
          </p>
        </div>

        <div class="schedule-workspace-actions">
          <button class="btn btn-outline-success btn-sm" id="btnPreviewAutoDraft">
            <i class="bx bx-magic-wand"></i> Start Draft
          </button>
          <button class="btn btn-outline-danger btn-sm" id="btnClearAllSchedules">
            <i class="bx bx-trash"></i> Clear All Schedules
          </button>
          <button class="btn btn-outline-primary btn-sm" id="btnShowMatrix">
            <i class="bx bx-grid-alt"></i> Room-Time Matrix
          </button>
        </div>
      </div>

      <div class="schedule-toolbar-grid">
        <div class="schedule-toolbar-panel schedule-set-panel">
          <label class="schedule-toolbar-label" for="scheduleSetSelect">Saved Schedule Sets</label>
          <div class="schedule-set-shell">
            <div class="schedule-set-select">
              <select id="scheduleSetSelect" class="form-select" disabled>
                <option value="">Select Academic Year and Semester first</option>
              </select>
            </div>
            <div class="schedule-set-actions">
              <button class="btn btn-outline-secondary btn-sm" id="btnRefreshScheduleSets" disabled>
                <i class="bx bx-refresh"></i> Refresh Sets
              </button>
              <button class="btn btn-outline-primary btn-sm" id="btnSaveScheduleSet" disabled>
                <i class="bx bx-save"></i> Save Live as Set
              </button>
              <button class="btn btn-primary btn-sm" id="btnLoadScheduleSet" disabled>
                <i class="bx bx-import"></i> Load Set to Live
              </button>
            </div>
          </div>
          <div class="schedule-set-meta mt-2" id="scheduleSetDetails">
            Live schedules remain the active workspace. Save them as a reusable set, then clear or revise live schedules and load any saved set back later.
          </div>
        </div>

        <div class="schedule-toolbar-panel">
          <label class="schedule-toolbar-label" for="scheduleSortMode">Group View</label>
          <select id="scheduleSortMode" class="form-select schedule-sort-shell">
            <option value="year_level">Year Level</option>
            <option value="subject">Subject</option>
          </select>
          <div class="schedule-controls-note mt-2">
            Use Subject view to compare multiple sections that share the same course.
          </div>
        </div>

        <div class="schedule-toolbar-panel search-panel">
          <div class="schedule-search-shell w-100">
            <label class="schedule-toolbar-label" for="scheduleSubjectSearch">Search Subjects</label>
            <input
              type="text"
              id="scheduleSubjectSearch"
              class="form-control"
              placeholder="Search by subject code, description, or section within the current filters"
            >
            <div class="schedule-controls-note mt-2">
              Search works inside the current prospectus, academic year, semester, and group view.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="p-3" id="scheduleListContainer">
      <div class="text-center text-muted py-4">
        Filtered class offerings will load automatically.
      </div>
    </div>
</div>

<button
  type="button"
  class="room-browser-launcher"
  id="btnOpenRoomBrowser"
  aria-controls="roomBrowserDrawer"
  aria-label="Open room browser"
>
  <span class="room-browser-launcher-icon">
    <i class="bx bx-buildings"></i>
  </span>
  <span class="room-browser-launcher-label">Room Browser</span>
</button>

</div>
<?php include '../footer.php'; ?>
</div>
</div>
</div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="roomBrowserDrawer" aria-labelledby="roomBrowserDrawerLabel" data-bs-backdrop="false" data-bs-scroll="true">
  <div class="offcanvas-header">
    <div class="room-browser-title">
      <span class="room-browser-title-icon">
        <i class="bx bx-door-open"></i>
      </span>
      <div>
        <h5 id="roomBrowserDrawerLabel">Room Browser</h5>
        <p>Browse accessible rooms by type and see how many scheduled classes are using each one.</p>
      </div>
    </div>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div class="room-browser-summary" id="roomBrowserSummary"></div>
    <div class="room-browser-note">
      Counts follow the current prospectus, academic term, semester, and subject search results shown on this page.
    </div>
    <div id="roomBrowserList">
      <div class="room-browser-empty">Select a term to load the room browser.</div>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="scheduleModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header">
    <div class="modal-header-copy">
      <h5 class="modal-title mb-0">Define Class Schedule</h5>
      <div class="modal-context-inline d-none" id="sched_context_line">
        <span id="sched_subject_label"></span>
        <span class="modal-context-separator">-</span>
        <span class="modal-context-section" id="sched_section_label"></span>
      </div>
    </div>
    </div>

    <div class="modal-body">

    <input type="hidden" id="sched_offering_id">

    <div class="row g-4 align-items-start">
      <div class="col-lg-7">

    <div class="modal-assist-strip">
      <div class="modal-assist-copy">
        <strong>Need room and time visibility?</strong>
        <small>Open the room-time matrix without closing this scheduling dialog.</small>
      </div>
      <div class="modal-assist-actions">
        <button type="button" class="btn btn-outline-primary btn-sm btn-open-modal-matrix">
          <i class="bx bx-grid-alt me-1"></i> Room-Time Matrix
        </button>
      </div>
    </div>

    <hr>

    <div class="step-label mb-2">Step 1 - When does the class meet?</div>

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

    <div class="step-label mb-2">Step 2 - Where is the class held?</div>

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
<div class="modal fade" id="dualScheduleModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <div class="modal-header-copy">
          <h5 class="modal-title mb-0">Define Lecture & Laboratory Schedule</h5>
          <div class="modal-context-inline d-none" id="dual_context_line">
            <span id="dual_subject_label"></span>
            <span class="modal-context-separator">-</span>
            <span class="modal-context-section" id="dual_section_label"></span>
          </div>
        </div>
      </div>

      <div class="modal-body">

        <input type="hidden" id="dual_offering_id">

        <div class="modal-assist-strip">
          <div class="modal-assist-copy">
            <strong>Need room and time visibility?</strong>
            <small>Open the room-time matrix without closing this scheduling dialog.</small>
          </div>
          <div class="modal-assist-actions">
            <button type="button" class="btn btn-outline-primary btn-sm btn-open-modal-matrix">
              <i class="bx bx-grid-alt me-1"></i> Room-Time Matrix
            </button>
          </div>
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
<div class="modal fade" id="blockScheduleModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-copy">
          <h5 class="modal-title mb-0">Define Class Schedule Blocks</h5>
          <div class="modal-context-inline d-none" id="block_sched_context_line">
            <span id="block_sched_subject_label"></span>
            <span class="modal-context-separator">-</span>
            <span class="modal-context-section" id="block_sched_section_label"></span>
          </div>
        </div>
      </div>

      <div class="modal-body">
        <input type="hidden" id="block_sched_offering_id">
        <input type="hidden" id="block_sched_lec_units">
        <input type="hidden" id="block_sched_lab_units">
        <input type="hidden" id="block_sched_total_units">

        <div class="block-schedule-overview mb-3">
          <div class="coverage-summary" id="scheduleBlockCoverageSummary">
            <strong>Schedule coverage will appear here.</strong>
          </div>

          <div class="modal-assist-strip">
            <div class="modal-assist-copy">
              <strong>Need more schedule visibility?</strong>
              <small>Open a helper matrix while keeping this block editor open.</small>
            </div>
            <div class="modal-assist-actions">
              <button type="button" class="btn btn-outline-primary btn-sm btn-open-modal-matrix">
                <i class="bx bx-grid-alt me-1"></i> Room-Time Matrix
              </button>
              <button type="button" class="btn btn-outline-info btn-sm btn-open-section-matrix">
                <i class="bx bx-table me-1"></i> Show All Section Matrix
              </button>
            </div>
          </div>
        </div>

        <div class="faculty-helper-shell mb-3">
          <div class="faculty-helper-toolbar">
            <div>
              <div class="step-label mb-1">Faculty Schedule Viewer</div>
              <div class="schedule-hint mb-0">
                Select a faculty from this college term, then open the faculty schedule board before saving blocks.
              </div>
            </div>
            <div class="faculty-helper-actions">
              <button type="button" class="btn btn-outline-info btn-sm" id="btnOpenFacultyScheduleMatrix" disabled>
                <i class="bx bx-calendar me-1"></i> Show Workload
              </button>
            </div>
          </div>

          <div class="row g-3 align-items-end">
            <div class="col-lg-6">
              <label class="form-label" for="blockScheduleFacultySelect">Faculty</label>
              <select id="blockScheduleFacultySelect" class="form-select" disabled>
                <option value="">Select faculty...</option>
              </select>
            </div>
            <div class="col-lg-6">
              <div class="schedule-hint" id="blockScheduleFacultyHint">
                Faculty from the selected college term will load here.
              </div>
            </div>
          </div>

          <div class="faculty-helper-summary d-none" id="blockScheduleFacultySummary">
            <div class="faculty-helper-empty">
              Select a faculty to view scheduled subjects and available time windows.
            </div>
          </div>
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


<div class="modal fade" id="scheduleMergeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-copy">
          <h5 class="modal-title mb-0">Manage Schedule Merge</h5>
          <div class="schedule-hint mt-1">
            The schedule owner keeps the live room, day, and time blocks. Selected offerings inherit that same schedule and free their own schedule entries.
          </div>
        </div>
      </div>

      <div class="modal-body">
        <input type="hidden" id="scheduleMergeOwnerOfferingId">

        <div class="schedule-merge-owner-shell mb-3">
          <span class="schedule-merge-owner-eyebrow">Schedule Owner</span>
          <div class="schedule-merge-owner-title" id="scheduleMergeOwnerTitle">Loading merge details...</div>
          <div class="schedule-merge-owner-meta" id="scheduleMergeOwnerMeta"></div>
          <div class="schedule-merge-hint" id="scheduleMergeOwnerHint"></div>
        </div>

        <div class="alert alert-warning d-none" id="scheduleMergeBlockingNotice"></div>

        <div class="schedule-merge-toolbar mb-3">
          <div class="schedule-hint mb-0">
            Select the offerings that should inherit the owner schedule in this college term.
          </div>
          <span class="badge bg-label-info text-info" id="scheduleMergeSelectedCount">0 selected</span>
        </div>

        <div class="schedule-merge-candidate-list" id="scheduleMergeCandidateList">
          <div class="schedule-merge-empty">Loading merge candidates...</div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnClearMergeSelection">Clear Selection</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="btnSaveScheduleMerge">Save Merge Group</button>
      </div>
    </div>
  </div>
</div>


<!-- AUTO DRAFT MODAL -->
<div class="modal fade" id="autoDraftModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Auto Draft Schedule</h5>
          <div class="small text-muted mt-1">
            Preview conflict-free drafts before applying them to the selected prospectus and term.
          </div>
        </div>
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
<div class="modal fade" id="matrixModal" tabindex="-1" data-bs-backdrop="false">
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

<div class="modal fade" id="sectionScheduleMatrixModal" tabindex="-1" data-bs-backdrop="false">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">
            <i class="bx bx-table me-1"></i> All Section Schedule Matrix
          </h5>
          <div class="small text-muted mt-1" id="sectionScheduleMatrixHeaderNote">
            Compare scheduled subjects across peer sections without leaving the block editor.
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="sectionScheduleMatrixContainer">
          <div class="section-matrix-empty">Loading section matrix...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="facultyScheduleMatrixModal" tabindex="-1" data-bs-backdrop="false">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">
            <i class="bx bx-user-pin me-1"></i> Faculty Workload Schedule
          </h5>
          <div class="small text-muted mt-1" id="facultyScheduleMatrixHeaderNote">
            View the selected faculty schedule in the same day-by-time board layout.
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="facultyScheduleMatrixContainer">
          <div class="faculty-matrix-empty">Select a faculty and click Show Workload.</div>
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
    const FACULTY_SCHEDULE_DAY_COLUMNS = [
        { key: "M", label: "Mon" },
        { key: "T", label: "Tue" },
        { key: "W", label: "Wed" },
        { key: "Th", label: "Thu" },
        { key: "F", label: "Fri" },
        { key: "S", label: "Sat" }
    ];
    const SUPPORTED_TIME_START = String(SCHEDULE_POLICY.day_start_input || "07:30");
    const SUPPORTED_TIME_END = String(SCHEDULE_POLICY.day_end_input || "17:30");
    const FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES = 30;
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
    let scheduleMergeRequest = null;
    let scheduleMergeState = null;
    let scheduleSetListRequest = null;
    let scheduleSetList = [];
    let scheduleAutoLoadTimer = null;
    let singleSuggestionTimer = null;
    let dualSuggestionTimer = null;
    let roomBrowserDrawerInstance = null;
    let matrixModalInstance = null;
    let sectionScheduleMatrixModalInstance = null;
    let sectionScheduleMatrixRequest = null;
    let sectionScheduleMatrixState = null;
    let facultyScheduleMatrixModalInstance = null;
    let scheduleBlockFacultyOptionsRequest = null;
    let scheduleBlockFacultyDetailRequest = null;
    let scheduleBlockFacultyPreviewTimer = null;
    const ROOM_BROWSER_TYPE_META = {
        lecture: {
            label: "Lecture Rooms",
            icon: "bx-chalkboard",
            chipClass: "is-lecture",
            subtitle: "Standard lecture spaces available for lecture schedules."
        },
        laboratory: {
            label: "Laboratory Rooms",
            icon: "bx-chip",
            chipClass: "is-laboratory",
            subtitle: "Specialized laboratory spaces matched to lab schedules."
        },
        lec_lab: {
            label: "Flexible Lec/Lab Rooms",
            icon: "bx-layer",
            chipClass: "is-flexible",
            subtitle: "Rooms that can host either lecture or laboratory blocks."
        }
    };
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

    function hasSchedulingModalOpen() {
        return $("#scheduleModal, #dualScheduleModal, #blockScheduleModal, #scheduleMergeModal, #autoDraftModal").filter(".show").length > 0;
    }

    function restoreBodyModalStateForScheduling() {
        if (!hasSchedulingModalOpen()) {
            return;
        }

        document.body.classList.add("modal-open");
        document.body.style.overflow = "hidden";
    }

    function openRoomBrowserHelper() {
        loadTermRoomOptions(false).always(function () {
            renderRoomBrowser();
            if (roomBrowserDrawerInstance) {
                roomBrowserDrawerInstance.show();
            }
        });
    }

    function openMatrixHelper() {
        loadRoomTimeMatrix(true, { preservePosition: false });
    }

    function abortSectionScheduleMatrixRequest() {
        if (sectionScheduleMatrixRequest && sectionScheduleMatrixRequest.readyState !== 4) {
            sectionScheduleMatrixRequest.abort();
        }

        sectionScheduleMatrixRequest = null;
    }

    function normalizeMatrixTimeInput(value) {
        const text = String(value || "").trim();
        if (text === "") {
            return "";
        }

        const parts = text.split(":");
        if (parts.length < 2) {
            return text;
        }

        return `${parts[0].padStart(2, "0")}:${parts[1].padStart(2, "0")}`;
    }

    function matrixTimeToMinutes(value) {
        const normalized = normalizeMatrixTimeInput(value);
        if (!/^\d{2}:\d{2}$/.test(normalized)) {
            return null;
        }

        const [hourText, minuteText] = normalized.split(":");
        const hour = Number(hourText);
        const minute = Number(minuteText);
        if (!Number.isFinite(hour) || !Number.isFinite(minute)) {
            return null;
        }

        return (hour * 60) + minute;
    }

    function matrixMinutesToTime(minutes) {
        const safeMinutes = Number(minutes);
        if (!Number.isFinite(safeMinutes)) {
            return "";
        }

        const hour = Math.floor(safeMinutes / 60);
        const minute = safeMinutes % 60;
        return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
    }

    function formatFacultyScheduleTimeRange(startMinutes, endMinutes) {
        return `${formatPolicyTimeLabel(matrixMinutesToTime(startMinutes))} - ${formatPolicyTimeLabel(matrixMinutesToTime(endMinutes))}`;
    }

    function buildSectionMatrixTimeSlots() {
        const slots = [];
        const start = matrixTimeToMinutes(SUPPORTED_TIME_START);
        const end = matrixTimeToMinutes(SUPPORTED_TIME_END);
        if (start === null || end === null || end <= start) {
            return slots;
        }

        for (let value = start; value < end; value += 30) {
            const timeValue = matrixMinutesToTime(value);
            const nextTimeValue = matrixMinutesToTime(Math.min(value + 30, end));
            slots.push({
                value: timeValue,
                minutes: value,
                label: formatPolicyTimeLabel(timeValue),
                endLabel: formatPolicyTimeLabel(nextTimeValue)
            });
        }

        return slots;
    }

    function normalizeSectionMatrixEntry(entry) {
        return {
            schedule_id: parseInt(entry?.schedule_id, 10) || 0,
            schedule_type: String(entry?.schedule_type || "LEC").toUpperCase() === "LAB" ? "LAB" : "LEC",
            time_start: normalizeMatrixTimeInput(entry?.time_start || ""),
            time_end: normalizeMatrixTimeInput(entry?.time_end || ""),
            days: normalizeScheduleDays(Array.isArray(entry?.days) ? entry.days : []),
            subject_code: String(entry?.subject_code || ""),
            subject_description: String(entry?.subject_description || ""),
            room_label: String(entry?.room_label || "TBA")
        };
    }

    function getSectionMatrixDefaultDay(sections) {
        const list = Array.isArray(sections) ? sections : [];
        for (const day of SCHEDULE_DAY_ORDER) {
            const hasEntry = list.some(section => Array.isArray(section?.entries) && section.entries.some(entry => Array.isArray(entry.days) && entry.days.includes(day)));
            if (hasEntry) {
                return day;
            }
        }

        return SCHEDULE_DAY_ORDER[0] || "M";
    }

    function sortSectionMatrixEntries(entries) {
        return (Array.isArray(entries) ? entries.slice() : []).sort((left, right) => {
            const leftStart = matrixTimeToMinutes(left?.time_start);
            const rightStart = matrixTimeToMinutes(right?.time_start);
            if (leftStart !== rightStart) {
                return (leftStart ?? 0) - (rightStart ?? 0);
            }

            const leftEnd = matrixTimeToMinutes(left?.time_end);
            const rightEnd = matrixTimeToMinutes(right?.time_end);
            if (leftEnd !== rightEnd) {
                return (leftEnd ?? 0) - (rightEnd ?? 0);
            }

            const leftCode = String(left?.subject_code || "");
            const rightCode = String(right?.subject_code || "");
            if (leftCode !== rightCode) {
                return leftCode.localeCompare(rightCode);
            }

            const leftType = String(left?.schedule_type || "");
            const rightType = String(right?.schedule_type || "");
            if (leftType !== rightType) {
                return leftType.localeCompare(rightType);
            }

            return (parseInt(left?.schedule_id, 10) || 0) - (parseInt(right?.schedule_id, 10) || 0);
        });
    }

    function buildSectionMatrixSignature(entries) {
        return sortSectionMatrixEntries(entries).map(entry => {
            const scheduleId = parseInt(entry?.schedule_id, 10) || 0;
            if (scheduleId > 0) {
                return `id:${scheduleId}`;
            }

            return [
                String(entry?.subject_code || ""),
                String(entry?.schedule_type || ""),
                String(entry?.time_start || ""),
                String(entry?.time_end || ""),
                String(entry?.room_label || "")
            ].join("|");
        }).join("||");
    }

    function buildSectionMatrixColumnState(section, selectedDay, timeSlots) {
        const state = Array.from({ length: timeSlots.length }, () => null);
        const entries = (Array.isArray(section?.entries) ? section.entries : [])
            .map(entry => {
                const startMinutes = matrixTimeToMinutes(entry?.time_start);
                const endMinutes = matrixTimeToMinutes(entry?.time_end);

                return {
                    ...entry,
                    _startMinutes: startMinutes,
                    _endMinutes: endMinutes
                };
            })
            .filter(entry => Array.isArray(entry.days)
                && entry.days.includes(selectedDay)
                && entry._startMinutes !== null
                && entry._endMinutes !== null
                && entry._endMinutes > entry._startMinutes);

        const slotEntries = timeSlots.map(slot => sortSectionMatrixEntries(entries.filter(entry => (
            slot.minutes >= entry._startMinutes && slot.minutes < entry._endMinutes
        ))));

        for (let slotIndex = 0; slotIndex < timeSlots.length; slotIndex++) {
            if (state[slotIndex]) {
                continue;
            }

            const activeEntries = slotEntries[slotIndex];
            if (!activeEntries.length) {
                state[slotIndex] = {
                    skip: false,
                    rowspan: 1,
                    items: [],
                    cellClass: "is-vacant"
                };
                continue;
            }

            const signature = buildSectionMatrixSignature(activeEntries);
            let rowspan = 1;

            for (let nextIndex = slotIndex + 1; nextIndex < timeSlots.length; nextIndex++) {
                const nextEntries = slotEntries[nextIndex];
                if (!nextEntries.length || buildSectionMatrixSignature(nextEntries) !== signature) {
                    break;
                }

                rowspan++;
            }

            state[slotIndex] = {
                skip: false,
                rowspan: rowspan,
                items: activeEntries,
                cellClass: activeEntries.length > 1 ? "is-conflict" : "is-occupied"
            };

            for (let coveredIndex = slotIndex + 1; coveredIndex < slotIndex + rowspan; coveredIndex++) {
                state[coveredIndex] = {
                    skip: true
                };
            }
        }

        return state;
    }

    function renderSectionScheduleMatrix() {
        const container = $("#sectionScheduleMatrixContainer");
        if (!sectionScheduleMatrixState || container.length === 0) {
            return;
        }

        const sections = Array.isArray(sectionScheduleMatrixState.sections) ? sectionScheduleMatrixState.sections : [];
        const selectedDay = String(sectionScheduleMatrixState.selectedDay || getSectionMatrixDefaultDay(sections));
        const timeSlots = buildSectionMatrixTimeSlots();

        $("#sectionScheduleMatrixHeaderNote").text(
            sections.length > 1
                ? `${sectionScheduleMatrixState.subjectLabel || "Subject"} across ${sections.length} peer sections, shown in 30-minute intervals.`
                : `Only one section offering is currently available for ${sectionScheduleMatrixState.subjectLabel || "this subject"}, shown in 30-minute intervals.`
        );

        if (sections.length === 0) {
            container.html('<div class="section-matrix-empty">No peer sections were found for this subject in the current term.</div>');
            return;
        }

        const dayButtons = SCHEDULE_DAY_ORDER.map(day => `
            <button
                type="button"
                class="btn btn-sm ${day === selectedDay ? "btn-primary" : "btn-outline-secondary"} btn-section-matrix-day"
                data-day="${escapeHtml(day)}"
            >
                ${escapeHtml(day)}
            </button>
        `).join("");

        const sectionColumnStates = sections.map(section => buildSectionMatrixColumnState(section, selectedDay, timeSlots));

        const rowsHtml = timeSlots.map((slot, slotIndex) => {
            const cellsHtml = sectionColumnStates.map(columnState => {
                const cellState = columnState[slotIndex];
                if (!cellState || cellState.skip) {
                    return "";
                }

                if (!Array.isArray(cellState.items) || cellState.items.length === 0) {
                    return `
                        <td class="section-matrix-cell is-vacant">
                            <div class="section-matrix-vacant"></div>
                        </td>
                    `;
                }

                const entryHtml = cellState.items.map(entry => {
                    const isFocus = String(entry.subject_code || "").toUpperCase() === String(sectionScheduleMatrixState.subjectCode || "").toUpperCase();
                    return `
                        <div class="section-matrix-entry ${isFocus ? "is-focus" : ""}">
                            <div class="section-matrix-entry-title">
                                <span>${escapeHtml(entry.subject_code || "TBA")}</span>
                                <span class="suggestion-chip">${escapeHtml(entry.schedule_type || "LEC")}</span>
                            </div>
                            <div class="section-matrix-entry-meta">
                                <div>${escapeHtml(entry.room_label || "TBA")}</div>
                                <div>${escapeHtml(formatPolicyTimeLabel(entry.time_start))} - ${escapeHtml(formatPolicyTimeLabel(entry.time_end))}</div>
                            </div>
                        </div>
                    `;
                }).join("");

                const rowspanAttr = cellState.rowspan > 1 ? ` rowspan="${cellState.rowspan}"` : "";
                return `<td class="section-matrix-cell ${cellState.cellClass}"${rowspanAttr}>${entryHtml}</td>`;
            }).join("");

            return `
                <tr>
                    <th class="section-matrix-time">
                        <div class="section-matrix-time-label">
                            <span>${escapeHtml(slot.label)}</span>
                            <span>${escapeHtml(slot.endLabel || "")}</span>
                        </div>
                    </th>
                    ${cellsHtml}
                </tr>
            `;
        }).join("");

        const sectionHeaders = sections.map(section => `
            <th class="section-matrix-section ${section.is_current ? "is-current" : ""}">
                <div>${escapeHtml(section.label || "Section")}</div>
                <div class="small text-muted mt-1">${escapeHtml(String(section.entry_count || 0))} scheduled block(s)</div>
            </th>
        `).join("");

        container.html(`
            <div class="section-matrix-toolbar">
                <div>
                    <div class="fw-semibold">${escapeHtml(sectionScheduleMatrixState.subjectLabel || "Subject")}</div>
                    <div class="text-muted small">${escapeHtml(sectionScheduleMatrixState.sectionLabel || "")}</div>
                </div>
                <div class="section-matrix-day-pills">${dayButtons}</div>
            </div>
            <div class="section-matrix-shell">
                <div class="section-matrix-scroll">
                    <table class="section-matrix-table">
                        <thead>
                            <tr>
                                <th class="section-matrix-time">Time</th>
                                ${sectionHeaders}
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>
            </div>
        `);
    }

    function openSectionScheduleMatrixHelper() {
        if (!scheduleBlockState || !scheduleBlockState.offeringId) {
            Swal.fire("Unavailable", "Open a class schedule block first.", "info");
            return;
        }

        sectionScheduleMatrixState = {
            offeringId: Number(scheduleBlockState.offeringId || 0),
            subjectLabel: String(scheduleBlockState.subjectLabel || "Subject"),
            sectionLabel: String(scheduleBlockState.sectionLabel || ""),
            subjectCode: "",
            sections: [],
            selectedDay: SCHEDULE_DAY_ORDER[0] || "M"
        };

        $("#sectionScheduleMatrixHeaderNote").text("Loading peer section schedules...");
        $("#sectionScheduleMatrixContainer").html('<div class="section-matrix-empty">Loading section matrix...</div>');

        if (sectionScheduleMatrixModalInstance) {
            sectionScheduleMatrixModalInstance.show();
        } else {
            $("#sectionScheduleMatrixModal").modal("show");
        }

        abortSectionScheduleMatrixRequest();
        sectionScheduleMatrixRequest = $.ajax({
            url: "../backend/query_class_schedule.php",
            type: "POST",
            dataType: "json",
            data: {
                load_section_schedule_matrix: 1,
                offering_id: sectionScheduleMatrixState.offeringId
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    $("#sectionScheduleMatrixHeaderNote").text("Unable to load peer section schedules.");
                    $("#sectionScheduleMatrixContainer").html(`<div class="section-matrix-empty">${escapeHtml((res && res.message) ? res.message : "Failed to load section matrix.")}</div>`);
                    return;
                }

                const sections = Array.isArray(res.sections) ? res.sections.map(section => ({
                    section_id: parseInt(section?.section_id, 10) || 0,
                    label: String(section?.label || ""),
                    entry_count: parseInt(section?.entry_count, 10) || 0,
                    is_current: Boolean(section?.is_current),
                    entries: Array.isArray(section?.entries) ? section.entries.map(normalizeSectionMatrixEntry) : []
                })) : [];

                sectionScheduleMatrixState.subjectCode = String(res.subject_code || "");
                sectionScheduleMatrixState.sections = sections;
                sectionScheduleMatrixState.selectedDay = getSectionMatrixDefaultDay(sections);
                renderSectionScheduleMatrix();
            },
            error: function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                $("#sectionScheduleMatrixHeaderNote").text("Unable to load peer section schedules.");
                $("#sectionScheduleMatrixContainer").html('<div class="section-matrix-empty">Failed to load section matrix.</div>');
            },
            complete: function () {
                sectionScheduleMatrixRequest = null;
            }
        });
    }

    function createScheduleBlockFacultyHelperState() {
        return {
            options: [],
            assignedFacultyIds: [],
            selectedFacultyId: 0,
            selectedFacultySchedule: null,
            optionsLoading: false,
            detailLoading: false,
            optionError: "",
            detailError: ""
        };
    }

    function normalizeFacultyScheduleOption(option) {
        return {
            faculty_id: parseInt(option?.faculty_id, 10) || 0,
            faculty_name: String(option?.faculty_name || "").trim(),
            scheduled_block_count: parseInt(option?.scheduled_block_count, 10) || 0,
            scheduled_class_count: parseInt(option?.scheduled_class_count, 10) || 0,
            is_assigned: Boolean(option?.is_assigned)
        };
    }

    function normalizeFacultyScheduleEntry(entry) {
        const roomLabel = String(entry?.room_label || entry?.room || "").trim();
        const sectionLabel = String(entry?.section_label || entry?.section || "").trim();

        return {
            offering_id: parseInt(entry?.offering_id, 10) || 0,
            schedule_id: parseInt(entry?.schedule_id, 10) || 0,
            schedule_type: String(entry?.schedule_type || entry?.type || "LEC").toUpperCase() === "LAB" ? "LAB" : "LEC",
            time_start: normalizeMatrixTimeInput(entry?.time_start || ""),
            time_end: normalizeMatrixTimeInput(entry?.time_end || ""),
            days: normalizeScheduleDays(Array.isArray(entry?.days) ? entry.days : []),
            subject_code: String(entry?.subject_code || entry?.sub_code || ""),
            subject_description: String(entry?.subject_description || entry?.sub_description || ""),
            section_label: sectionLabel || "Section",
            room_label: roomLabel || "TBA",
            owner_faculty_names: Array.isArray(entry?.owner_faculty_names)
                ? entry.owner_faculty_names.map(name => String(name || "").trim()).filter(Boolean)
                : [],
            preview_label: String(entry?.preview_label || "").trim(),
            preview_status_note: String(entry?.preview_status_note || "").trim(),
            preview_conflict_types: Array.isArray(entry?.preview_conflict_types)
                ? entry.preview_conflict_types.map(item => String(item || "").trim()).filter(Boolean)
                : [],
            preview_conflict_details: Array.isArray(entry?.preview_conflict_details)
                ? entry.preview_conflict_details.map(item => String(item || "").trim()).filter(Boolean)
                : [],
            is_current_offering: Boolean(entry?.is_current_offering),
            is_other_faculty_assignment: Boolean(entry?.is_other_faculty_assignment),
            is_preview_block: Boolean(entry?.is_preview_block),
            is_preview_conflict: Boolean(entry?.is_preview_conflict)
        };
    }

    function abortScheduleBlockFacultyRequests() {
        if (scheduleBlockFacultyOptionsRequest && scheduleBlockFacultyOptionsRequest.readyState !== 4) {
            scheduleBlockFacultyOptionsRequest.abort();
        }

        if (scheduleBlockFacultyDetailRequest && scheduleBlockFacultyDetailRequest.readyState !== 4) {
            scheduleBlockFacultyDetailRequest.abort();
        }

        scheduleBlockFacultyOptionsRequest = null;
        scheduleBlockFacultyDetailRequest = null;

        if (scheduleBlockFacultyPreviewTimer) {
            clearTimeout(scheduleBlockFacultyPreviewTimer);
            scheduleBlockFacultyPreviewTimer = null;
        }
    }

    function getSelectedScheduleBlockFacultyOption() {
        const helper = scheduleBlockState?.facultyHelper;
        if (!helper || !Array.isArray(helper.options)) {
            return null;
        }

        return helper.options.find(option => option.faculty_id === (parseInt(helper.selectedFacultyId, 10) || 0)) || null;
    }

    function formatFacultyAvailabilityRanges(ranges, windowStart, windowEnd) {
        if (!Array.isArray(ranges) || ranges.length === 0) {
            return "Fully booked";
        }

        if (
            ranges.length === 1
            && ranges[0].start === windowStart
            && ranges[0].end === windowEnd
        ) {
            return "Open all day";
        }

        const preview = ranges.slice(0, 2).map(range => (
            `${formatPolicyTimeLabel(matrixMinutesToTime(range.start))} - ${formatPolicyTimeLabel(matrixMinutesToTime(range.end))}`
        ));

        if (ranges.length > 2) {
            preview.push(`+${ranges.length - 2} more`);
        }

        return preview.join(" | ");
    }

    function buildFacultyAvailabilitySnapshot(entries) {
        const windowStart = matrixTimeToMinutes(SUPPORTED_TIME_START);
        const windowEnd = matrixTimeToMinutes(SUPPORTED_TIME_END);
        if (windowStart === null || windowEnd === null || windowEnd <= windowStart) {
            return [];
        }

        return SCHEDULE_DAY_ORDER.map(day => {
            const dayEntries = (Array.isArray(entries) ? entries : [])
                .map(entry => {
                    const start = matrixTimeToMinutes(entry?.time_start);
                    const end = matrixTimeToMinutes(entry?.time_end);

                    return {
                        start,
                        end,
                        days: Array.isArray(entry?.days) ? entry.days : []
                    };
                })
                .filter(entry => Array.isArray(entry.days)
                    && entry.days.includes(day)
                    && entry.start !== null
                    && entry.end !== null
                    && entry.end > entry.start)
                .sort((left, right) => left.start - right.start);

            const occupied = [];
            dayEntries.forEach(entry => {
                const interval = {
                    start: Math.max(windowStart, entry.start),
                    end: Math.min(windowEnd, entry.end)
                };

                if (interval.end <= interval.start) {
                    return;
                }

                const last = occupied[occupied.length - 1];
                if (!last || interval.start > last.end) {
                    occupied.push(interval);
                    return;
                }

                last.end = Math.max(last.end, interval.end);
            });

            const freeRanges = [];
            let cursor = windowStart;
            occupied.forEach(interval => {
                if (interval.start > cursor) {
                    freeRanges.push({ start: cursor, end: interval.start });
                }
                cursor = Math.max(cursor, interval.end);
            });

            if (cursor < windowEnd) {
                freeRanges.push({ start: cursor, end: windowEnd });
            }

            return {
                day,
                free_ranges: freeRanges,
                summary: formatFacultyAvailabilityRanges(freeRanges, windowStart, windowEnd),
                occupied_count: dayEntries.length
            };
        });
    }

    function renderScheduleBlockFacultyOptions() {
        const select = $("#blockScheduleFacultySelect");
        const hint = $("#blockScheduleFacultyHint");
        const matrixButton = $("#btnOpenFacultyScheduleMatrix");
        const helper = scheduleBlockState?.facultyHelper;

        if (!helper) {
            select.html('<option value="">Select faculty...</option>').prop("disabled", true);
            hint.text("Faculty from the selected college term will load here.");
            matrixButton.prop("disabled", true);
            return;
        }

        let optionsHtml = '<option value="">Select faculty...</option>';
        if (helper.optionsLoading && helper.options.length === 0) {
            optionsHtml = '<option value="">Loading faculty...</option>';
        } else if (helper.options.length > 0) {
            optionsHtml += helper.options.map(option => {
                const meta = [];
                if (option.is_assigned) {
                    meta.push("assigned here");
                }

                if (option.scheduled_block_count > 0) {
                    meta.push(`${option.scheduled_block_count} block${option.scheduled_block_count === 1 ? "" : "s"}`);
                } else {
                    meta.push("no workload blocks");
                }

                const label = `${option.faculty_name}${meta.length > 0 ? " - " + meta.join(", ") : ""}`;
                return `<option value="${escapeHtml(String(option.faculty_id))}">${escapeHtml(label)}</option>`;
            }).join("");
        } else if (helper.optionError) {
            optionsHtml = '<option value="">Unable to load faculty</option>';
        } else {
            optionsHtml = '<option value="">No faculty found for this term</option>';
        }

        select.html(optionsHtml);
        select.val(helper.selectedFacultyId ? String(helper.selectedFacultyId) : "");
        select.prop("disabled", helper.optionsLoading || helper.options.length === 0);

        const assignedNames = helper.options
            .filter(option => option.is_assigned)
            .map(option => option.faculty_name);

        if (helper.optionsLoading && helper.options.length === 0) {
            hint.text("Loading active faculty for this college term...");
        } else if (helper.optionError) {
            hint.text(helper.optionError);
        } else if (helper.options.length === 0) {
            hint.text("No active faculty were found for this college term.");
        } else if (assignedNames.length > 0) {
            hint.text(`Currently assigned in Faculty Workload: ${assignedNames.join(", ")}. Pick a faculty, then click Show Workload.`);
        } else {
            hint.text("Select a faculty, then click Show Workload to open the schedule board.");
        }

        matrixButton.prop("disabled", !helper.selectedFacultyId || helper.detailLoading);
    }

    function renderScheduleBlockFacultySummary() {
        const container = $("#blockScheduleFacultySummary");
        const helper = scheduleBlockState?.facultyHelper;
        if (!helper || container.length === 0) {
            return;
        }

        const selectedOption = getSelectedScheduleBlockFacultyOption();
        if (helper.optionsLoading && helper.options.length === 0) {
            container.html('<div class="faculty-helper-empty">Loading faculty availability helper...</div>');
            return;
        }

        if (helper.detailLoading && helper.selectedFacultyId) {
            const facultyName = selectedOption?.faculty_name || "Selected faculty";
            container.html(`<div class="faculty-helper-empty">Loading the current schedule for <strong>${escapeHtml(facultyName)}</strong>...</div>`);
            return;
        }

        if (helper.detailError) {
            container.html(`<div class="faculty-helper-empty">${escapeHtml(helper.detailError)}</div>`);
            return;
        }

        const schedule = helper.selectedFacultySchedule;
        if (!schedule || !schedule.faculty_id) {
            container.html('<div class="faculty-helper-empty">Select a faculty to preview scheduled subjects for this term.</div>');
            return;
        }

        const entries = Array.isArray(schedule.entries) ? schedule.entries.slice() : [];
        const availability = buildFacultyAvailabilitySnapshot(entries);
        const openDays = availability.filter(item => Array.isArray(item.free_ranges) && item.free_ranges.length > 0).length;
        const sortedEntries = entries.slice().sort((left, right) => {
            const leftDayIndex = SCHEDULE_DAY_ORDER.findIndex(day => Array.isArray(left.days) && left.days.includes(day));
            const rightDayIndex = SCHEDULE_DAY_ORDER.findIndex(day => Array.isArray(right.days) && right.days.includes(day));
            if (leftDayIndex !== rightDayIndex) {
                return (leftDayIndex === -1 ? 999 : leftDayIndex) - (rightDayIndex === -1 ? 999 : rightDayIndex);
            }

            const leftStart = matrixTimeToMinutes(left?.time_start);
            const rightStart = matrixTimeToMinutes(right?.time_start);
            if (leftStart !== rightStart) {
                return (leftStart ?? 0) - (rightStart ?? 0);
            }

            return String(left?.subject_code || "").localeCompare(String(right?.subject_code || ""));
        });

        const previewEntries = sortedEntries.slice(0, 6);
        const entryCards = previewEntries.length > 0
            ? previewEntries.map(entry => `
                <div class="faculty-helper-subject-card">
                    <div class="faculty-helper-subject-title">
                        <span>${escapeHtml(entry.subject_code || "TBA")}</span>
                        <span class="suggestion-chip">${escapeHtml(entry.schedule_type || "LEC")}${entry.is_current_offering ? " | Current" : ""}</span>
                    </div>
                    <div class="faculty-helper-subject-meta">
                        <div>${escapeHtml(entry.section_label || "Section")}</div>
                        <div>${escapeHtml((entry.days || []).join(""))} | ${escapeHtml(formatPolicyTimeLabel(entry.time_start))} - ${escapeHtml(formatPolicyTimeLabel(entry.time_end))}</div>
                        <div>${escapeHtml(entry.room_label || "TBA")}</div>
                    </div>
                </div>
            `).join("")
            : '<div class="faculty-helper-empty">No scheduled workload blocks yet. This faculty appears open within the current scheduling window.</div>';

        const availabilityCards = availability.length > 0
            ? availability.map(item => `
                <div class="faculty-helper-day-card">
                    <span class="faculty-helper-day-label">${escapeHtml(item.day)}</span>
                    <span class="faculty-helper-day-value">${escapeHtml(item.summary)}</span>
                    <span class="faculty-helper-day-meta">
                        ${item.occupied_count > 0
                            ? `${escapeHtml(String(item.occupied_count))} scheduled block(s)`
                            : `Open for ${escapeHtml(scheduleWindowLabel())}`}
                    </span>
                </div>
            `).join("")
            : '<div class="faculty-helper-empty">Availability could not be calculated for the current schedule window.</div>';

        const footerNote = sortedEntries.length > previewEntries.length
            ? `<div class="faculty-helper-note">Showing ${escapeHtml(String(previewEntries.length))} of ${escapeHtml(String(sortedEntries.length))} scheduled block(s). Use Show Workload for the complete schedule board.</div>`
            : "";
        container.html(`
            <div class="faculty-helper-title-row">
                <div>
                    <div class="faculty-helper-title">${escapeHtml(schedule.faculty_name || "Faculty")}</div>
                    <div class="faculty-helper-note">
                        Selected faculty workload for the current college term. Use Show Workload to see warning-only red awareness blocks for time slots already occupied by other faculty.
                    </div>
                </div>
                ${schedule.is_assigned
                    ? '<span class="badge bg-label-info text-info">Assigned To Current Subject</span>'
                    : ''}
            </div>
            <div class="faculty-helper-metrics">
                <span class="faculty-helper-metric">${escapeHtml(String(schedule.scheduled_block_count || entries.length))} block(s)</span>
                <span class="faculty-helper-metric">${escapeHtml(String(schedule.scheduled_class_count || 0))} class(es)</span>
                <span class="faculty-helper-metric">${escapeHtml(String(openDays))} day(s) with free time</span>
            </div>
            <div class="faculty-helper-section-title">Availability Snapshot</div>
            <div class="faculty-helper-day-grid">${availabilityCards}</div>
            <div class="faculty-helper-section-title">Scheduled Subjects</div>
            <div class="faculty-helper-subject-list">${entryCards}</div>
            ${footerNote}
        `);
    }

    function loadScheduleBlockFacultyOptions(offeringId) {
        if (!scheduleBlockState || !scheduleBlockState.facultyHelper || !offeringId) {
            return;
        }

        const helper = scheduleBlockState.facultyHelper;
        helper.optionsLoading = true;
        helper.optionError = "";
        helper.detailError = "";
        helper.options = [];
        helper.assignedFacultyIds = [];
        helper.selectedFacultyId = 0;
        helper.selectedFacultySchedule = null;
        renderScheduleBlockFacultyOptions();
        renderScheduleBlockFacultySummary();

        if (scheduleBlockFacultyOptionsRequest && scheduleBlockFacultyOptionsRequest.readyState !== 4) {
            scheduleBlockFacultyOptionsRequest.abort();
        }

        scheduleBlockFacultyOptionsRequest = $.ajax({
            url: "../backend/query_class_schedule.php",
            type: "POST",
            dataType: "json",
            data: {
                load_schedule_faculty_options: 1,
                offering_id: offeringId
            },
            success: function (res) {
                if (!scheduleBlockState || !scheduleBlockState.facultyHelper || scheduleBlockState.offeringId !== offeringId) {
                    return;
                }

                if (!res || res.status !== "ok") {
                    helper.optionsLoading = false;
                    helper.optionError = (res && res.message) ? String(res.message) : "Unable to load faculty for this term.";
                    renderScheduleBlockFacultyOptions();
                    renderScheduleBlockFacultySummary();
                    return;
                }

                const options = Array.isArray(res.faculty) ? res.faculty.map(normalizeFacultyScheduleOption) : [];
                helper.options = options;
                helper.assignedFacultyIds = Array.isArray(res.assigned_faculty_ids)
                    ? res.assigned_faculty_ids.map(value => parseInt(value, 10) || 0).filter(value => value > 0)
                    : [];
                helper.optionsLoading = false;
                helper.optionError = "";

                const assignedOption = options.find(option => option.is_assigned);
                helper.selectedFacultyId = assignedOption ? assignedOption.faculty_id : 0;

                renderScheduleBlockFacultyOptions();
                renderScheduleBlockFacultySummary();

                if (helper.selectedFacultyId > 0) {
                    loadScheduleBlockFacultyDetails(helper.selectedFacultyId);
                }
            },
            error: function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                if (!scheduleBlockState || !scheduleBlockState.facultyHelper || scheduleBlockState.offeringId !== offeringId) {
                    return;
                }

                helper.optionsLoading = false;
                helper.optionError = xhr.responseText || "Unable to load faculty for this term.";
                renderScheduleBlockFacultyOptions();
                renderScheduleBlockFacultySummary();
            },
            complete: function () {
                scheduleBlockFacultyOptionsRequest = null;
            }
        });
    }

    function loadScheduleBlockFacultyDetails(facultyId, options = {}) {
        if (!scheduleBlockState || !scheduleBlockState.facultyHelper || !scheduleBlockState.offeringId) {
            return;
        }

        const helper = scheduleBlockState.facultyHelper;
        const normalizedFacultyId = parseInt(facultyId, 10) || 0;
        const shouldOpenMatrix = Boolean(options.openMatrix);
        const shouldForceReload = Boolean(options.forceReload);

        helper.selectedFacultyId = normalizedFacultyId;
        helper.detailError = "";

        if (!normalizedFacultyId) {
            helper.detailLoading = false;
            helper.selectedFacultySchedule = null;
            renderScheduleBlockFacultyOptions();
            renderScheduleBlockFacultySummary();
            return;
        }

        if (
            !shouldForceReload
            && helper.selectedFacultySchedule
            && helper.selectedFacultySchedule.faculty_id === normalizedFacultyId
            && !helper.detailLoading
        ) {
            renderScheduleBlockFacultyOptions();
            renderScheduleBlockFacultySummary();
            if (shouldOpenMatrix) {
                renderFacultyScheduleMatrix();
                if (facultyScheduleMatrixModalInstance) {
                    facultyScheduleMatrixModalInstance.show();
                } else {
                    $("#facultyScheduleMatrixModal").modal("show");
                }
            }
            return;
        }

        if (scheduleBlockFacultyDetailRequest && scheduleBlockFacultyDetailRequest.readyState !== 4) {
            scheduleBlockFacultyDetailRequest.abort();
        }

        helper.detailLoading = true;
        helper.selectedFacultySchedule = null;
        renderScheduleBlockFacultyOptions();
        renderScheduleBlockFacultySummary();

        scheduleBlockFacultyDetailRequest = $.ajax({
            url: "../backend/query_class_schedule.php",
            type: "POST",
            dataType: "json",
            data: {
                load_faculty_schedule_overview: 1,
                offering_id: scheduleBlockState.offeringId,
                faculty_id: normalizedFacultyId
            },
            success: function (res) {
                if (!scheduleBlockState || !scheduleBlockState.facultyHelper || helper.selectedFacultyId !== normalizedFacultyId) {
                    return;
                }

                if (!res || res.status !== "ok") {
                    helper.detailLoading = false;
                    helper.detailError = (res && res.message) ? String(res.message) : "Unable to load the selected faculty schedule.";
                    renderScheduleBlockFacultyOptions();
                    renderScheduleBlockFacultySummary();
                    if ($("#facultyScheduleMatrixModal").hasClass("show")) {
                        renderFacultyScheduleMatrix();
                    }
                    return;
                }

                const facultySchedule = normalizeFacultyScheduleOption(res.faculty || {});
                facultySchedule.entries = Array.isArray(res.entries) ? res.entries.map(normalizeFacultyScheduleEntry) : [];
                facultySchedule.other_assigned_entries = Array.isArray(res.other_assigned_entries)
                    ? res.other_assigned_entries.map(normalizeFacultyScheduleEntry)
                    : [];
                facultySchedule.draft_entries = Array.isArray(res.draft_entries)
                    ? res.draft_entries.map(normalizeFacultyScheduleEntry)
                    : [];
                facultySchedule.preview_issues = Array.isArray(res.preview_issues)
                    ? res.preview_issues.map(item => String(item || "").trim()).filter(Boolean)
                    : [];
                facultySchedule.draft_block_count = parseInt(res?.draft_block_count, 10) || facultySchedule.draft_entries.length;
                facultySchedule.draft_conflict_count = parseInt(res?.draft_conflict_count, 10) || 0;
                facultySchedule.draft_ready_count = parseInt(res?.draft_ready_count, 10) || 0;
                helper.selectedFacultySchedule = facultySchedule;
                helper.detailLoading = false;
                helper.detailError = "";
                helper.options = helper.options.map(option => (
                    option.faculty_id === facultySchedule.faculty_id
                        ? { ...option, ...facultySchedule }
                        : option
                ));

                renderScheduleBlockFacultyOptions();
                renderScheduleBlockFacultySummary();

                if ($("#facultyScheduleMatrixModal").hasClass("show") || shouldOpenMatrix) {
                    renderFacultyScheduleMatrix();
                }

                if (shouldOpenMatrix) {
                    if (facultyScheduleMatrixModalInstance) {
                        facultyScheduleMatrixModalInstance.show();
                    } else {
                        $("#facultyScheduleMatrixModal").modal("show");
                    }
                }
            },
            error: function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                if (!scheduleBlockState || !scheduleBlockState.facultyHelper || helper.selectedFacultyId !== normalizedFacultyId) {
                    return;
                }

                helper.detailLoading = false;
                helper.detailError = xhr.responseText || "Unable to load the selected faculty schedule.";
                renderScheduleBlockFacultyOptions();
                renderScheduleBlockFacultySummary();
                if ($("#facultyScheduleMatrixModal").hasClass("show")) {
                    renderFacultyScheduleMatrix();
                }
            },
            complete: function () {
                scheduleBlockFacultyDetailRequest = null;
            }
        });
    }

    function refreshFacultyAwarenessModalIfOpen() {
        if ($("#facultyScheduleMatrixModal").hasClass("show")) {
            renderFacultyScheduleMatrix();
        }
    }

    function applyScheduleBlockSuggestion(blockKey, roomId, timeStart, timeEnd, days) {
        if (!scheduleBlockState) {
            return;
        }

        const block = scheduleBlockState.blocks.find(item => item.block_key === blockKey);
        if (!block) {
            return;
        }

        block.room_id = String(roomId || "");
        block.time_start = String(timeStart || "");
        block.time_end = String(timeEnd || "");
        block.days = Array.isArray(days) ? days.filter(day => SCHEDULE_DAY_ORDER.includes(day)) : [];
        block.suggestionsVisible = false;
        renderScheduleBlocks();
        refreshFacultyAwarenessModalIfOpen();
    }

    function queueScheduleBlockFacultyPreviewRefresh() {
        if (!scheduleBlockState || !scheduleBlockState.facultyHelper) {
            return;
        }

        const selectedFacultyId = parseInt(scheduleBlockState.facultyHelper.selectedFacultyId, 10) || 0;
        if (!selectedFacultyId) {
            return;
        }

        if (scheduleBlockFacultyPreviewTimer) {
            clearTimeout(scheduleBlockFacultyPreviewTimer);
        }

        scheduleBlockFacultyPreviewTimer = window.setTimeout(function () {
            scheduleBlockFacultyPreviewTimer = null;

            if (!scheduleBlockState || !$("#blockScheduleModal").hasClass("show")) {
                return;
            }

            loadScheduleBlockFacultyDetails(selectedFacultyId, {
                forceReload: true,
                openMatrix: $("#facultyScheduleMatrixModal").hasClass("show")
            });
        }, 260);
    }

    function sortFacultyScheduleBoardEntries(entries) {
        return (Array.isArray(entries) ? entries.slice() : []).sort((left, right) => {
            const leftDayIndex = SCHEDULE_DAY_ORDER.findIndex(day => Array.isArray(left?.days) && left.days.includes(day));
            const rightDayIndex = SCHEDULE_DAY_ORDER.findIndex(day => Array.isArray(right?.days) && right.days.includes(day));
            if (leftDayIndex !== rightDayIndex) {
                return (leftDayIndex === -1 ? 999 : leftDayIndex) - (rightDayIndex === -1 ? 999 : rightDayIndex);
            }

            const leftStart = matrixTimeToMinutes(left?.time_start);
            const rightStart = matrixTimeToMinutes(right?.time_start);
            if (leftStart !== rightStart) {
                return (leftStart ?? 0) - (rightStart ?? 0);
            }

            const leftPriority = left?.is_other_faculty_assignment ? 0 : (left?.is_current_offering ? 1 : 2);
            const rightPriority = right?.is_other_faculty_assignment ? 0 : (right?.is_current_offering ? 1 : 2);
            if (leftPriority !== rightPriority) {
                return leftPriority - rightPriority;
            }

            return String(left?.subject_code || "").localeCompare(String(right?.subject_code || ""));
        });
    }

    function buildFacultyScheduleEntryKey(entry) {
        const scheduleId = parseInt(entry?.schedule_id, 10) || 0;
        if (scheduleId > 0) {
            return `id:${scheduleId}`;
        }

        const days = normalizeScheduleDays(Array.isArray(entry?.days) ? entry.days : []).join("");
        return [
            String(entry?.subject_code || "").trim(),
            String(entry?.section_label || "").trim(),
            String(entry?.room_label || "").trim(),
            String(entry?.time_start || "").trim(),
            String(entry?.time_end || "").trim(),
            days
        ].join("|");
    }

    function formatFacultySchedulePreviewList(values, limit = 2) {
        const uniqueValues = Array.from(new Set(
            (Array.isArray(values) ? values : [])
                .map(value => String(value || "").trim())
                .filter(Boolean)
        ));

        if (uniqueValues.length === 0) {
            return "";
        }

        if (uniqueValues.length <= limit) {
            return uniqueValues.join(", ");
        }

        return `${uniqueValues.slice(0, limit).join(", ")} +${uniqueValues.length - limit} more`;
    }

    function buildFacultyScheduleBoard(entries) {
        const startMinutes = matrixTimeToMinutes(SUPPORTED_TIME_START);
        const endMinutes = matrixTimeToMinutes(SUPPORTED_TIME_END);
        const slots = [];
        const occupancy = {};
        const warnings = [];

        if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
            return {
                slots,
                occupancy,
                warnings
            };
        }

        for (let minutes = startMinutes; minutes < endMinutes; minutes += FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES) {
            slots.push(minutes);
        }

        FACULTY_SCHEDULE_DAY_COLUMNS.forEach(day => {
            occupancy[day.key] = {};
        });

        sortFacultyScheduleBoardEntries(entries).forEach(entry => {
            let entryStart = matrixTimeToMinutes(entry?.time_start);
            let entryEnd = matrixTimeToMinutes(entry?.time_end);
            const days = normalizeScheduleDays(Array.isArray(entry?.days) ? entry.days : []);

            if (entryStart === null || entryEnd === null) {
                return;
            }

            entryStart = Math.max(startMinutes, entryStart);
            entryEnd = Math.min(endMinutes, entryEnd);
            if (!days.length || entryEnd <= entryStart) {
                return;
            }

            let hasConflict = false;
            days.forEach(dayKey => {
                for (let cursor = entryStart; cursor < entryEnd; cursor += FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES) {
                    if (occupancy[dayKey]?.[cursor]) {
                        hasConflict = true;
                        return;
                    }
                }
            });

            if (hasConflict) {
                const subjectCode = String(entry?.subject_code || "Scheduled class").trim() || "Scheduled class";
                const ownershipLabel = entry?.is_preview_block
                    ? "draft preview block"
                    : (entry?.is_other_faculty_assignment
                        ? "assigned to another faculty"
                        : (entry?.is_current_offering ? "current subject block" : "selected faculty schedule"));
                warnings.push(`${subjectCode} (${ownershipLabel}) overlaps another block and was skipped in the board view.`);
                return;
            }

            const block = {
                ...entry,
                _slotSpan: Math.max(1, Math.ceil((entryEnd - entryStart) / FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES))
            };

            days.forEach(dayKey => {
                occupancy[dayKey][entryStart] = {
                    type: "start",
                    block
                };

                for (let cursor = entryStart + FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES; cursor < entryEnd; cursor += FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES) {
                    occupancy[dayKey][cursor] = {
                        type: "covered",
                        start: entryStart
                    };
                }
            });
        });

        return {
            slots,
            occupancy,
            warnings: warnings.filter((warning, index, list) => list.indexOf(warning) === index)
        };
    }

    function buildOtherFacultyScheduleBoard(entries, baseSlots = []) {
        const startMinutes = matrixTimeToMinutes(SUPPORTED_TIME_START);
        const endMinutes = matrixTimeToMinutes(SUPPORTED_TIME_END);
        const slots = Array.isArray(baseSlots) && baseSlots.length > 0
            ? baseSlots.slice()
            : [];
        const slotOccupancy = {};
        const occupancy = {};

        if (slots.length === 0 && startMinutes !== null && endMinutes !== null && endMinutes > startMinutes) {
            for (let minutes = startMinutes; minutes < endMinutes; minutes += FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES) {
                slots.push(minutes);
            }
        }

        FACULTY_SCHEDULE_DAY_COLUMNS.forEach(day => {
            slotOccupancy[day.key] = {};
            occupancy[day.key] = {};
        });

        (Array.isArray(entries) ? entries : []).forEach(entry => {
            let entryStart = matrixTimeToMinutes(entry?.time_start);
            let entryEnd = matrixTimeToMinutes(entry?.time_end);
            const days = normalizeScheduleDays(Array.isArray(entry?.days) ? entry.days : []);

            if (entryStart === null || entryEnd === null) {
                return;
            }

            entryStart = Math.max(startMinutes ?? entryStart, entryStart);
            entryEnd = Math.min(endMinutes ?? entryEnd, entryEnd);
            if (!days.length || entryEnd <= entryStart) {
                return;
            }

            days.forEach(dayKey => {
                for (let cursor = entryStart; cursor < entryEnd; cursor += FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES) {
                    if (!slotOccupancy[dayKey][cursor]) {
                        slotOccupancy[dayKey][cursor] = [];
                    }
                    slotOccupancy[dayKey][cursor].push(entry);
                }
            });
        });

        FACULTY_SCHEDULE_DAY_COLUMNS.forEach(day => {
            let slotIndex = 0;
            while (slotIndex < slots.length) {
                const slotStart = slots[slotIndex];
                const rawEntries = Array.isArray(slotOccupancy[day.key][slotStart]) ? slotOccupancy[day.key][slotStart] : [];
                const entriesAtSlot = [];
                const seenKeys = new Set();

                rawEntries.forEach(item => {
                    const key = buildFacultyScheduleEntryKey(item);
                    if (!seenKeys.has(key)) {
                        seenKeys.add(key);
                        entriesAtSlot.push(item);
                    }
                });

                const signature = entriesAtSlot
                    .map(item => buildFacultyScheduleEntryKey(item))
                    .sort()
                    .join("||");

                if (!signature) {
                    slotIndex += 1;
                    continue;
                }

                let span = 1;
                while ((slotIndex + span) < slots.length) {
                    const nextRawEntries = Array.isArray(slotOccupancy[day.key][slots[slotIndex + span]])
                        ? slotOccupancy[day.key][slots[slotIndex + span]]
                        : [];
                    const nextEntries = [];
                    const nextSeenKeys = new Set();

                    nextRawEntries.forEach(item => {
                        const key = buildFacultyScheduleEntryKey(item);
                        if (!nextSeenKeys.has(key)) {
                            nextSeenKeys.add(key);
                            nextEntries.push(item);
                        }
                    });

                    const nextSignature = nextEntries
                        .map(item => buildFacultyScheduleEntryKey(item))
                        .sort()
                        .join("||");

                    if (nextSignature !== signature) {
                        break;
                    }

                    span += 1;
                }

                const ownerNames = Array.from(new Set(entriesAtSlot.flatMap(item => (
                    Array.isArray(item?.owner_faculty_names) ? item.owner_faculty_names : []
                )))).filter(Boolean);
                const subjectCodes = Array.from(new Set(entriesAtSlot
                    .map(item => String(item?.subject_code || "").trim())
                    .filter(Boolean)));

                let block;
                if (entriesAtSlot.length === 1) {
                    block = {
                        ...entriesAtSlot[0],
                        owner_faculty_names: ownerNames,
                        occupied_entry_count: 1,
                        entries: entriesAtSlot.slice(),
                        _slotSpan: span
                    };
                } else {
                    block = {
                        is_other_faculty_assignment: true,
                        is_current_offering: entriesAtSlot.some(item => item?.is_current_offering),
                        subject_code: "Occupied Time Block",
                        subject_description: `${entriesAtSlot.length} other schedule${entriesAtSlot.length === 1 ? "" : "s"} already assigned`,
                        section_label: `${ownerNames.length || entriesAtSlot.length} faculty workload entr${(ownerNames.length || entriesAtSlot.length) === 1 ? "y" : "ies"}`,
                        room_label: formatFacultySchedulePreviewList(subjectCodes, 2) || "Current college term workload",
                        owner_faculty_names: ownerNames,
                        owner_faculty_preview: formatFacultySchedulePreviewList(ownerNames, 2),
                        occupied_entry_count: entriesAtSlot.length,
                        entries: entriesAtSlot.slice(),
                        _slotSpan: span
                    };
                }

                occupancy[day.key][slotStart] = {
                    type: "start",
                    block
                };

                for (let offset = 1; offset < span; offset += 1) {
                    occupancy[day.key][slots[slotIndex + offset]] = {
                        type: "covered",
                        start: slotStart
                    };
                }

                slotIndex += span;
            }
        });

        return {
            slots,
            occupancy
        };
    }

    function countFacultyScheduleBoardBlocks(board) {
        let total = 0;
        FACULTY_SCHEDULE_DAY_COLUMNS.forEach(day => {
            const dayOccupancy = board?.occupancy?.[day.key] || {};
            Object.keys(dayOccupancy).forEach(slotKey => {
                if (dayOccupancy[slotKey]?.type === "start") {
                    total += 1;
                }
            });
        });
        return total;
    }

    function formatFacultyScheduleDaysLabel(days) {
        return normalizeScheduleDays(days).map(day => {
            const match = FACULTY_SCHEDULE_DAY_COLUMNS.find(item => item.key === day);
            return match ? match.label : day;
        }).join(" / ");
    }

    function getScheduleBlockDurationMinutes(block) {
        const start = matrixTimeToMinutes(block?.time_start);
        const end = matrixTimeToMinutes(block?.time_end);
        if (start === null || end === null || end <= start) {
            return 0;
        }

        return end - start;
    }

    function buildFacultyScheduleIntervalMap(entries) {
        const map = {};
        FACULTY_SCHEDULE_DAY_COLUMNS.forEach(day => {
            map[day.key] = [];
        });

        (Array.isArray(entries) ? entries : []).forEach(entry => {
            const start = matrixTimeToMinutes(entry?.time_start);
            const end = matrixTimeToMinutes(entry?.time_end);
            const days = normalizeScheduleDays(Array.isArray(entry?.days) ? entry.days : []);
            if (start === null || end === null || end <= start || days.length === 0) {
                return;
            }

            const key = buildFacultyScheduleEntryKey(entry);
            days.forEach(day => {
                map[day].push({
                    key,
                    start,
                    end
                });
            });
        });

        Object.keys(map).forEach(day => {
            map[day].sort((left, right) => left.start - right.start);
        });

        return map;
    }

    function countFacultyScheduleIntervalOverlap(intervalMap, days, start, end) {
        const keys = new Set();
        normalizeScheduleDays(days).forEach(day => {
            (intervalMap?.[day] || []).forEach(interval => {
                if (start < interval.end && end > interval.start) {
                    keys.add(interval.key);
                }
            });
        });
        return keys.size;
    }

    function buildFacultyScheduleDraftEntries(excludedBlockKey = "") {
        if (!scheduleBlockState || !Array.isArray(scheduleBlockState.blocks)) {
            return [];
        }

        const indexMap = { LEC: 0, LAB: 0 };
        return scheduleBlockState.blocks
            .filter(block => String(block?.block_key || "") !== String(excludedBlockKey || ""))
            .map(block => ({
                schedule_id: 0,
                subject_code: getScheduleBlockLabel(block, indexMap),
                section_label: "Current draft",
                room_label: String(block?.room_id || "").trim() ? "Selected room" : "Room not chosen",
                time_start: String(block?.time_start || ""),
                time_end: String(block?.time_end || ""),
                days: normalizeScheduleDays(Array.isArray(block?.days) ? block.days : [])
            }))
            .filter(entry => entry.time_start && entry.time_end && entry.days.length > 0);
    }

    function buildFacultySuggestionDayPatterns(meetingCount, preferredDays = []) {
        const availableDays = SCHEDULE_DAY_ORDER.filter(day => !BLOCKED_SCHEDULE_DAY_SET.has(day));
        const normalizedCount = Math.max(1, Math.min(availableDays.length, parseInt(meetingCount, 10) || 1));
        const preferred = normalizeScheduleDays(preferredDays);
        const patterns = [];

        function walk(startIndex, picked) {
            if (picked.length === normalizedCount) {
                patterns.push(picked.slice());
                return;
            }

            for (let index = startIndex; index < availableDays.length; index += 1) {
                picked.push(availableDays[index]);
                walk(index + 1, picked);
                picked.pop();
            }
        }

        walk(0, []);

        return patterns.sort((left, right) => {
            const leftDistance = left.reduce((total, day, index) => {
                if (preferred.length === 0) {
                    return total;
                }

                const preferredDay = preferred[Math.min(index, preferred.length - 1)];
                return total + Math.abs(SCHEDULE_DAY_ORDER.indexOf(day) - SCHEDULE_DAY_ORDER.indexOf(preferredDay));
            }, 0);
            const rightDistance = right.reduce((total, day, index) => {
                if (preferred.length === 0) {
                    return total;
                }

                const preferredDay = preferred[Math.min(index, preferred.length - 1)];
                return total + Math.abs(SCHEDULE_DAY_ORDER.indexOf(day) - SCHEDULE_DAY_ORDER.indexOf(preferredDay));
            }, 0);

            if (leftDistance !== rightDistance) {
                return leftDistance - rightDistance;
            }

            return left.join("").localeCompare(right.join(""));
        });
    }

    function estimateFacultySuggestionMeetingCount(block) {
        const selectedDays = normalizeScheduleDays(Array.isArray(block?.days) ? block.days : []);
        if (selectedDays.length > 0) {
            return selectedDays.length;
        }

        const durationMinutes = getScheduleBlockDurationMinutes(block);
        if (durationMinutes <= 0) {
            return 0;
        }

        const requiredMinutes = getRequiredMinutesByTypeClient();
        const type = normalizeScheduleBlockType(block?.type);
        const remainingBlocks = Array.isArray(scheduleBlockState?.blocks)
            ? scheduleBlockState.blocks.filter(item => String(item?.block_key || "") !== String(block?.block_key || ""))
            : [];
        const scheduledMinutes = getScheduledMinutesByTypeClient(remainingBlocks);
        const remainingMinutes = Math.max(durationMinutes, Number(requiredMinutes[type] || 0) - Number(scheduledMinutes[type] || 0));

        return Math.max(1, Math.min(SCHEDULE_DAY_ORDER.length, Math.ceil(remainingMinutes / durationMinutes)));
    }

    function buildFacultyAwarenessSuggestions(schedule) {
        if (!scheduleBlockState || !Array.isArray(scheduleBlockState.blocks) || scheduleBlockState.blocks.length === 0) {
            return [];
        }

        syncBlocksFromDom();

        const facultyEntries = (Array.isArray(schedule?.entries) ? schedule.entries : []).filter(entry => !entry?.is_current_offering);
        const awarenessEntries = Array.isArray(schedule?.other_assigned_entries) ? schedule.other_assigned_entries : [];
        const facultyIntervals = buildFacultyScheduleIntervalMap(facultyEntries);
        const awarenessIntervals = buildFacultyScheduleIntervalMap(awarenessEntries);
        const windowStart = matrixTimeToMinutes(SUPPORTED_TIME_START);
        const windowEnd = matrixTimeToMinutes(SUPPORTED_TIME_END);
        const labelIndexMap = { LEC: 0, LAB: 0 };

        if (windowStart === null || windowEnd === null || windowEnd <= windowStart) {
            return [];
        }

        return scheduleBlockState.blocks.map(block => {
            const label = getScheduleBlockLabel(block, labelIndexMap);
            const durationMinutes = getScheduleBlockDurationMinutes(block);
            const preferredStart = matrixTimeToMinutes(block?.time_start);
            const preferredDays = normalizeScheduleDays(Array.isArray(block?.days) ? block.days : []);
            const meetingCount = estimateFacultySuggestionMeetingCount(block);

            if (durationMinutes <= 0) {
                return {
                    block_key: block.block_key,
                    label,
                    summary: "Set a valid start and end time in the block editor to calculate nearby suggestions.",
                    empty_message: "Set a valid start and end time in the block editor to calculate nearby suggestions.",
                    suggestions: []
                };
            }

            const draftIntervals = buildFacultyScheduleIntervalMap(buildFacultyScheduleDraftEntries(block.block_key));
            const patterns = buildFacultySuggestionDayPatterns(meetingCount, preferredDays);
            const suggestions = [];
            const currentPatternKey = `${preferredDays.join("")}|${String(block?.time_start || "")}|${String(block?.time_end || "")}`;

            patterns.forEach(pattern => {
                for (let start = windowStart; start + durationMinutes <= windowEnd; start += FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES) {
                    const end = start + durationMinutes;
                    const timeStart = matrixMinutesToTime(start);
                    const timeEnd = matrixMinutesToTime(end);
                    if (validateSchedulePolicy(pattern, timeStart, timeEnd, label)) {
                        continue;
                    }

                    if (countFacultyScheduleIntervalOverlap(facultyIntervals, pattern, start, end) > 0) {
                        continue;
                    }

                    if (countFacultyScheduleIntervalOverlap(draftIntervals, pattern, start, end) > 0) {
                        continue;
                    }

                    const patternKey = `${pattern.join("")}|${timeStart}|${timeEnd}`;
                    if (currentPatternKey === patternKey) {
                        continue;
                    }

                    const awarenessCount = countFacultyScheduleIntervalOverlap(awarenessIntervals, pattern, start, end);
                    const samePattern = preferredDays.length > 0 && preferredDays.join("") === pattern.join("");
                    const dayPenalty = preferredDays.length > 0
                        ? pattern.reduce((total, day, index) => {
                              const preferredDay = preferredDays[Math.min(index, preferredDays.length - 1)];
                              return total + Math.abs(SCHEDULE_DAY_ORDER.indexOf(day) - SCHEDULE_DAY_ORDER.indexOf(preferredDay));
                          }, 0) * 30
                        : 0;
                    const timePenalty = preferredStart === null ? 0 : Math.abs(start - preferredStart);
                    const awarenessPenalty = awarenessCount > 0 ? 500 + (awarenessCount * 20) : 0;

                    suggestions.push({
                        block_key: block.block_key,
                        room_id: String(block?.room_id || ""),
                        days: pattern,
                        time_start: timeStart,
                        time_end: timeEnd,
                        days_label: formatFacultyScheduleDaysLabel(pattern),
                        time_label: formatFacultyScheduleTimeRange(start, end),
                        fit_class: awarenessCount > 0 ? "is-caution" : "is-best",
                        fit_label: awarenessCount > 0 ? "Caution" : "Best",
                        pattern_label: samePattern
                            ? "Matches selected day pattern"
                            : `${pattern.length}-day pattern`,
                        note: awarenessCount > 0
                            ? `Faculty is free here, but ${awarenessCount} warning block(s) from other faculty overlap this time.`
                            : "Selected faculty is free here with no awareness warning on this slot.",
                        score: awarenessPenalty + dayPenalty + timePenalty + (samePattern ? -25 : 0)
                    });
                }
            });

            suggestions.sort((left, right) => {
                if (left.score !== right.score) {
                    return left.score - right.score;
                }

                return String(left.days.join("") + left.time_start).localeCompare(String(right.days.join("") + right.time_start));
            });

            const deduped = [];
            const seen = new Set();
            suggestions.forEach(item => {
                const key = `${item.days.join("")}|${item.time_start}|${item.time_end}`;
                if (!seen.has(key) && deduped.length < 4) {
                    seen.add(key);
                    deduped.push(item);
                }
            });

            return {
                block_key: block.block_key,
                label,
                summary: `${formatCoverageMinutes(durationMinutes)} per meeting | ${meetingCount} day${meetingCount === 1 ? "" : "s"} per week`,
                empty_message: "No nearby slot kept this block clear for the selected faculty. Try adjusting the duration or selected days.",
                suggestions: deduped
            };
        });
    }

    function renderFacultyAwarenessSuggestionPanel(schedule) {
        const sections = buildFacultyAwarenessSuggestions(schedule);
        if (sections.length === 0) {
            return "";
        }

        const cards = sections.map(section => {
            if (!Array.isArray(section.suggestions) || section.suggestions.length === 0) {
                return `
                    <div class="faculty-awareness-card">
                        <div class="faculty-awareness-card-header">
                            <div>
                                <div class="faculty-awareness-card-title">${escapeHtml(section.label || "Schedule Block")}</div>
                                <div class="faculty-awareness-card-meta">${escapeHtml(section.summary || "")}</div>
                            </div>
                        </div>
                        <div class="faculty-awareness-empty">${escapeHtml(section.empty_message || "No nearby suggestion is available yet.")}</div>
                    </div>
                `;
            }

            return `
                <div class="faculty-awareness-card">
                    <div class="faculty-awareness-card-header">
                        <div>
                            <div class="faculty-awareness-card-title">${escapeHtml(section.label || "Schedule Block")}</div>
                            <div class="faculty-awareness-card-meta">${escapeHtml(section.summary || "")}</div>
                        </div>
                    </div>
                    <div class="faculty-awareness-list">
                        ${section.suggestions.map(item => `
                            <div class="faculty-awareness-item ${escapeHtml(item.fit_class || "is-best")}">
                                <div class="faculty-awareness-item-top">
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="faculty-awareness-fit ${escapeHtml(item.fit_class || "is-best")}">${escapeHtml(item.fit_label || "Best")}</span>
                                        <span class="faculty-schedule-block-chip">${escapeHtml(item.pattern_label || "Suggested pattern")}</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary btn-use-faculty-awareness-slot"
                                        data-block-key="${escapeHtml(item.block_key || "")}"
                                        data-room-id="${escapeHtml(item.room_id || "")}"
                                        data-time-start="${escapeHtml(item.time_start || "")}"
                                        data-time-end="${escapeHtml(item.time_end || "")}"
                                        data-days='${escapeHtml(JSON.stringify(item.days || []))}'
                                    >
                                        Use Days & Time
                                    </button>
                                </div>
                                <div class="faculty-awareness-item-slot">${escapeHtml(item.days_label || "")} | ${escapeHtml(item.time_label || "")}</div>
                                <div class="faculty-awareness-item-meta">${escapeHtml(item.note || "")}</div>
                            </div>
                        `).join("")}
                    </div>
                </div>
            `;
        }).join("");

        return `
            <div class="faculty-awareness-panel">
                <div class="faculty-awareness-header">
                    <div>
                        <div class="step-label mb-1">Closest Suggested Spots</div>
                        <div class="schedule-hint mb-0">These suggestions use the current block duration and selected faculty workload. Applying one fills only the block days and time.</div>
                    </div>
                </div>
                <div class="faculty-awareness-grid">${cards}</div>
            </div>
        `;
    }

    function buildFacultyScheduleBoardCell(entry) {
        const subjectCode = String(entry?.subject_code || "").trim();
        const subjectDescription = String(entry?.subject_description || "").trim();
        const sectionLabel = String(entry?.section_label || "TBA").trim() || "TBA";
        const roomLabel = String(entry?.room_label || "TBA").trim() || "TBA";
        const scheduleType = String(entry?.schedule_type || "").trim().toUpperCase();
        const ownerNames = Array.isArray(entry?.owner_faculty_names) ? entry.owner_faculty_names.filter(Boolean) : [];
        const ownerLabel = ownerNames.length > 0 ? ownerNames.join("; ") : "Another faculty";
        const ownerPreview = String(entry?.owner_faculty_preview || "").trim();
        const occupiedEntryCount = parseInt(entry?.occupied_entry_count, 10) || 0;
        const previewLabel = String(entry?.preview_label || "").trim();
        const previewConflictTypes = Array.isArray(entry?.preview_conflict_types) ? entry.preview_conflict_types.filter(Boolean) : [];
        const previewConflictDetails = Array.isArray(entry?.preview_conflict_details) ? entry.preview_conflict_details.filter(Boolean) : [];

        if (entry?.is_preview_block) {
            const blockClass = entry?.is_preview_conflict
                ? "faculty-schedule-class-block is-preview-conflict"
                : "faculty-schedule-class-block is-preview";
            const previewChips = [];
            if (scheduleType === "LAB") {
                previewChips.push('<span class="faculty-schedule-block-chip">LAB</span>');
            }
            previewChips.push('<span class="faculty-schedule-block-chip is-preview">Draft</span>');
            if (entry?.is_preview_conflict) {
                previewConflictTypes.slice(0, 2).forEach(type => {
                    previewChips.push(`<span class="faculty-schedule-block-chip is-conflict">${escapeHtml(type)}</span>`);
                });
            } else if (entry?.preview_status_note) {
                previewChips.push(`<span class="faculty-schedule-block-chip is-ready">${escapeHtml(entry.preview_status_note)}</span>`);
            }

            const detailLines = previewConflictDetails.slice(0, 2).map(detail => (
                `<div class="faculty-schedule-preview-detail">${escapeHtml(detail)}</div>`
            )).join("");

            return `
                <div class="${blockClass}">
                    ${previewLabel ? `<div class="faculty-schedule-preview-label">${escapeHtml(previewLabel)}</div>` : ""}
                    ${subjectCode ? `<div class="faculty-schedule-subject-code">${escapeHtml(subjectCode)}</div>` : ""}
                    ${subjectDescription ? `<div class="faculty-schedule-subject-description">${escapeHtml(subjectDescription)}</div>` : ""}
                    <div class="faculty-schedule-block-line">${escapeHtml(sectionLabel)}</div>
                    <div class="faculty-schedule-block-line">${escapeHtml(roomLabel)}</div>
                    ${detailLines ? `<div class="faculty-schedule-preview-details">${detailLines}</div>` : ""}
                    ${previewChips.join("")}
                </div>
            `;
        }

        const blockClass = entry?.is_other_faculty_assignment
            ? "faculty-schedule-class-block is-occupied"
            : "faculty-schedule-class-block";

        return `
            <div class="${blockClass}">
                ${subjectCode ? `<div class="faculty-schedule-subject-code">${escapeHtml(subjectCode)}</div>` : ""}
                ${subjectDescription ? `<div class="faculty-schedule-subject-description">${escapeHtml(subjectDescription)}</div>` : ""}
                <div class="faculty-schedule-block-line">${escapeHtml(sectionLabel)}</div>
                <div class="faculty-schedule-block-line">${escapeHtml(roomLabel)}</div>
                ${entry?.is_other_faculty_assignment
                    ? `<div class="faculty-schedule-owner-line">${occupiedEntryCount > 1 ? "Other faculty: " : "Assigned to "}${escapeHtml(ownerPreview || ownerLabel)}</div>`
                    : ""}
                ${scheduleType === "LAB" ? '<span class="faculty-schedule-block-chip">LAB</span>' : ""}
                ${entry?.is_other_faculty_assignment ? '<span class="faculty-schedule-block-chip is-occupied">Occupied by Others</span>' : ""}
                ${entry?.is_current_offering ? '<span class="faculty-schedule-block-chip is-current">Current</span>' : ""}
            </div>
        `;
    }

    function renderFacultyScheduleMatrix() {
        const container = $("#facultyScheduleMatrixContainer");
        const helper = scheduleBlockState?.facultyHelper;
        if (!container.length) {
            return;
        }

        if (!scheduleBlockState || !helper || !helper.selectedFacultyId) {
            $("#facultyScheduleMatrixHeaderNote").text("View the selected faculty schedule in the same day-by-time board layout.");
            container.html('<div class="faculty-matrix-empty">Select a faculty and click Show Workload.</div>');
            return;
        }

        if (helper.detailLoading) {
            $("#facultyScheduleMatrixHeaderNote").text("Loading the selected faculty schedule...");
            container.html('<div class="faculty-matrix-empty">Loading faculty schedule...</div>');
            return;
        }

        if (helper.detailError) {
            $("#facultyScheduleMatrixHeaderNote").text("Unable to load the selected faculty schedule.");
            container.html(`<div class="faculty-matrix-empty">${escapeHtml(helper.detailError)}</div>`);
            return;
        }

        const schedule = helper.selectedFacultySchedule;
        if (!schedule || !schedule.faculty_id) {
            $("#facultyScheduleMatrixHeaderNote").text("View the selected faculty schedule in the same day-by-time board layout.");
            container.html('<div class="faculty-matrix-empty">Select a faculty and click Show Workload.</div>');
            return;
        }

        const entries = Array.isArray(schedule.entries) ? schedule.entries : [];
        const otherAssignedEntries = Array.isArray(schedule.other_assigned_entries) ? schedule.other_assigned_entries : [];
        const facultyBoard = buildFacultyScheduleBoard(entries);
        const awarenessBoard = buildOtherFacultyScheduleBoard(otherAssignedEntries, facultyBoard.slots);
        const facultyName = schedule.faculty_name || "Selected faculty";
        const slots = facultyBoard.slots.length > 0 ? facultyBoard.slots : awarenessBoard.slots;
        const hasCurrentOffering = entries.some(entry => entry.is_current_offering);
        const awarenessBlockCount = countFacultyScheduleBoardBlocks(awarenessBoard);

        $("#facultyScheduleMatrixHeaderNote").text(
            entries.length > 0 || awarenessBlockCount > 0
                ? `${facultyName} schedule with awareness of other faculty workload occupancy for the current college term.`
                : `${facultyName} has no scheduled workload blocks in the current college term.`
        );

        const summaryNotes = [
            schedule.is_assigned
                ? "Assigned to the current subject in Faculty Workload."
                : "Selected faculty schedule for this term."
        ];
        if (hasCurrentOffering) {
            summaryNotes.push("Current subject blocks are marked.");
        }
        if (awarenessBlockCount > 0) {
            summaryNotes.push("Red blocks are awareness only and show time slots already occupied by other faculty workload in the current college term.");
        }
        summaryNotes.push("Actual save-time conflict checks still happen when Define Class Schedule Blocks is saved.");

        const legendItems = [
            '<span class="faculty-schedule-legend-item"><span class="faculty-schedule-legend-swatch"></span>Selected faculty schedule</span>'
        ];
        if (hasCurrentOffering) {
            legendItems.push('<span class="faculty-schedule-legend-item"><span class="faculty-schedule-legend-swatch is-current"></span>Current subject block</span>');
        }
        if (awarenessBlockCount > 0) {
            legendItems.push('<span class="faculty-schedule-legend-item"><span class="faculty-schedule-legend-swatch is-occupied"></span>Occupied by other faculty (warning only)</span>');
        }

        let html = `
            <div class="faculty-schedule-summary">
                <div>
                    <div class="fw-semibold">${escapeHtml(schedule.faculty_name || "Faculty")}</div>
                    <div class="text-muted small">${escapeHtml(summaryNotes.join(" "))}</div>
                </div>
                <div class="faculty-helper-metrics">
                    <span class="faculty-helper-metric">${escapeHtml(String(schedule.scheduled_block_count || entries.length))} block(s)</span>
                    <span class="faculty-helper-metric">${escapeHtml(String(schedule.scheduled_class_count || 0))} class(es)</span>
                    ${awarenessBlockCount > 0 ? `<span class="faculty-helper-metric">${escapeHtml(String(awarenessBlockCount))} occupied time block${awarenessBlockCount === 1 ? "" : "s"}</span>` : ""}
                </div>
            </div>
        `;

        html += `<div class="faculty-schedule-legend">${legendItems.join("")}</div>`;

        const warningItems = []
            .concat(awarenessBlockCount > 0 ? ["Red occupied blocks are warning-only awareness and do not automatically mean the selected faculty cannot be scheduled there."] : [])
            .concat(Array.isArray(facultyBoard.warnings) ? facultyBoard.warnings : []);

        if (warningItems.length > 0) {
            html += `
                <div class="faculty-schedule-warning">
                    ${warningItems.map(warning => `<div>${escapeHtml(warning)}</div>`).join("")}
                </div>
            `;
        }

        html += renderFacultyAwarenessSuggestionPanel(schedule);

        if (slots.length === 0) {
            container.html(`${html}<div class="faculty-matrix-empty">No schedule window is available for rendering.</div>`);
            return;
        }

        html += `
            <div class="faculty-schedule-sheet">
                <div class="table-responsive">
                    <table class="table table-bordered faculty-schedule-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                ${FACULTY_SCHEDULE_DAY_COLUMNS.map(day => `<th>${escapeHtml(day.label)}</th>`).join("")}
                            </tr>
                        </thead>
                        <tbody>
        `;

        slots.forEach(slotStart => {
            const slotEnd = slotStart + FACULTY_SCHEDULE_SLOT_INTERVAL_MINUTES;
            html += `
                <tr>
                    <td class="faculty-schedule-time-cell">${escapeHtml(formatFacultyScheduleTimeRange(slotStart, slotEnd))}</td>
            `;

            FACULTY_SCHEDULE_DAY_COLUMNS.forEach(day => {
                const facultyEntry = facultyBoard.occupancy[day.key]?.[slotStart];
                if (facultyEntry && facultyEntry.type === "covered") {
                    return;
                }

                const awarenessEntry = awarenessBoard.occupancy[day.key]?.[slotStart];
                if (!facultyEntry && awarenessEntry && awarenessEntry.type === "covered") {
                    return;
                }

                if (facultyEntry && facultyEntry.type === "start") {
                    const cellClasses = ["faculty-schedule-class-cell"];
                    if (facultyEntry.block.is_other_faculty_assignment) {
                        cellClasses.push("is-occupied");
                    } else if (facultyEntry.block.is_current_offering) {
                        cellClasses.push("is-current");
                    }
                    html += `
                        <td rowspan="${facultyEntry.block._slotSpan}" class="${cellClasses.join(" ")}">
                            ${buildFacultyScheduleBoardCell(facultyEntry.block)}
                        </td>
                    `;
                    return;
                }

                if (awarenessEntry && awarenessEntry.type === "start") {
                    html += `
                        <td rowspan="${awarenessEntry.block._slotSpan}" class="faculty-schedule-class-cell is-occupied">
                            ${buildFacultyScheduleBoardCell(awarenessEntry.block)}
                        </td>
                    `;
                    return;
                }

                html += '<td class="faculty-schedule-empty-cell"></td>';
            });

            html += "</tr>";
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        container.html(html);
    }

    function openFacultyScheduleMatrixHelper() {
        if (!scheduleBlockState || !scheduleBlockState.facultyHelper) {
            Swal.fire("Unavailable", "Open a class schedule block first.", "info");
            return;
        }

        const selectedFacultyId = parseInt($("#blockScheduleFacultySelect").val(), 10) || 0;
        if (!selectedFacultyId) {
            Swal.fire("Select Faculty", "Choose a faculty first to view the faculty schedule board.", "info");
            return;
        }

        loadScheduleBlockFacultyDetails(selectedFacultyId, { openMatrix: true });
    }

    function roomMatchesSchedule(room, scheduleType) {
        const roomType = normalizeRoomType(room && room.room_type);
        const type = String(scheduleType || "").toUpperCase();

        if (type === "LAB") {
            return roomType === "laboratory" || roomType === "lec_lab";
        }

        return roomType === "lecture" || roomType === "lec_lab";
    }

    function formatCountLabel(count, singular, plural) {
        const value = Number(count || 0);
        const pluralLabel = plural || `${singular}s`;
        return `${value} ${value === 1 ? singular : pluralLabel}`;
    }

    function getRoomBrowserTypeMeta(roomType) {
        const normalized = normalizeRoomType(roomType);
        return ROOM_BROWSER_TYPE_META[normalized] || ROOM_BROWSER_TYPE_META.lecture;
    }

    function buildRoomUsageMap() {
        const counts = {};
        const seenKeys = new Set();
        const allRows = $("#scheduleListContainer tr.schedule-offering-row");
        const trackedRows = allRows.filter("[data-search-match]");
        const sourceRows = trackedRows.length > 0
            ? trackedRows.filter("[data-search-match='1']")
            : allRows;

        sourceRows.find(".schedule-room-entry[data-room-id]").each(function () {
            const item = $(this);
            const roomId = parseInt(item.data("roomId"), 10) || 0;
            if (!roomId) {
                return;
            }

            if (!counts[roomId]) {
                counts[roomId] = { total: 0, LEC: 0, LAB: 0 };
            }

            const type = String(item.data("scheduleType") || "LEC").toUpperCase() === "LAB" ? "LAB" : "LEC";
            const offeringId = parseInt(item.data("offeringId"), 10) || 0;
            const uniqueKey = `${roomId}|${offeringId}|${type}`;
            if (seenKeys.has(uniqueKey)) {
                return;
            }
            seenKeys.add(uniqueKey);

            counts[roomId].total += 1;
            counts[roomId][type] += 1;
        });

        return counts;
    }

    function buildRoomBrowserMetric(label, value) {
        return `
            <div class="room-browser-metric">
                <span class="room-browser-metric-label">${escapeHtml(label)}</span>
                <span class="room-browser-metric-value">${escapeHtml(String(value))}</span>
            </div>
        `;
    }

    function renderRoomBrowser() {
        const summary = $("#roomBrowserSummary");
        const list = $("#roomBrowserList");

        if (summary.length === 0 || list.length === 0) {
            return;
        }

        const ay = $("#ay_id").val();
        const sem = $("#semester").val();
        if (!ay || !sem) {
            summary.html("");
            list.html("<div class='room-browser-empty'>Select Academic Year and Semester to browse rooms for this term.</div>");
            return;
        }

        const rooms = Array.isArray(termRoomCache) ? termRoomCache.slice() : [];
        if (rooms.length === 0) {
            summary.html("");
            list.html("<div class='room-browser-empty'>No rooms are available for the selected term. Check room access for this college.</div>");
            return;
        }

        const usageMap = buildRoomUsageMap();
        const totalRooms = rooms.length;
        const activeRooms = rooms.filter(room => Number((usageMap[room.room_id] || {}).total || 0) > 0).length;
        const totalClasses = rooms.reduce(
            (sum, room) => sum + Number((usageMap[room.room_id] || {}).total || 0),
            0
        );

        summary.html(
            buildRoomBrowserMetric("Rooms", totalRooms) +
            buildRoomBrowserMetric("Active", activeRooms) +
            buildRoomBrowserMetric("Classes", totalClasses)
        );

        const typeOrder = ["lecture", "laboratory", "lec_lab"];
        const sectionsHtml = typeOrder.map(type => {
            const meta = getRoomBrowserTypeMeta(type);
            const typeRooms = rooms
                .filter(room => normalizeRoomType(room.room_type) === type)
                .sort((left, right) => {
                    const usageDelta = Number((usageMap[right.room_id] || {}).total || 0) - Number((usageMap[left.room_id] || {}).total || 0);
                    if (usageDelta !== 0) {
                        return usageDelta;
                    }
                    return String(left.label || "").localeCompare(String(right.label || ""));
                });

            if (typeRooms.length === 0) {
                return "";
            }

            const sectionUsage = typeRooms.reduce(
                (sum, room) => sum + Number((usageMap[room.room_id] || {}).total || 0),
                0
            );

            const cardsHtml = typeRooms.map(room => {
                const usage = usageMap[room.room_id] || { total: 0, LEC: 0, LAB: 0 };
                const accessType = String(room.access_type || "owner").toLowerCase() === "shared" ? "shared" : "owner";
                const accessLabel = accessType === "shared" ? "Shared Access" : "Owned Room";
                const accessNote = accessType === "shared" && room.owner_code
                    ? `Shared from ${room.owner_code}`
                    : "Managed within your college room list";
                const tags = [];

                if (usage.LEC > 0) {
                    tags.push(`<span class="room-browser-room-tag is-lec">LEC ${escapeHtml(String(usage.LEC))}</span>`);
                }
                if (usage.LAB > 0) {
                    tags.push(`<span class="room-browser-room-tag is-lab">LAB ${escapeHtml(String(usage.LAB))}</span>`);
                }

                tags.push(
                    `<span class="room-browser-room-tag ${accessType === "shared" ? "is-shared" : "is-owned"}">${escapeHtml(accessLabel)}</span>`
                );

                return `
                    <article class="room-browser-room-card ${usage.total > 0 ? "is-active" : "is-idle"}">
                        <div>
                            <div class="room-browser-room-name">${escapeHtml(room.label || "Unnamed Room")}</div>
                            <div class="room-browser-room-note">${escapeHtml(accessNote)}</div>
                            <div class="room-browser-room-tags">${tags.join("")}</div>
                        </div>
                        <div class="room-browser-count">
                            <span class="room-browser-count-number">${escapeHtml(String(usage.total || 0))}</span>
                            <span class="room-browser-count-label">${escapeHtml((usage.total || 0) === 1 ? "Class" : "Classes")}</span>
                        </div>
                    </article>
                `;
            }).join("");

            return `
                <section class="room-browser-section">
                    <div class="room-browser-section-header">
                        <div>
                            <div class="room-browser-section-title">
                                <span class="room-browser-type-chip ${meta.chipClass}">
                                    <i class="bx ${meta.icon}"></i>
                                    ${escapeHtml(meta.label)}
                                </span>
                            </div>
                            <div class="room-browser-section-subtitle">${escapeHtml(meta.subtitle)}</div>
                        </div>
                        <div class="room-browser-section-badges">
                            <span class="room-browser-section-badge">${escapeHtml(formatCountLabel(typeRooms.length, "room"))}</span>
                            <span class="room-browser-section-badge">${escapeHtml(formatCountLabel(sectionUsage, "class", "classes"))}</span>
                        </div>
                    </div>
                    <div class="room-browser-room-list">${cardsHtml}</div>
                </section>
            `;
        }).join("");

        list.html(
            sectionsHtml !== ""
                ? sectionsHtml
                : "<div class='room-browser-empty'>No rooms matched the available room types for this term.</div>"
        );
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
        renderRoomBrowser();
    }

    let scheduleBlockState = null;
    let scheduleBlockCounter = 0;
    const blockSuggestionTimers = {};

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
            availabilityTimeSlots: Array.isArray(source.availabilityTimeSlots) ? source.availabilityTimeSlots : [],
            availabilitySelectedKey: String(source.availabilitySelectedKey || ""),
            suggestionMessage: String(source.suggestionMessage || ""),
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

    function blockHasSuggestionFilter(block) {
        const days = normalizeScheduleDays(block?.days || []);
        const timeStart = String(block?.time_start || "").trim();
        const timeEnd = String(block?.time_end || "").trim();
        const roomId = String(block?.room_id || "").trim();
        return days.length > 0 || timeStart !== "" || timeEnd !== "" || roomId !== "";
    }

    function normalizeScheduleBlockSuggestionState() {
        if (!scheduleBlockState || !Array.isArray(scheduleBlockState.blocks)) {
            return;
        }

        scheduleBlockState.blocks = scheduleBlockState.blocks.map(block => {
            if (blockHasSuggestionFilter(block)) {
                return block;
            }

            return {
                ...block,
                suggestionsVisible: false,
                suggestions: [],
                availabilityTimeSlots: [],
                availabilitySelectedKey: "",
                suggestionMessage: "Choose at least one day, time, or room first."
            };
        });
    }

    function updateRenderedBlockSuggestionState() {
        if (!scheduleBlockState || !Array.isArray(scheduleBlockState.blocks)) {
            return;
        }

        scheduleBlockState.blocks.forEach(block => {
            const root = $(`[data-schedule-block="${block.block_key}"]`);
            if (!root.length) {
                return;
            }

            const canSuggest = blockHasSuggestionFilter(block);
            root.find(".btn-block-suggestions").toggleClass("d-none", !canSuggest);
            root.find(".block-suggestion-hint").toggleClass("d-none", canSuggest);

            const panel = root.find(".suggestion-panel");
            if (!canSuggest || !block.suggestionsVisible) {
                panel.addClass("d-none");
            } else {
                panel.removeClass("d-none");
            }

            if (!canSuggest) {
                root.find(".suggestion-list").html('<div class="suggestion-empty">Choose at least one day, time, or room first.</div>');
            }
        });
    }

    function queueBlockSuggestionRefresh(blockKey) {
        if (!blockKey) {
            return;
        }

        if (blockSuggestionTimers[blockKey]) {
            clearTimeout(blockSuggestionTimers[blockKey]);
        }

        blockSuggestionTimers[blockKey] = window.setTimeout(function () {
            delete blockSuggestionTimers[blockKey];
            if (!scheduleBlockState || !$("#blockScheduleModal").hasClass("show")) {
                return;
            }

            const block = scheduleBlockState.blocks.find(item => item.block_key === blockKey);
            if (!block || !block.suggestionsVisible || !blockHasSuggestionFilter(block)) {
                return;
            }

            loadSuggestionsForBlock(blockKey);
        }, 220);
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

    function getSelectedAvailabilitySlot(block) {
        const timeSlots = Array.isArray(block?.availabilityTimeSlots) ? block.availabilityTimeSlots : [];
        if (timeSlots.length === 0) {
            return null;
        }

        const selectedKey = String(block?.availabilitySelectedKey || "");
        return timeSlots.find(item => String(item.time_key || "") === selectedKey) || timeSlots[0];
    }

    function renderBlockAvailabilityFinder(block) {
        const timeSlots = Array.isArray(block?.availabilityTimeSlots) ? block.availabilityTimeSlots : [];
        if (timeSlots.length === 0) {
            const emptyMessage = String(block?.suggestionMessage || "").trim() || "No available entries matched the current filters.";
            return `<div class="suggestion-empty">${escapeHtml(emptyMessage)}</div>`;
        }

        const selectedSlot = getSelectedAvailabilitySlot(block);
        const selectedKey = String(selectedSlot?.time_key || "");

        const timeSlotCards = timeSlots.map(slot => {
            const slotKey = String(slot.time_key || "");
            const isSelected = slotKey !== "" && slotKey === selectedKey;
            return `
                <button
                    type="button"
                    class="availability-slot-card btn-select-block-time-slot ${isSelected ? "is-selected" : ""}"
                    data-block-key="${escapeHtml(block.block_key)}"
                    data-time-key="${escapeHtml(slotKey)}"
                >
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="suggestion-slot">${escapeHtml(slot.days_label || "")} | ${escapeHtml(slot.time_label || "")}</div>
                            <div class="suggestion-meta mt-1">${escapeHtml(slot.pattern_label || "Available slot")}</div>
                        </div>
                        <span class="availability-count">${escapeHtml(String(slot.room_count || 0))}</span>
                    </div>
                </button>
            `;
        }).join("");

        const roomCards = Array.isArray(selectedSlot?.rooms) && selectedSlot.rooms.length > 0
            ? selectedSlot.rooms.map(room => `
                <div class="suggestion-card ${escapeHtml(room.fit_class || "best")}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="suggestion-fit ${escapeHtml(room.fit_class || "best")}">${escapeHtml(room.fit_label || "Available")}</span>
                            <span class="suggestion-chip">${escapeHtml(selectedSlot.pattern_label || "Selected time")}</span>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary btn-use-block-suggestion"
                            data-block-key="${escapeHtml(block.block_key)}"
                            data-room-id="${escapeHtml(room.room_id || "")}"
                            data-time-start="${escapeHtml(selectedSlot.time_start || "")}"
                            data-time-end="${escapeHtml(selectedSlot.time_end || "")}"
                            data-days='${escapeHtml(JSON.stringify(selectedSlot.days || []))}'
                        >
                            Use
                        </button>
                    </div>
                    <div class="suggestion-slot mt-2">${escapeHtml(room.room_label || "")}</div>
                    <div class="suggestion-meta mt-1">${escapeHtml(selectedSlot.days_label || "")} | ${escapeHtml(selectedSlot.time_label || "")}</div>
                    <div class="suggestion-reasons mt-2">
                        ${Array.isArray(room.reasons) && room.reasons.length > 0
                            ? room.reasons.map(reason => `<span class="suggestion-reason">${escapeHtml(reason)}</span>`).join("")
                            : '<span class="suggestion-reason">Ready to use</span>'}
                    </div>
                </div>
            `).join("")
            : '<div class="suggestion-empty">Select a time slot to view available rooms.</div>';

        return `
            <div class="availability-shell">
                <div>
                    <div class="step-label mb-2">Available Time Slots</div>
                    <div class="availability-list">${timeSlotCards}</div>
                </div>
                <div>
                    <div class="step-label mb-2">Available Rooms</div>
                    <div class="suggestion-list">${roomCards}</div>
                </div>
            </div>
        `;
    }

    function renderBlockSuggestionCards(block) {
        if (!Array.isArray(block.suggestions) || block.suggestions.length === 0) {
            const emptyMessage = String(block?.suggestionMessage || "").trim() || "No ranked suggestions are available for this block.";
            return `<div class="suggestion-empty">${escapeHtml(emptyMessage)}</div>`;
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
                <div class="suggestion-slot mt-2">${escapeHtml(item.days_label || "")} | ${escapeHtml(item.time_label || "")}</div>
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
        normalizeScheduleBlockSuggestionState();
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
        refreshFacultyAwarenessModalIfOpen();
    }

    function removeScheduleBlock(blockKey) {
        if (!scheduleBlockState) {
            return;
        }

        scheduleBlockState.blocks = scheduleBlockState.blocks.filter(block => block.block_key !== blockKey);
        renderScheduleBlocks();
        refreshFacultyAwarenessModalIfOpen();
    }

    function openScheduleBlockModal(button) {
        const offeringId = parseInt(button.data("offeringId"), 10) || 0;
        const labUnits = Number(button.data("labUnits")) || 0;
        const lecUnits = Number(button.data("lecUnits")) || 0;
        const totalUnits = Number(button.data("totalUnits")) || 0;
        const subjectLabel = `${button.data("subCode")} - ${button.data("subDesc")}`;
        const sectionLabel = String(button.data("sectionLabel") || `Section: ${button.data("section")}`);

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
                        blocks,
                        facultyHelper: createScheduleBlockFacultyHelperState()
                    };

                    $("#block_sched_offering_id").val(String(offeringId));
                    $("#block_sched_subject_label").text(subjectLabel);
                    $("#block_sched_section_label").text(sectionLabel);
                    $("#block_sched_context_line").removeClass("d-none");
                    $("#block_sched_lec_units").val(String(lecUnits));
                    $("#block_sched_lab_units").val(String(labUnits));
                    $("#block_sched_total_units").val(String(totalUnits));
                    $("#btnClearBlockSchedule")
                        .data("offering-id", offeringId)
                        .data("subject-label", subjectLabel)
                        .toggleClass("d-none", !(Array.isArray(res.blocks) && res.blocks.length > 0));

                    renderScheduleBlocks();
                    renderScheduleBlockFacultyOptions();
                    renderScheduleBlockFacultySummary();
                    loadScheduleBlockFacultyOptions(offeringId);
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

    function abortScheduleMergeRequest() {
        if (scheduleMergeRequest && scheduleMergeRequest.readyState !== 4) {
            scheduleMergeRequest.abort();
        }

        scheduleMergeRequest = null;
    }

    function resetScheduleMergeModalState() {
        scheduleMergeState = null;
        $("#scheduleMergeOwnerOfferingId").val("");
        $("#scheduleMergeOwnerTitle").text("Loading merge details...");
        $("#scheduleMergeOwnerMeta").text("");
        $("#scheduleMergeOwnerHint").text("");
        $("#scheduleMergeBlockingNotice").addClass("d-none").html("");
        $("#scheduleMergeCandidateList").html('<div class="schedule-merge-empty">Loading merge candidates...</div>');
        $("#scheduleMergeSelectedCount").text("0 selected");
        $("#btnClearMergeSelection").prop("disabled", true);
        $("#btnSaveScheduleMerge").prop("disabled", true);
    }

    function scheduleMergeCandidateStatusBadge(label) {
        const value = String(label || "").trim();
        const normalized = value.toLowerCase();
        let className = "bg-label-secondary text-secondary";

        if (normalized.includes("merged here")) {
            className = "bg-label-info text-info";
        } else if (normalized.includes("merge owner")) {
            className = "bg-label-primary text-primary";
        } else if (normalized.includes("merged elsewhere")) {
            className = "bg-label-warning text-warning";
        } else if (normalized.includes("scheduled") && !normalized.includes("not scheduled")) {
            className = "bg-label-success text-success";
        }

        return `<span class="badge ${className}">${escapeHtml(value || "Status")}</span>`;
    }

    function getSelectedScheduleMergeMemberIds() {
        const ids = [];

        $("#scheduleMergeCandidateList .schedule-merge-checkbox:checked").each(function () {
            const value = parseInt($(this).val(), 10) || 0;
            if (value > 0) {
                ids.push(value);
            }
        });

        return ids;
    }

    function updateScheduleMergeSelectionSummary() {
        const selectedCount = getSelectedScheduleMergeMemberIds().length;
        $("#scheduleMergeSelectedCount").text(`${selectedCount} selected`);
        $("#btnClearMergeSelection").prop("disabled", selectedCount === 0);
    }

    function buildScheduleMergeBlockingMessages(state) {
        const messages = [];
        const ownerLabel = String(state?.owner?.full_section || state?.owner?.section_name || "the selected owner").trim();

        if (!state?.owner_has_schedule) {
            messages.push(`${escapeHtml(ownerLabel)} needs a saved schedule first before other offerings can inherit it.`);
        }

        if (state?.owner_has_workload) {
            const names = Array.isArray(state.owner_workload_names) ? state.owner_workload_names.filter(Boolean) : [];
            const facultyLine = names.length > 0
                ? ` It is already assigned in Faculty Workload to <b>${names.map(name => escapeHtml(name)).join("</b>, <b>")}</b>.`
                : "";
            messages.push(`This merge group cannot be changed while the owner schedule is used in Faculty Workload.${facultyLine}`);
        }

        if (state?.group_has_locked_members) {
            messages.push("This merge group currently includes a locked offering. Unlock the group first before changing its members.");
        }

        return messages;
    }

    function renderScheduleMergeCandidates(state) {
        const list = $("#scheduleMergeCandidateList");
        const candidates = Array.isArray(state?.candidates) ? state.candidates : [];

        if (candidates.length === 0) {
            list.html('<div class="schedule-merge-empty">No compatible same-subject offerings were found in this college term.</div>');
            updateScheduleMergeSelectionSummary();
            return;
        }

        const html = candidates.map(candidate => {
            const offeringId = Number(candidate?.offering_id || 0);
            const checked = Boolean(candidate?.is_selected);
            const disabled = !Boolean(candidate?.can_select);
            const sectionLabel = String(candidate?.full_section || candidate?.section_name || `Offering ${offeringId}`).trim();
            const scheduleBadge = candidate?.has_schedule
                ? '<span class="badge bg-label-success text-success">Has Schedule</span>'
                : '<span class="badge bg-label-secondary text-secondary">No Schedule</span>';
            const reasonHtml = candidate?.reason
                ? `<div class="schedule-merge-candidate-reason">${escapeHtml(candidate.reason)}</div>`
                : "";

            return `
                <label class="schedule-merge-candidate ${disabled ? "is-disabled" : ""}">
                    <div class="d-flex align-items-start gap-3">
                        <input
                            type="checkbox"
                            class="form-check-input mt-1 schedule-merge-checkbox"
                            value="${escapeHtml(String(offeringId))}"
                            ${checked ? "checked" : ""}
                            ${disabled ? "disabled" : ""}
                        >
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <div class="schedule-merge-candidate-title">${escapeHtml(sectionLabel)}</div>
                                    <div class="schedule-merge-candidate-meta d-flex flex-wrap gap-2 align-items-center">
                                        ${scheduleMergeCandidateStatusBadge(candidate?.status_label || "")}
                                        ${scheduleBadge}
                                    </div>
                                </div>
                            </div>
                            ${reasonHtml}
                        </div>
                    </div>
                </label>
            `;
        }).join("");

        list.html(html);
        updateScheduleMergeSelectionSummary();
    }

    function openScheduleMergeModal(button) {
        const offeringId = parseInt(button.data("offeringId"), 10) || 0;

        if (!offeringId) {
            Swal.fire("Error", "Missing offering reference.", "error");
            return;
        }

        abortScheduleMergeRequest();
        resetScheduleMergeModalState();
        $("#scheduleMergeModal").modal("show");

        scheduleMergeRequest = $.ajax({
            url: "../backend/query_class_schedule.php",
            type: "POST",
            dataType: "json",
            data: {
                load_schedule_merge_candidates: 1,
                offering_id: offeringId
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    $("#scheduleMergeCandidateList").html('<div class="schedule-merge-empty">Unable to load merge candidates.</div>');
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        html: (res && res.message) ? res.message : "Failed to load merge candidates.",
                        customClass: { popup: "swal-top" }
                    });
                    return;
                }

                scheduleMergeState = res;
                $("#scheduleMergeOwnerOfferingId").val(String(res.owner_offering_id || ""));

                const owner = res.owner || {};
                const ownerTitle = String(owner.group_course_label || owner.full_section || owner.section_name || "Schedule owner").trim();
                const subjectParts = [owner.sub_code, owner.sub_description].filter(Boolean).map(value => String(value).trim());
                const subjectLine = subjectParts.join(" - ");
                const ownerLine = String(owner.full_section || owner.section_name || "").trim();
                const metaParts = [];

                if (subjectLine) {
                    metaParts.push(subjectLine);
                }
                if (ownerLine && ownerLine !== ownerTitle) {
                    metaParts.push(`Owner row: ${ownerLine}`);
                }

                $("#scheduleMergeOwnerTitle").text(ownerTitle || "Schedule owner");
                $("#scheduleMergeOwnerMeta").text(metaParts.join(" | "));
                $("#scheduleMergeOwnerHint").text(
                    Number(owner.group_size || 1) > 1
                        ? `Current merged label: ${ownerTitle}`
                        : "No additional offerings are merged yet."
                );

                const blockingMessages = buildScheduleMergeBlockingMessages(res);
                if (blockingMessages.length > 0) {
                    $("#scheduleMergeBlockingNotice")
                        .removeClass("d-none")
                        .html(blockingMessages.map(message => `<div>${message}</div>`).join(""));
                } else {
                    $("#scheduleMergeBlockingNotice").addClass("d-none").html("");
                }

                renderScheduleMergeCandidates(res);
                $("#btnSaveScheduleMerge").prop("disabled", blockingMessages.length > 0);
            },
            error: function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                $("#scheduleMergeCandidateList").html('<div class="schedule-merge-empty">Unable to load merge candidates.</div>');
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    html: xhr.responseText || "Failed to load merge candidates.",
                    customClass: { popup: "swal-top" }
                });
            },
            complete: function () {
                scheduleMergeRequest = null;
            }
        });
    }

    function saveScheduleMergeGroup() {
        if (!scheduleMergeState) {
            Swal.fire("Error", "No merge group is loaded.", "error");
            return;
        }

        const ownerOfferingId = Number(scheduleMergeState.owner_offering_id || $("#scheduleMergeOwnerOfferingId").val() || 0);
        if (!ownerOfferingId) {
            Swal.fire("Error", "Missing schedule owner reference.", "error");
            return;
        }

        const blockingMessages = buildScheduleMergeBlockingMessages(scheduleMergeState);
        if (blockingMessages.length > 0) {
            Swal.fire({
                icon: "warning",
                title: "Merge Locked",
                html: blockingMessages.join("<br><br>"),
                customClass: { popup: "swal-top" }
            });
            return;
        }

        const selectedMemberIds = getSelectedScheduleMergeMemberIds();
        const owner = scheduleMergeState.owner || {};
        const ownerLabel = String(owner.group_course_label || owner.full_section || owner.section_name || "this schedule owner").trim();
        const confirmTitle = selectedMemberIds.length > 0 ? "Save Merge Group?" : "Clear Merge Group?";
        const confirmHtml = selectedMemberIds.length > 0
            ? `The schedule of <b>${escapeHtml(ownerLabel)}</b> will stay as the live schedule.<br><br><b>${escapeHtml(String(selectedMemberIds.length))}</b> selected offering(s) will inherit it, and their own schedule entries will be cleared.`
            : `This will remove every merged member from <b>${escapeHtml(ownerLabel)}</b> and make them schedulable again.`;

        Swal.fire({
            icon: "question",
            title: confirmTitle,
            html: confirmHtml,
            showCancelButton: true,
            confirmButtonText: selectedMemberIds.length > 0 ? "Save Merge" : "Clear Merge",
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $("#btnSaveScheduleMerge, #btnClearMergeSelection").prop("disabled", true);

            $.ajax({
                url: "../backend/query_class_schedule.php",
                type: "POST",
                dataType: "json",
                data: {
                    save_schedule_merge: 1,
                    owner_offering_id: ownerOfferingId,
                    member_offering_ids_json: JSON.stringify(selectedMemberIds)
                },
                success: function (res) {
                    if (res && res.status === "ok") {
                        Swal.fire({
                            icon: "success",
                            title: selectedMemberIds.length > 0 ? "Merge Saved" : "Merge Cleared",
                            html: escapeHtml(res.message || "Merge group updated."),
                            timer: 1400,
                            showConfirmButton: false,
                            customClass: { popup: "swal-top" }
                        });

                        $("#scheduleMergeModal").modal("hide");
                        setTimeout(function () {
                            loadScheduleTable();
                        }, 300);
                        return;
                    }

                    $("#btnSaveScheduleMerge").prop("disabled", false);
                    updateScheduleMergeSelectionSummary();
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        html: (res && res.message) ? res.message : "Failed to save the merge group.",
                        customClass: { popup: "swal-top" }
                    });
                },
                error: function (xhr) {
                    $("#btnSaveScheduleMerge").prop("disabled", false);
                    updateScheduleMergeSelectionSummary();
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        html: xhr.responseText || "Failed to save the merge group.",
                        customClass: { popup: "swal-top" }
                    });
                }
            });
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

        if (!blockHasSuggestionFilter(block)) {
            block.suggestionsVisible = false;
            block.suggestions = [];
            block.availabilityTimeSlots = [];
            block.availabilitySelectedKey = "";
            block.suggestionMessage = "Choose at least one day, time, or room first.";
            renderScheduleBlocks();
            return;
        }

        const board = $(`#blockSuggestionBoard_${blockKey}`);
        board.html(`<div class="suggestion-empty">Loading availability...</div>`);

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
                    block.suggestionMessage = (res && res.message) ? String(res.message) : "Failed to load availability.";
                    block.suggestions = [];
                    block.availabilityTimeSlots = [];
                    block.availabilitySelectedKey = "";
                    board.html(`<div class="suggestion-empty">${escapeHtml((res && res.message) ? res.message : "Failed to load availability.")}</div>`);
                    return;
                }

                block.suggestionMessage = String((res && res.message) ? res.message : "");
                block.suggestionsVisible = true;
                if (String(res.mode || "") === "availability_finder") {
                    const timeSlots = Array.isArray(res.time_slots) ? res.time_slots : [];
                    const currentSelectedKey = String(block.availabilitySelectedKey || "");
                    const responseSelectedKey = String(res.selected_time_key || "");
                    const firstKey = String((timeSlots[0] && timeSlots[0].time_key) || "");
                    const hasCurrentSelection = timeSlots.some(item => String(item.time_key || "") === currentSelectedKey);
                    const hasResponseSelection = timeSlots.some(item => String(item.time_key || "") === responseSelectedKey);

                    block.suggestions = [];
                    block.availabilityTimeSlots = timeSlots;
                    block.availabilitySelectedKey = hasCurrentSelection
                        ? currentSelectedKey
                        : (hasResponseSelection ? responseSelectedKey : firstKey);
                } else {
                    block.suggestions = Array.isArray(res.suggestions) ? res.suggestions : [];
                    block.availabilityTimeSlots = [];
                    block.availabilitySelectedKey = "";
                }
                renderScheduleBlocks();
            },
            error: function () {
                block.suggestionMessage = "Failed to load availability.";
                block.suggestions = [];
                block.availabilityTimeSlots = [];
                block.availabilitySelectedKey = "";
                board.html(`<div class="suggestion-empty">Failed to load availability.</div>`);
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
        renderRoomBrowser();
    }

    function getScheduleSortMode() {
        const value = String($("#scheduleSortMode").val() || "year_level").trim().toLowerCase();
        return value === "subject" ? "subject" : "year_level";
    }

    function updateScheduleGroupCounts() {
        $("#scheduleListContainer .schedule-group-card").each(function () {
            const card = $(this);
            const visibleRows = card.find("tbody tr.schedule-offering-row[data-search-match='1']").length;
            const label = String(card.find(".schedule-group-count").data("countLabel") || "class(es)");
            card.find(".schedule-group-count").text(`${visibleRows} ${label}`);
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
                        <div class="suggestion-slot">${escapeHtml(item.days_label || "")} | ${escapeHtml(item.time_label || "")}</div>
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


    function captureWindowScrollPosition() {
        return {
            left: window.pageXOffset || document.documentElement.scrollLeft || 0,
            top: window.pageYOffset || document.documentElement.scrollTop || 0
        };
    }

    function restoreWindowScrollPosition(position) {
        if (!position) {
            return;
        }

        window.scrollTo(
            Number(position.left || 0),
            Number(position.top || 0)
        );
    }

    function captureMatrixScrollState() {
        const wrap = $("#matrixContainer .matrix-scroll-wrap").get(0);
        return {
            left: wrap ? wrap.scrollLeft : 0,
            top: wrap ? wrap.scrollTop : 0
        };
    }

    function restoreMatrixScrollState(state) {
        if (!state) {
            return;
        }

        const wrap = $("#matrixContainer .matrix-scroll-wrap").get(0);
        if (!wrap) {
            return;
        }

        wrap.scrollLeft = Number(state.left || 0);
        wrap.scrollTop = Number(state.top || 0);
    }

    function loadScheduleTable(forceRoomReload = true, options = {}) {
        const preservePagePosition = options.preservePagePosition !== false;
        const pagePosition = preservePagePosition ? captureWindowScrollPosition() : null;

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
                        semester: sem,
                        sort_by: getScheduleSortMode()
                    },
                    function (rows) {
                        $("#scheduleListContainer").html(rows);
                        initializeScheduleTablePan();
                        applyScheduleSearchFilter();
                        renderRoomBrowser();
                        if (pagePosition) {
                            window.requestAnimationFrame(function () {
                                restoreWindowScrollPosition(pagePosition);
                            });
                        }
                    }
                ).fail(function (xhr) {
                    if (xhr.statusText === "abort") {
                        return;
                    }
                    $("#scheduleListContainer").html(
                        "<div class='text-center text-danger py-4'>Failed to load classes.</div>"
                    );
                    renderRoomBrowser();
                    console.error(xhr.responseText);
                    if (pagePosition) {
                        window.requestAnimationFrame(function () {
                            restoreWindowScrollPosition(pagePosition);
                        });
                    }
                });
            });
    }

    function loadRoomTimeMatrix(openModal = false, options = {}) {
        const preservePosition = options.preservePosition !== false;
        const pagePosition = preservePosition ? captureWindowScrollPosition() : null;
        const matrixScrollState = preservePosition ? captureMatrixScrollState() : null;
        const ay = $("#ay_id").val();
        const sem = $("#semester").val();

        if (!ay || !sem) {
            if (openModal) {
                Swal.fire(
                    "Missing Filters",
                    "Please select Academic Year and Semester first.",
                    "warning"
                );
            }
            return;
        }

        if (openModal) {
            if (matrixModalInstance) {
                matrixModalInstance.show();
            } else {
                $("#matrixModal").modal("show");
            }
        }

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
                if (preservePosition) {
                    window.requestAnimationFrame(function () {
                        restoreMatrixScrollState(matrixScrollState);
                        restoreWindowScrollPosition(pagePosition);
                    });
                }
            }
        ).fail(function (xhr) {
            $("#matrixContainer").html(
                "<div class='text-danger text-center'>Failed to load matrix.</div>"
            );
            console.error(xhr.responseText);
            if (pagePosition) {
                window.requestAnimationFrame(function () {
                    restoreWindowScrollPosition(pagePosition);
                });
            }
        });
    }

    function refreshRoomTimeMatrixIfOpen() {
        if ($("#matrixModal").hasClass("show")) {
            loadRoomTimeMatrix(false);
        }
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

            function isInteractiveTarget(target) {
                return target instanceof Element && Boolean(
                    target.closest("button, a, input, select, textarea, label, summary, [role='button'], [contenteditable='true'], .btn, .select2-container, .select2-selection")
                );
            }

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

                if (isInteractiveTarget(event.target)) {
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
            });

            shell.addEventListener("pointermove", function (event) {
                if (activePointerId === null || event.pointerId !== activePointerId) {
                    return;
                }

                const deltaX = event.clientX - startX;
                if (!isDragging && Math.abs(deltaX) > 6) {
                    isDragging = true;
                    shell.classList.add("is-pan-active");

                    if (shell.setPointerCapture) {
                        try {
                            shell.setPointerCapture(activePointerId);
                        } catch (error) {
                            // Ignore browsers that do not support pointer capture in this context.
                        }
                    }
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
            semester: $("#semester").val(),
            sort_by: getScheduleSortMode()
        };
    }

    function abortScheduleSetListRequest() {
        if (scheduleSetListRequest && scheduleSetListRequest.readyState !== 4) {
            scheduleSetListRequest.abort();
        }
    }

    function renderScheduleSetDetailHtml(html) {
        $("#scheduleSetDetails").html(html);
    }

    function renderScheduleSetDetailText(message) {
        renderScheduleSetDetailHtml(escapeHtml(message || ""));
    }

    function formatScheduleSetTimestamp(value) {
        const raw = String(value || "").trim();
        if (raw === "") {
            return "Unknown update";
        }

        const normalized = raw.replace(" ", "T");
        const parsed = new Date(normalized);
        if (Number.isNaN(parsed.getTime())) {
            return raw;
        }

        return parsed.toLocaleString();
    }

    function getSelectedScheduleSet() {
        const selectedId = String($("#scheduleSetSelect").val() || "");
        if (selectedId === "") {
            return null;
        }

        return scheduleSetList.find(item => String(item.schedule_set_id) === selectedId) || null;
    }

    function updateScheduleSetControls() {
        const filters = getCurrentScheduleFilters();
        const hasFilters = Boolean(filters.ay_id && filters.semester);
        const hasSets = scheduleSetList.length > 0;
        const hasSelection = Boolean(getSelectedScheduleSet());

        $("#scheduleSetSelect").prop("disabled", !hasFilters || !hasSets);
        $("#btnRefreshScheduleSets, #btnSaveScheduleSet").prop("disabled", !hasFilters);
        $("#btnLoadScheduleSet").prop("disabled", !hasFilters || !hasSelection);
    }

    function updateScheduleSetDetails() {
        const filters = getCurrentScheduleFilters();
        const hasFilters = Boolean(filters.ay_id && filters.semester);
        const selectedSet = getSelectedScheduleSet();

        if (!hasFilters) {
            renderScheduleSetDetailText("Live schedules remain the active workspace. Select Academic Year and Semester first to manage saved sets for the whole college.");
            updateScheduleSetControls();
            return;
        }

        if (!selectedSet) {
            if (scheduleSetList.length === 0) {
                renderScheduleSetDetailText("No saved schedule sets were found for this college term yet. Save the current live workspace to keep a reusable version for all programs in the college.");
            } else {
                renderScheduleSetDetailText("Select a saved schedule set to review its snapshot details and load it back into the live workspace for the whole college term.");
            }
            updateScheduleSetControls();
            return;
        }

        let html = `<strong>${escapeHtml(selectedSet.set_name || "Saved Set")}</strong> includes <strong>${escapeHtml(formatCountLabel(selectedSet.row_count, "row"))}</strong> across <strong>${escapeHtml(formatCountLabel(selectedSet.offering_count, "offering"))}</strong>.`;
        html += ` Updated ${escapeHtml(formatScheduleSetTimestamp(selectedSet.date_updated || selectedSet.date_created))}.`;

        if (String(selectedSet.remarks || "").trim() !== "") {
            html += ` ${escapeHtml(String(selectedSet.remarks || "").trim())}`;
        }

        html += " Loading this set will replace the current live schedules for the selected college term across all programs.";
        renderScheduleSetDetailHtml(html);
        updateScheduleSetControls();
    }

    function loadScheduleSets(selectedId = null) {
        const filters = getCurrentScheduleFilters();
        const hasFilters = Boolean(filters.ay_id && filters.semester);

        abortScheduleSetListRequest();

        if (!hasFilters) {
            scheduleSetList = [];
            $("#scheduleSetSelect")
                .html('<option value="">Select Academic Year and Semester first</option>')
                .val("");
            updateScheduleSetDetails();
            return;
        }

        const preferredId = selectedId === null
            ? String($("#scheduleSetSelect").val() || "")
            : String(selectedId || "");

        scheduleSetList = [];
        $("#scheduleSetSelect")
            .html('<option value="">Loading saved sets...</option>')
            .prop("disabled", true);
        renderScheduleSetDetailText("Loading saved schedule sets...");
        updateScheduleSetControls();

        scheduleSetListRequest = $.ajax({
            url: "../backend/query_class_schedule.php",
            type: "POST",
            dataType: "json",
            data: {
                load_schedule_sets: 1,
                ay_id: filters.ay_id,
                semester: filters.semester
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    scheduleSetList = [];
                    $("#scheduleSetSelect")
                        .html('<option value="">Unable to load saved sets</option>')
                        .val("");
                    renderScheduleSetDetailText((res && res.message) ? res.message : "Failed to load saved schedule sets.");
                    updateScheduleSetControls();
                    return;
                }

                scheduleSetList = Array.isArray(res.sets) ? res.sets : [];

                if (scheduleSetList.length === 0) {
                    $("#scheduleSetSelect")
                        .html('<option value="">No saved sets yet</option>')
                        .val("");
                    updateScheduleSetDetails();
                    return;
                }

                const optionsHtml = ['<option value="">Select a saved set...</option>']
                    .concat(scheduleSetList.map(item => (
                        `<option value="${escapeHtml(item.schedule_set_id)}">${escapeHtml(item.set_name || `Set ${item.schedule_set_id}`)}</option>`
                    )))
                    .join("");

                $("#scheduleSetSelect").html(optionsHtml);

                if (preferredId !== "" && scheduleSetList.some(item => String(item.schedule_set_id) === preferredId)) {
                    $("#scheduleSetSelect").val(preferredId);
                }

                updateScheduleSetDetails();
            },
            error: function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                scheduleSetList = [];
                $("#scheduleSetSelect")
                    .html('<option value="">Unable to load saved sets</option>')
                    .val("");
                renderScheduleSetDetailText("Failed to load saved schedule sets.");
                updateScheduleSetControls();
            }
        });
    }

    function suggestNextScheduleSetName() {
        return `Set ${scheduleSetList.length + 1}`;
    }

    function saveLiveScheduleAsSet(setName, overwriteExisting = false, options = {}) {
        const filters = getCurrentScheduleFilters();
        const afterSuccess = typeof options.afterSuccess === "function" ? options.afterSuccess : null;
        if (!filters.ay_id || !filters.semester) {
            Swal.fire("Missing Filters", "Select Academic Year and Semester first.", "warning");
            return;
        }

        Swal.fire({
            title: overwriteExisting ? "Updating Saved Set..." : "Saving Set...",
            html: "Please wait while the current live schedules for the selected college term are copied into the saved set.",
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
                save_schedule_set: 1,
                ay_id: filters.ay_id,
                semester: filters.semester,
                set_name: setName,
                overwrite_existing_set: overwriteExisting ? 1 : 0
            },
            success: function (res) {
                Swal.close();

                if (res && res.status === "exists") {
                    Swal.fire({
                        icon: "question",
                        title: "Overwrite Saved Set?",
                        html: `<b>${escapeHtml(setName)}</b> already exists for this college term. Replace it with the current live schedules across all programs?`,
                        showCancelButton: true,
                        confirmButtonText: "Overwrite Set",
                        cancelButtonText: "Cancel",
                        allowOutsideClick: false,
                        customClass: { popup: "swal-top" }
                    }).then(function (result) {
                        if (!result.isConfirmed) {
                            return;
                        }
                        saveLiveScheduleAsSet(setName, true, options);
                    });
                    return;
                }

                if (res && res.status === "ok") {
                    const rowCount = Number(res.row_count || 0);
                    const offeringCount = Number(res.offering_count || 0);

                    Swal.fire({
                        icon: "success",
                        title: overwriteExisting ? "Set Updated" : "Set Saved",
                        html: `<b>${escapeHtml(res.set_name || setName)}</b> now stores <b>${escapeHtml(String(rowCount))}</b> schedule row(s) across <b>${escapeHtml(String(offeringCount))}</b> offering(s) for the selected college term.<br><br>Use <b>Clear All Schedules</b> when you want a blank live workspace for the next version.`,
                        allowOutsideClick: false,
                        customClass: { popup: "swal-top" },
                        confirmButtonText: afterSuccess ? "Continue" : "OK"
                    }).then(function () {
                        loadScheduleSets(String(res.schedule_set_id || ""));
                        if (afterSuccess) {
                            afterSuccess(res);
                        }
                    });
                    return;
                }

                Swal.fire("Error", (res && res.message) ? res.message : "Failed to save the current live schedules as a set.", "error");
            },
            error: function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                Swal.close();
                Swal.fire("Error", xhr.responseText || "Failed to save the current live schedules as a set.", "error");
            }
        });
    }

    function promptSaveScheduleSet(options = {}) {
        const filters = getCurrentScheduleFilters();
        const title = String(options.title || "Save Live as Set");
        const inputLabel = String(options.inputLabel || "Set name");
        const inputPlaceholder = String(options.inputPlaceholder || "Enter a saved set name");
        const confirmButtonText = String(options.confirmButtonText || "Save Set");
        const afterSave = typeof options.afterSave === "function" ? options.afterSave : null;
        if (!filters.ay_id || !filters.semester) {
            Swal.fire("Missing Filters", "Select Academic Year and Semester first.", "warning");
            return;
        }

        Swal.fire({
            title: title,
            input: "text",
            inputValue: suggestNextScheduleSetName(),
            inputLabel: inputLabel,
            inputPlaceholder: inputPlaceholder,
            showCancelButton: true,
            confirmButtonText: confirmButtonText,
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" },
            inputValidator: function (value) {
                const normalized = String(value || "").trim();
                if (normalized === "") {
                    return "Provide a name for this saved schedule set.";
                }
                return "";
            }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            saveLiveScheduleAsSet(String(result.value || "").trim(), false, {
                afterSuccess: afterSave
            });
        });
    }

    function executeLoadScheduleSetIntoLive(selectedSet) {
        Swal.fire({
            title: "Loading Saved Set...",
            html: "Please wait while the saved set replaces the current live schedules.",
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
                load_schedule_set_into_live: 1,
                schedule_set_id: selectedSet.schedule_set_id
            },
            success: function (res) {
                Swal.close();

                if (res && res.status === "conflict") {
                    Swal.fire({
                        icon: "error",
                        title: "Schedule Conflict",
                        html: res.message,
                        allowOutsideClick: false,
                        customClass: { popup: "swal-top" }
                    });
                    return;
                }

                if (res && res.status === "ok") {
                    const affectedCount = Number(res.affected_offering_count || 0);
                    const loadedCount = Number(res.loaded_offering_count || 0);
                    const rowCount = Number(res.loaded_row_count || 0);

                    Swal.fire({
                        icon: "success",
                        title: "Set Loaded",
                        html: `<b>${escapeHtml(res.set_name || selectedSet.set_name || "Saved Set")}</b> is now the live schedule workspace.<br><br>Affected offerings: <b>${escapeHtml(String(affectedCount))}</b><br>Offerings loaded from the set: <b>${escapeHtml(String(loadedCount))}</b><br>Schedule rows inserted: <b>${escapeHtml(String(rowCount))}</b>`,
                        allowOutsideClick: false,
                        customClass: { popup: "swal-top" }
                    });

                    $("#scheduleModal").modal("hide");
                    $("#dualScheduleModal").modal("hide");
                    $("#blockScheduleModal").modal("hide");
                    $("#autoDraftModal").modal("hide");

                    resetAutoDraftPreview();
                    loadScheduleSets(String(res.schedule_set_id || selectedSet.schedule_set_id || ""));
                    loadScheduleTable();
                    refreshRoomTimeMatrixIfOpen();
                    return;
                }

                Swal.fire("Error", (res && res.message) ? res.message : "Failed to load the saved set into live schedules.", "error");
            },
            error: function (xhr) {
                if (xhr.statusText === "abort") {
                    return;
                }

                Swal.close();
                Swal.fire("Error", xhr.responseText || "Failed to load the saved set into live schedules.", "error");
            }
        });
    }

    function loadSelectedScheduleSetIntoLive() {
        const selectedSet = getSelectedScheduleSet();
        if (!selectedSet) {
            Swal.fire("No Saved Set", "Select a saved schedule set first.", "warning");
            return;
        }

        Swal.fire({
            icon: "question",
            title: "Save Current Live Workspace First?",
            html: `You are about to load <b>${escapeHtml(selectedSet.set_name || "the selected set")}</b>, which will replace the current live schedules for this college term across all programs.<br><br>Do you want to save the current live workspace as a set first?`,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: "Save First",
            denyButtonText: "Load Without Saving",
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" }
        }).then(function (result) {
            if (result.isConfirmed) {
                promptSaveScheduleSet({
                    title: "Save Current Live Workspace",
                    inputLabel: "Set name for the current live workspace",
                    inputPlaceholder: "Enter a set name before loading",
                    confirmButtonText: "Save and Continue",
                    afterSave: function () {
                        executeLoadScheduleSetIntoLive(selectedSet);
                    }
                });
                return;
            }

            if (result.isDenied) {
                executeLoadScheduleSetIntoLive(selectedSet);
            }
        });
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
                    <div class="suggestion-slot mt-2">${escapeHtml(block.days_label || "")} | ${escapeHtml(block.time_label || "")}</div>
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

    function clearScheduleForOffering(offeringId, subjectLabel, options = {}) {
        if (!offeringId) {
            Swal.fire("Error", "Missing offering reference.", "error");
            return;
        }

        const label = subjectLabel || "this class";
        const confirmTitle = options.confirmTitle || "Clear Schedule?";
        const confirmHtml = options.confirmHtml || `This will remove all saved schedules for <b>${escapeHtml(label)}</b>, including paired lecture and laboratory rows if this offering has both.`;
        const confirmButtonText = options.confirmButtonText || "Yes, clear it";
        const successTitle = options.successTitle || "Schedule Cleared";

        const runClearRequest = function () {
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
                            title: successTitle,
                            timer: 1200,
                            showConfirmButton: false
                        });

                        $("#scheduleModal").modal("hide");
                        $("#dualScheduleModal").modal("hide");
                        $("#blockScheduleModal").modal("hide");

                        setTimeout(function () {
                            loadScheduleTable();
                            refreshRoomTimeMatrixIfOpen();
                            if (typeof options.afterSuccess === "function") {
                                options.afterSuccess(res);
                            }
                        }, 300);
                        return;
                    }

                    Swal.fire("Error", res.message || "Failed to clear schedule.", "error");
                },
                error: function (xhr) {
                    Swal.fire("Error", xhr.responseText || "Failed to clear schedule.", "error");
                }
            });
        };

        Swal.fire({
            icon: "warning",
            title: confirmTitle,
            html: confirmHtml,
            showCancelButton: true,
            confirmButtonText: confirmButtonText,
            cancelButtonText: "Cancel",
            allowOutsideClick: false,
            customClass: { popup: "swal-top" }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            runClearRequest();
        });
    }

    function clearAllSchedulesInScope() {
        const filters = getCurrentScheduleFilters();
        if (!filters.ay_id || !filters.semester) {
            Swal.fire("Missing Filters", "Select Academic Year and Semester first.", "warning");
            return;
        }

        const $button = $("#btnClearAllSchedules");
        const originalHtml = $button.html();

        Swal.fire({
            icon: "warning",
            title: "Clear All Schedules?",
            html: [
                "This will remove all <b>live schedules</b> for <b>all programs in the selected college academic year and semester</b>.",
                "Saved schedule sets will remain available to load later.",
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
                html: "Please wait while all scheduled subjects in the selected college term are being reset.",
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

                    let html = `Checked <b>${escapeHtml(String(scopedCount))}</b> offering(s) in the selected college term.`;
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

const roomBrowserElement = document.getElementById("roomBrowserDrawer");
if (roomBrowserElement) {
  roomBrowserDrawerInstance = bootstrap.Offcanvas.getOrCreateInstance(roomBrowserElement);
}
const matrixModalElement = document.getElementById("matrixModal");
if (matrixModalElement) {
  matrixModalInstance = bootstrap.Modal.getOrCreateInstance(matrixModalElement);
}
const sectionScheduleMatrixModalElement = document.getElementById("sectionScheduleMatrixModal");
if (sectionScheduleMatrixModalElement) {
  sectionScheduleMatrixModalInstance = bootstrap.Modal.getOrCreateInstance(sectionScheduleMatrixModalElement);
}
const facultyScheduleMatrixModalElement = document.getElementById("facultyScheduleMatrixModal");
if (facultyScheduleMatrixModalElement) {
  facultyScheduleMatrixModalInstance = bootstrap.Modal.getOrCreateInstance(facultyScheduleMatrixModalElement);
}
renderRoomBrowser();

$("#prospectus_id, #ay_id, #semester").on("change", function () {
  if (this.id === "ay_id" || this.id === "semester") {
    clearTermRoomOptions();
  }
  resetAutoDraftPreview();
  loadScheduleSets();
  scheduleAutoLoad(true);
});

$("#scheduleSetSelect").on("change", function () {
  updateScheduleSetDetails();
});

$("#btnRefreshScheduleSets").on("click", function () {
  loadScheduleSets(String($("#scheduleSetSelect").val() || ""));
});

$("#btnSaveScheduleSet").on("click", function () {
  promptSaveScheduleSet();
});

$("#btnLoadScheduleSet").on("click", function () {
  loadSelectedScheduleSetIntoLive();
});

$("#scheduleSortMode").on("change", function () {
  scheduleAutoLoad(false);
});

$("#scheduleSubjectSearch").on("input", function () {
  applyScheduleSearchFilter();
  renderRoomBrowser();
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

$("#btnOpenRoomBrowser").on("click", function () {
  openRoomBrowserHelper();
});

$(document).on("click", ".btn-open-modal-matrix", function () {
  openMatrixHelper();
});

$(document).on("click", "#blockScheduleModal .btn-open-section-matrix", function () {
  openSectionScheduleMatrixHelper();
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
  $("#sched_subject_label, #sched_section_label").text("");
  $("#sched_context_line").addClass("d-none");
  resetSingleSuggestionPanel();
});

$("#dualScheduleModal").on("hidden.bs.modal", function () {
  $("#dual_subject_label, #dual_section_label").text("");
  $("#dual_context_line").addClass("d-none");
  resetDualSuggestionPanels();
});

$("#autoDraftModal").on("hidden.bs.modal", function () {
  if (autoDraftRequest && autoDraftRequest.readyState !== 4) {
    autoDraftRequest.abort();
  }
  stopAutoDraftLoader();
});

$("#matrixModal").on("hidden.bs.modal", function () {
  restoreBodyModalStateForScheduling();
});

$("#sectionScheduleMatrixModal").on("show.bs.modal", function () {
  $(this).appendTo("body").css("z-index", 1110);
});

$("#sectionScheduleMatrixModal").on("hidden.bs.modal", function () {
  abortSectionScheduleMatrixRequest();
  sectionScheduleMatrixState = null;
  $("#sectionScheduleMatrixContainer").html('<div class="section-matrix-empty">Loading section matrix...</div>');
  $("#sectionScheduleMatrixHeaderNote").text("Compare scheduled subjects across peer sections without leaving the block editor.");
  restoreBodyModalStateForScheduling();
});

$("#facultyScheduleMatrixModal").on("show.bs.modal", function () {
  $(this).appendTo("body").css("z-index", 1115);
  renderFacultyScheduleMatrix();
});

$("#facultyScheduleMatrixModal").on("hidden.bs.modal", function () {
  $("#facultyScheduleMatrixContainer").html('<div class="faculty-matrix-empty">Select a faculty and click Show Workload.</div>');
  $("#facultyScheduleMatrixHeaderNote").text("View the selected faculty schedule in the same day-by-time board layout.");
  restoreBodyModalStateForScheduling();
});

$(document).on("click", "#sectionScheduleMatrixModal .btn-section-matrix-day", function () {
  if (!sectionScheduleMatrixState) {
    return;
  }

  sectionScheduleMatrixState.selectedDay = String($(this).data("day") || "");
  renderSectionScheduleMatrix();
});

$(document).on("change", "#blockScheduleFacultySelect", function () {
  if (!scheduleBlockState || !scheduleBlockState.facultyHelper) {
    return;
  }

  const selectedFacultyId = parseInt($(this).val(), 10) || 0;
  loadScheduleBlockFacultyDetails(selectedFacultyId, {
    openMatrix: $("#facultyScheduleMatrixModal").hasClass("show")
  });
});

$("#btnOpenFacultyScheduleMatrix").on("click", function () {
  openFacultyScheduleMatrixHelper();
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
loadScheduleSets();


$("#btnShowMatrix").on("click", function () {
  loadRoomTimeMatrix(true);
});

$(document).on("click", ".matrix-entry", function (event) {
  const entry = $(this);
  const offeringId = Number(entry.data("offeringId") || 0);
  const removable = String(entry.attr("data-removable") || entry.data("removable") || "0") === "1";
  const subCode = String(entry.data("subCode") || "").trim();
  const sectionName = String(entry.data("sectionName") || "").trim();
  const typeLabel = String(entry.data("typeLabel") || "").trim();
  const collegeCode = String(entry.data("collegeCode") || "").trim();
  const labelParts = [subCode, sectionName].filter(Boolean);
  const offeringLabel = labelParts.join(" - ") || "this offering";

  event.preventDefault();

  if (!removable || !offeringId) {
    const ownerLine = collegeCode ? ` It belongs to <b>${escapeHtml(collegeCode)}</b>.` : "";
    Swal.fire({
      icon: "info",
      title: "View-Only Schedule",
      html: `This matrix entry cannot be removed from your scheduler account.${ownerLine}`,
      customClass: { popup: "swal-top" }
    });
    return;
  }

  const blockLabel = typeLabel ? `<b>${escapeHtml(typeLabel)}</b>` : "this";
  clearScheduleForOffering(offeringId, offeringLabel, {
    confirmTitle: "Remove Offering Schedule?",
    confirmHtml: `You selected the ${blockLabel} block for <b>${escapeHtml(offeringLabel)}</b> in the Room-Time Matrix.<br><br>Removing it here clears the <b>entire offering schedule</b>, including any paired lecture and laboratory rows.`,
    confirmButtonText: "Remove Entire Schedule",
    successTitle: "Offering Schedule Removed"
  });
});

$(document).on("keydown", ".matrix-entry", function (event) {
  if (event.key !== "Enter" && event.key !== " ") {
    return;
  }

  event.preventDefault();
  $(this).trigger("click");
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
    const subjectLabel = subCode + " - " + subDesc;

    // ============================
    // CASE A - LECTURE ONLY
    // ============================
    if (labUnits === 0) {

        // Populate existing modal
        $("#sched_offering_id").val(offeringId);
        $("#sched_subject_label").text(subjectLabel);
        $("#sched_section_label").text("Section: " + section);
        $("#sched_context_line").removeClass("d-none");
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
// CASE B - LECTURE + LAB
// ============================
$("#dual_offering_id").val(offeringId);
$("#dual_subject_label").text(subjectLabel);
$("#dual_section_label").text("Section: " + section);
$("#dual_context_line").removeClass("d-none");
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
// EDIT MODE -> LOAD EXISTING
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
    if ($("#sectionScheduleMatrixModal").hasClass("show")) {
        if (sectionScheduleMatrixModalInstance) {
            sectionScheduleMatrixModalInstance.hide();
        } else {
            $("#sectionScheduleMatrixModal").modal("hide");
        }
    }

    if ($("#facultyScheduleMatrixModal").hasClass("show")) {
        if (facultyScheduleMatrixModalInstance) {
            facultyScheduleMatrixModalInstance.hide();
        } else {
            $("#facultyScheduleMatrixModal").modal("hide");
        }
    }

    abortScheduleBlockFacultyRequests();

    scheduleBlockState = null;
    $("#block_sched_subject_label, #block_sched_section_label").text("");
    $("#block_sched_context_line").addClass("d-none");
    $("#scheduleBlockList").empty();
    $("#scheduleBlockCoverageSummary").html("<strong>Schedule coverage will appear here.</strong>");
    $("#blockScheduleFacultySelect").html('<option value="">Select faculty...</option>').prop("disabled", true);
    $("#blockScheduleFacultyHint").text("Faculty from the selected college term will load here.");
    $("#blockScheduleFacultySummary").html('<div class="faculty-helper-empty">Select a faculty to preview scheduled subjects for this term.</div>');
    $("#btnOpenFacultyScheduleMatrix").prop("disabled", true);
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
    refreshFacultyAwarenessModalIfOpen();
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

$(document).on("click", "#blockScheduleModal .btn-select-block-time-slot", function () {
    if (!scheduleBlockState) {
        return;
    }

    const blockKey = String($(this).data("blockKey") || "");
    const timeKey = String($(this).data("timeKey") || "");
    const block = scheduleBlockState.blocks.find(item => item.block_key === blockKey);
    if (!block || !Array.isArray(block.availabilityTimeSlots)) {
        return;
    }

    const hasMatch = block.availabilityTimeSlots.some(item => String(item.time_key || "") === timeKey);
    if (!hasMatch) {
        return;
    }

    block.availabilitySelectedKey = timeKey;
    renderScheduleBlocks();
});

$(document).on("click", "#blockScheduleModal .btn-use-block-suggestion", function () {
    const blockKey = String($(this).data("blockKey") || "");
    let days = [];
    try {
        days = JSON.parse($(this).attr("data-days") || "[]");
    } catch (error) {
        days = [];
    }

    applyScheduleBlockSuggestion(
        blockKey,
        String($(this).data("roomId") || ""),
        String($(this).data("timeStart") || ""),
        String($(this).data("timeEnd") || ""),
        days
    );
});

$(document).on("click", "#facultyScheduleMatrixModal .btn-use-faculty-awareness-slot", function () {
    let days = [];
    try {
        days = JSON.parse($(this).attr("data-days") || "[]");
    } catch (error) {
        days = [];
    }

    applyScheduleBlockSuggestion(
        String($(this).data("blockKey") || ""),
        String($(this).data("roomId") || ""),
        String($(this).data("timeStart") || ""),
        String($(this).data("timeEnd") || ""),
        days
    );
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

$("#scheduleMergeModal").on("hidden.bs.modal", function () {
    abortScheduleMergeRequest();
    resetScheduleMergeModalState();
});

$(document).off("click", ".btn-merge-schedule").on("click", ".btn-merge-schedule", function () {
    openScheduleMergeModal($(this));
});

$(document).on("change", ".schedule-merge-checkbox", function () {
    updateScheduleMergeSelectionSummary();
});

$("#btnClearMergeSelection").on("click", function () {
    $("#scheduleMergeCandidateList .schedule-merge-checkbox:not(:disabled)").prop("checked", false);
    updateScheduleMergeSelectionSummary();
});

$("#btnSaveScheduleMerge").on("click", function () {
    saveScheduleMergeGroup();
});

resetScheduleMergeModalState();

});




</script>


</body>
</html>
