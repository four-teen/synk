<?php 
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$college_id   = $_SESSION['college_id'];
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"/>

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

<style>
    /* ===============================
       SELECT2 CONSISTENCY
    =============================== */
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

    /* ===============================
       ROOM UTILIZATION LABELS
    =============================== */
    .ru-room-label {
        font-weight: 600;
    }
    .ru-term-label {
        font-size: 0.85rem;
        color: #6c757d;
    }

    /* ===============================
       GRID CELLS (BODY)
    =============================== */
    .ru-cell {
        min-width: 70px;          /* 🔽 reduced from 130px */
        padding: 4px 6px;
    }
    .ru-cell div.small {
        line-height: 1.1;
        font-size: 0.72rem;
    }

    /* ===============================
       TIME HEADER (IMPORTANT PART)
    =============================== */
    .ru-time-header {
        width: 70px;              /* 🔥 controls column width */
        min-width: 70px;
        max-width: 70px;
        font-size: 0.72rem;
        white-space: nowrap;
        padding: 4px 2px;
        text-align: center;
    }

    /* ===============================
       SCHEDULE BLOCKS
    =============================== */
    .ru-block {
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 4px;
        text-align: center;
    }

    /* ===============================
       HOVER HIGHLIGHT
    =============================== */
    .ru-hover,
    .ru-hover-highlight {
        outline: 3px solid #ffbf00 !important; /* gold highlight */
        z-index: 10;
        position: relative;
        border-radius: 4px;
    }


    /* ===============================
   ROOM COLUMN – AUTO WIDTH & WRAP
   (Overview Table Only)
=============================== */
.ru-overview-table th:first-child,
.ru-overview-table td:first-child {
    white-space: normal;          /* allow wrapping */
    width: auto;                  /* auto-adjust width */
    min-width: 140px;             /* readable minimum */
    max-width: 220px;             /* prevent over-expansion */
    word-break: break-word;       /* wrap long words */
    line-height: 1.2;
    vertical-align: middle;
}


    /* ===============================
       ROOM COLUMN – NO WRAP
    =============================== */
    table th:first-child,
    table td:first-child {
        white-space: nowrap;      /* prevent wrapping */
        width: 90px;              /* fixed width for ROOM column */
        min-width: 90px;
        max-width: 90px;
        text-align: left;
    }
    .ru-loader {
        position: fixed;
        inset: 0;
        background: rgba(255,255,255,0.85);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .ru-loader-box {
        text-align: center;
    }

    /* SINGLE ROOM TIME COLUMN */
    .ru-time-cell {
        white-space: nowrap;      /* 🔥 prevent wrapping */
        min-width: 95px;          /* adjust width if needed */
        text-align: center;
        font-size: 0.75rem;
        line-height: 1.2;
    }
    /* ===============================
    PAN / DRAG SCROLL CURSOR
    =============================== */
    .ru-pan {
        cursor: grab;
    }
    .ru-pan:active {
        cursor: grabbing;
    }

    /* ===============================
    DAY COLUMN (SLIM + CENTERED)
    =============================== */
    .ru-overview-table th:nth-child(2),
    .ru-overview-table td:nth-child(2) {
        width: 55px;
        min-width: 55px;
        max-width: 55px;
        text-align: center;
        font-weight: 600;
        white-space: nowrap;
    }

    /* =====================================================
    ROOM UTILIZATION – FULLSCREEN MODE (FIXED UX)
    ===================================================== */
    .ru-fullscreen {
        position: fixed !important;
        inset: 12px;                 /* 🔥 padding from all sides */
        z-index: 9999;
        background: #ffffff;
        display: flex;
        flex-direction: column;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.25);
    }

    /* Lock body scroll */
    body.ru-lock {
        overflow: hidden;
    }

    /* Header stays natural size */
    .ru-fullscreen .card-header {
        flex-shrink: 0;
    }

    /* Scrollable table area */
    .ru-fullscreen #allRoomsWrapper {
        flex: 1;
        overflow: auto;              /* 🔥 vertical + horizontal */
        padding: 18px;               /* 🔥 inner breathing space */
        background: #f8f9fa;
    }

    /* =====================================================
    FORCE SCROLL – FULLSCREEN OVERRIDE (SNEAT FIX)
    ===================================================== */

    /* Allow native scrolling inside fullscreen */
    .ru-fullscreen {
        overflow: hidden !important;
    }

    /* THIS is the real scroll container */
    .ru-fullscreen #allRoomsWrapper {
        overflow-y: auto !important;
        overflow-x: auto !important;
        height: 100% !important;
        max-height: calc(100vh - 120px) !important;
        overscroll-behavior: contain;
    }

    /* Disable perfect-scrollbar interference */
    .ru-fullscreen .ps {
        overflow: auto !important;
    }

    /* =====================================================
    NORMAL MODE – CONSTRAIN OVERVIEW HEIGHT
    ===================================================== */

    #allRoomsCard:not(.ru-fullscreen) #allRoomsWrapper {
        max-height: 1000px;          /* adjust if needed */
        overflow-x: auto;
        overflow-y: auto;
        padding: 8px;
        background: #f8f9fa;
        border-top: 1px solid #e5e7eb;
    }

    /* =====================================================
    SINGLE ROOM REPORT – FIX TIME COLUMN OVERLAP
    (WIDER + NO WRAP)
    ===================================================== */
    .ru-room-report-table {
        table-layout: fixed; /* force column widths to apply */
        width: 100%;
    }

    .ru-room-report-table th,
    .ru-room-report-table td {
        vertical-align: middle;
    }

    .ru-room-report-table th:nth-child(1),
    .ru-room-report-table td:nth-child(1) {
        width: 220px;          /* ✅ wider TIME column */
        min-width: 220px;
        white-space: nowrap;   /* ✅ prevent wrapping */
    }

    .ru-room-report-table td:nth-child(1) {
        font-size: 0.85rem;    /* optional: makes time clearer */
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

    <!-- MODE SWITCHER -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="btn-group" role="group">
                <button class="btn btn-outline-primary active" id="btnModeSingle">Single Room View</button>
                <button class="btn btn-outline-primary" id="btnModeAll">Overview (All Rooms)</button>
            </div>
        </div>
    </div>

    <!-- FILTERS CARD -->
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
                        <?php
                        $r_sql = "SELECT room_id, room_code, room_name 
                                  FROM tbl_rooms 
                                  WHERE college_id='$college_id'
                                  ORDER BY room_code";
                        $r_run = mysqli_query($conn, $r_sql);
                        while ($r = mysqli_fetch_assoc($r_run)) {
                            $rid = (int)$r['room_id'];
                            $rm  = $r['room_code'].' '.($r['room_name'] ? '— '.$r['room_name'] : '');
                            echo "<option value='{$rid}'>".htmlspecialchars($rm)."</option>";
                        }
                        ?>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ROOM TIMETABLE CARD (SINGLE ROOM) -->
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


<!-- ALL ROOMS OVERVIEW -->
<div class="card" id="allRoomsCard" style="display:none;">

    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="m-0">Room Utilization – Overview (All Rooms)</h5>
            <small class="text-muted">Displays all rooms horizontally per time slot.</small>
        </div>

        <!-- FULLSCREEN BUTTON -->
        <button class="btn btn-sm btn-outline-secondary" id="btnFullscreenRU">
            <i class="bx bx-expand"></i>
        </button>
    </div>

    <div class="card-body p-0">
        <div id="allRoomsWrapper"></div>
    </div>

</div>


</div>

<?php include '../footer.php'; ?>

<!-- GLOBAL LOADER -->
<div id="ruLoader" class="ru-loader d-none">
    <div class="ru-loader-box">
        <div class="spinner-border text-primary mb-2" role="status"></div>
        <div class="small fw-semibold">Loading room utilization…</div>
    </div>
</div>

</div>
</div>
</div>

<!-- JS -->
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

/* =====================================================
   FULLSCREEN TOGGLE – ROOM UTILIZATION
===================================================== */
$("#btnFullscreenRU").on("click", function () {

    const card = $("#allRoomsCard");
    const body = $("body");
    const icon = $(this).find("i");

    card.toggleClass("ru-fullscreen");
    body.toggleClass("ru-lock");

    // 🔥 DISABLE PERFECT SCROLLBAR WHEN FULLSCREEN
    if (card.hasClass("ru-fullscreen")) {
        card.find(".ps").each(function () {
            this.style.overflow = "auto";
        });
        icon.removeClass("bx-expand").addClass("bx-collapse");
    } else {
        icon.removeClass("bx-collapse").addClass("bx-expand");
    }
});



    /* =====================================================
    PAN / DRAG SCROLL (OVERVIEW TABLE)
    ===================================================== */
    (function enablePanScroll() {

        const container = document.getElementById('allRoomsWrapper');
        if (!container) return;

        let isDown = false;
        let startX;
        let scrollLeft;

        container.classList.add('ru-pan');

        container.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
        });

        container.addEventListener('mouseleave', () => {
            isDown = false;
        });

        container.addEventListener('mouseup', () => {
            isDown = false;
        });

        container.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - container.offsetLeft;
            const walk = (x - startX) * 1.2; // scroll speed
            container.scrollLeft = scrollLeft - walk;
        });

    })();


