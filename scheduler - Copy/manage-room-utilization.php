<?php 
session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$college_id   = $_SESSION['college_id'];
$college_name = $_SESSION['college_name'] ?? '';

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
                                echo "<option value='{$ayval}'>{$ayval}</option>";
                            }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Semester</label>
                    <select id="ru_semester" class="form-select">
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                        <option value="Midyear">Midyear</option>
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
        <div class="card-header">
            <h5 class="m-0">Room Utilization – Overview (All Rooms)</h5>
            <small class="text-muted">Displays all rooms horizontally per time slot.</small>
        </div>
        <div class="card-body p-0">
            <div id="allRoomsWrapper" class="table-responsive"></div>
        </div>
    </div>

</div>

<?php include '../footer.php'; ?>

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

    $.post('../backend/query_room_utilization.php', {
        load_room_schedule: 1,
        ay: ay,
        semester: semester,
        room_id: room_id
    }, function (res) {

        let data = [];
        try { data = JSON.parse(res); } catch (e) {}

        if (!data.length) {
            $("#roomTimetableWrapper").html(
                '<div class="p-3 text-muted text-center">No schedule found</div>'
            );
            return;
        }

        renderRoomTimetable(data);
    });
}

function renderRoomTimetable(data) {

    const days = [
        { key: "M",  label: "Mon" },
        { key: "T",  label: "Tue" },
        { key: "W",  label: "Wed" },
        { key: "TH", label: "Thu" },
        { key: "F",  label: "Fri" },
        { key: "S",  label: "Sat" }
    ];

    const slots = generateTimeSlots();

    let html = `
    <table class="table table-bordered table-sm mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:90px">TIME</th>
                ${days.map(d => `<th class="text-center">${d.label}</th>`).join('')}
            </tr>
        </thead>
        <tbody>
    `;

    slots.forEach(slot => {

        html += `
        <tr>
            <td class="small text-nowrap">
                ${minutesToAMPM(slot.start)}<br>—<br>${minutesToAMPM(slot.end)}
            </td>
        `;

        days.forEach(day => {

            let found = data.find(item => {
                let days = item.days_raw || [];
                if (!days.includes(day.key)) return false;

                let s = timeToMinutes(item.time_start.substring(0,5));
                let e = timeToMinutes(item.time_end.substring(0,5));

                return slot.start >= s && slot.start < e;
            });

            if (found) {
                let bg = getColorForSubject(found.subject_code);
                html += `
                <td style="background:${bg}" class="text-center">
                    <div class="small fw-semibold">${found.subject_code}</div>
                    <div class="small">${found.section_name}</div>
                    <div class="small text-muted">${found.faculty_name}</div>
                </td>`;
            } else {
                html += `<td></td>`;
            }
        });

        html += `</tr>`;
    });

    html += `</tbody></table>`;

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

        $.post('../backend/query_room_utilization.php', {
            load_all_rooms: 1,
            ay: ay,
            semester: semester
        }, function (res) {
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
        <table class="table table-bordered table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>ROOM</th>
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

            let slotMap = new Array(slots.length).fill(null);

            room.items.forEach(item => {
                let start = timeToMinutes(item.time_start.substring(0,5));
                let end   = timeToMinutes(item.time_end.substring(0,5));
                let span  = Math.ceil((end - start) / 30);

                let startIdx = slots.findIndex(s => s.start === start);
                if (startIdx === -1) return;

                for (let i = startIdx; i < startIdx + span; i++) {
                    if (i < slotMap.length) slotMap[i] = item;
                }
            });

            html += `<tr><td class="fw-semibold">${room.room_code}</td>`;

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
                    <div class="small fw-semibold">${item.subject_code}</div>
                    <div class="small">${item.section_name}</div>
                    <div class="small text-muted">${item.faculty_name}</div>
                </td>`;

                i += span;
            }

            html += `</tr>`;
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
