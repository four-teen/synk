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
<div class="card mt-4" id="workloadCard" style="display:none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="m-0">Current Faculty Workload</h5>
            <small class="text-muted">
                Classes already assigned for this term
            </small>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Course No.</th>
                    <th>Description</th>
                    <th>Section</th>
                    <th>Type</th>
                    <th>Days</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th class="text-center">LEC</th>
                    <th class="text-center">LAB</th>
                    <th class="text-center">Units</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>

            <tbody id="workloadTbody"></tbody>
        </table>
    </div>
</div>
<!-- SCHEDULED CLASSES CARD -->
<div class="card mb-4 mt-4" id="scheduledClassCard" style="display:none;">
    <div class="card-header">
        <h5 class="m-0">Scheduled Classes</h5>
        <small class="text-muted">
            Select from existing class schedules for the chosen faculty, A.Y., and semester.
        </small>
    </div>

<!-- SEARCH & FILTER BAR -->
<div class="card-body py-2 border-bottom">
    <div class="row g-2 align-items-center">
        <div class="col-md-8">
            <input type="text"
                   id="scheduleSearch"
                   class="form-control"
                   placeholder="🔍 Search by course no, description, section, room, or days...">
        </div>

        <div class="col-md-4">
            <select id="scheduleFilter" class="form-select">
                <option value="all">Filter by: All</option>
                <option value="course">Course No.</option>
                <option value="desc">Description</option>
                <option value="section">Section</option>
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
                    <th>Days</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th class="text-center">Units</th>
                    <th class="text-center">Lec</th>
                    <th class="text-center">Lab</th>
                </tr>
            </thead>
            <tbody id="scheduledClassTbody">
                <!-- populated via AJAX -->
            </tbody>
        </table>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Data pulled from <strong>Class Scheduling</strong>
        </small>

        <button class="btn btn-primary" id="btnApplyToWorkload">
            <i class="bx bx-check"></i> Apply to Workload
        </button>
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
/* =========================================================
   GLOBAL STATE
========================================================= */
let currentAyId = null;
let currentSemesterNum = null;

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

    /* =========================================================
       LOAD SCHEDULED CLASSES
    ========================================================= */
    function loadScheduledClasses() {

        let faculty_id = $("#faculty_id").val();
        let ay_text    = $("#fw_ay").val();
        let semester_ui = $("#fw_semester").val();

        if (!faculty_id || !ay_text || !semester_ui) return;

        $("#scheduledClassCard").show();
        $("#scheduledClassTbody").html(`
            <tr>
                <td colspan="10" class="text-center text-muted">
                    Loading scheduled classes...
                </td>
            </tr>
        `);

        $.post(
            "../backend/query_class_schedule_loader.php",
            {
                faculty_id: faculty_id,
                ay: ay_text,
                semester: semester_ui
            },
            function (res) {

                let data = (typeof res === "string") ? JSON.parse(res) : res;

                if (!Array.isArray(data)) {
                    showInvalid();
                    return;
                }

                if (data.length === 0) {
                    $("#scheduledClassTbody").html(`
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                No scheduled classes found.
                            </td>
                        </tr>
                    `);
                    return;
                }

        let rows = "";
        data.forEach(item => {
            rows += `
                <tr>
                    <td>
                        <input type="checkbox"
                               class="chkSchedule"
                               value="${item.schedule_id}">
                    </td>
                    <td>${item.subject_code}</td>
                    <td>${item.subject_description}</td>
                    <td>${item.section_name}</td>
                    <td>${item.days}</td>
                    <td>${item.time}</td>
                    <td>${item.room_code}</td>
                    <td class="text-center">${item.units}</td>
                    <td class="text-center">${item.hours_lec}</td>
                    <td class="text-center">${item.hours_lab}</td>
                </tr>
            `;
        });
        $("#scheduledClassTbody").html(rows);

            }
        ).fail(showInvalid);
    }

    function showInvalid() {
        $("#scheduledClassTbody").html(`
            <tr>
                <td colspan="10" class="text-danger text-center">
                    Invalid response from server
                </td>
            </tr>
        `);
    }

    /* =========================================================
       LOAD FACULTY WORKLOAD LIST
    ========================================================= */
    function loadWorkloadList() {

        if (!currentAyId || !currentSemesterNum) return;

        let faculty_id = $("#faculty_id").val();
        if (!faculty_id) return;

        $.post(
            "../backend/query_load_faculty_workload.php",
            {
                faculty_id: faculty_id,
                ay_id: currentAyId,
                semester: currentSemesterNum
            },
            function (data) {

                if (!Array.isArray(data) || data.length === 0) {
                    $("#workloadCard").hide();
                    return;
                }

                let rows = "";
                data.forEach(row => {
                    let typeBadge = row.type === 'LAB'
                        ? '<span class="badge bg-label-warning">LAB</span>'
                        : '<span class="badge bg-label-primary">LEC</span>';

                    rows += `
                        <tr>
                            <td>${row.sub_code}</td>
                            <td>${row.desc}</td>
                            <td>${row.section}</td>
                            <td>${typeBadge}</td>
                            <td>${row.days}</td>
                            <td>${row.time}</td>
                            <td>${row.room}</td>
                            <td class="text-center">${row.lec}</td>
                            <td class="text-center">${row.lab}</td>
                            <td class="text-center">${row.units}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger btnRemoveWL"
                                        data-id="${row.workload_id}">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });

                $("#workloadTbody").html(rows);
                $("#workloadCard").show();
            },
            "json"
        );
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
                    loadScheduledClasses();
                    loadWorkloadList();
                }
            );
        });
    });

    /* =========================================================
       AUTO LOAD ON SELECTION CHANGE
    ========================================================= */
    $("#faculty_id, #fw_ay, #fw_semester").on("change", function () {

        let faculty_id = $("#faculty_id").val();
        let ay_text    = $("#fw_ay").val();
        let semester_ui = $("#fw_semester").val();

        if (!faculty_id || !ay_text || !semester_ui) {
            $("#facultyAlert").hide();
            $("#scheduledClassCard").hide();
            $("#workloadCard").hide();
            return;
        }

        $("#facultyNameText").text(
            $("#faculty_id option:selected").text()
        );
        $("#termSummary").text(semester_ui + " A.Y. " + ay_text);

        $("#facultyAlert").slideDown();
        $("#scheduledClassCard").slideDown();

        currentSemesterNum = SEMESTER_MAP[semester_ui];

        // Resolve AY → ay_id
        $.post(
            "../backend/query_get_ay_id.php",
            { ay: ay_text },
            function (res) {
                if (res && res.ay_id) {
                    currentAyId = res.ay_id;
                    loadScheduledClasses();
                    loadWorkloadList();
                }
            },
            "json"
        );
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

                if (res.status === "success") {
                    Swal.fire(
                        "Applied",
                        res.inserted + " class(es) added to workload.",
                        "success"
                    );

                    loadScheduledClasses();
                    loadWorkloadList();
                } else {
                    Swal.fire("Error", res.message, "error");
                }
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
        let days    = row.find("td:eq(4)").text().toLowerCase();
        let room    = row.find("td:eq(6)").text().toLowerCase();

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
