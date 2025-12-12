<?php
session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}
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

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
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
.matrix-table th,
.matrix-table td {
    min-width: 110px;
    vertical-align: middle;
}

.matrix-room {
    background: #f8f9fa;
    white-space: nowrap;
}

.matrix-cell {
    border-radius: 6px;
    padding: 8px;
    text-align: center;
    font-size: 0.85rem;
}

.matrix-vacant {
    background: #e9ecef;
    color: #6c757d;
}

.matrix-occupied {
    background: #0d6efd;
    color: #fff;
    font-weight: 600;
}


/* SUBJECT COLORS (auto-rotated) */
.sub-0 { background: #0d6efd; } /* blue */
.sub-1 { background: #198754; } /* green */
.sub-2 { background: #fd7e14; } /* orange */
.sub-3 { background: #6f42c1; } /* purple */
.sub-4 { background: #20c997; } /* teal */

.matrix-table th {
    font-size: 0.75rem;
    white-space: nowrap;
}

.matrix-cell {
    min-height: 48px;
    font-size: 0.8rem;
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
  Schedule classes by defining <strong>time, room, and instructor</strong> for each offering.
</p>

<!-- FILTERS -->
<div class="card mb-4">
<div class="card-body">
<div class="row g-3">

<div class="col-md-4">
<label class="form-label">Prospectus</label>
<select id="prospectus_id" class="form-select">
<option value="">Select...</option>
<?php
$q = $conn->query("
  SELECT h.prospectus_id, h.effective_sy,
         p.program_code, p.program_name
  FROM tbl_prospectus_header h
  JOIN tbl_program p ON p.program_id = h.program_id
  WHERE p.college_id = '{$_SESSION['college_id']}'
  ORDER BY p.program_name
");
while ($r = $q->fetch_assoc()) {
  echo "<option value='{$r['prospectus_id']}'>
        {$r['program_code']} — {$r['program_name']} (SY {$r['effective_sy']})
        </option>";
}
?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Academic Year</label>
<select id="ay_id" class="form-select">
<option value="">Select...</option>
<?php
$ayQ = $conn->query("SELECT ay_id, ay FROM tbl_academic_years WHERE status='active'");
while ($ay = $ayQ->fetch_assoc()) {
  echo "<option value='{$ay['ay_id']}'>{$ay['ay']}</option>";
}
?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Semester</label>
<select id="semester" class="form-select">
<option value="">Select...</option>
<option value="1">First Semester</option>
<option value="2">Second Semester</option>
<option value="3">Midyear</option>
</select>
</div>

<div class="col-md-2 d-grid">
<label class="form-label">&nbsp;</label>
<button class="btn btn-primary" id="btnLoadSchedule">
<i class="bx bx-search me-1"></i> Load Classes
</button>
</div>

</div>
</div>
</div>

<!-- TABLE -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5 class="m-0">Class Offerings</h5>
            <small class="text-muted">
              Each row represents one class that must be scheduled.
            </small>
          </div>

          <button class="btn btn-outline-primary btn-sm" id="btnShowMatrix">
            <i class="bx bx-grid-alt me-1"></i> Room–Time Matrix
          </button>
        </div>
    </div>

    <div class="table-responsive p-3">
    <table class="table table-bordered table-hover" id="scheduleTable">
        <thead>
            <tr>
                <th>Section</th>
                <th>Subject</th>
                <th>Description</th>
                <th>Units</th>
                <th>Faculty</th>
                <th>Days</th>
                <th>Time</th>
                <th>Room</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="10" class="text-center text-muted">
                Select filters and click <strong>Load Classes</strong>.
                </td>
            </tr>
        </tbody>
    </table>
    </div>
</div>

</div>
<?php include '../footer.php'; ?>
</div>
</div>
</div>
</div>

<!-- MODAL -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header">
    <h5 class="modal-title">Define Class Schedule</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">

    <input type="hidden" id="sched_offering_id">

    <div class="mb-3">
    <strong id="sched_subject_label"></strong><br>
    <small class="text-muted" id="sched_section_label"></small>
    </div>

    <hr>

    <div class="step-label mb-2">Step 1 — When does the class meet?</div>

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
    <input type="time" id="sched_time_start" class="form-control">
    </div>

    <div class="col-md-3">
    <label class="form-label">End</label>
    <input type="time" id="sched_time_end" class="form-control">
    </div>
    </div>

    <div class="step-label mb-2">Step 2 — Where is the class held?</div>

    <div class="mb-3">
    <select id="sched_room_id" class="form-select">
    <option value="">Select room...</option>
    <?php
    $rQ = $conn->query("SELECT room_id, room_name FROM tbl_rooms WHERE status='active'");
    while ($r = $rQ->fetch_assoc()) {
      echo "<option value='{$r['room_id']}'>{$r['room_name']}</option>";
    }
    ?>
    </select>
    </div>

    </div>

    <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-primary" id="btnSaveSchedule">
    <i class="bx bx-save me-1"></i> Save Class Schedule
    </button>
    </div>

    </div>
    </div>
</div>

<!-- ROOM MATRIX MODAL -->
<div class="modal fade" id="matrixModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bx bx-building me-1"></i> Room–Time Matrix
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div id="matrixContainer">
          <div class="text-center text-muted py-5">
            Loading room utilization…
          </div>
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
    function loadScheduleTable() {

        const pid = $("#prospectus_id").val();
        const ay  = $("#ay_id").val();
        const sem = $("#semester").val();

        if (!pid || !ay || !sem) {
            Swal.fire(
                "Missing Filters",
                "Please select Prospectus, Academic Year, and Semester.",
                "warning"
            );
            return;
        }

        // Show loading state
        $("#scheduleTable tbody").html(
            "<tr><td colspan='10' class='text-center text-muted'>Loading classes...</td></tr>"
        );

        $.post(
            "../backend/load_class_offerings.php",
            {
                prospectus_id: pid,
                ay_id: ay,
                semester: sem
            },
            function (rows) {
                $("#scheduleTable tbody").html(rows);
            }
        ).fail(function (xhr) {
            $("#scheduleTable tbody").html(
                "<tr><td colspan='10' class='text-center text-danger'>Failed to load classes.</td></tr>"
            );
            console.error(xhr.responseText);
        });
    }

    // BUTTON BINDING
    $("#btnLoadSchedule").on("click", function () {
        loadScheduleTable();
    });


    // ===============================================================
    $(document).ready(function () {


$("#btnShowMatrix").on("click", function () {

  const pid = $("#prospectus_id").val();
  const ay  = $("#ay_id").val();
  const sem = $("#semester").val();

  if (!pid || !ay || !sem) {
    Swal.fire(
      "Missing Filters",
      "Please select Prospectus, Academic Year, and Semester first.",
      "warning"
    );
    return;
  }

  $("#matrixModal").modal("show");

  $("#matrixContainer").html(`
    <div class="text-center text-muted py-5">
      Loading room utilization…
    </div>
  `);

  $.post(
    "../backend/load_room_time_matrix.php",
    {
      prospectus_id: pid,
      ay_id: ay,
      semester: sem
    },
    function (html) {
      $("#matrixContainer").html(html);
    }
  ).fail(function (xhr) {
    $("#matrixContainer").html(
      "<div class='text-danger text-center'>Failed to load matrix.</div>"
    );
    console.error(xhr.responseText);
  });

});



        // ============================
        // CLICK SCHEDULE / EDIT BUTTON
        // ============================
        $(document).on("click", ".btn-schedule", function () {

            const btn = $(this);

            // Set hidden offering ID
            $("#sched_offering_id").val(btn.data("offering-id"));

            // Labels
            $("#sched_subject_label").text(
                btn.data("sub-code") + " — " + btn.data("sub-desc")
            );
            $("#sched_section_label").text(
                "Section: " + btn.data("section")
            );

            // Reset fields
            $(".sched-day").prop("checked", false);
            $("#sched_time_start").val("");
            $("#sched_time_end").val("");

            // Faculty & room
            $("#sched_faculty_id").val(btn.data("faculty-id") || "").trigger("change");
            $("#sched_room_id").val(btn.data("room-id") || "").trigger("change");

            // Days (JSON)
            let daysJson = btn.data("days-json");
            if (daysJson) {
                try {
                    let days = JSON.parse(daysJson);
                    days.forEach(d => {
                        $("#day_" + d).prop("checked", true);
                    });
                } catch (e) {
                    console.warn("Invalid days JSON", e);
                }
            }

            // Time
            if (btn.data("time-start")) {
                $("#sched_time_start").val(btn.data("time-start"));
            }
            if (btn.data("time-end")) {
                $("#sched_time_end").val(btn.data("time-end"));
            }

            // Show modal
            $("#scheduleModal").modal("show");
        });


        // ============================
        // SAVE CLASS SCHEDULE
        // ============================
        $("#btnSaveSchedule").on("click", function () {

            const offering_id = $("#sched_offering_id").val();
            const faculty_id  = $("#sched_faculty_id").val();
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

                    Swal.fire({
                        icon: "error",
                        title: "Schedule Conflict",
                        html: res.message,
                        allowOutsideClick: false,
                        customClass: {
                            popup: 'swal-top'
                        }
                    });

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

    });


</script>


</body>
</html>
