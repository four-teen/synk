<?php
session_start();
ob_start();
include '../backend/db.php';
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">

<head>
    <meta charset="utf-8" />
    <title>Generate Offerings | Synk</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" type="text/css" href="custom_css.css">
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
            <i class="bx bx-layer-plus me-2"></i> Generate Prospectus Offerings
          </h4>

          <!-- ========================= -->
          <!-- FILTERS / GENERATE FORM   -->
          <!-- ========================= -->
          <div class="card mb-4">
            <div class="card-body">

              <div class="row g-3">

                <!-- Prospectus -->
                <div class="col-md-4">
                  <label class="form-label">Select Prospectus</label>
                  <select id="prospectus_id" class="form-select">
                    <option value="">Select...</option>
                    <?php
                    $collegeId = $_SESSION['college_id'] ?? 0;

                    $q = $conn->query("
                        SELECT h.prospectus_id,
                               h.effective_sy,
                               p.program_code,
                               p.program_name
                        FROM tbl_prospectus_header h
                        JOIN tbl_program p ON p.program_id = h.program_id
                        WHERE p.college_id = '".$conn->real_escape_string($collegeId)."'
                        ORDER BY p.program_name
                    ");

                    while ($r = $q->fetch_assoc()) {
                        $label = $r['program_code'] . ' — ' . $r['program_name'] . ' (SY ' . $r['effective_sy'] . ')';
                        echo "<option value='{$r['prospectus_id']}'>".htmlspecialchars($label)."</option>";
                    }
                    ?>
                  </select>
                </div>

                <!-- Academic Year -->
                <div class="col-md-3">
                  <label class="form-label">Academic Year</label>
                  <select id="ay_id" class="form-select">
                    <option value="">Select Academic Year...</option>
                    <?php
                    $ayQuery = $conn->query("
                        SELECT ay_id, ay 
                        FROM tbl_academic_years 
                        WHERE status = 'active'
                        ORDER BY ay ASC
                    ");

                    while ($ayRow = $ayQuery->fetch_assoc()) {
                        echo '<option value="'.$ayRow['ay_id'].'">'.htmlspecialchars($ayRow['ay']).'</option>';
                    }
                    ?>
                  </select>
                </div>

                <!-- Semester -->
                <div class="col-md-3">
                  <label class="form-label">Semester</label>
                  <select id="semester" class="form-select">
                    <option value="">Select...</option>
                    <!-- IMPORTANT: values are 1 / 2 / 3 matching tbl_prospectus_year_sem.semester -->
                    <option value="1">First Semester</option>
                    <option value="2">Second Semester</option>
                    <option value="3">Midyear</option>
                  </select>
                </div>

                <!-- Button -->
                <div class="col-md-2 d-grid">
                  <label class="form-label">&nbsp;</label>
                  <button class="btn btn-primary" id="btnGenerateOfferings">
                    <i class="bx bx-refresh me-1"></i> Generate
                  </button>
                </div>

              </div>
            </div>
          </div>

          <!-- ========================= -->
          <!-- GENERATED OFFERINGS TABLE -->
          <!-- ========================= -->
          <div class="card">
            <div class="card-header">
              <h5 class="m-0">Generated Offerings</h5>
              <small class="text-muted">
                List of offerings for selected prospectus, AY, and semester.
              </small>
            </div>

            <div class="table-responsive p-3">
              <table class="table table-bordered table-hover" id="offeringsTable">
                <thead>
                  <tr>
                    <th>Section</th>
                    <th>Subject Code</th>
                    <th>Description</th>
                    <th>Units</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="5" class="text-center text-muted">
                      Select filters and generate.
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

  // ---------------------
  // Initialize Select2
  // ---------------------
  $('#prospectus_id').select2({
    placeholder: "Select Prospectus",
    width: '100%'
  });

  $('#ay_id').select2({
    placeholder: "Select Academic Year",
    width: '100%'
  });

  $('#semester').select2({
    placeholder: "Select Semester",
    width: '100%'
  });

  // ---------------------
  // Generate Offerings
  // ---------------------
  $('#btnGenerateOfferings').on('click', function () {

    let pid = $('#prospectus_id').val();
    let ay  = $('#ay_id').val();      // ay_id (e.g., 2)
    let sem = $('#semester').val();   // 1 / 2 / 3

    console.log("DEBUG:", pid, ay, sem);

    if (!pid || !ay || !sem) {
      Swal.fire("Missing Data", "Fill all fields.", "warning");
      return;
    }

    Swal.fire({
      title: "Sync Offerings?",
      text: "This will synchronize offerings based on current active sections.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Proceed"
    }).then((res) => {
      if (!res.isConfirmed) return;

      Swal.fire({
        title: "Generating...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });

      $.ajax({
        url: "../backend/query_generate_offerings.php",
        type: "POST",
        dataType: "json",
        data: {
          generate_offerings: 1,
          prospectus_id: pid,
          ay_id: ay,
          semester: sem
        },
        success: function (out) {
          if (out.status !== 'ok') {
            Swal.fire("Error", out.message || "Unknown error.", "error");
            return;
          }

          Swal.fire({
            icon: "success",
            title: "Done!",
            text: `Added: ${out.inserted}, Removed: ${out.deleted_offerings}`,
            timer: 1500,
            showConfirmButton: false
          });

          loadOfferings(pid, ay, sem);
        },
        error: function (xhr) {
          Swal.fire("Error", xhr.responseText, "error");
        }
      });

    });
  });

  // ---------------------
  // Load Offerings Table
  // ---------------------
  function loadOfferings(pid, ay, sem) {
    $.post(
      "../backend/load_offerings.php",
      { prospectus_id: pid, ay_id: ay, semester: sem },
      function (rows) {
        $('#offeringsTable tbody').html(rows);
      }
    ).fail(function (xhr) {
      $('#offeringsTable tbody').html(
        "<tr><td colspan='5' class='text-danger text-center'>Error loading offerings.</td></tr>"
      );
      console.error(xhr.responseText);
    });
  }

});
</script>

</body>
</html>
