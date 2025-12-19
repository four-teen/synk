<?php
session_start();
ob_start();
include '../backend/db.php';

/*
|--------------------------------------------------------------------------
| ADMIN PROSPECTUS BUILDER
|--------------------------------------------------------------------------
| • Uses the SAME backend as Scheduler
| • Admin can see ALL programs across campuses
| • Supports:
|   - Prospectus Header (Add / Load)
|   - Year & Semester
|   - Subject Add / Edit / Delete
|--------------------------------------------------------------------------
*/

$role      = $_SESSION['role'] ?? 'admin';
$collegeId = $_SESSION['college_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en"
      class="light-style layout-menu-fixed"
      dir="ltr"
      data-theme="theme-default"
      data-assets-path="../assets/"
      data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Prospectus Builder | Synk</title>

  <!-- Core Styles -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <!-- Page Styles -->
  <style>
    .prospectus-header-label {
      font-size: 0.85rem;
      text-transform: uppercase;
      color: #888;
      letter-spacing: 0.04em;
    }

    .table-prospectus td {
      padding-top: 0.4rem !important;
      padding-bottom: 0.4rem !important;
    }

    .accordion-header {
      background: #FFF7E6 !important;
      border: 1px solid #FFE4C2 !important;
      border-radius: 6px;
      padding: 6px 12px;
      margin-bottom: 6px;
    }

    .swal2-container {
      z-index: 20000 !important;
    }

    .swal2-popup {
      font-size: 0.85rem !important;
      padding: 1.2rem !important;
      border-radius: 12px !important;
    }

    .swal2-timer-progress-bar {
      background: #28a745 !important;
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

<!-- ======================================================
     PAGE TITLE
====================================================== -->
<h4 class="fw-bold mb-4">
  <i class="bx bx-spreadsheet me-2"></i> Prospectus Builder
</h4>

<!-- ======================================================
     PROSPECTUS HEADER
====================================================== -->
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">Prospectus Information</h5>
    <small class="text-muted">Select program and define CMO + Effectivity</small>
  </div>

  <div class="card-body">
    <form id="prospectusHeaderForm" class="row g-3 align-items-end">
      <input type="hidden" name="prospectus_id" id="prospectus_id">

      <!-- Program -->
      <div class="col-md-5">
        <label class="form-label prospectus-header-label">Program</label>
        <select class="form-select" name="program_id" id="program_id" required>
          <option value="">Select Program</option>
          <?php
          $prog = $conn->query("
            SELECT p.program_id, p.program_code, p.program_name, p.major, c.college_name
            FROM tbl_program p
            LEFT JOIN tbl_college c ON p.college_id = c.college_id
            WHERE p.status='active'
            ORDER BY c.college_name, p.program_name, p.major
          ");
          while ($r = $prog->fetch_assoc()) {
            $label = $r['college_name'] . ' - ' . $r['program_name'];
            if (trim($r['major']) !== '') {
              $label .= ' major in ' . $r['major'];
            }
            $label .= ' (' . $r['program_code'] . ')';
            echo "<option value='{$r['program_id']}'>" . htmlspecialchars($label) . "</option>";
          }
          ?>
        </select>
      </div>

      <!-- CMO -->
      <div class="col-md-3">
        <label class="form-label prospectus-header-label">CMO Number</label>
        <input type="text" class="form-control" name="cmo_no" id="cmo_no" required>
      </div>

      <!-- Effectivity -->
      <div class="col-md-2">
        <label class="form-label prospectus-header-label">Effectivity SY</label>
        <input type="text" class="form-control" name="effective_sy" id="effective_sy" required>
      </div>

      <!-- Actions -->
      <div class="col-md-2 text-end">
        <button type="button" id="btnSaveHeader" class="btn btn-success w-100 mb-1">
          <i class="bx bx-save"></i> Save New
        </button>
        <button type="button" id="btnLoadHeader" class="btn btn-primary w-100">
          <i class="bx bx-search"></i> Load Existing
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================
     YEAR & SEMESTER SECTION
====================================================== -->
<div class="card" id="yearSemCard" style="display:none;">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <h5 class="mb-0">Year & Semester Structure</h5>
      <small class="text-muted">Manage subjects per year and semester</small>
    </div>
    <button class="btn btn-outline-primary btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#yearSemModal">
      <i class="bx bx-plus"></i> Add Year / Semester
    </button>
  </div>

  <div class="card-body">
    <div class="accordion" id="yearSemAccordion"></div>
  </div>
</div>

</div>
<?php include '../footer.php'; ?>
<div class="content-backdrop fade"></div>
</div>
</div>
</div>

<!-- ======================================================
     MODALS
====================================================== -->

<!-- Add Year / Semester -->
<div class="modal fade" id="yearSemModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Year & Semester</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="yearSemForm">
          <input type="hidden" name="prospectus_id" id="ys_prospectus_id">
          <select name="year_level" class="form-select mb-3" required>
            <option value="">Year Level</option>
            <option value="1">First Year</option>
            <option value="2">Second Year</option>
            <option value="3">Third Year</option>
            <option value="4">Fourth Year</option>
          </select>
          <select name="semester" class="form-select" required>
            <option value="">Semester</option>
            <option value="1">First Semester</option>
            <option value="2">Second Semester</option>
            <option value="3">Summer</option>
          </select>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveYearSem">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Add / Edit Subject -->
<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="subjectModalTitle">Add Subject</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="prospectusSubjectForm">
          <input type="hidden" name="pys_id" id="ps_pys_id">
          <input type="hidden" name="ps_id" id="ps_id">

          <div class="mb-3">
            <label class="form-label">Subject</label>
            <select id="ps_sub_id" name="sub_id" class="form-select" required></select>
          </div>

          <div class="row">
            <div class="col-md-3 mb-3">
              <label class="form-label">Lec</label>
              <input type="number" name="lec_units" id="ps_lec_units"
                     class="form-control" min="0" value="3">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Lab</label>
              <input type="number" name="lab_units" id="ps_lab_units"
                     class="form-control" min="0" value="0">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Total Units</label>
              <input type="number" name="total_units" id="ps_total_units"
                     class="form-control" min="0" value="3">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Sort Order</label>
              <input type="number" name="sort_order" id="ps_sort_order"
                     class="form-control" min="1">
            </div>
          </div>


          <div class="mb-3">
            <label class="form-label">Pre-requisite Subjects</label>
            <select id="ps_prerequisites" class="form-select" multiple></select>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveProspectusSubject">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Load Prospectus -->
<div class="modal fade" id="loadProspectusModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Load Prospectus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <select id="existingProspectusList" class="form-select"></select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnLoadSelectedProspectus">Load</button>
      </div>
    </div>
  </div>
</div>

<!-- ======================================================
     JAVASCRIPT
====================================================== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../assets/js/main.js"></script>

<script>
/*
|--------------------------------------------------------------------------
| GLOBAL STATE
|--------------------------------------------------------------------------
*/
let lastOpenedPys = null;
let currentSortOrder = 1;

/*
|--------------------------------------------------------------------------
| LOAD YEAR & SEMESTER ACCORDION
|--------------------------------------------------------------------------
*/
function loadYearSem(prospectusId) {
  $.post(
    "../backend/query_prospectus.php",
    { load_year_sem: 1, prospectus_id: prospectusId },
    function (html) {

      $("#yearSemAccordion").html(html);

      // Determine next sort order from existing rows
      currentSortOrder = 1;

      $("#yearSemAccordion table tbody tr").each(function () {
        let order = parseInt($(this).find("td:first").text());
        if (!isNaN(order)) {
          currentSortOrder = Math.max(currentSortOrder, order + 1);
        }
      });

      // Apply computed value
      $("#ps_sort_order").val(currentSortOrder);

      if (lastOpenedPys) {
        $("#" + lastOpenedPys).collapse("show");
      }
    }
  );
}


/*
|--------------------------------------------------------------------------
| REMEMBER OPENED ACCORDION
|--------------------------------------------------------------------------
*/
$(document).on("shown.bs.collapse", ".accordion-collapse", function () {
  lastOpenedPys = $(this).attr("id");
});

/*
|--------------------------------------------------------------------------
| SUBJECT UTILITIES
|--------------------------------------------------------------------------
*/
function loadAllSubjects() {
  return $.post(
    "../backend/query_prospectus.php",
    { get_all_subjects: 1 },
    function (res) {

      let data = JSON.parse(res);

      let sel = $("#ps_sub_id").empty();
      let pre = $("#ps_prerequisites").empty();

      // Optional placeholder (Select2-friendly)
      sel.append(`<option value=""></option>`);

      data.forEach(s => {
        sel.append(
          `<option value="${s.sub_id}">
            ${s.sub_code} - ${s.sub_description}
          </option>`
        );

        pre.append(
          `<option value="${s.sub_id}">
            ${s.sub_code} - ${s.sub_description}
          </option>`
        );
      });
    }
  );
}


/*
|--------------------------------------------------------------------------
| DOCUMENT READY
|--------------------------------------------------------------------------
*/
$(document).ready(function () {
/* --------------------------------------------------
   INIT SELECT2 (SUBJECT & PREREQUISITES)
-------------------------------------------------- */
$('#ps_sub_id').select2({
  placeholder: "Select Subject",
  width: "100%",
  dropdownParent: $('#subjectModal')
});

$('#ps_prerequisites').select2({
  placeholder: "Select pre-requisite subjects",
  width: "100%",
  dropdownParent: $('#subjectModal')
});


  /* ---------------------------
     Load Prospectus List
  --------------------------- */
  $("#btnLoadHeader").click(function () {
    $("#loadProspectusModal").modal("show");

    $.post(
      "../backend/query_prospectus.php",
      { load_prospectus_list: 1 },
      function (res) {
        $("#existingProspectusList").empty()
          .append(`<option value="">Select Prospectus</option>`);
        res.forEach(r => {
          $("#existingProspectusList").append(
            `<option value="${r.prospectus_id}">
              ${r.program_name} (${r.program_code}) — ${r.cmo_no} — ${r.effective_sy}
            </option>`
          );
        });
      },
      "json"
    );
  });

  /* ---------------------------
     Load Selected Prospectus
  --------------------------- */
  $("#btnLoadSelectedProspectus").click(function () {
    let pid = $("#existingProspectusList").val();
    if (!pid) return;

    $.post(
      "../backend/query_prospectus.php",
      { load_header: 1, prospectus_id: pid },
      function (res) {
        let p = res.split("|");
        if (p[0] !== "OK") return;

        $("#prospectus_id, #ys_prospectus_id").val(pid);
        $("#program_id").val(p[1]);
        $("#cmo_no").val(p[2]);
        $("#effective_sy").val(p[3]);

        $("#yearSemCard").show();
        loadYearSem(pid);
        $("#loadProspectusModal").modal("hide");
      }
    );
  });

  /* ---------------------------
     Save Prospectus Header
  --------------------------- */
  $("#btnSaveHeader").click(function () {
    $.post(
      "../backend/query_prospectus.php",
      $("#prospectusHeaderForm").serialize() + "&save_header=1",
      function (res) {
        let p = res.split("|");
        Swal.fire(p[2]);
        if (p[0] === "OK") {
          $("#prospectus_id, #ys_prospectus_id").val(p[1]);
          $("#yearSemCard").show();
          loadYearSem(p[1]);
        }
      }
    );
  });

  /* ---------------------------
     Save Year / Semester
  --------------------------- */
  $("#btnSaveYearSem").click(function () {
    $.post(
      "../backend/query_prospectus.php",
      $("#yearSemForm").serialize() + "&save_year_sem=1",
      function (res) {
        let p = res.split("|");
        Swal.fire(p[2]);
        if (p[0] === "OK") {
          $("#yearSemModal").modal("hide");
          loadYearSem($("#prospectus_id").val());
        }
      }
    );
  });

  /* ---------------------------
     Add Subject
  --------------------------- */
/* ---------------------------
   Add Subject
--------------------------- */
$(document).on("click", ".btnAddSubject", function () {

  let pys = $(this).data("pys");

  $("#ps_pys_id").val(pys);
  $("#ps_id").val("");

  // Load subjects FIRST, then open modal
  loadAllSubjects().then(() => {

    // Set computed sort order (DO NOT increment here)
    $("#ps_sort_order").val(currentSortOrder);

    // Clear selections only
    $("#ps_sub_id").val(null).trigger("change");
    $("#ps_prerequisites").val(null).trigger("change");

    $("#subjectModalTitle").text("Add Subject");
    $("#subjectModal").modal("show");

  });
});




  /* ---------------------------
     Edit Subject
  --------------------------- */
  $(document).on("click", ".btnEditSubject", function () {
    let psId = $(this).data("ps");

    $.post(
      "../backend/query_prospectus.php",
      { load_subject_for_edit: 1, ps_id: psId },
      function (res) {
        let r = JSON.parse(res);

        $("#subjectModalTitle").text("Edit Subject");
        $("#ps_id").val(r.ps_id);
        $("#ps_sub_id").val(r.sub_id);
        $("#ps_lec_units").val(r.lec_units);
        $("#ps_lab_units").val(r.lab_units);
        $("#ps_total_units").val(r.total_units);
        $("#ps_sort_order").val(r.sort_order);

        loadAllSubjects().then(() => {
          if (r.prerequisite_sub_ids) {
            $("#ps_prerequisites")
              .val(JSON.parse(r.prerequisite_sub_ids))
              .trigger("change");
          }
        });

        $("#subjectModal").modal("show");
      }
    );
  });

  /* ---------------------------
     Save Subject (Add / Edit)
  --------------------------- */
$("#btnSaveProspectusSubject").click(function () {

  let data = $("#prospectusSubjectForm").serializeArray();
  let prereq = $("#ps_prerequisites").val() || [];

  data.push({
    name: "prerequisite_sub_ids",
    value: JSON.stringify(prereq)
  });

  if ($("#ps_id").val()) {
    data.push({ name: "update_prospectus_subject", value: 1 });
  } else {
    data.push({ name: "save_prospectus_subject", value: 1 });
  }

  $.post("../backend/query_prospectus.php", data, function (res) {

    let p = res.split("|");

    if (p[0] !== "OK") {
      Swal.fire("Error", p[2] || p[1], "error");
      return;
    }

    /* ==========================================
       SUCCESS — SCHEDULER-LIKE BEHAVIOR
    ========================================== */
    Swal.fire({
      icon: "success",
      title: "Saved!",
      text: p[2],
      timer: 1200,
      showConfirmButton: false,
      heightAuto: false
    });

    // 🔁 Refresh table
    loadYearSem($("#prospectus_id").val());

  /* ---------------------------------------
     REFRESH SUBJECT TABLE
  --------------------------------------- */
  loadYearSem($("#prospectus_id").val());

  /* ---------------------------------------
     ✅ FIX 4 — INCREMENT SORT ORDER HERE
     (AFTER successful save ONLY)
  --------------------------------------- */
  currentSortOrder++;
  $("#ps_sort_order").val(currentSortOrder);

  /* ---------------------------------------
     CLEAR FORM (DO NOT TOUCH DEFAULTS)
  --------------------------------------- */
  $("#ps_id").val("");
  $("#ps_sub_id").val(null).trigger("change");
  $("#ps_prerequisites").val(null).trigger("change");

  });

});


  /* ---------------------------
     Delete Subject
  --------------------------- */
  $(document).on("click", ".btnDeleteSubject", function () {
    let psId = $(this).data("ps");

    Swal.fire({
      title: "Remove subject?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33"
    }).then(result => {
      if (!result.isConfirmed) return;

      $.post(
        "../backend/query_prospectus.php",
        { delete_prospectus_subject: 1, ps_id: psId },
        function (res) {
          let p = res.split("|");
          if (p[0] === "OK") {
            Swal.fire("Removed", p[1], "success");
            loadYearSem($("#prospectus_id").val());
          } else {
            Swal.fire("Error", p[1], "error");
          }
        }
      );
    });
  });

});
</script>

</body>
</html>
