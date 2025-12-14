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

// LOAD FACULTY ASSIGNED TO THIS COLLEGE
$faculty_options = "";
$faculty_sql = "
    SELECT f.faculty_id,
           CONCAT(f.last_name, ', ', f.first_name, ' ', COALESCE(f.ext_name,'')) AS full_name
    FROM tbl_college_faculty cf
    JOIN tbl_faculty f ON cf.faculty_id = f.faculty_id
    WHERE cf.college_id = '$college_id'
      AND cf.status = 'active'
      AND f.status  = 'active'
    ORDER BY f.last_name, f.first_name
";
$f_run = mysqli_query($conn, $faculty_sql);
while ($f = mysqli_fetch_assoc($f_run)) {
    $fid   = (int)$f['faculty_id'];
    $fname = htmlspecialchars($f['full_name']);
    $faculty_options .= "<option value='{$fid}'>{$fname}</option>";
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
        .day-btn.active { 
            background-color: #696cff; 
            color:#fff; 
        }
        .table-sm td { 
            padding-top: .4rem!important; 
            padding-bottom: .4rem!important; 
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

        /* Adjust arrow alignment */
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
            right: 10px !important;
        }

        /* Placeholder fix */
        .select2-selection__rendered {
            line-height: 42px !important;
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
            <small class="text-muted">Add subjects, sections, days, time, and room for faculty.</small>
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
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                        <option value="Midyear">Midyear</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">A.Y.</label>
                    <select id="fw_ay" class="form-select select2-single">
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

    <!-- WORKLOAD ENTRY CARD -->
    <div class="card mb-4" id="workloadCard" style="display:none;">
        <div class="card-header">
            <h5 class="m-0">Add Class Load</h5>
            <small class="text-muted">Fill in the details below then click <strong>Add to Workload</strong>.</small>
        </div>
        <div class="card-body">

            <div class="row g-3">

                <!-- Program -->
                <div class="col-md-3">
                    <label class="form-label">Program</label>
                    <select id="wl_program_id" class="form-select">
                        <option value="">Select Program</option>
                        <?php
                        $p_sql = "SELECT program_id, program_code, program_name 
                                  FROM tbl_program 
                                  WHERE college_id='$college_id' 
                                  ORDER BY program_code";
                        $p_run = mysqli_query($conn, $p_sql);
                        while ($p = mysqli_fetch_assoc($p_run)) {
                            $pid   = (int)$p['program_id'];
                            $label = htmlspecialchars($p['program_code'] . " — " . $p['program_name']);
                            echo "<option value='{$pid}'>{$label}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Year level -->
                <div class="col-md-2">
                    <label class="form-label">Year Level</label>
                    <select id="wl_year_level" class="form-select">
                        <option value="">Year</option>
                        <option value="1">1st</option>
                        <option value="2">2nd</option>
                        <option value="3">3rd</option>
                        <option value="4">4th</option>
                        <option value="5">5th</option>
                        <option value="6">6th</option>
                    </select>
                </div>

                <!-- Section (depends on program+year) -->
                <div class="col-md-3">
                    <label class="form-label">Section</label>
                    <select id="wl_section_id" class="form-select">
                        <option value="">Select Section</option>
                    </select>
                </div>

                <!-- Subject -->
                <div class="col-md-4">
                    <label class="form-label">Subject</label>
                    <select id="wl_subject_id" class="form-select select2-single">
                        <option value="">Select Subject</option>
                        <?php
                        $s_sql = "SELECT sub_id, sub_code, sub_description 
                                  FROM tbl_subject_masterlist
                                  WHERE status='active'
                                  ORDER BY sub_code";
                        $s_run = mysqli_query($conn, $s_sql);
                        while ($s = mysqli_fetch_assoc($s_run)) {
                            $sid   = (int)$s['sub_id'];
                            $label = $s['sub_code']." — ".$s['sub_description'];
                            echo "<option value='{$sid}'>".htmlspecialchars($label)."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <hr>

            <div class="row g-3">

                <!-- Days: buttons + Select2 multi -->
                <div class="col-md-4">
                    <label class="form-label">Days</label>

                    <div class="mb-2" id="dayButtons">
                        <button type="button" class="btn btn-outline-secondary btn-sm me-1 day-btn" data-day="M">M</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-1 day-btn" data-day="T">T</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-1 day-btn" data-day="W">W</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-1 day-btn" data-day="TH">Th</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-1 day-btn" data-day="F">F</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-1 day-btn" data-day="S">Sat</button>
                    </div>

                    <select id="wl_days_multi" class="form-select" multiple>
                        <option value="M">M</option>
                        <option value="T">T</option>
                        <option value="W">W</option>
                        <option value="TH">Th</option>
                        <option value="F">F</option>
                        <option value="S">Sat</option>
                    </select>

                    <small class="text-muted">
                        Use the buttons for quick selection, or fine-tune using the dropdown.
                    </small>
                </div>

                <!-- Time range -->
                <div class="col-md-4">
                    <label class="form-label">Time Range</label>
                    <div class="d-flex align-items-center">
                        <input type="time" id="wl_time_start" class="form-control" step="1800">
                        <span class="mx-2">to</span>
                        <input type="time" id="wl_time_end" class="form-control" step="1800">
                    </div>
                    <small class="text-muted">30-minute increments (e.g., 07:30–09:00).</small>
                </div>

                <!-- Room -->
                <div class="col-md-4">
                    <label class="form-label">Room</label>
                    <select id="wl_room_id" class="form-select select2-single">
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

            <hr>

            <div class="row g-3">

                <div class="col-md-3">
                    <label class="form-label">Units</label>
                    <input type="number" id="wl_units" class="form-control" value="3" min="0">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Hours (Lec)</label>
                    <input type="number" id="wl_hrs_lec" class="form-control" value="3" min="0">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Hours (Lab)</label>
                    <input type="number" id="wl_hrs_lab" class="form-control" value="0" min="0">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Load</label>
                    <input type="number" id="wl_load" class="form-control" value="3" step="0.25" min="0">
                </div>

            </div>

            <div class="mt-4">
                <button class="btn btn-primary" id="btnAddWorkload">
                    <i class="bx bx-plus"></i> Add to Workload
                </button>
            </div>

        </div>
    </div>

    <!-- EXISTING WORKLOAD TABLE -->
    <div class="card" id="workloadListCard" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="m-0">Current Workload</h5>
                <small class="text-muted">Based on selected faculty, semester, and A.Y.</small>
            </div>
            <span id="wlSummaryTotal" class="badge bg-label-primary"></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Course No.</th>
                        <th>Course Description</th>
                        <th>Section</th>
                        <th>Days</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Unit</th>
                        <th>Lec</th>
                        <th>Lab</th>
                        <th>Load</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="wl_tbody"></tbody>
            </table>
        </div>
    </div>


    <!-- FACULTY TIMETABLE VIEW -->
    <div class="card mt-4" id="facultyTimetableCard" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="m-0">Faculty Timetable</h5>
                <small class="text-muted">Visual schedule for the selected faculty, A.Y., and semester.</small>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="facultyTimetableWrapper" class="table-responsive"></div>
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
$(document).ready(function(){

    // INIT SELECT2 FOR SINGLE SELECTS
    $('.select2-single').select2({
        width: '100%',
        placeholder: "Select...",
        allowClear: true
    });

    // INIT SELECT2 FOR DAYS (MULTI)
    $('#wl_days_multi').select2({
        placeholder: "Select days",
        width: '100%'
    });

    // -----------------------------
    // Helpers: DAYS BUTTONS & SELECT2
    // -----------------------------
    function syncSelectFromButtons() {
        let days = [];
        $('.day-btn.active').each(function(){
            days.push($(this).data('day'));
        });
        $('#wl_days_multi').val(days).trigger('change.select2');
    }

    function syncButtonsFromSelect() {
        let days = $('#wl_days_multi').val() || [];
        $('.day-btn').each(function(){
            let d = $(this).data('day');
            if (days.indexOf(d) !== -1) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    }

    // button click
    $(document).on('click', '.day-btn', function(){
        $(this).toggleClass('active');
        syncSelectFromButtons();
    });

    // select2 change
    $('#wl_days_multi').on('change', function(){
        syncButtonsFromSelect();
    });

    // -----------------------------
    // Faculty / Term change
    // -----------------------------
    $("#faculty_id, #fw_semester, #fw_ay").on('change keyup', function(){
        let fid = $("#faculty_id").val();
        let sem = $("#fw_semester").val();
        let ay  = $("#fw_ay").val().trim();

        if (fid && ay !== "") {
            let fname = $("#faculty_id option:selected").text();
            $("#facultyNameText").text(fname);
            $("#termSummary").text(sem + " A.Y. " + ay);
            $("#facultyAlert").slideDown();
            $("#workloadCard").slideDown();
            loadFacultyWorkload();
        } else {
            $("#facultyAlert").hide();
            $("#workloadCard").hide();
            $("#workloadListCard").hide();
        }
    });

    // -----------------------------
    // Load Sections when program or year changes
    // -----------------------------
    $("#wl_program_id, #wl_year_level").on('change', function(){
        let pid  = $("#wl_program_id").val();
        let year = $("#wl_year_level").val();

        $("#wl_section_id").html('<option value="">Select Section</option>');

        if (!pid || !year) return;

        $.post('../backend/query_sections.php', {
            load_sections_by_prog_year: 1,
            program_id: pid,
            year_level: year
        }, function(res){
            $("#wl_section_id").html(res);
        });
    });

    // -----------------------------
    // Add Workload
    // -----------------------------
    $("#btnAddWorkload").click(function(e){
        e.preventDefault();

        let faculty_id = $("#faculty_id").val();
        let ay         = $("#fw_ay").val().trim();
        let semester   = $("#fw_semester").val();

        let program_id = $("#wl_program_id").val();
        let year       = $("#wl_year_level").val();
        let section_id = $("#wl_section_id").val();
        let subject_id = $("#wl_subject_id").val();
        let days       = $("#wl_days_multi").val() || [];
        let tstart     = $("#wl_time_start").val();
        let tend       = $("#wl_time_end").val();
        let room_id    = $("#wl_room_id").val();
        let units      = $("#wl_units").val();
        let hrs_lec    = $("#wl_hrs_lec").val();
        let hrs_lab    = $("#wl_hrs_lab").val();
        let load_val   = $("#wl_load").val();

        // basic validation
        if (!faculty_id || !ay || !program_id || !year || !section_id || !subject_id || days.length === 0 || !tstart || !tend || !room_id) {
            Swal.fire("Missing Data", "Please complete all required fields.", "warning");
            return;
        }

        if (tend <= tstart) {
            Swal.fire("Invalid Time", "End time must be later than start time.", "warning");
            return;
        }

        $.post('../backend/query_workload.php', {
            save_workload: 1,
            faculty_id: faculty_id,
            ay: ay,
            semester: semester,
            program_id: program_id,
            year_level: year,
            section_id: section_id,
            subject_id: subject_id,
            days: JSON.stringify(days),
            time_start: tstart,
            time_end: tend,
            room_id: room_id,
            units: units,
            hours_lec: hrs_lec,
            hours_lab: hrs_lab,
            load_value: load_val
        }, function(res){
            res = res.trim();
            if (res === 'success') {
                Swal.fire({
                    icon: "success",
                    title: "Saved",
                    text: "Workload added successfully.",
                    timer: 1500,
                    showConfirmButton: false
                });
                loadFacultyWorkload();
            } else {
                Swal.fire("Error", res, "error");
            }
        });

    });

    // -----------------------------
    // Load Workload List
    // -----------------------------
    function loadFacultyWorkload() {

        let faculty_id = $("#faculty_id").val();
        let ay         = $("#fw_ay").val().trim();
        let semester   = $("#fw_semester").val();

        if (!faculty_id || !ay) return;

        $.post('../backend/query_workload.php', {
            load_workload: 1,
            faculty_id: faculty_id,
            ay: ay,
            semester: semester
        }, function(res){
            try {
                let data = JSON.parse(res);
                let rows = "";
                let totalLoad = 0;

                if (!Array.isArray(data) || data.length === 0) {
                    $("#wl_tbody").html("");
                    $("#workloadListCard").hide();
                    $("#wlSummaryTotal").text("");
                    $("#facultyTimetableWrapper").html("");
                    $("#facultyTimetableCard").hide();
                    return;
                }

                data.forEach(function(item, index){
                    totalLoad += parseFloat(item.load_value);

                    rows += `
                        <tr>
                            <td>${index+1}</td>
                            <td>${item.subject_code}</td>
                            <td>${item.subject_desc}</td>
                            <td>${item.section_name}</td>
                            <td>${item.days_display}</td>
                            <td>${item.time_display}</td>
                            <td>${item.room_code}</td>
                            <td>${item.units}</td>
                            <td>${item.hours_lec}</td>
                            <td>${item.hours_lab}</td>
                            <td>${item.load_value}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger btnDelWL" data-id="${item.workload_id}">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });

                $("#wl_tbody").html(rows);
                $("#workloadListCard").show();
                $("#wlSummaryTotal").text("Total Load: " + totalLoad.toFixed(2));

                // 🔥 NEW: render faculty timetable grid
                renderFacultyTimetable(data);

            } catch(e) {
                console.error("Invalid JSON:", res);
                $("#wl_tbody").html("");
                $("#workloadListCard").hide();
                $("#wlSummaryTotal").text("");
                $("#facultyTimetableWrapper").html("");
                $("#facultyTimetableCard").hide();
            }
        });

    }

    // -----------------------------
    // Delete Workload Row
    // -----------------------------
    $(document).on('click', '.btnDelWL', function(){
        let id = $(this).data('id');

        Swal.fire({
            title: "Delete class?",
            text: "This will remove the workload entry.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#aaa",
            confirmButtonText: "Delete"
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../backend/query_workload.php', {
                    delete_workload: 1,
                    workload_id: id
                }, function(res){
                    if (res.trim() === 'success') {
                        Swal.fire({
                            icon: "success",
                            title: "Deleted",
                            text: "Entry removed.",
                            timer: 1200,
                            showConfirmButton: false
                        });
                        loadFacultyWorkload();
                    } else {
                        Swal.fire("Error", res, "error");
                    }
                });
            }
        });
    });

    // ==========================================
    // FACULTY TIMETABLE RENDERING
    // ==========================================

    // Generate 30-min slots from 07:00 to 19:00
    function generateTimeSlots() {
        let slots = [];
        let startMinutes = 7 * 60;   // 07:00
        let endMinutes   = 19 * 60;  // 19:00

        for (let m = startMinutes; m < endMinutes; m += 30) {
            let hh = String(Math.floor(m / 60)).padStart(2, '0');
            let mm = String(m % 60).padStart(2, '0');
            slots.push({ 
                label: hh + ":" + mm,
                minutes: m
            });
        }
        return slots;
    }

    function timeStringToMinutes(t) {
        // expects "HH:MM" or "HH:MM:SS"
        if (!t) return 0;
        let parts = t.split(':');
        let hh = parseInt(parts[0] || "0", 10);
        let mm = parseInt(parts[1] || "0", 10);
        return hh * 60 + mm;
    }

    function renderFacultyTimetable(data) {
        if (!Array.isArray(data) || data.length === 0) {
            $("#facultyTimetableWrapper").html("");
            $("#facultyTimetableCard").hide();
            return;
        }

        // Define days order and labels
        const days = [
            { key: "M",  label: "Mon" },
            { key: "T",  label: "Tue" },
            { key: "W",  label: "Wed" },
            { key: "TH", label: "Thu" },
            { key: "F",  label: "Fri" },
            { key: "S",  label: "Sat" }
        ];

        const slots = generateTimeSlots(); // 07:00, 07:30, ...

        // Build table HTML
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
            // Time label e.g. 07:00–07:30
            let nextSlot = (idx < slots.length - 1) ? slots[idx + 1] : { minutes: slot.minutes + 30 };
            let startLabel = slot.label;
            let endHour = String(Math.floor(nextSlot.minutes / 60)).padStart(2,'0');
            let endMin  = String(nextSlot.minutes % 60).padStart(2,'0');
            let endLabel = endHour + ":" + endMin;

            html += `<tr>
                        <td><small>${startLabel}–${endLabel}</small></td>
            `;

            days.forEach(day => {
                let cellText = "";
                let matchedItem = null;

                data.forEach(function(item) {
                    let days_raw = item.days_raw || [];
                    if (days_raw.indexOf(day.key) === -1) return;

                    let ts = (item.time_start || '').substring(0,5); // "HH:MM"
                    let te = (item.time_end   || '').substring(0,5);

                    if (!ts || !te) return;

                    let slotMin = slot.minutes;
                    let startMin = timeStringToMinutes(ts);
                    let endMin   = timeStringToMinutes(te);

                    // If this 30-min slot is within the class time
                    if (slotMin >= startMin && slotMin < endMin) {
                        matchedItem = item;
                    }
                });

                if (matchedItem) {
                    cellText = `
                        <div class="small fw-semibold">${matchedItem.subject_code}</div>
                        <div class="small">${matchedItem.section_name}</div>
                        <div class="small text-muted">Rm ${matchedItem.room_code}</div>
                    `;
                }

                html += `<td class="align-middle text-center" style="min-width: 120px;">${cellText}</td>`;
            });

            html += `</tr>`;
        });

        html += `
                </tbody>
            </table>
        `;

        $("#facultyTimetableWrapper").html(html);
        $("#facultyTimetableCard").show();
    }

    

});
</script>

</body>
</html>
