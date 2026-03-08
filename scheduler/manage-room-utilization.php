<?php
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$college_name = $_SESSION['college_name'] ?? '';
$currentTerm = synk_fetch_current_academic_term($conn);
$defaultAyLabel = (string)$currentTerm['ay_label'];
$defaultSemesterUi = '';

if ((int)$currentTerm['semester'] === 1) {
    $defaultSemesterUi = '1st';
} elseif ((int)$currentTerm['semester'] === 2) {
    $defaultSemesterUi = '2nd';
} elseif ((int)$currentTerm['semester'] === 3) {
    $defaultSemesterUi = 'Midyear';
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
    <title>Room Utilization | Synk Scheduler</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

<style>
    .select2-container--default .select2-selection--single {
        height: 45px !important;
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

    .ru-room-label {
        font-weight: 600;
    }

    .ru-term-label {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .ru-time-header {
        width: 70px;
        min-width: 70px;
        max-width: 70px;
        font-size: 0.72rem;
        white-space: nowrap;
        padding: 4px 2px;
        text-align: center;
    }

    .ru-block {
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 4px;
        text-align: center;
    }

    .ru-block.ru-clickable {
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .ru-block.ru-clickable:hover,
    .ru-block.ru-clickable:focus {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(31, 41, 55, 0.12);
        outline: none;
    }

    .ru-hover,
    .ru-hover-highlight {
        outline: 3px solid #ffbf00 !important;
        z-index: 10;
        position: relative;
        border-radius: 4px;
    }

    .ru-overview-table th:first-child,
    .ru-overview-table td:first-child {
        white-space: normal;
        width: auto;
        min-width: 140px;
        max-width: 220px;
        word-break: break-word;
        line-height: 1.2;
        vertical-align: middle;
    }

    .ru-room-report-table th:first-child,
    .ru-room-report-table td:first-child {
        white-space: nowrap;
        width: 90px;
        min-width: 90px;
        max-width: 90px;
        text-align: left;
    }

    .ru-loader {
        position: fixed;
        inset: 0;
        background: rgba(255, 255, 255, 0.85);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .ru-loader-box {
        text-align: center;
    }

    .ru-pan {
        cursor: grab;
    }

    .ru-pan:active {
        cursor: grabbing;
    }

    .ru-overview-table th:nth-child(2),
    .ru-overview-table td:nth-child(2) {
        width: 55px;
        min-width: 55px;
        max-width: 55px;
        text-align: center;
        font-weight: 600;
        white-space: nowrap;
    }

    .ru-fullscreen {
        position: fixed !important;
        inset: 12px;
        z-index: 9999;
        background: #ffffff;
        display: flex;
        flex-direction: column;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
        overflow: hidden !important;
    }

    body.ru-lock {
        overflow: hidden;
    }

    .ru-fullscreen .card-header {
        flex-shrink: 0;
    }

    .ru-fullscreen #allRoomsWrapper {
        flex: 1;
        overflow-y: auto !important;
        overflow-x: auto !important;
        height: 100% !important;
        max-height: calc(100vh - 120px) !important;
        overscroll-behavior: contain;
        padding: 18px;
        background: #f8f9fa;
    }

    .ru-fullscreen .ps {
        overflow: auto !important;
    }

    #allRoomsCard:not(.ru-fullscreen) #allRoomsWrapper {
        max-height: 1000px;
        overflow-x: auto;
        overflow-y: auto;
        padding: 8px;
        background: #f8f9fa;
        border-top: 1px solid #e5e7eb;
    }

    .ru-room-report-table {
        table-layout: fixed;
        width: 100%;
    }

    .ru-room-report-table th,
    .ru-room-report-table td {
        vertical-align: middle;
    }

    .ru-room-report-table th:nth-child(1),
    .ru-room-report-table td:nth-child(1) {
        width: 220px;
        min-width: 220px;
        white-space: nowrap;
    }

    .ru-room-report-table td:nth-child(1) {
        font-size: 0.85rem;
    }

    .ru-empty-note {
        background: #fbfcfe;
    }

    .ru-workload-modal .modal-dialog {
        max-width: 1080px;
    }

    .ru-workload-modal .modal-body {
        padding-top: 1rem;
    }

    .ru-workload-sheet {
        border: 1px solid #dbe5f1;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }

    .ru-workload-table {
        margin-bottom: 0;
        font-size: 0.84rem;
    }

    .ru-workload-table thead th {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #5f728b;
        border-bottom: 1px solid #dbe4ef;
        border-top: 1px solid #dbe4ef;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), inset 0 -1px 0 rgba(204, 216, 229, 0.9);
        white-space: nowrap;
    }

    .ru-workload-table tbody td {
        color: #5c6f88;
        border-color: #e7edf5;
        vertical-align: middle;
    }

    .ru-workload-table tfoot th,
    .ru-workload-table tfoot td {
        color: #5f728b;
        border-top: 2px solid #d7e1ec;
        background: #f9fbfd;
        vertical-align: middle;
    }

    .ru-workload-code {
        font-weight: 700;
        color: #5b6f86;
        white-space: nowrap;
    }

    .ru-workload-desc {
        color: #5f728b;
    }

    .ru-workload-days,
    .ru-workload-room {
        white-space: nowrap;
    }

    .ru-workload-time {
        white-space: normal;
        line-height: 1.08;
        min-width: 88px;
    }

    .ru-time-line {
        display: block;
        white-space: nowrap;
    }

    .ru-merged-metric {
        vertical-align: middle !important;
        background: #fbfcfe;
        font-weight: 600;
    }

    .ru-workload-summary-row th,
    .ru-workload-summary-row td {
        background: #f9fbfd;
        border-top: 1px solid #d7e1ec;
        border-bottom: 1px solid #d7e1ec;
    }

    .ru-workload-summary-label {
        color: #52657d;
        font-weight: 700;
        white-space: nowrap;
    }

    .ru-workload-summary-value {
        color: #4f6279;
        font-weight: 600;
    }

    .ru-summary-separator th,
    .ru-summary-separator td {
        border-top: 2px solid #b9c8d9 !important;
    }

    .ru-workload-total-row th,
    .ru-workload-total-row td {
        border-top: 2px solid #b7c6d8 !important;
        background: #f6f9fc;
    }

    .ru-workload-state {
        min-height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #6c7a8f;
        font-weight: 500;
    }

    .ru-workload-empty-row td {
        color: #7d8ea5;
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
        <i class="bx bx-grid-alt me-2"></i>
        Room Utilization
        <small class="text-muted">(<?= htmlspecialchars($college_name) ?>)</small>
    </h4>

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="btn-group" role="group">
                <button class="btn btn-outline-primary active" id="btnModeSingle">Single Room View</button>
                <button class="btn btn-outline-primary" id="btnModeAll">Overview (All Rooms)</button>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="m-0">Filter Room Schedule</h5>
            <small class="text-muted">Select academic year, semester, and room to view its timetable.</small>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">A.Y.</label>
                    <select id="ru_ay" class="form-select select2-single">
                        <option value="">Select A.Y.</option>
                        <?php
                        $ay = mysqli_query($conn, "SELECT ay FROM tbl_academic_years ORDER BY ay ASC");
                        while ($r = mysqli_fetch_assoc($ay)) {
                            $ayval = htmlspecialchars($r['ay']);
                            $selected = ($r['ay'] === $defaultAyLabel) ? " selected" : "";
                            echo "<option value='{$ayval}'{$selected}>{$ayval}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Semester</label>
                    <select id="ru_semester" class="form-select">
                        <option value="1st"<?= $defaultSemesterUi === '1st' ? ' selected' : '' ?>>1st Semester</option>
                        <option value="2nd"<?= $defaultSemesterUi === '2nd' ? ' selected' : '' ?>>2nd Semester</option>
                        <option value="Midyear"<?= $defaultSemesterUi === 'Midyear' ? ' selected' : '' ?>>Midyear</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Room</label>
                    <select id="ru_room_id" class="form-select select2-single">
                        <option value="">Select Room</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="roomTimetableCard" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="ru-room-label" id="ruRoomLabel">Room:</div>
                <div class="ru-term-label" id="ruTermLabel"></div>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="roomTimetableWrapper" class="table-responsive"></div>
        </div>
    </div>

    <div class="card" id="allRoomsCard" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="m-0">Room Utilization - Overview (All Rooms)</h5>
                <small class="text-muted">Displays all rooms horizontally per time slot. Click a colored class block to view the assigned faculty workload.</small>
            </div>

            <button class="btn btn-sm btn-outline-secondary" id="btnFullscreenRU">
                <i class="bx bx-expand"></i>
            </button>
        </div>

        <div class="card-body p-0">
            <div id="allRoomsWrapper"></div>
        </div>
    </div>

    <div class="modal fade ru-workload-modal" id="facultyWorkloadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="facultyWorkloadModalTitle">Faculty Workload</h5>
                        <div class="small text-muted" id="facultyWorkloadModalMeta"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="facultyWorkloadModalBody" class="ru-workload-state">Select a schedule block to view faculty workload.</div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../footer.php'; ?>

<div id="ruLoader" class="ru-loader d-none">
    <div class="ru-loader-box">
        <div class="spinner-border text-primary mb-2" role="status"></div>
        <div class="small fw-semibold" id="ruLoaderText">Loading room utilization...</div>
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
$(document).ready(function () {
    let loaderCount = 0;
    let roomOptionsRequest = null;
    let roomScheduleRequest = null;
    let overviewRequest = null;
    let facultyWorkloadRequest = null;
    const subjectColorMap = {};
    const facultyWorkloadModalEl = document.getElementById("facultyWorkloadModal");
    const facultyWorkloadModal = facultyWorkloadModalEl ? new bootstrap.Modal(facultyWorkloadModalEl) : null;

    $("#btnFullscreenRU").on("click", function () {
        const card = $("#allRoomsCard");
        const body = $("body");
        const icon = $(this).find("i");

        card.toggleClass("ru-fullscreen");
        body.toggleClass("ru-lock");

        if (card.hasClass("ru-fullscreen")) {
            card.find(".ps").each(function () {
                this.style.overflow = "auto";
            });
            icon.removeClass("bx-expand").addClass("bx-collapse");
        } else {
            icon.removeClass("bx-collapse").addClass("bx-expand");
        }
    });

    (function enablePanScroll() {
        const container = document.getElementById("allRoomsWrapper");
        if (!container) {
            return;
        }

        let isDown = false;
        let startX = 0;
        let scrollLeft = 0;

        container.classList.add("ru-pan");

        container.addEventListener("mousedown", function (event) {
            isDown = true;
            startX = event.pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
        });

        container.addEventListener("mouseleave", function () {
            isDown = false;
        });

        container.addEventListener("mouseup", function () {
            isDown = false;
        });

        container.addEventListener("mousemove", function (event) {
            if (!isDown) {
                return;
            }

            event.preventDefault();
            const x = event.pageX - container.offsetLeft;
            const walk = (x - startX) * 1.2;
            container.scrollLeft = scrollLeft - walk;
        });
    })();

    function escapeHtml(value) {
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function createResolvedPromise() {
        const deferred = $.Deferred();
        deferred.resolve();
        return deferred.promise();
    }

    function showLoader(message) {
        loaderCount += 1;
        $("#ruLoaderText").text(message || "Loading room utilization...");
        $("#ruLoader").removeClass("d-none");
    }

    function hideLoader() {
        loaderCount = Math.max(0, loaderCount - 1);
        if (loaderCount === 0) {
            $("#ruLoader").addClass("d-none");
        }
    }

    function showSingleRoomMessage(message) {
        $("#roomTimetableWrapper").html(
            `<div class="p-3 text-muted text-center">${escapeHtml(message)}</div>`
        );
    }

    function showOverviewMessage(message) {
        $("#allRoomsWrapper").html(
            `<div class="p-3 text-muted text-center">${escapeHtml(message)}</div>`
        );
    }

    function toNumber(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatNumber(value) {
        const number = toNumber(value);
        if (Math.abs(number % 1) < 0.0001) {
            return String(Math.round(number));
        }

        return number.toFixed(2).replace(/\.?0+$/, "");
    }

    function getWorkloadGroupKey(row) {
        const groupId = Number(row && row.group_id) || 0;
        if (groupId > 0) {
            return `g:${groupId}`;
        }

        const offeringId = Number(row && row.offering_id) || 0;
        if (offeringId > 0) {
            return `o:${offeringId}`;
        }

        return `w:${Number(row && row.workload_id) || 0}`;
    }

    function formatStudentCount(value) {
        const number = Math.round(toNumber(value));
        return number > 0 ? String(number) : "";
    }

    function formatCompactTime(value) {
        const raw = String(value == null ? "" : value).trim();
        if (raw === "") {
            return "";
        }

        const parts = raw.split("-");
        if (parts.length !== 2) {
            return escapeHtml(raw);
        }

        return `
            <span class="ru-time-line">${escapeHtml(parts[0].trim())}</span>
            <span class="ru-time-line">${escapeHtml(parts[1].trim())}</span>
        `;
    }

    function formatDesignationDisplay(meta) {
        const name = String(meta && (meta.designation_name || meta.designation_label) || "").trim();
        const label = String(meta && (meta.designation_label || name) || "").trim();
        const collegeName = String(meta && meta.designation_college_name || "").trim();

        if (!label) {
            return "";
        }

        if (name.toUpperCase() === "DEAN" && collegeName !== "") {
            return `${label}, ${collegeName.toUpperCase()}`;
        }

        return label;
    }

    function isPartneredWorkloadPair(currentRow, nextRow) {
        if (!currentRow || !nextRow) {
            return false;
        }

        const currentGroupId = Number(currentRow.group_id) || 0;
        const nextGroupId = Number(nextRow.group_id) || 0;

        if (currentGroupId <= 0 || currentGroupId !== nextGroupId) {
            return false;
        }

        return String(currentRow.sub_code || "") === String(nextRow.sub_code || "") &&
               String(currentRow.course || currentRow.section || "") === String(nextRow.course || nextRow.section || "") &&
               String(currentRow.type || "").toUpperCase() !== String(nextRow.type || "").toUpperCase();
    }

    function buildWorkloadDescription(row, isPaired) {
        const description = escapeHtml(row && (row.desc || row.subject_description) || "");
        if (!isPaired) {
            return description;
        }

        const typeSuffix = String(row && row.type || "LEC").toUpperCase() === "LAB" ? "lab" : "lec";
        return `${description} (${escapeHtml(typeSuffix)})`;
    }

    function setFacultyWorkloadModalState(message, isError) {
        const stateClass = isError ? "text-danger" : "";
        $("#facultyWorkloadModalBody").html(
            `<div class="ru-workload-state ${stateClass}">${escapeHtml(message)}</div>`
        );
    }

    function renderFacultyWorkloadModal(response, fallbackFacultyName, ay, semester) {
        const rows = Array.isArray(response && response.rows) ? response.rows : [];
        const meta = response && typeof response.meta === "object" && response.meta !== null ? response.meta : {};
        const facultyName = String(meta.faculty_name || fallbackFacultyName || "Faculty").trim() || "Faculty";
        const countedGroups = new Set();
        const preparations = new Set();
        let totalUnits = 0;
        let totalLec = 0;
        let totalLab = 0;
        let totalLoad = 0;
        let rowsHtml = "";

        $("#facultyWorkloadModalTitle").text(facultyName + " Workload");
        $("#facultyWorkloadModalMeta").text("AY " + ay + " | " + semester);

        if (!rows.length) {
            setFacultyWorkloadModalState("No workload rows found for this faculty in the selected term.", false);
            return;
        }

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const nextRow = rows[i + 1] || null;
            const groupKey = getWorkloadGroupKey(row);
            const isPairStart = isPartneredWorkloadPair(row, nextRow);

            if (!countedGroups.has(groupKey)) {
                countedGroups.add(groupKey);
                totalUnits += toNumber(row.units);
                totalLec += toNumber(row.lec);
                totalLab += toNumber(row.lab);
                totalLoad += toNumber(row.faculty_load);
            }

            const subjectCode = String(row.sub_code || "").trim();
            if (subjectCode !== "") {
                preparations.add(subjectCode);
            }

            if (isPairStart) {
                const mergedStudents = Math.max(
                    toNumber(row.student_count),
                    toNumber(nextRow.student_count)
                );

                rowsHtml += `
                    <tr>
                        <td class="ru-workload-code">${escapeHtml(row.sub_code)}</td>
                        <td class="ru-workload-desc">${buildWorkloadDescription(row, true)}</td>
                        <td>${escapeHtml(row.course || row.section || "")}</td>
                        <td class="ru-workload-days">${escapeHtml(row.days || "")}</td>
                        <td class="ru-workload-time">${formatCompactTime(row.time)}</td>
                        <td class="ru-workload-room">${escapeHtml(row.room || "")}</td>
                        <td class="text-center ru-merged-metric" rowspan="2">${escapeHtml(formatNumber(row.units))}</td>
                        <td class="text-center ru-merged-metric" rowspan="2">${escapeHtml(formatNumber(row.lab))}</td>
                        <td class="text-center ru-merged-metric" rowspan="2">${escapeHtml(formatNumber(row.lec))}</td>
                        <td class="text-center ru-merged-metric" rowspan="2">${escapeHtml(formatNumber(row.faculty_load))}</td>
                        <td class="text-center ru-merged-metric" rowspan="2">${escapeHtml(formatStudentCount(mergedStudents))}</td>
                    </tr>
                    <tr>
                        <td class="ru-workload-code">${escapeHtml(nextRow.sub_code)}</td>
                        <td class="ru-workload-desc">${buildWorkloadDescription(nextRow, true)}</td>
                        <td>${escapeHtml(nextRow.course || nextRow.section || "")}</td>
                        <td class="ru-workload-days">${escapeHtml(nextRow.days || "")}</td>
                        <td class="ru-workload-time">${formatCompactTime(nextRow.time)}</td>
                        <td class="ru-workload-room">${escapeHtml(nextRow.room || "")}</td>
                    </tr>
                `;
                i += 1;
                continue;
            }

            rowsHtml += `
                <tr>
                    <td class="ru-workload-code">${escapeHtml(row.sub_code)}</td>
                    <td class="ru-workload-desc">${buildWorkloadDescription(row, false)}</td>
                    <td>${escapeHtml(row.course || row.section || "")}</td>
                    <td class="ru-workload-days">${escapeHtml(row.days || "")}</td>
                    <td class="ru-workload-time">${formatCompactTime(row.time)}</td>
                    <td class="ru-workload-room">${escapeHtml(row.room || "")}</td>
                    <td class="text-center">${escapeHtml(formatNumber(row.units))}</td>
                    <td class="text-center">${escapeHtml(formatNumber(row.lab))}</td>
                    <td class="text-center">${escapeHtml(formatNumber(row.lec))}</td>
                    <td class="text-center fw-semibold">${escapeHtml(formatNumber(row.faculty_load))}</td>
                    <td class="text-center">${escapeHtml(formatStudentCount(row.student_count))}</td>
                </tr>
            `;
        }

        const totalPreparations = Math.max(Number(meta.total_preparations) || 0, preparations.size);
        const designationUnits = toNumber(meta.designation_units);
        const grandTotalLoad = totalLoad + designationUnits;
        const designationText = formatDesignationDisplay(meta);

        $("#facultyWorkloadModalBody").html(`
            <div class="ru-workload-sheet">
                <div class="table-responsive">
                    <table class="table table-hover table-sm ru-workload-table">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2">Course No.</th>
                                <th rowspan="2">Course Description</th>
                                <th rowspan="2">Course</th>
                                <th rowspan="2">Day</th>
                                <th rowspan="2">Time</th>
                                <th rowspan="2">Room</th>
                                <th rowspan="2" class="text-center">Unit</th>
                                <th colspan="2" class="text-center">No. of Hours</th>
                                <th rowspan="2" class="text-center">Load</th>
                                <th rowspan="2" class="text-center"># of<br>Students</th>
                            </tr>
                            <tr>
                                <th class="text-center">Lab</th>
                                <th class="text-center">Lec</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml || `
                                <tr class="ru-workload-empty-row">
                                    <td colspan="11" class="text-center text-muted">No workload assigned yet.</td>
                                </tr>
                            `}
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="ru-workload-summary-row">
                                <th colspan="2" class="text-start ru-workload-summary-label">Designation:</th>
                                <td colspan="4" class="ru-workload-summary-value">${escapeHtml(designationText)}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="text-center fw-semibold">${designationUnits > 0 ? escapeHtml(formatNumber(designationUnits)) : ""}</td>
                                <td></td>
                            </tr>
                            <tr class="ru-workload-summary-row ru-summary-separator">
                                <th colspan="2" class="text-start ru-workload-summary-label">No. of Prep:</th>
                                <td colspan="4" class="ru-workload-summary-value">${escapeHtml(formatNumber(totalPreparations))}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="ru-workload-summary-row ru-workload-total-row">
                                <th colspan="6" class="text-end fw-semibold">Total Load</th>
                                <th class="text-center">${escapeHtml(formatNumber(totalUnits))}</th>
                                <th class="text-center">${escapeHtml(formatNumber(totalLab))}</th>
                                <th class="text-center">${escapeHtml(formatNumber(totalLec))}</th>
                                <th class="text-center fw-semibold">${escapeHtml(formatNumber(grandTotalLoad))}</th>
                                <th class="text-center"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        `);
    }

    function loadFacultyWorkloadModal(facultyId, facultyName) {
        const ay = $("#ru_ay").val();
        const semester = $("#ru_semester").val();

        if (!facultyId || !ay || !semester || !facultyWorkloadModal) {
            return;
        }

        if (facultyWorkloadRequest) {
            facultyWorkloadRequest.abort();
        }

        $("#facultyWorkloadModalTitle").text((facultyName || "Faculty") + " Workload");
        $("#facultyWorkloadModalMeta").text("AY " + ay + " | " + semester);
        setFacultyWorkloadModalState("Loading faculty workload...", false);
        facultyWorkloadModal.show();

        facultyWorkloadRequest = $.ajax({
            url: "../backend/query_room_utilization.php",
            type: "POST",
            dataType: "json",
            data: {
                load_faculty_workload: 1,
                faculty_id: facultyId,
                ay: ay,
                semester: semester
            }
        }).done(function (response) {
            renderFacultyWorkloadModal(response, facultyName, ay, semester);
        }).fail(function (_, textStatus) {
            if (textStatus === "abort") {
                return;
            }

            setFacultyWorkloadModalState("Unable to load this faculty's workload right now.", true);
        }).always(function () {
            facultyWorkloadRequest = null;
        });
    }

    function normalizeDayKey(daysArr) {
        const order = { M: 1, T: 2, W: 3, TH: 4, F: 5, S: 6 };
        const cleaned = (daysArr || [])
            .map(function (day) {
                return String(day || "").toUpperCase().trim();
            })
            .filter(Boolean)
            .filter(function (day, index, array) {
                return array.indexOf(day) === index;
            })
            .sort(function (a, b) {
                return (order[a] || 99) - (order[b] || 99);
            });

        return cleaned.map(function (day) {
            return day === "TH" ? "Th" : day;
        }).join("");
    }

    function timeToMinutes(time) {
        const parts = String(time || "").split(":").map(Number);
        return ((parts[0] || 0) * 60) + (parts[1] || 0);
    }

    function minutesToAMPM(minutes) {
        let hour = Math.floor(minutes / 60);
        const minute = minutes % 60;
        const period = hour >= 12 ? "PM" : "AM";
        hour = hour % 12 || 12;
        return `${hour}:${String(minute).padStart(2, "0")} ${period}`;
    }

    function generateTimeSlots() {
        const slots = [];
        let start = 7 * 60 + 30;
        const end = 17 * 60;

        while (start < end) {
            slots.push({
                start: start,
                end: start + 30
            });
            start += 30;
        }

        return slots;
    }

    function getColorForSubject(code) {
        const key = String(code || "").trim().toUpperCase() || "UNASSIGNED";

        if (!subjectColorMap[key]) {
            let hash = 0;
            for (let i = 0; i < key.length; i++) {
                hash = ((hash << 5) - hash) + key.charCodeAt(i);
                hash |= 0;
            }

            const hue = Math.abs(hash) % 360;
            subjectColorMap[key] = `hsl(${hue}, 72%, 85%)`;
        }

        return subjectColorMap[key];
    }

    function setRoomOptions(rooms, selectedRoomId) {
        const availableRooms = Array.isArray(rooms) ? rooms : [];
        let html = '<option value="">Select Room</option>';

        if (!availableRooms.length) {
            html = '<option value="">No rooms available for this term</option>';
        } else {
            html += availableRooms.map(function (room) {
                return `<option value="${escapeHtml(room.room_id)}">${escapeHtml(room.label)}</option>`;
            }).join("");
        }

        $("#ru_room_id").html(html);

        const targetRoomId = String(selectedRoomId || "");
        const hasSelectedRoom = availableRooms.some(function (room) {
            return String(room.room_id) === targetRoomId;
        });

        $("#ru_room_id").val(hasSelectedRoom ? targetRoomId : "");
    }

    function loadRoomOptions() {
        const ay = $("#ru_ay").val();
        const semester = $("#ru_semester").val();
        const currentRoomId = $("#ru_room_id").val();

        if (!ay || !semester) {
            setRoomOptions([], "");
            return createResolvedPromise();
        }

        if (roomOptionsRequest) {
            roomOptionsRequest.abort();
        }

        showLoader("Loading available rooms...");

        roomOptionsRequest = $.ajax({
            url: "../backend/query_room_utilization.php",
            type: "POST",
            dataType: "json",
            data: {
                load_room_options: 1,
                ay: ay,
                semester: semester
            }
        }).done(function (response) {
            const rooms = response && response.status === "ok" ? response.rooms : [];
            setRoomOptions(rooms, currentRoomId);
        }).fail(function (_, textStatus) {
            if (textStatus === "abort") {
                return;
            }

            setRoomOptions([], "");
        }).always(function () {
            roomOptionsRequest = null;
            hideLoader();
        });

        return roomOptionsRequest;
    }

    function loadRoomSchedule() {
        const ay = $("#ru_ay").val();
        const semester = $("#ru_semester").val();
        const roomId = $("#ru_room_id").val();

        if (!ay || !roomId) {
            showSingleRoomMessage("Select A.Y. and Room");
            return;
        }

        if (roomScheduleRequest) {
            roomScheduleRequest.abort();
        }

        showLoader("Loading room schedule...");

        roomScheduleRequest = $.ajax({
            url: "../backend/query_room_utilization.php",
            type: "POST",
            dataType: "json",
            data: {
                load_room_schedule: 1,
                ay: ay,
                semester: semester,
                room_id: roomId
            }
        }).done(function (data) {
            if (!Array.isArray(data) || !data.length) {
                showSingleRoomMessage("No schedule found");
                return;
            }

            renderRoomReport(data);
        }).fail(function (_, textStatus) {
            if (textStatus === "abort") {
                return;
            }

            showSingleRoomMessage("Unable to load the selected room schedule right now.");
        }).always(function () {
            roomScheduleRequest = null;
            hideLoader();
        });
    }

    function loadAllRoomsOverview() {
        const ay = $("#ru_ay").val();
        const semester = $("#ru_semester").val();

        if (!ay) {
            showOverviewMessage("Select A.Y. to view the room overview.");
            return;
        }

        if (overviewRequest) {
            overviewRequest.abort();
        }

        showLoader("Loading room overview...");

        overviewRequest = $.ajax({
            url: "../backend/query_room_utilization.php",
            type: "POST",
            dataType: "json",
            data: {
                load_all_rooms: 1,
                ay: ay,
                semester: semester
            }
        }).done(function (data) {
            renderAllRoomsTable(data);
        }).fail(function (_, textStatus) {
            if (textStatus === "abort") {
                return;
            }

            showOverviewMessage("Unable to load the room overview right now.");
        }).always(function () {
            overviewRequest = null;
            hideLoader();
        });
    }

    function renderRoomReport(data) {
        const lunchStart = 12 * 60;
        const lunchEnd = 13 * 60;
        const ay = $("#ru_ay").val();
        const semLabel = $("#ru_semester").val();
        const roomText = $("#ru_room_id option:selected").text();
        const groups = {};

        $("#ruRoomLabel").text("Room: " + roomText);
        $("#ruTermLabel").text("A.Y. " + ay + " - " + semLabel);

        data.forEach(function (item) {
            const normalized = normalizeDayKey(item.days_raw);
            const upper = normalized.toUpperCase();
            const bucketKey = (upper.includes("T") || upper.includes("TH")) ? "TTh" : normalized;

            item._rowPrefix = normalized;

            if (!groups[bucketKey]) {
                groups[bucketKey] = [];
            }

            groups[bucketKey].push(item);
        });

        Object.keys(groups).forEach(function (key) {
            groups[key].sort(function (a, b) {
                return a.time_start.localeCompare(b.time_start);
            });

            const hasLunchClass = groups[key].some(function (item) {
                const start = timeToMinutes(String(item.time_start).substring(0, 5));
                const end = timeToMinutes(String(item.time_end).substring(0, 5));
                return !(end <= lunchStart || start >= lunchEnd);
            });

            if (!hasLunchClass) {
                groups[key].push({
                    _isLunch: true,
                    _rowPrefix: key,
                    time_start: "12:00",
                    time_end: "13:00",
                    subject_code: "",
                    section_name: "",
                    room_capacity: ""
                });

                groups[key].sort(function (a, b) {
                    return a.time_start.localeCompare(b.time_start);
                });
            }
        });

        function formatTimeStr(value) {
            return minutesToAMPM(timeToMinutes(String(value).substring(0, 5)));
        }

        const dayOrder = ["MW", "TTh", "F", "S"];
        const dayKeys = Object.keys(groups).sort(function (a, b) {
            const indexA = dayOrder.indexOf(a);
            const indexB = dayOrder.indexOf(b);
            return (indexA === -1 ? 99 : indexA) - (indexB === -1 ? 99 : indexB);
        });

        let html = `
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0 ru-room-report-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:170px">TIME</th>
                            <th style="width:120px">COURSE</th>
                            <th style="width:160px">SECTION</th>
                            <th style="width:140px" class="text-center">ROOM<br>CAPACITY</th>
                            <th>REMARKS</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        if (!dayKeys.length) {
            html += `
                <tr>
                    <td colspan="5" class="text-center text-muted p-3">No schedule found</td>
                </tr>
            `;
        } else {
            dayKeys.forEach(function (dayKey) {
                html += `
                    <tr class="table-secondary">
                        <td colspan="5" class="fw-bold">${escapeHtml(dayKey)}</td>
                    </tr>
                `;

                groups[dayKey].forEach(function (item) {
                    const timeRange = `${formatTimeStr(item.time_start)} - ${formatTimeStr(item.time_end)}`;

                    if (item._isLunch) {
                        html += `
                            <tr style="background:#fff3cd;">
                                <td class="fw-semibold">${escapeHtml(item._rowPrefix)} ${escapeHtml(timeRange)}</td>
                                <td colspan="3" class="text-center fst-italic text-muted">Lunch Break</td>
                                <td></td>
                            </tr>
                        `;
                        return;
                    }

                    html += `
                        <tr>
                            <td>${escapeHtml(item._rowPrefix)} ${escapeHtml(timeRange)}</td>
                            <td class="fw-semibold">${escapeHtml(item.subject_code)}</td>
                            <td>${escapeHtml(item.section_name)}</td>
                            <td class="text-center">${escapeHtml(item.room_capacity)}</td>
                            <td></td>
                        </tr>
                    `;
                });
            });
        }

        html += `
                    </tbody>
                </table>
            </div>
        `;

        $("#roomTimetableWrapper").html(html);
    }

    function renderAllRoomsTable(roomsData) {
        const rooms = Array.isArray(roomsData) ? roomsData : [];
        const slots = generateTimeSlots();
        const dayOrder = ["MW", "T", "Th", "TTh", "F", "S"];

        if (!rooms.length) {
            showOverviewMessage("No rooms found for the selected term.");
            return;
        }

        let html = `
            <table class="table table-bordered table-sm mb-0 ru-overview-table">
                <thead class="table-light">
                    <tr>
                        <th>ROOM</th>
                        <th class="text-center">DAY</th>
                        ${slots.map(function (slot) {
                            return `
                                <th class="ru-time-header text-center">
                                    <div>${minutesToAMPM(slot.start)}</div>
                                    <div>-</div>
                                    <div>${minutesToAMPM(slot.end)}</div>
                                </th>
                            `;
                        }).join("")}
                    </tr>
                </thead>
                <tbody>
        `;

        rooms.forEach(function (room) {
            const groups = room && room.groups ? room.groups : {};
            const dayKeys = Object.keys(groups).sort(function (a, b) {
                const indexA = dayOrder.indexOf(a);
                const indexB = dayOrder.indexOf(b);

                if (indexA === -1 && indexB === -1) {
                    return a.localeCompare(b);
                }

                return (indexA === -1 ? 99 : indexA) - (indexB === -1 ? 99 : indexB);
            });
            const roomCode = room.room_code || room.room_label || "";
            const roomLabel = room.room_label || roomCode;

            if (!dayKeys.length) {
                html += `
                    <tr class="ru-empty-row">
                        <td class="fw-semibold align-middle" title="${escapeHtml(roomLabel)}">${escapeHtml(roomCode)}</td>
                        <td class="text-center text-muted">-</td>
                        <td colspan="${slots.length}" class="text-center text-muted ru-empty-note">No scheduled classes</td>
                    </tr>
                `;
                return;
            }

            let firstRow = true;

            dayKeys.forEach(function (dayKey) {
                const items = (groups[dayKey] || []).slice().sort(function (a, b) {
                    return a.time_start.localeCompare(b.time_start);
                });
                const slotMap = new Array(slots.length).fill(null);

                items.forEach(function (item) {
                    const start = timeToMinutes(String(item.time_start).substring(0, 5));
                    const end = timeToMinutes(String(item.time_end).substring(0, 5));
                    const span = Math.ceil((end - start) / 30);
                    const startIdx = slots.findIndex(function (slot) {
                        return slot.start === start;
                    });

                    if (startIdx === -1) {
                        return;
                    }

                    for (let i = startIdx; i < startIdx + span; i++) {
                        if (i < slotMap.length) {
                            slotMap[i] = item;
                        }
                    }
                });

                html += "<tr>";

                if (firstRow) {
                    html += `
                        <td rowspan="${dayKeys.length}" class="fw-semibold align-middle" title="${escapeHtml(roomLabel)}">
                            ${escapeHtml(roomCode)}
                        </td>
                    `;
                    firstRow = false;
                }

                html += `<td class="text-center fw-semibold">${escapeHtml(dayKey)}</td>`;

                for (let i = 0; i < slots.length;) {
                    const item = slotMap[i];

                    if (!item) {
                        html += "<td></td>";
                        i += 1;
                        continue;
                    }

                    const start = timeToMinutes(String(item.time_start).substring(0, 5));
                    const end = timeToMinutes(String(item.time_end).substring(0, 5));
                    const span = Math.ceil((end - start) / 30);
                    const background = getColorForSubject(item.subject_code);
                    const facultyId = Number(item.faculty_id) || 0;
                    const isClickable = facultyId > 0;
                    const blockClass = isClickable ? "ru-block ru-clickable js-faculty-workload" : "ru-block";
                    const blockAttrs = isClickable
                        ? `data-faculty-id="${escapeHtml(facultyId)}"
                           data-faculty-name="${escapeHtml(item.faculty_name || "")}"
                           title="View ${escapeHtml(item.faculty_name || "assigned faculty")} workload"
                           role="button"
                           tabindex="0"`
                        : `title="${escapeHtml(item.faculty_name || "No assigned faculty")}"`;

                    html += `
                        <td colspan="${span}" class="${blockClass}" style="background:${background}" ${blockAttrs}>
                            <div class="small fw-semibold">
                                ${escapeHtml(item.subject_code)} <span class="fw-normal">(${escapeHtml(item.section_name)})</span>
                            </div>
                            <div class="small text-muted">${escapeHtml(item.faculty_name)}</div>
                        </td>
                    `;

                    i += span;
                }

                html += "</tr>";
            });
        });

        html += `
                </tbody>
            </table>
        `;

        $("#allRoomsWrapper").html(html);
    }

    function reloadCurrentView() {
        const ay = $("#ru_ay").val();
        const room = $("#ru_room_id").val();

        if ($("#btnModeSingle").hasClass("active")) {
            $("#allRoomsCard").hide();
            $("#roomTimetableCard").show();

            if (!ay || !room) {
                showSingleRoomMessage("Select A.Y. and Room");
                return;
            }

            loadRoomSchedule();
            return;
        }

        if ($("#btnModeAll").hasClass("active")) {
            $("#roomTimetableCard").hide();
            $("#allRoomsCard").show();

            if (!ay) {
                showOverviewMessage("Select A.Y. to view the room overview.");
                return;
            }

            loadAllRoomsOverview();
        }
    }

    $("#btnModeAll").on("click", function () {
        $(this).addClass("active");
        $("#btnModeSingle").removeClass("active");
        $("#ru_room_id").prop("disabled", true);
        reloadCurrentView();
    });

    $("#btnModeSingle").on("click", function () {
        $(this).addClass("active");
        $("#btnModeAll").removeClass("active");
        $("#ru_room_id").prop("disabled", false);
        reloadCurrentView();
    });

    $("#ru_ay, #ru_semester").on("change", function () {
        loadRoomOptions().always(function () {
            reloadCurrentView();
        });
    });

    $("#ru_room_id").on("change", function () {
        reloadCurrentView();
    });

    $(document).on("click", ".js-faculty-workload", function () {
        const facultyId = Number($(this).data("facultyId")) || 0;
        const facultyName = String($(this).data("facultyName") || "").trim();
        loadFacultyWorkloadModal(facultyId, facultyName);
    });

    $(document).on("keydown", ".js-faculty-workload", function (event) {
        if (event.key !== "Enter" && event.key !== " ") {
            return;
        }

        event.preventDefault();
        $(this).trigger("click");
    });

    showSingleRoomMessage("Select A.Y. and Room");
    showOverviewMessage("Select A.Y. to view the room overview.");

    setTimeout(function () {
        loadRoomOptions().always(function () {
            reloadCurrentView();
        });
    }, 200);
});
</script>

</body>
</html>
