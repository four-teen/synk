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
        font-size: 1.2rem;
        font-weight: 700;
        color: #2c415c;
    }

    .ru-term-label {
        font-size: 0.9rem;
        color: #6d7f95;
    }

    .ru-sheet {
        border: 1px solid #d9e2ec;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }

    .ru-sheet-table {
        width: 100%;
        margin-bottom: 0;
        table-layout: fixed;
    }

    .ru-sheet-table thead th {
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

    .ru-sheet-table tbody td {
        border-color: #d9e2ec;
        color: #576c85;
        vertical-align: top;
        background: #fff;
    }

    .ru-sheet-table th:nth-child(1),
    .ru-sheet-table td:nth-child(1) {
        width: 26%;
    }

    .ru-sheet-table th:nth-child(2),
    .ru-sheet-table td:nth-child(2) {
        width: 14%;
        text-align: center;
    }

    .ru-sheet-table th:nth-child(3),
    .ru-sheet-table td:nth-child(3) {
        width: 34%;
    }

    .ru-sheet-table th:nth-child(4),
    .ru-sheet-table td:nth-child(4) {
        width: 26%;
    }

    .ru-time-cell {
        white-space: nowrap;
        font-weight: 600;
        color: #334a63;
    }

    .ru-day-cell {
        white-space: nowrap;
        font-weight: 700;
        color: #304560;
    }

    .ru-sheet-group td {
        background: #f6f9fc;
        color: #2f455f;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }

    .ru-subject-code {
        font-weight: 700;
        color: #334a63;
    }

    .ru-subject-section {
        margin-top: 0.2rem;
        color: #6c8199;
    }

    .ru-faculty-name {
        font-weight: 700;
        color: #2d4159;
        text-transform: uppercase;
    }

    .ru-empty-state {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #73859b;
        font-weight: 500;
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

    .ru-print-trigger {
        white-space: nowrap;
    }

    @media print {
        @page {
            margin: 0.45in;
        }

        body {
            background: #fff !important;
        }

        .layout-menu,
        .layout-navbar,
        footer,
        .ru-no-print,
        #ruLoader {
            display: none !important;
        }

        .layout-wrapper,
        .layout-container,
        .layout-page,
        .content-wrapper,
        .container-xxl {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        #roomTimetableCard {
            display: block !important;
            border: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
        }

        #roomTimetableCard .card-header {
            border: 0 !important;
            padding: 0 0 12px !important;
        }

        #roomTimetableCard .card-body {
            padding: 0 !important;
        }

        .ru-room-label,
        .ru-term-label {
            color: #000 !important;
        }

        .ru-sheet {
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .ru-sheet-table thead th,
        .ru-sheet-table tbody td {
            color: #000 !important;
            border-color: #000 !important;
        }

        .ru-sheet-group td {
            background: #fff !important;
        }

        .ru-print-trigger {
            display: none !important;
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
        <i class="bx bx-grid-alt me-2"></i>
        Room Utilization
        <small class="text-muted">(<?= htmlspecialchars($college_name) ?>)</small>
    </h4>

    <div class="card mb-4 ru-no-print">
        <div class="card-header">
            <h5 class="m-0">Filter Room Schedule</h5>
            <small class="text-muted">Select academic year, semester, and room to view and print its utilization sheet.</small>
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
        <div class="card-header d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="ru-room-label" id="ruRoomLabel">Room number/name:</div>
                <div class="ru-term-label" id="ruTermLabel"></div>
            </div>
            <button type="button" class="btn btn-outline-primary ru-print-trigger" id="btnPrintRoomUtilization" disabled>
                <i class="bx bx-printer me-1"></i>Print Utilization
            </button>
        </div>
        <div class="card-body">
            <div id="roomTimetableWrapper"></div>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>

<script>
$(document).ready(function () {
    const DAY_START_MINUTES = 7 * 60;
    const DAY_END_MINUTES = 18 * 60;
    const GROUP_ORDER = ["MWF", "TTH", "S", "OTHER"];
    const DAY_SORT = {
        M: 1,
        MW: 2,
        MWF: 3,
        WF: 4,
        W: 5,
        F: 6,
        T: 1,
        TTh: 2,
        Th: 3,
        S: 1
    };

    let loaderCount = 0;
    let roomOptionsRequest = null;
    let roomScheduleRequest = null;

    if ($.fn.select2) {
        $(".select2-single").select2({
            width: "100%"
        });
    }

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

    function setPrintState(enabled) {
        $("#btnPrintRoomUtilization").prop("disabled", !enabled);
    }

    function formatSemesterLabel(semester) {
        if (!semester) {
            return "";
        }

        return semester === "Midyear" ? "Midyear" : semester + " Semester";
    }

    function updateHeader(roomText, ay, semester) {
        const title = roomText ? "Room number/name: " + roomText : "Room number/name:";
        const meta = [];

        if (ay) {
            meta.push("A.Y. " + ay);
        }

        if (semester) {
            meta.push(formatSemesterLabel(semester));
        }

        $("#ruRoomLabel").text(title);
        $("#ruTermLabel").text(meta.join(" | "));
    }

    function showRoomMessage(message) {
        const roomId = $("#ru_room_id").val();
        const roomText = roomId ? $("#ru_room_id option:selected").text() : "";
        $("#roomTimetableCard").show();
        updateHeader(roomText, $("#ru_ay").val(), $("#ru_semester").val());
        setPrintState(false);
        $("#roomTimetableWrapper").html(
            `<div class="ru-empty-state">${escapeHtml(message)}</div>`
        );
    }

    function timeToMinutes(time) {
        const parts = String(time || "").split(":").map(Number);
        return ((parts[0] || 0) * 60) + (parts[1] || 0);
    }

    function minutesToAMPM(minutes) {
        const normalized = Math.max(0, Number(minutes) || 0);
        let hour = Math.floor(normalized / 60);
        const minute = normalized % 60;
        const period = hour >= 12 ? "PM" : "AM";
        hour = hour % 12 || 12;
        return `${hour}:${String(minute).padStart(2, "0")} ${period}`;
    }

    function formatTimeRange(startMinutes, endMinutes) {
        return `${minutesToAMPM(startMinutes)} - ${minutesToAMPM(endMinutes)}`;
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

    function tokenizeDayKey(dayKey) {
        return String(dayKey || "").match(/Th|M|T|W|F|S/g) || [];
    }

    function getGroupKey(dayKey) {
        const tokens = tokenizeDayKey(dayKey);

        if (!tokens.length) {
            return "OTHER";
        }

        if (tokens.every(function (token) { return ["M", "W", "F"].indexOf(token) !== -1; })) {
            return "MWF";
        }

        if (tokens.every(function (token) { return ["T", "Th"].indexOf(token) !== -1; })) {
            return "TTH";
        }

        if (tokens.every(function (token) { return token === "S"; })) {
            return "S";
        }

        return "OTHER";
    }

    function buildTimeSegments(rows) {
        const segments = [];
        let cursor = DAY_START_MINUTES;

        if (!rows.length) {
            segments.push({
                isBlank: true,
                start: DAY_START_MINUTES,
                end: DAY_END_MINUTES
            });
            return segments;
        }

        rows.forEach(function (row) {
            let start = timeToMinutes(String(row.time_start || "").substring(0, 5));
            let end = timeToMinutes(String(row.time_end || "").substring(0, 5));

            start = Math.max(DAY_START_MINUTES, start);
            end = Math.min(DAY_END_MINUTES, end);

            if (end <= start) {
                return;
            }

            if (start > cursor) {
                segments.push({
                    isBlank: true,
                    start: cursor,
                    end: start
                });
            }

            segments.push({
                isBlank: false,
                start: start,
                end: end,
                row: row
            });

            cursor = Math.max(cursor, end);
        });

        if (cursor < DAY_END_MINUTES) {
            segments.push({
                isBlank: true,
                start: cursor,
                end: DAY_END_MINUTES
            });
        }

        return segments;
    }

    function buildSubjectCell(row) {
        const subjectCode = String(row.subject_code || "").trim();
        const sectionName = String(row.section_name || "").trim();
        const parts = [];

        if (subjectCode !== "") {
            parts.push(`<div class="ru-subject-code">${escapeHtml(subjectCode)}</div>`);
        }

        if (sectionName !== "") {
            parts.push(`<div class="ru-subject-section">${escapeHtml(sectionName)}</div>`);
        }

        return parts.join("");
    }

    function buildGroupedRows(data) {
        const groups = {
            MWF: [],
            TTH: [],
            S: [],
            OTHER: []
        };

        (Array.isArray(data) ? data : []).forEach(function (item) {
            const dayKey = normalizeDayKey(item.days_raw);
            const groupKey = getGroupKey(dayKey);
            const startMinutes = timeToMinutes(String(item.time_start || "").substring(0, 5));

            groups[groupKey].push($.extend({}, item, {
                _day_key: dayKey,
                _start_minutes: startMinutes
            }));
        });

        Object.keys(groups).forEach(function (groupKey) {
            groups[groupKey].sort(function (a, b) {
                if (a._start_minutes !== b._start_minutes) {
                    return a._start_minutes - b._start_minutes;
                }

                const dayA = DAY_SORT[a._day_key] || 99;
                const dayB = DAY_SORT[b._day_key] || 99;
                if (dayA !== dayB) {
                    return dayA - dayB;
                }

                return String(a.subject_code || "").localeCompare(String(b.subject_code || ""));
            });
        });

        return groups;
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

        if ($.fn.select2) {
            $("#ru_room_id").trigger("change.select2");
        }
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

    function renderRoomReport(data) {
        const ay = $("#ru_ay").val();
        const semester = $("#ru_semester").val();
        const roomId = $("#ru_room_id").val();
        const roomText = roomId ? $("#ru_room_id option:selected").text() : "";
        const groups = buildGroupedRows(data);
        const visibleGroups = GROUP_ORDER.filter(function (groupKey) {
            return groupKey !== "OTHER" || groups.OTHER.length;
        });

        $("#roomTimetableCard").show();
        updateHeader(roomText, ay, semester);
        setPrintState(Boolean(roomId));

        let html = `
            <div class="ru-sheet">
                <div class="table-responsive">
                    <table class="table table-bordered ru-sheet-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Day</th>
                                <th>Subject</th>
                                <th>Faculty</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        visibleGroups.forEach(function (groupKey) {
            const rows = groups[groupKey] || [];
            const segments = buildTimeSegments(rows);
            const label = groupKey === "OTHER" ? "OTHER" : groupKey;

            html += `
                <tr class="ru-sheet-group">
                    <td colspan="4">${escapeHtml(label)}</td>
                </tr>
            `;

            segments.forEach(function (segment) {
                if (segment.isBlank) {
                    html += `
                        <tr>
                            <td class="ru-time-cell">${escapeHtml(formatTimeRange(segment.start, segment.end))}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    `;
                    return;
                }

                const facultyName = String(segment.row.faculty_name || "TBA").trim() || "TBA";

                html += `
                    <tr>
                        <td class="ru-time-cell">${escapeHtml(formatTimeRange(segment.start, segment.end))}</td>
                        <td class="ru-day-cell">${escapeHtml(segment.row._day_key)}</td>
                        <td>${buildSubjectCell(segment.row)}</td>
                        <td class="ru-faculty-name">${escapeHtml(facultyName)}</td>
                    </tr>
                `;
            });
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        $("#roomTimetableWrapper").html(html);
    }

    function loadRoomSchedule() {
        const ay = $("#ru_ay").val();
        const semester = $("#ru_semester").val();
        const roomId = $("#ru_room_id").val();

        if (!ay || !semester || !roomId) {
            showRoomMessage("Select academic year, semester, and room to view the utilization sheet.");
            return createResolvedPromise();
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
            renderRoomReport(Array.isArray(data) ? data : []);
        }).fail(function (_, textStatus) {
            if (textStatus === "abort") {
                return;
            }

            showRoomMessage("Unable to load the selected room schedule right now.");
        }).always(function () {
            roomScheduleRequest = null;
            hideLoader();
        });

        return roomScheduleRequest;
    }

    function reloadCurrentView() {
        const ay = $("#ru_ay").val();
        const semester = $("#ru_semester").val();
        const roomId = $("#ru_room_id").val();

        if (!ay || !semester || !roomId) {
            showRoomMessage("Select academic year, semester, and room to view the utilization sheet.");
            return;
        }

        loadRoomSchedule();
    }

    $("#ru_ay, #ru_semester").on("change", function () {
        loadRoomOptions().always(function () {
            reloadCurrentView();
        });
    });

    $("#ru_room_id").on("change", function () {
        reloadCurrentView();
    });

    $("#btnPrintRoomUtilization").on("click", function () {
        if ($(this).prop("disabled")) {
            return;
        }

        window.print();
    });

    showRoomMessage("Select academic year, semester, and room to view the utilization sheet.");

    setTimeout(function () {
        loadRoomOptions().always(function () {
            reloadCurrentView();
        });
    }, 200);
});
</script>

</body>
</html>
