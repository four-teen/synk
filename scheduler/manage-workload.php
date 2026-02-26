<?php 
session_start();
ob_start();
include '../backend/db.php';

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
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #5f728b;
            border-bottom: 1px solid #dbe4ef;
            white-space: nowrap;
        }

        .workload-table tbody td {
            color: #5c6f88;
            border-color: #e7edf5;
            vertical-align: middle;
        }

        .workload-table tfoot th {
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
            text-transform: uppercase;
        }

        .workload-days,
        .workload-time,
        .workload-room {
            white-space: nowrap;
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
        @media print {
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
                width: 100%;
            }

            .btn,
            .badge,
            .select2-container {
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
<div id="printHeader" class="mb-3" style="display:none;">
    <h5 class="mb-1 fw-bold">FACULTY WORKLOAD SUMMARY</h5>
    <div class="text-muted" style="font-size:0.9rem;">
        <div><strong>College:</strong> <?= htmlspecialchars($college_name) ?></div>
        <div><strong>Faculty:</strong> <span id="printFacultyName"></span></div>
        <div><strong>Term:</strong> <span id="printTerm"></span></div>
        <div><strong>Date Generated:</strong> <?= date("F d, Y") ?></div>
    </div>
    <hr>
</div>

    
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 workload-table">
            <thead class="table-light">
                <tr>
                    <th rowspan="2">Course No.</th>
                    <th rowspan="2">Description</th>
                    <th rowspan="2">Section</th>
                    <th rowspan="2">Type</th>
                    <th rowspan="2">Days</th>
                    <th rowspan="2">Time</th>
                    <th rowspan="2">Room</th>
                    <th rowspan="2" class="text-center">Unit</th>
                    <th colspan="2" class="text-center">No. of Hours</th>
                    <th rowspan="2" class="text-center">Faculty Load</th>
                    <th rowspan="2" class="text-end">Action</th>
                </tr>
                <tr>
                    <th class="text-center">Lec</th>
                    <th class="text-center">Lab</th>
                </tr>
            </thead>

            <tbody id="workloadTbody"></tbody>
    <!-- ✅ TABLE FOOTER -->
    <tfoot class="table-light">
        <tr>
            <!-- Span first 7 columns -->
            <th colspan="7" class="text-end fw-semibold total-label">
                Teaching Load
            </th>

            <th class="text-center" id="totalUNIT">0</th>
            <th class="text-center" id="totalLEC">0</th>
            <th class="text-center" id="totalLAB">0</th>
            <th class="text-center" id="totalLOADCell">
                <span id="totalLOAD">0.00</span>
            </th>
            <th></th>
        </tr>
    </tfoot>            
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
                   placeholder="Search by course no, description, section, room, or days...">
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
        return Number.isInteger(n) ? String(n) : n.toFixed(2);
    }

    /* =========================================================
       LOAD SCHEDULED CLASSES
    ========================================================= */
    function loadScheduledClasses() {

        let faculty_id = $("#faculty_id").val();
        let ay_text    = $("#fw_ay").val();
        let semester_ui = $("#fw_semester").val();

        if (!faculty_id || !ay_text || !semester_ui) return;

        $("#scheduledClassCard").show();
        $("#checkAllSchedules").prop("checked", false);
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
                    $("#checkAllSchedules").prop("checked", false);
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
        $("#checkAllSchedules").prop("checked", false);
        $("#scheduledClassTbody").html(`
            <tr>
                <td colspan="10" class="text-danger text-center">
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
    function loadWorkloadList() {

        let totalLEC   = 0;
        let totalLAB   = 0;
        let totalUNIT  = 0;
        let totalLOAD  = 0;

        /* Count one load row per offering (LEC+LAB should count once). */
        const countedOfferings = new Set();

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
                    $("#workloadTbody").html("");
                    $("#totalLEC").text("0");
                    $("#totalLAB").text("0");
                    $("#totalUNIT").text("0");
                    $("#totalLOADCell").html(`<span class="load-pill load-high">0.00 <small>(UNDERLOAD)</small></span>`);
                    $("#workloadCard").hide();
                    return;
                }

                let rows = "";

                for (let i = 0; i < data.length; i++) {

                    let row = data[i];

                    const offeringKey = String(row.offering_id ?? ("w" + row.workload_id));
                    if (!countedOfferings.has(offeringKey)) {
                        countedOfferings.add(offeringKey);
                        totalLEC  += toNumber(row.lec);
                        totalLAB  += toNumber(row.lab);
                        totalUNIT += toNumber(row.units);
                        totalLOAD += toNumber(row.faculty_load);
                    }

                    const type = String(row.type || "").toUpperCase();
                    const typeBadge = type === "LAB"
                        ? '<span class="type-pill lab">LAB</span>'
                        : '<span class="type-pill lec">LEC</span>';

                    let curOffering = row.offering_id ?? null;
                    let next = (i + 1 < data.length) ? data[i + 1] : null;
                    let prev = (i - 1 >= 0) ? data[i - 1] : null;

                    let isStartPair =
                        type === "LEC" &&
                        curOffering !== null &&
                        next &&
                        String(next.offering_id ?? "") === String(curOffering);

                    let isSecondPairRow =
                        curOffering !== null &&
                        prev &&
                        String(prev.offering_id ?? "") === String(curOffering);

                    rows += `
                        <tr>
                            <td class="workload-code">${escapeHtml(row.sub_code)}</td>
                            <td class="workload-desc">${escapeHtml(row.desc)}</td>
                            <td>${escapeHtml(row.section)}</td>
                            <td class="text-center">${typeBadge}</td>
                            <td class="workload-days">${escapeHtml(row.days)}</td>
                            <td class="workload-time">${escapeHtml(row.time)}</td>
                            <td class="workload-room">${escapeHtml(row.room)}</td>
                    `;

                    if (isStartPair) {
                        rows += `
                            <td class="text-center" rowspan="2" style="vertical-align: middle;">${formatNumber(row.units)}</td>
                            <td class="text-center" rowspan="2" style="vertical-align: middle;">${formatNumber(row.lec)}</td>
                            <td class="text-center" rowspan="2" style="vertical-align: middle;">${formatNumber(row.lab)}</td>
                            <td class="text-center fw-semibold" rowspan="2" style="vertical-align: middle;">
                                ${toNumber(row.faculty_load).toFixed(2)}
                            </td>
                        `;
                    } else if (!isSecondPairRow) {
                        rows += `
                            <td class="text-center">${formatNumber(row.units)}</td>
                            <td class="text-center">${formatNumber(row.lec)}</td>
                            <td class="text-center">${formatNumber(row.lab)}</td>
                            <td class="text-center fw-semibold">${toNumber(row.faculty_load).toFixed(2)}</td>
                        `;
                    }

                    rows += `
                            <td class="text-end">
                                <button class="btn btn-sm btn-delete-workload btnRemoveWL"
                                        data-id="${row.workload_id}">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }

                $("#workloadTbody").html(rows);

                let loadClass = "load-high";
                let loadLabel = "UNDERLOAD";
                if (totalLOAD >= 18 && totalLOAD <= 21) {
                    loadClass = "load-normal";
                    loadLabel = "NORMAL LOAD";
                } else if (totalLOAD > 21) {
                    loadClass = "load-over";
                    loadLabel = "OVERLOAD";
                }

                $("#totalLEC").text(formatNumber(totalLEC));
                $("#totalLAB").text(formatNumber(totalLAB));
                $("#totalUNIT").text(formatNumber(totalUNIT));
                $("#totalLOADCell").html(`
                    <span class="load-pill ${loadClass}">
                        ${totalLOAD.toFixed(2)} <small>(${loadLabel})</small>
                    </span>
                `);

                $("#printFacultyName").text($("#faculty_id option:selected").text());
                $("#printTerm").text($("#fw_semester").val() + " A.Y. " + $("#fw_ay").val());

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
                    loadScheduledClasses();
                    loadWorkloadList();
                    return;
                }

                if (res.status === "partial" || res.status === "conflict") {
                    Swal.fire({
                        icon: res.status === "partial" ? "warning" : "error",
                        title: res.status === "partial" ? "Partially Applied" : "Faculty Conflict",
                        html: res.message || "Selected classes conflict with this faculty's existing load."
                    });
                    loadScheduledClasses();
                    loadWorkloadList();
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