function showLoader() {
    $("#ruLoader").removeClass("d-none");
}

function hideLoader() {
    $("#ruLoader").addClass("d-none");
}

/* =====================================================
   LOAD SINGLE ROOM SCHEDULE
===================================================== */
function loadRoomSchedule() {

    let ay       = $("#ru_ay").val();
    let semester = $("#ru_semester").val();
    let room_id  = $("#ru_room_id").val();

    if (!ay || !room_id) {
        $("#roomTimetableWrapper").html(
            '<div class="p-3 text-muted text-center">Select A.Y. and Room</div>'
        );
        return;
    }

    showLoader(); // 🔥 START LOADER

    $.post('../backend/query_room_utilization.php', {
        load_room_schedule: 1,
        ay: ay,
        semester: semester,
        room_id: room_id
    }, function (res) {

        hideLoader(); // 🔥 STOP LOADER

        let data = [];
        try { data = JSON.parse(res); } catch (e) {}

        if (!data.length) {
            $("#roomTimetableWrapper").html(
                '<div class="p-3 text-muted text-center">No schedule found</div>'
            );
            return;
        }

        // renderRoomTimetable(data);
        renderRoomReport(data);
    });
}

/* =====================================================
   SINGLE ROOM VIEW (REPORT TABLE FORMAT)
===================================================== */
function renderRoomReport(data) {

    /* =====================================================
    LUNCH BREAK RULE (12:00 PM – 1:00 PM)
    ===================================================== */
    const LUNCH_START = 12 * 60; // 12:00 PM
    const LUNCH_END   = 13 * 60; // 1:00 PM


    // Build label (optional but nice UX)
    const ay = $("#ru_ay").val();
    const semLabel = $("#ru_semester").val();
    const roomText = $("#ru_room_id option:selected").text();

    $("#ruRoomLabel").text("Room: " + roomText);
    $("#ruTermLabel").text("A.Y. " + ay + " • " + semLabel);

    /* =====================================================
    GROUP BY DAY PATTERN (NORMALIZED)
    - Forces canonical order: MW, T, Th, TTh, F, S
    - Fixes cases like ["Th","T"] becoming "ThT" (wrong)
    ===================================================== */
    function normalizeDayKey(daysArr) {
        const order = { "M": 1, "T": 2, "W": 3, "TH": 4, "F": 5, "S": 6 };

        // Normalize raw values -> consistent tokens
        const cleaned = (daysArr || [])
            .map(d => (d || "").toUpperCase().trim())   // "Th" -> "TH"
            .filter(Boolean);

        // Sort by canonical weekday order
        cleaned.sort((a, b) => (order[a] || 99) - (order[b] || 99));

        // Convert to display-friendly labels
        // TH should display as "Th"
        const display = cleaned.map(d => (d === "TH" ? "Th" : d));

        // Join into a single key like MW, TTh
        return display.join("");
    }

    /* =====================================================
    GROUPING RULE (TARGET FORMAT)
    - Put T, Th, and TTh into ONE bucket: "TTh"
    - Still keep row prefix per item: T / Th / TTh
    ===================================================== */
    let groups = {};

    data.forEach(item => {

        const normalized = normalizeDayKey(item.days_raw); // ex: "T", "Th", "TTh", "MW"
        const up = normalized.toUpperCase();               // "T", "TH", "TTH", "MW"

        // ✅ Bucket logic:
        // Any Tue/Thu-related schedule goes under ONE header: "TTh"
        // (covers T-only, Th-only, and TTh)
        const bucketKey = (up.includes("T") || up.includes("TH")) ? "TTh" : normalized;

        // keep the row prefix (what appears in TIME column)
        // T stays T, Th stays Th, TTh stays TTh
        item._rowPrefix = normalized;

        if (!groups[bucketKey]) groups[bucketKey] = [];
        groups[bucketKey].push(item);
    });



    // Sort each day group by time_start
    Object.keys(groups).forEach(k => {
        groups[k].sort((a,b) => a.time_start.localeCompare(b.time_start));
    });

/* =====================================================
   INSERT LUNCH BREAK ROW IF GAP EXISTS
===================================================== */
Object.keys(groups).forEach(dayKey => {

    const items = groups[dayKey];

    // Check if any class overlaps lunch
    const hasLunchClass = items.some(it => {
        const start = timeToMinutes(it.time_start.substring(0,5));
        const end   = timeToMinutes(it.time_end.substring(0,5));
        return !(end <= LUNCH_START || start >= LUNCH_END);
    });

    if (!hasLunchClass) {
        items.push({
            _isLunch: true,
            _rowPrefix: dayKey,
            time_start: "12:00",
            time_end: "13:00",
            subject_code: "",
            section_name: "",
            expected_students: "",
        });

        // Re-sort including lunch
        items.sort((a,b) => a.time_start.localeCompare(b.time_start));
    }
});


    // Helper: format time "07:30:00" -> "07:30 AM"
    function formatTimeStr(t) {
        // expects "HH:MM:SS" or "HH:MM"
        const hhmm = t.substring(0,5);
        const mins = timeToMinutes(hhmm);
        return minutesToAMPM(mins);
    }

    let html = `
    <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0 ru-room-report-table">
            <thead class="table-light">
                <tr>
                    <th style="width:170px">TIME</th>
                    <th style="width:120px">COURSE</th>
                    <th style="width:160px">SECTION</th>
                    <th style="width:140px" class="text-center">EXPECTED NO.<br>OF STUDENTS</th>
                    <th>REMARKS</th>
                </tr>
            </thead>
            <tbody>
    `;

    /* =====================================================
    DAY BLOCK DISPLAY ORDER (BUCKETS)
    ===================================================== */
    const dayOrder = ['MW', 'TTh', 'F', 'S'];

    const dayKeys = Object.keys(groups).sort((a, b) => {
        const ia = dayOrder.indexOf(a);
        const ib = dayOrder.indexOf(b);
        return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
    });


    if (!dayKeys.length) {
        html += `
            <tr>
                <td colspan="5" class="text-center text-muted p-3">
                    No schedule found
                </td>
            </tr>
        `;
    } else {

        dayKeys.forEach(dayKey => {

            // Day header row (like MW / TTh)
            html += `
                <tr class="table-secondary">
                    <td colspan="5" class="fw-bold">${dayKey}</td>
                </tr>
            `;

groups[dayKey].forEach(item => {

    const timeRange = `${formatTimeStr(item.time_start)}–${formatTimeStr(item.time_end)}`;

    // 🍽 LUNCH BREAK ROW
    if (item._isLunch) {
        html += `
            <tr style="background:#fff3cd;">
                <td class="fw-semibold">${item._rowPrefix} ${timeRange}</td>
                <td colspan="3" class="text-center fst-italic text-muted">
                    Lunch Break
                </td>
                <td></td>
            </tr>
        `;
        return;
    }

    // 🟦 NORMAL CLASS ROW
    const expected = item.expected_students ?? '';

    html += `
        <tr>
            <td>${item._rowPrefix} ${timeRange}</td>
            <td class="fw-semibold">${item.subject_code}</td>
            <td>${item.section_name}</td>
            <td class="text-center">${expected}</td>
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


    /* =====================================================
       TIME HELPERS
    ===================================================== */
    function timeToMinutes(t) {
        let [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }

    function minutesToAMPM(mins) {
        let h = Math.floor(mins / 60);
        let m = mins % 60;
        let ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${String(m).padStart(2,'0')} ${ampm}`;
    }

    function generateTimeSlots() {
        let slots = [];
        let start = 7 * 60 + 30; // 7:30 AM
        let end   = 17 * 60;     // 5:00 PM

        for (let m = start; m < end; m += 30) {
            slots.push({
                start: m,
                end: m + 30
            });
        }
        return slots;
    }

    /* =====================================================
       COLORS
    ===================================================== */
    let subjectColorMap = {};

    function getColorForSubject(code) {
        if (!subjectColorMap[code]) {
            let hue = Math.floor(Math.random() * 360);
            subjectColorMap[code] = `hsl(${hue},70%,85%)`;
        }
        return subjectColorMap[code];
    }

    /* =====================================================
       LOAD ALL ROOMS OVERVIEW
    ===================================================== */
function loadAllRoomsOverview() {

    let ay = $("#ru_ay").val();
    let semester = $("#ru_semester").val();

    if (!ay) return;

    showLoader(); // 🔥 START LOADER

    $.post('../backend/query_room_utilization.php', {
        load_all_rooms: 1,
        ay: ay,
        semester: semester
    }, function (res) {

        hideLoader(); // 🔥 STOP LOADER

        let data = JSON.parse(res);
        renderAllRoomsTable(data);
    });
}


    /* =====================================================
       RENDER OVERVIEW TABLE (WORKING)
    ===================================================== */
    function renderAllRoomsTable(roomsData) {

        const slots = generateTimeSlots();

        let html = `
        <table class="table table-bordered table-sm mb-0 ru-overview-table">
            <thead class="table-light">
                <tr>
                    <th>ROOM</th>
                    <th class="text-center">DAY</th>
                    ${slots.map(s => `
                        <th class="ru-time-header text-center">
                            <div>${minutesToAMPM(s.start)}</div>
                            <div>—</div>
                            <div>${minutesToAMPM(s.end)}</div>
                        </th>
                    `).join('')}
                </tr>
            </thead>
            <tbody>
        `;

        roomsData.forEach(room => {

            const dayKeys = Object.keys(room.groups);
            let firstRow = true;

            dayKeys.forEach(dayKey => {

                const items = room.groups[dayKey];
                let slotMap = new Array(slots.length).fill(null);

                // Build slot map for THIS day-pattern
                items.forEach(item => {

                    let start = timeToMinutes(item.time_start.substring(0,5));
                    let end   = timeToMinutes(item.time_end.substring(0,5));
                    let span  = Math.ceil((end - start) / 30);

                    let startIdx = slots.findIndex(s => s.start === start);
                    if (startIdx === -1) return;

                    for (let i = startIdx; i < startIdx + span; i++) {
                        if (i < slotMap.length) slotMap[i] = item;
                    }
                });

                html += `<tr>`;

                // ROOM column (rowspan only once)
                if (firstRow) {
                    html += `
                        <td rowspan="${dayKeys.length}" 
                            class="fw-semibold align-middle">
                            ${room.room_code}
                        </td>
                    `;
                    firstRow = false;
                }

                // DAY pattern column
                html += `<td class="text-center fw-semibold">${dayKey}</td>`;

                // TIME SLOTS
                for (let i = 0; i < slots.length; ) {

                    let item = slotMap[i];

                    if (!item) {
                        html += `<td></td>`;
                        i++;
                        continue;
                    }

                    let start = timeToMinutes(item.time_start.substring(0,5));
                    let end   = timeToMinutes(item.time_end.substring(0,5));
                    let span  = Math.ceil((end - start) / 30);
                    let bg    = getColorForSubject(item.subject_code);

html += `
    <td colspan="${span}" class="ru-block" style="background:${bg}">
        <div class="small fw-semibold">
            ${item.subject_code} <span class="fw-normal">(${item.section_name})</span>
        </div>
        <div class="small text-muted">${item.faculty_name}</div>
    </td>
`;


                    i += span;
                }

                html += `</tr>`;
            });
        });


        html += `</tbody></table>`;
        $("#allRoomsWrapper").html(html);
    }

/* =====================================================
   MODE SWITCH (FIXED)
===================================================== */

$("#btnModeAll").click(function () {

    $(this).addClass("active");
    $("#btnModeSingle").removeClass("active");

    $("#roomTimetableCard").hide();
    $("#allRoomsCard").show();

    $("#ru_room_id").prop("disabled", true).val("").trigger("change");

    loadAllRoomsOverview(); // ✅ force load
});

$("#btnModeSingle").click(function () {

    $(this).addClass("active");
    $("#btnModeAll").removeClass("active");

    $("#allRoomsCard").hide();
    $("#roomTimetableCard").show();

    $("#ru_room_id").prop("disabled", false);

    loadRoomSchedule(); // ✅ force load
});


$("#ru_ay, #ru_semester, #ru_room_id").on("change", function () {
    reloadCurrentView();
});

function reloadCurrentView() {

    let ay = $("#ru_ay").val();
    let room = $("#ru_room_id").val();

    // ----------------------------
    // SINGLE ROOM MODE
    // ----------------------------
    if ($("#btnModeSingle").hasClass("active")) {

        if (!ay || !room) return;

        $("#allRoomsCard").hide();
        $("#roomTimetableCard").show();   // 🔥 THIS WAS MISSING
        loadRoomSchedule();
    }

    // ----------------------------
    // OVERVIEW MODE
    // ----------------------------
    if ($("#btnModeAll").hasClass("active")) {

        if (!ay) return;

        $("#roomTimetableCard").hide();
        $("#allRoomsCard").show();        // 🔥 THIS WAS MISSING
        loadAllRoomsOverview();
    }
}

    /* ===============================
       🔥 AUTO LOAD ON PAGE READY
    =============================== */

    setTimeout(() => {
        reloadCurrentView();
    }, 200);
});
</script>

</body>
</html>
