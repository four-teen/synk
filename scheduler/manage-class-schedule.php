<?php
session_start();
ob_start();
include '../backend/db.php';
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
    <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />


    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

  <style>
    .select2-selection--single {
      height: 40px !important;
      padding: 6px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 38px !important;
    }
    .badge-status {
      font-size: 0.75rem;
      padding: 0.25rem 0.5rem;
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

          <h4 class="fw-bold mb-4">
            <i class="bx bx-time-five me-2"></i> Class Scheduling
          </h4>

          <!-- ========================= -->
          <!-- FILTERS                   -->
          <!-- ========================= -->
          <div class="card mb-4">
            <div class="card-body">
              <div class="row g-3">

                <!-- Prospectus -->
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
                      $label = $r['program_code'] . ' — ' . $r['program_name'] .
                               ' (SY ' . $r['effective_sy'] . ')';
                      echo "<option value='{$r['prospectus_id']}'>$label</option>";
                    }
                    ?>
                  </select>
                </div>

                <!-- AY -->
                <div class="col-md-3">
                  <label class="form-label">Academic Year</label>
                  <select id="ay_id" class="form-select">
                    <option value="">Select Academic Year...</option>
                    <?php
                    $ayQ = $conn->query("
                        SELECT ay_id, ay
                        FROM tbl_academic_years
                        WHERE status = 'active'
                        ORDER BY ay ASC
                    ");
                    while ($ayRow = $ayQ->fetch_assoc()) {
                      echo '<option value="' . $ayRow['ay_id'] . '">' .
                           htmlspecialchars($ayRow['ay']) . '</option>';
                    }
                    ?>
                  </select>
                </div>

                <!-- Semester -->
                <div class="col-md-3">
                  <label class="form-label">Semester</label>
                  <select id="semester" class="form-select">
                    <option value="">Select...</option>
                    <option value="1">First Semester</option>
                    <option value="2">Second Semester</option>
                    <option value="3">Midyear</option>
                  </select>
                </div>

                <!-- Load Button -->
                <div class="col-md-2 d-grid">
                  <label class="form-label">&nbsp;</label>
                  <button class="btn btn-primary" id="btnLoadSchedule">
                    <i class="bx bx-search-alt-2 me-1"></i> Load
                  </button>
                </div>

              </div>
            </div>
          </div>

          <!-- ========================= -->
          <!-- CLASS SCHEDULE TABLE      -->
          <!-- ========================= -->
          <div class="card">
            <div class="card-header">
              <h5 class="m-0">Class Offerings & Schedule</h5>
              <small class="text-muted">
                List of generated offerings for the selected prospectus, AY, and semester.
              </small>
            </div>

            <div class="table-responsive p-3">
              <table class="table table-bordered table-hover" id="scheduleTable">
                <thead>
                <tr>
                  <th style="width: 80px;">Section</th>
                  <th style="width: 110px;">Subject Code</th>
                  <th>Description</th>
                  <th style="width: 65px;">Units</th>
                  <th style="width: 180px;">Faculty</th>
                  <th style="width: 100px;">Days</th>
                  <th style="width: 130px;">Time</th>
                  <th style="width: 120px;">Room</th>
                  <th style="width: 90px;">Status</th>
                  <th style="width: 90px;">Action</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                  <td colspan="10" class="text-center text-muted">
                    Select filters and click <strong>Load</strong>.
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

<!-- ========================= -->
<!-- SCHEDULE MODAL            -->
<!-- ========================= -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          Schedule Class
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="sched_offering_id">

        <div class="mb-3">
          <strong id="sched_subject_label"></strong><br>
          <small class="text-muted" id="sched_section_label"></small>
        </div>

        <div class="row g-3">
          <!-- Faculty -->
          <div class="col-md-6">
            <label class="form-label">Faculty</label>
<select id="sched_faculty_id" class="form-select">
  <option value="">Select faculty...</option>
  <?php
  $fQ = $conn->query("
        SELECT 
            faculty_id,
            TRIM(CONCAT(
                last_name, ', ',
                first_name, ' ',
                IF(middle_name <> '' AND middle_name IS NOT NULL, CONCAT(LEFT(middle_name,1),'. '), ''),
                IF(ext_name <> '' AND ext_name IS NOT NULL, CONCAT(ext_name,' '), '')
            )) AS fullname
        FROM tbl_faculty
        WHERE status = 'active'
        ORDER BY last_name, first_name
  ");

  while ($f = $fQ->fetch_assoc()) {
      echo '<option value="' . $f['faculty_id'] . '">' . 
           htmlspecialchars($f['fullname']) . 
           '</option>';
  }
  ?>
</select>


          </div>

          <!-- Room -->
          <div class="col-md-6">
            <label class="form-label">Room</label>
            <select id="sched_room_id" class="form-select">
              <option value="">Select room...</option>
              <?php
              $rQ = $conn->query("
                    SELECT room_id, room_name
                    FROM tbl_rooms
                    WHERE status = 'active'
                    ORDER BY room_name
              ");
              while ($r = $rQ->fetch_assoc()) {
                echo '<option value="' . $r['room_id'] . '">' .
                     htmlspecialchars($r['room_name']) . '</option>';
              }
              ?>
            </select>
          </div>
        </div>

        <hr>

        <div class="row g-3">
          <!-- Days -->
          <div class="col-md-6">
            <label class="form-label d-block">Days</label>
            <div class="btn-group" role="group" aria-label="Days">
              <?php
              $dayMap = [
                'M'  => 'M',
                'T'  => 'T',
                'W'  => 'W',
                'Th' => 'Th',
                'F'  => 'F',
                'S'  => 'Sat'
              ];
              foreach ($dayMap as $code => $label) {
                echo '
                  <input type="checkbox" class="btn-check sched-day" id="day_' . $code . '" value="' . $code . '">
                  <label class="btn btn-outline-secondary btn-sm" for="day_' . $code . '">' . $label . '</label>
                ';
              }
              ?>
            </div>
          </div>

          <!-- Time -->
          <div class="col-md-3">
            <label class="form-label">Time Start</label>
            <input type="time" id="sched_time_start" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Time End</label>
            <input type="time" id="sched_time_end" class="form-control">
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="btnSaveSchedule">
          <i class="bx bx-save me-1"></i> Save Schedule
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ========================= -->
<!-- JS SCRIPTS                -->
<!-- ========================= -->
<!-- Core JS -->
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

  // INIT SELECT2
  $('#prospectus_id').select2({ placeholder: 'Select Prospectus', width: '100%' });
  $('#ay_id').select2({ placeholder: 'Select Academic Year', width: '100%' });
  $('#semester').select2({ placeholder: 'Select Semester', width: '100%' });
  $('#sched_faculty_id').select2({ dropdownParent: $('#scheduleModal'), width: '100%' });
  $('#sched_room_id').select2({ dropdownParent: $('#scheduleModal'), width: '100%' });

  // LOAD SCHEDULE TABLE
$('#btnLoadSchedule').on('click', function () {

    const pid = $('#prospectus_id').val();
    const ay  = $('#ay_id').val();
    const sem = $('#semester').val();

    if (!pid || !ay || !sem) {
        Swal.fire('Missing Data','Please select Prospectus, AY, and Semester.','warning');
        return;
    }

    // RESET TABLE to prevent ghost rows
// RESET TABLE to show loading state
$("#scheduleTable tbody").html(
    "<tr><td colspan='10' class='text-center text-muted'>Loading...</td></tr>"
);


    $.post('../backend/load_class_offerings.php', {
        prospectus_id: pid,
        ay: ay,
        semester: sem
    }, function(rows){
        $('#scheduleTable tbody').html(rows);
    });
});



  // OPEN MODAL FOR SCHEDULING
  $(document).on('click', '.btn-schedule', function () {
    const $btn = $(this);

    $('#sched_offering_id').val($btn.data('offering-id'));
    $('#sched_subject_label').text(
      $btn.data('sub-code') + ' — ' + $btn.data('sub-desc')
    );
    $('#sched_section_label').text('Section: ' + $btn.data('section'));

    // reset fields
    $('#sched_faculty_id').val($btn.data('faculty-id') || '').trigger('change');
    $('#sched_room_id').val($btn.data('room-id') || '').trigger('change');
    $('.sched-day').prop('checked', false);
    $('#sched_time_start').val('');
    $('#sched_time_end').val('');

    // prefill existing, if any
    const daysJson = $btn.data('days-json');
    if (daysJson) {
      try {
        const arr = JSON.parse(daysJson);
        arr.forEach(d => $('#day_' + d.replace('"','')).prop('checked', true));
      } catch (e) {}
    }
    if ($btn.data('time-start')) $('#sched_time_start').val($btn.data('time-start'));
    if ($btn.data('time-end')) $('#sched_time_end').val($btn.data('time-end'));

    $('#scheduleModal').modal('show');
  });

  // SAVE SCHEDULE
$('#btnSaveSchedule').on('click', function () {

    const offering_id = $('#sched_offering_id').val();
    const faculty_id  = $('#sched_faculty_id').val();
    const room_id     = $('#sched_room_id').val();
    const time_start  = $('#sched_time_start').val();
    const time_end    = $('#sched_time_end').val();

    let days = [];
    $('.sched-day:checked').each(function () {
      days.push($(this).val());
    });

    if (!offering_id || !faculty_id || !room_id || !time_start || !time_end || days.length === 0) {
      Swal.fire('Missing Data', 'Please complete all schedule fields.', 'warning');
      return;
    }

    if (time_end <= time_start) {
      Swal.fire('Invalid Time', 'End time must be later than start time.', 'warning');
      return;
    }

    $.ajax({
      url: '../backend/query_class_schedule.php',
      type: 'POST',
      dataType: 'json',
      data: {
        save_schedule: 1,
        offering_id: offering_id,
        faculty_id: faculty_id,
        room_id: room_id,
        time_start: time_start,
        time_end: time_end,
        days_json: JSON.stringify(days)
      },
      success: function (res) {

        console.log("RELOAD WITH: ", 
            $("#prospectus_id").val(),
            $("#ay_id").val(),
            $("#semester").val()
        );

        if (res.status === 'conflict') {
          Swal.fire({
            icon: 'error',
            title: 'Schedule Conflict',
            html: res.message
          });
          return;
        }

        if (res.status === 'ok') {
          Swal.fire({
            icon: 'success',
            title: 'Schedule Saved',
            timer: 1200,
            showConfirmButton: false
          });
          $('#scheduleModal').modal('hide');

          $('#btnLoadSchedule').click();   // 🔥 this is correct
        } 
        else {
          Swal.fire('Error', res.message || 'Unknown error.', 'error');
        }
      },
      error: function (xhr) {
        Swal.fire('Error', xhr.responseText, 'error');
      }
    });
});


});
</script>

</body>
</html>
