<?php 
    session_start();
    ob_start();
    include '../backend/db.php';
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
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Faculty Assignment | Synk</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="custom_css.css">
    <!-- Select2 (for searchable dropdowns) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
      #collegeFacultyTable td {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
      }
      #collegeFacultyTable td:last-child {
        white-space: nowrap !important;
      }
      .select2-container .select2-selection--single {
        height: 38px !important;
        padding: 4px 10px;
      }
      .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
      }

  #collegeFacultyTable td {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
  #collegeFacultyTable td:last-child {
    white-space: nowrap !important;
  }
  .select2-container .select2-selection--single {
    height: 38px !important;
    padding: 4px 10px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
  }

  /* 🔥 FIX MODAL + SWEETALERT + SELECT2 z-index conflict */
  .modal.show {
    z-index: 99999 !important;
  }
  .select2-container--open {
    z-index: 100000 !important;
  }
  .swal2-container {
    z-index: 1000000 !important;
  }
  .swal2-timer-progress-bar {
  background: linear-gradient(90deg, #28a745, #20c997) !important;
}
    </style>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    <!-- /Sidebar -->

    <!-- Layout page -->
    <div class="layout-page">

      <!-- Navbar -->
      <?php include 'navbar.php'; ?>
      <!-- /Navbar -->

      <!-- Content wrapper -->
      <div class="content-wrapper">

        <div class="container-xxl flex-grow-1 container-p-y">

          <!-- Title + Controls -->
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
            <div class="mb-3 mb-md-0">
              <h4 class="fw-bold mb-1">
                <i class="bx bx-group me-2"></i> Faculty Assignment per College
              </h4>
              <p class="mb-0 text-muted">
                Map faculty members to their respective colleges for scheduling and workload.
              </p>
            </div>

            <div class="d-flex flex-column align-items-stretch align-items-md-end">
              <!-- College Select -->

<div class="mb-2">
    
                <?php
                    $myCollege     = $_SESSION['college_id'];
                    $college_sql   = $conn->query("SELECT college_name, college_code FROM tbl_college WHERE college_id='$myCollege'");
                    $college_row   = $college_sql->fetch_assoc();
                    $college_label = $college_row['college_code'] . " - " . $college_row['college_name'];
                ?>
    <div class="fw-bold" style="font-size: 16px;">
        <?= $college_label ?>
    </div>

    <input type="hidden" id="college_id" value="<?= $myCollege ?>">
</div>              




              <!-- Add Faculty Button -->
              <button class="btn btn-primary" id="btnAddFaculty">
                <i class="bx bx-plus"></i> Add Faculty to College
              </button>
            </div>
          </div>

          <!-- Card: Assigned Faculty List -->
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div>
                <h5 class="m-0">Assigned Faculty</h5>
                <small class="text-muted">Faculty currently linked to the selected college.</small>
              </div>
            </div>

            <div class="card-datatable table-responsive p-3">
<table class="table table-hover align-middle" id="collegeFacultyTable">
  <thead>
    <tr>
      <th style="width:60px;">#</th>
      <th>Faculty Name</th>
      <th style="width:120px;">Status</th>
      <th style="width:170px;">Date Assigned</th>
      <th class="text-end" style="width:100px;">Actions</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <?php include '../footer.php'; ?>
        <!-- /Footer -->

        <div class="content-backdrop fade"></div>
      </div>
      <!-- Content wrapper -->

    </div>
    <!-- /Layout page -->

  </div>

  <!-- Overlay -->
  <div class="layout-overlay layout-menu-toggle"></div>
</div>

<!-- ========================= -->
<!-- ADD FACULTY MODAL        -->
<!-- ========================= -->
<div class="modal fade" id="addFacultyModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Add Faculty to College</h5>
      </div>

      <div class="modal-body">
        <form id="addFacultyForm">
          <div class="mb-3">
            <label class="form-label">Faculty</label>
            <select id="faculty_id" class="form-select" style="width: 100%;">
              <option value="">Select Faculty...</option>
            </select>
          </div>

          <!-- hidden submit to support Enter -->
          <button type="submit" hidden></button>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" form="addFacultyForm">
          Save
        </button>
      </div>

    </div>
  </div>
</div>


<!-- Core JS -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/main.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let facultyTable;

$(document).ready(function () {

  /* ================================
     INITIALIZE DATATABLE (ONCE)
  ================================= */
  facultyTable = $('#collegeFacultyTable').DataTable({
    paging: true,
    searching: true,
    ordering: false,
    info: true,
    lengthChange: false,
    pageLength: 10,
    language: {
      emptyTable: "No faculty assigned to this college."
    }
  });

  /* ================================
     AUTO LOAD FACULTY (NON-ADMIN)
  ================================= */
  <?php if ($_SESSION['role'] !== 'admin'): ?>
    let myCollege = "<?= $_SESSION['college_id'] ?>";
    loadCollegeFaculty(myCollege);
  <?php endif; ?>

  /* ================================
     SELECT2 INITIALIZATION
  ================================= */
  $('#faculty_id').select2({
    dropdownParent: $('#addFacultyModal .modal-body'),
    width: '100%',
    placeholder: "Select Faculty...",
    allowClear: true
  });

  loadFacultyDropdown();

  /* ================================
     ENTER KEY FIX (SELECT2 AWARE)
  ================================= */
  $('#addFacultyModal').on('keydown', function (e) {

    if (e.key === "Enter") {

      // Allow Enter for Select2 option selection
      if ($('.select2-container--open').length) {
        return;
      }

      e.preventDefault();
      $('#addFacultyForm').submit();
    }

  });

  /* ================================
     OPEN MODAL
  ================================= */
  $('#btnAddFaculty').on('click', function () {

    const college_id = $('#college_id').val();

    if (!college_id) {
      Swal.fire("No College Selected", "Please select a college first.", "warning");
      return;
    }

    $('#faculty_id').val(null).trigger('change');

    const modal = new bootstrap.Modal(
      document.getElementById('addFacultyModal'),
      {
        backdrop: 'static',
        keyboard: false
      }
    );

    modal.show();
  });

  /* ================================
     SAVE ASSIGNMENT
  ================================= */
  $('#addFacultyForm').on('submit', function (e) {
    e.preventDefault();

    const college_id = $('#college_id').val();
    const faculty_id = $('#faculty_id').val();

    if (!college_id || !faculty_id) {
      Swal.fire({
        icon: "warning",
        title: "Missing Data",
        text: "Please select faculty to assign."
      });
      return;
    }

    $.ajax({
      url: "../backend/query_college_faculty.php",
      type: "POST",
      dataType: "json",
      data: {
        save_assignment: 1,
        college_id: college_id,
        faculty_id: faculty_id
      },
      success: function (res) {

        if (res.status === "inserted") {

          Swal.fire({
            icon: "success",
            title: "Faculty Assigned!",
            text: "Faculty has been successfully assigned to this college.",
            timer: 1200,
            timerProgressBar: true,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
          });

          // $('#addFacultyModal').modal('hide');
          loadCollegeFaculty();
        }

        else if (res.status === "duplicate") {
          Swal.fire("Already Assigned", "This faculty is already assigned.", "info");
        }

        else {
          Swal.fire("Error", "Something went wrong.", "error");
        }
      },
      error: function () {
        Swal.fire("Server Error", "Unable to connect.", "error");
      }
    });
  });

  /* ================================
     REMOVE ASSIGNMENT
  ================================= */
  $(document).on('click', '.btn-remove-assignment', function () {

    const college_faculty_id = $(this).data('id');

    Swal.fire({
      title: "Remove Assignment?",
      text: "This will mark the faculty as inactive for this college.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, remove",
      cancelButtonText: "Cancel"
    }).then((result) => {

      if (!result.isConfirmed) return;

      $.ajax({
        url: "../backend/query_college_faculty.php",
        type: "POST",
        dataType: "json",
        data: {
          remove_assignment: 1,
          college_faculty_id: college_faculty_id
        },
        success: function (res) {

          if (res.status === "removed") {
            Swal.fire({
              icon: "success",
              title: "Removed!",
              text: "Faculty assignment removed successfully.",
              timer: 1200,
              showConfirmButton: false
            });
            loadCollegeFaculty();
          }
          else {
            Swal.fire("Error", "Unable to remove assignment.", "error");
          }
        },
        error: function () {
          Swal.fire("Error", "Unable to connect to the server.", "error");
        }
      });
    });
  });

}); // END DOCUMENT READY


/* ==========================================================
   LOAD FACULTY DROPDOWN
========================================================== */
function loadFacultyDropdown() {

  $.post(
    "../backend/query_college_faculty.php",
    { load_faculty_dropdown: 1 },
    function (data) {

      $('#faculty_id').empty().append('<option value=""></option>');

      $.each(data, function (i, item) {
        $('#faculty_id').append(
          $('<option>', { value: item.id, text: item.text })
        );
      });
    },
    "json"
  );
}


/* ==========================================================
   LOAD FACULTY ASSIGNMENTS (DATATABLE SAFE)
========================================================== */
function loadCollegeFaculty(collegeID = null) {

  const college_id = collegeID || $('#college_id').val();
  if (!college_id) return;

  $.ajax({
    url: "../backend/query_college_faculty.php",
    type: "POST",
    dataType: "json",
    data: {
      load_college_faculty: 1,
      college_id: college_id
    },
    success: function (data) {

      facultyTable.clear();

      if (!data || data.length === 0) {
        facultyTable.draw();
        return;
      }

      let n = 1;
      data.forEach(row => {
        facultyTable.row.add([
          n++,
          `${row.last_name}, ${row.first_name}`,
          row.status,
          row.date_created,
          `
          <button class="btn btn-sm btn-danger btn-remove-assignment"
                  data-id="${row.college_faculty_id}">
            <i class="bx bx-trash"></i>
          </button>
          `
        ]);
      });

      facultyTable.draw();
    }
  });
}
</script>

