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
        .ru-cell {
            min-width: 130px;
        }
        .ru-cell div.small {
            line-height: 1.1;
        }

        .ru-hover-highlight {
            outline: 3px solid #ffbf00 !important; /* gold highlight */
            z-index: 10;
            position: relative;
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
$(document).ready(function(){

    // INIT SELECT2
    $('.select2-single').select2({
        width: '100%',
        placeholder: "Select...",
        allowClear: true
    });

    // ============================================================
    // COLOR MAP PER SUBJECT (CONSISTENT)
    // ============================================================
    let subjectColorMap = {};

    function getColorForSubject(subjCode) {
        if (!subjectColorMap[subjCode]) {
            subjectColorMap[subjCode] = generateRandomPastelColor();
        }
        return subjectColorMap[subjCode];
    }

    function generateRandomPastelColor() {
        const hue = Math.floor(Math.random() * 360);
        return `hsl(${hue}, 70%, 85%)`;
    }

    // ============================================================
    // FILTER CHANGE HANDLING
    // ============================================================
    $("#ru_ay, #ru_semester").on('change', function() {
        if ($("#btnModeAll").hasClass("active")) {
            loadAllRoomsOverview();
        } else {
            loadRoomSchedule();
        }
    });

    $("#ru_room_id").on('change', function() {
        if (!$("#btnModeAll").hasClass("active")) {
            loadRoomSchedule();
        }
    });

    // ============================================================
    // LOAD SINGLE ROOM SCHEDULE
    // ============================================================
    function loadRoomSchedule() {
        let ay       = $("#ru_ay").val().trim();
        let semester = $("#ru_semester").val();
        let room_id  = $("#ru_room_id").val();

        if (!ay || !room_id) {
            $("#roomTimetableWrapper").html("");
            $("#roomTimetableCard").hide();
            return;
        }

        $.post('../backend/query_workload.php', {
            load_room_schedule: 1,
            ay: ay,
            semester: semester,
            room_id: room_id
        }, function(res){
            try {
                let data = JSON.parse(res);

                if (!Array.isArray(data) || data.length === 0) {
                    $("#roomTimetableWrapper").html(
                        '<div class="p-3 text-center text-muted">No scheduled classes for this room.</div>'
                    );
                    $("#roomTimetableCard").show();
                    updateRoomHeader();
                    return;
                }

                renderRoomTimetable(data);
                updateRoomHeader();

            } catch(e) {
                $("#roomTimetableWrapper").html(
                    '<div class="p-3 text-center text-danger">Error loading room schedule.</div>'
                );
                $("#roomTimetableCard").show();
            }
        });
    }

    function updateRoomHeader() {
        let roomText = $("#ru_room_id option:selected").text() || "Room";
        let ay       = $("#ru_ay").val().trim();
        let semester = $("#ru_semester").val();

        $("#ruRoomLabel").text(roomText);
        $("#ruTermLabel").text(semester + " Semester • A.Y. " + ay);
        $("#roomTimetableCard").show();
    }

    // ============================================================
    // TIME HELPERS
    // ============================================================
    function generateTimeSlots() {
        let slots = [];
        let startMinutes = 7 * 60;
        let endMinutes   = 19 * 60;

        for (let m = startMinutes; m < endMinutes; m += 30) {
            let hh = String(Math.floor(m / 60)).padStart(2, '0');
            let mm = String(m % 60).padStart(2, '0');
            slots.push({ label: hh + ":" + mm, minutes: m });
        }
        return slots;
    }

    function timeStringToMinutes(t) {
        if (!t) return 0;
        let parts = t.split(':');
        let hh = parseInt(parts[0] || "0", 10);
        let mm = parseInt(parts[1] || "0", 10);
        return hh * 60 + mm;
    }

    // ============================================================
    // SINGLE ROOM TIMETABLE (COLORED)
    // ============================================================
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
                        <th style="width: 90px;">Time</th>
                        ${days.map(d => `<th class="text-center">${d.label}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
        `;

        slots.forEach((slot, idx) => {
            let nextSlot = (idx < slots.length - 1) ? slots[idx + 1] : { minutes: slot.minutes + 30 };
            let endHour = String(Math.floor(nextSlot.minutes / 60)).padStart(2,'0');
            let endMin  = String(nextSlot.minutes % 60).padStart(2,'0');
            let endLabel = endHour + ":" + endMin;

            html += `<tr><td><small>${slot.label}–${endLabel}</small></td>`;

            days.forEach(day => {
                let matchedItem = null;

                data.forEach(function(item) {
                    let days_raw = item.days_raw || [];
                    if (days_raw.indexOf(day.key) === -1) return;

                    let ts = (item.time_start || '').substring(0,5);
                    let te = (item.time_end   || '').substring(0,5);
                    if (!ts || !te) return;

                    let slotMin  = slot.minutes;
                    let startMin = timeStringToMinutes(ts);
                    let endMin   = timeStringToMinutes(te);

                    if (slotMin >= startMin && slotMin < endMin) {
                        matchedItem = item;
                    }
                });

                if (matchedItem) {
                    let bg = getColorForSubject(matchedItem.subject_code);
                    html += `
                        <td class="align-middle text-center ru-cell" 
                            style="background:${bg}">
                            <div class="small fw-semibold">${matchedItem.subject_code}</div>
                            <div class="small">${matchedItem.section_name}</div>
                            <div class="small text-muted">${matchedItem.faculty_name || ""}</div>
                        </td>
                    `;
                } else {
                    html += `<td class="align-middle text-center ru-cell"></td>`;
                }
            });

            html += `</tr>`;
        });

        html += `</tbody></table>`;

        $("#roomTimetableWrapper").html(html);
        $("#roomTimetableCard").show();
    }

    // ============================================================
    // OVERVIEW MODE – LOAD ALL ROOMS
    // ============================================================
    function loadAllRoomsOverview() {
        let ay       = $("#ru_ay").val().trim();
        let semester = $("#ru_semester").val();

        if (!ay) {
            $("#allRoomsWrapper").html("");
            return;
        }

        $.post('../backend/query_workload.php', {
            load_all_rooms: 1,
            ay: ay,
            semester: semester
        }, function(res){
            let data = [];
            try { data = JSON.parse(res); }
            catch(e){ return; }

            renderAllRoomsTable(data);
        });
    }

    // ============================================================
    // OVERVIEW MODE – MERGED BLOCKS + HOVER + CLICK
    // ============================================================
    function renderAllRoomsTable(roomsData) {

        const slots = generateTimeSlots();

        let html = `
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="white-space:nowrap;">Room</th>
                        ${slots.map(s => `<th class="text-center"><small>${s.label}</small></th>`).join('')}
                    </tr>
                </thead>
                <tbody>
        `;

        roomsData.forEach(room => {

            html += `<tr>
                <td class="fw-semibold">${room.room_code}</td>
            `;

            let slotItems = new Array(slots.length).fill(null);

            room.items.forEach(item => {
                let start = timeStringToMinutes(item.time_start.substring(0,5));
                let end   = timeStringToMinutes(item.time_end.substring(0,5));

                let startIdx = slots.findIndex(s => s.minutes >= start);
                let endIdx   = slots.findIndex(s => s.minutes >= end) - 1;

                if (startIdx < 0) startIdx = 0;
                if (endIdx < startIdx) endIdx = startIdx;
                if (endIdx >= slots.length) endIdx = slots.length - 1;

                for (let i = startIdx; i <= endIdx; i++) {
                    slotItems[i] = item;
                }
            });

            for (let i = 0; i < slots.length; ) {

                let item = slotItems[i];

                if (!item) {
                    html += `<td></td>`;
                    i++;
                    continue;
                }

                let j = i + 1;
                while (j < slots.length && slotItems[j] === item) {
                    j++;
                }

                let span = j - i;
                let bg = getColorForSubject(item.subject_code);
                let blockId = `${room.room_id}_${item.subject_code}_${i}`;

                html += `
                    <td colspan="${span}" 
                        class="ru-block"
                        data-block-id="${blockId}"
                        data-subject="${item.subject_code}"
                        data-section="${item.section_name}"
                        data-faculty="${item.faculty_name}"
                        data-start="${item.time_start}"
                        data-end="${item.time_end}"
                        data-room="${room.room_code}"
                        style="background:${bg}; 
                               text-align:center;
                               cursor:pointer;">
                        <div class="small fw-semibold">${item.subject_code}</div>
                        <div class="small">${item.section_name}</div>
                        <div class="small text-muted">${item.faculty_name}</div>
                    </td>
                `;

                i = j;
            }

            html += `</tr>`;
        });

        html += `</tbody></table>`;
        $("#allRoomsWrapper").html(html);
    }

    // ============================================================
    // HOVER EFFECT + MODAL CLICK DETAILS
    // ============================================================
    $(document).on("mouseenter", ".ru-block", function() {
        let blockId = $(this).data("block-id");
        $(`.ru-block[data-block-id="${blockId}"]`).addClass("ru-hover");
    });

    $(document).on("mouseleave", ".ru-block", function() {
        let blockId = $(this).data("block-id");
        $(`.ru-block[data-block-id="${blockId}"]`).removeClass("ru-hover");
    });

    $(document).on("click", ".ru-block", function() {
        Swal.fire({
            title: `<strong>${$(this).data("subject")} — ${$(this).data("section")}</strong>`,
            html: `
                <div style='text-align:left;'>
                    <p><b>Faculty:</b> ${$(this).data("faculty")}</p>
                    <p><b>Room:</b> ${$(this).data("room")}</p>
                    <p><b>Time:</b> ${$(this).data("start")} — ${$(this).data("end")}</p>
                </div>
            `,
            icon: "info",
            confirmButtonText: "Close"
        });
    });

    // ============================================================
    // MODE SWITCHING
    // ============================================================
    $("#btnModeSingle").click(function(){
        $(this).addClass("active");
        $("#btnModeAll").removeClass("active");

        $("#allRoomsCard").hide();
        $("#roomTimetableCard").hide();

        $("#ru_room_id").prop("disabled", false);
        loadRoomSchedule();
    });

    $("#btnModeAll").click(function(){
        $(this).addClass("active");
        $("#btnModeSingle").removeClass("active");

        $("#roomTimetableCard").hide();
        $("#allRoomsWrapper").html("");
        $("#allRoomsCard").show();

        $("#ru_room_id").prop("disabled", true).val("").trigger("change");

        loadAllRoomsOverview();
    });

});
</script>

<style>
/* Hover highlight for merged blocks */
.ru-hover {
    outline: 3px solid #ffbf00 !important;
    z-index: 10;
    position: relative;
    border-radius: 4px;
}
</style>


</body>
</html>
