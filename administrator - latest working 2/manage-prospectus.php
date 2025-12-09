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

    <title>Prospectus Builder | Synk</title>

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
      .prospectus-header-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #888;
        letter-spacing: 0.04em;
      }
      .accordion-button span.badge {
        font-size: 0.75rem;
      }
      .table-prospectus td {
        padding-top: 0.4rem !important;
        padding-bottom: 0.4rem !important;
      }

      .swal2-container {
          z-index: 20000 !important;
      }

      /* Smaller and cleaner SweetAlert */
      .swal2-popup {
          font-size: 0.85rem !important;
          padding: 1.2rem !important;
          border-radius: 12px !important;
      }

      /* Green loader bar */
      .swal2-timer-progress-bar {
          background: #28a745 !important; /* Bootstrap success green */
      }

      /* Ensure alert stays on top of modal */
      .swal2-container {
          z-index: 20000 !important;
      }

.accordion-header {
    background: #FFF7E6 !important;     /* light beige */
    border: 1px solid #FFE4C2 !important;
    border-radius: 6px;
    padding: 6px 12px;
    margin-bottom: 6px;
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

          <!-- TITLE -->
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">
              <i class="bx bx-spreadsheet me-2"></i> Prospectus Builder
            </h4>
          </div>

          <!-- ===================== -->
          <!-- PROSPECTUS HEADER     -->
          <!-- ===================== -->
          <div class="card mb-4">
            <div class="card-header">
              <h5 class="mb-0">Prospectus Information</h5>
              <small class="text-muted">Select program and define CMO + Effectivity</small>
            </div>
            <div class="card-body">

              <form id="prospectusHeaderForm" class="row g-3 align-items-end">
                <input type="hidden" id="prospectus_id" name="prospectus_id">

                <!-- Program -->
                <div class="col-md-5">
                  <label class="form-label prospectus-header-label">Program</label>
                  <select class="form-select" name="program_id" id="program_id" required>
                    <option value="">Select Program</option>
                    <?php
                      $prog = $conn->query("
                        SELECT p.program_id, p.program_code, p.program_name, c.college_name
                        FROM tbl_program p
                        LEFT JOIN tbl_college c ON p.college_id = c.college_id
                        WHERE p.status='active'
                        ORDER BY c.college_name, p.program_name
                      ");
                      while ($r = $prog->fetch_assoc()) {
                        $label = $r['college_name'] . ' - ' . $r['program_name'] . ' (' . $r['program_code'] . ')';
                        echo "<option value='{$r['program_id']}'>" . htmlspecialchars($label) . "</option>";
                      }
                    ?>
                  </select>
                </div>

                <!-- CMO No -->
                <div class="col-md-3">
                  <label class="form-label prospectus-header-label">CMO Number</label>
                  <input type="text" class="form-control" name="cmo_no" id="cmo_no"
                         placeholder="e.g., CMO 25, Series of 2015" required>
                </div>

                <!-- Effectivity SY -->
                <div class="col-md-2">
                  <label class="form-label prospectus-header-label">Effectivity SY</label>
                  <input type="text" class="form-control" name="effective_sy" id="effective_sy"
                         placeholder="e.g., 2018-2019" required>
                </div>

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

          <!-- ===================== -->
          <!-- YEAR / SEM SECTIONS   -->
          <!-- ===================== -->
          <div class="card" id="yearSemCard" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-0">Year & Semester Structure</h5>
                <small class="text-muted">Manage subjects per year and semester</small>
              </div>
              <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#yearSemModal">
                <i class="bx bx-plus"></i> Add Year / Semester
              </button>
            </div>

            <div class="card-body">
              <div class="accordion" id="yearSemAccordion">
                <!-- Dynamic items via AJAX -->
              </div>
            </div>
          </div>

        </div>

        <?php include '../footer.php'; ?>

        <div class="content-backdrop fade"></div>
      </div>

    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>
</div>

<!-- ====================================== -->
<!-- MODAL: ADD YEAR / SEMESTER             -->
<!-- ====================================== -->
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

          <div class="mb-3">
            <label class="form-label">Year Level</label>
            <select name="year_level" class="form-select" required>
              <option value="">Select</option>
              <option value="1">First Year</option>
              <option value="2">Second Year</option>
              <option value="3">Third Year</option>
              <option value="4">Fourth Year</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select" required>
              <option value="">Select</option>
              <option value="1">First Semester</option>
              <option value="2">Second Semester</option>
              <option value="3">Summer</option>
            </select>
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveYearSem">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- ====================================== -->
<!-- MODAL: ADD SUBJECT TO YEAR/SEM         -->
<!-- ====================================== -->
<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Subject to Prospectus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="prospectusSubjectForm">
          <input type="hidden" name="pys_id" id="ps_pys_id">

          <!-- MAIN SUBJECT -->
          <div class="mb-3">
            <label class="form-label">Subject</label>
            <select name="sub_id" id="ps_sub_id" class="form-select" required>
              <option value="">Select Subject</option>
              <?php
                $sub = $conn->query("
                  SELECT sub_id, sub_code, sub_description
                  FROM tbl_subject_masterlist
                  WHERE status='active'
                  ORDER BY sub_code
                ");
                while ($s = $sub->fetch_assoc()) {
                  $label = $s['sub_code'] . ' - ' . $s['sub_description'];
                  echo "<option value='{$s['sub_id']}'>" . htmlspecialchars($label) . "</option>";
                }
              ?>
            </select>
          </div>

          <!-- UNITS -->
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Lec</label>
              <input type="number" name="lec_units" id="ps_lec_units"
                     class="form-control" min="0" value="0">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Lab</label>
              <input type="number" name="lab_units" id="ps_lab_units"
                     class="form-control" min="0" value="0">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Sort Order</label>
              <input type="number" name="sort_order" id="ps_sort_order"
                     class="form-control" min="1" value="1">
            </div>
          </div>

          <!-- PRE-REQUISITE SUBJECTS -->
          <div class="mb-3">
            <label class="form-label">Pre-requisite Subjects</label>
            <select name="prerequisites[]" 
                    id="ps_prerequisites" 
                    class="form-select js-prereq-multiple" 
                    multiple="multiple">
              <!-- options will be populated by AJAX (loadPrerequisiteOptions) -->
            </select>
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



<div class="modal fade" id="loadProspectusModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Load Prospectus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <label class="form-label">Select Prospectus</label>
        <select id="existingProspectusList" class="form-select">
          <option value="">Loading...</option>
        </select>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnLoadSelectedProspectus">Load</button>
      </div>
    </div>
  </div>
</div>



<!-- JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/js/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>    
<script src="../assets/js/main.js"></script>

<script>

let lastOpenedPys = null;

$(document).on("shown.bs.collapse", ".accordion-collapse", function () {
    lastOpenedPys = $(this).attr("id"); // example: collapse12
});

function loadPrerequisiteOptions(prospectus_id) {
    return $.post(
        "../backend/query_prospectus.php",
        { get_all_subjects: 1, prospectus_id },
        function(res) {
            let data = JSON.parse(res);
            let select = $("#ps_prerequisites");

            select.empty();

            data.forEach(sub => {
                select.append(
                    `<option value="${sub.sub_code}">
                        ${sub.sub_code} - ${sub.sub_description}
                    </option>`
                );
            });
        }
    );
}

// -----------------------------
// DELETE YEAR SEMESTER
// -----------------------------
$(document).on("click", ".btnDeleteYearSem", function () {
    let pys_id = $(this).data("pys");

    Swal.fire({
        title: "Delete Year & Semester?",
        html: "<small>This will permanently remove this year/semester <br> and all subjects under it.</small>",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#aaa",
        confirmButtonText: "Yes, delete it",
    }).then((result) => {
        if (result.isConfirmed) {
            $.post("../backend/query_prospectus.php", {
                delete_year_sem: 1,
                pys_id: pys_id
            }, function (res) {

                let parts = res.split("|");
                let status = parts[0];
                let msg = parts[1];

                if (status === "OK") {
                    Swal.fire({
                        icon: "success",
                        title: msg,
                        timer: 1200,
                        showConfirmButton: false,
                    });

                    loadYearSem($("#prospectus_id").val());
                } else {
                    Swal.fire("Error", msg, "error");
                }
            });
        }
    });
});


// -----------------------------
// GLOBAL FUNCTION (must be here)
// -----------------------------
// function loadYearSem(prospectus_id) {
//     console.log("Loading Year/Sem for ID:", prospectus_id);

//     $.post(
//         "../backend/query_prospectus.php",
//         { load_year_sem: 1, prospectus_id: prospectus_id },
//         function (html) {
//             $("#yearSemAccordion").html(html);
//         }
//     );
// }
function loadYearSem(prospectus_id) {
    $.post(
        "../backend/query_prospectus.php",
        { load_year_sem: 1, prospectus_id: prospectus_id },
        function (html) {
            $("#yearSemAccordion").html(html);

            // Re-open the previously opened section
            if (lastOpenedPys) {
                $("#" + lastOpenedPys).collapse("show");
            }
        }
    );
}

// -----------------------------
// DOCUMENT READY
// -----------------------------
$(document).ready(function () {

// $('#ps_prerequisites').select2({
//     placeholder: "Select prerequisite subjects",
//     allowClear: true,
//     width: "100%",
//     dropdownParent: $('#subjectModal')
// });

    // Make globally accessible
    window.loadYearSem = loadYearSem;

    // -------------------------------------------
    // OPEN MODAL + LOAD PROSPECTUS LIST
    // -------------------------------------------
    $("#btnLoadHeader").click(function () {
        $("#loadProspectusModal").modal("show");

        $.ajax({
            url: "../backend/query_prospectus.php",
            type: "POST",
            data: { load_prospectus_list: 1 },
            dataType: "json",
            success: function (res) {
                $("#existingProspectusList").empty()
                    .append(`<option value="">Select Prospectus</option>`);

                res.forEach(item => {
                    $("#existingProspectusList").append(`
                        <option value="${item.prospectus_id}">
                            ${item.program_name} — ${item.cmo_no} — ${item.effective_sy}
                        </option>
                    `);
                });
            }
        });
    });

    // -------------------------------------------
    // LOAD SELECTED PROSPECTUS HEADER
    // -------------------------------------------
    $("#btnLoadSelectedProspectus").click(function () {

        let pid = $("#existingProspectusList").val();
        if (!pid) return;

        $.post("../backend/query_prospectus.php",
            { load_header: 1, prospectus_id: pid },
            function (res) {

                let parts = res.split("|");

                if (parts[0] !== "OK") {
                    Swal.fire("Error", parts[1], "error");
                    return;
                }

                // Populate fields
                $("#prospectus_id").val(pid);
                $("#ys_prospectus_id").val(pid);
                $("#program_id").val(parts[1]);
                $("#cmo_no").val(parts[2]);
                $("#effective_sy").val(parts[3]);

                Swal.fire("Loaded!", "Prospectus loaded successfully.", "success");

                $("#loadProspectusModal").modal("hide");
                $("#yearSemCard").show();

                loadYearSem(pid);
            }
        );
    });

    // -------------------------------------------
    // SAVE NEW PROSPECTUS HEADER
    // -------------------------------------------
    $("#btnSaveHeader").click(function () {

        $.post(
            "../backend/query_prospectus.php",
            $("#prospectusHeaderForm").serialize() + "&save_header=1",
            function (res) {

                let parts = res.split("|");
                let status = parts[0];
                let id = parts[1];
                let msg = parts[2];

                Swal.fire({
                    icon: status === "OK" ? "success" : "error",
                    title: msg
                });

                if (status === "OK") {
                    $("#prospectus_id").val(id);
                    $("#ys_prospectus_id").val(id);
                    $("#yearSemCard").show();
                    loadYearSem(id);
                }
            }
        );
    });

    // -------------------------------------------
    // SAVE NEW YEAR / SEM ENTRY
    // -------------------------------------------
    $("#btnSaveYearSem").click(function () {

        $.post(
            "../backend/query_prospectus.php",
            $("#yearSemForm").serialize() + "&save_year_sem=1",
            function (res) {

                let parts = res.split("|");
                let status = parts[0];
                let msg = parts[2];

                if (status === "ERROR") {
                    Swal.fire("Error", msg, "error");
                    return;
                }

                if (status === "EXISTS") {
                    Swal.fire("Notice", msg, "info");
                    $("#yearSemModal").modal("hide");
                    return;
                }

                Swal.fire("Success", msg, "success");
                $("#yearSemModal").modal("hide");

                loadYearSem($("#prospectus_id").val());
            }
        );
    });

// -------------------------------------------
// OPEN ADD SUBJECT MODAL
// -------------------------------------------
$(document).on("click", ".btnAddSubject", function () {

    let pys = $(this).data("pys");
    $("#ps_pys_id").val(pys);

    // Load prerequisite options (AJAX)
    loadPrerequisiteOptions($("#prospectus_id").val()).then(() => {

// Destroy previous Select2 to avoid duplicate initialization
if ($('#ps_prerequisites').hasClass("select2-hidden-accessible")) {
    $('#ps_prerequisites').select2('destroy');
}

// Initialize Select2 normally
$('#ps_prerequisites').select2({
    placeholder: "Select prerequisite subjects",
    allowClear: true,
    width: "100%",
    dropdownParent: $('#subjectModal')
});

    });

    $("#subjectModal").modal("show");
});





    // -------------------------------------------
    // SAVE SUBJECT
    // -------------------------------------------
$("#btnSaveProspectusSubject").click(function () {

    // Get all form values manually
    let form = $("#prospectusSubjectForm");
    let formData = form.serializeArray();

    // Extract prerequisite values (array)
    let prereqArray = $("#ps_prerequisites").val(); // returns array or null

    // Convert to comma-separated string
    let prereqString = prereqArray && prereqArray.length ? prereqArray.join(",") : "";

    // Remove old prerequisites[] entry
    formData = formData.filter(item => item.name !== "prerequisites[]");

    // Add new prereq string
    formData.push({ name: "prerequisites", value: prereqString });

    // Add save flag
    formData.push({ name: "save_prospectus_subject", value: 1 });

    $.post("../backend/query_prospectus.php", formData, function (res) {

        console.log("Subject Save Response:", res);

        let parts = res.split("|");
        let status = parts[0];
        let msg = parts[2];

        if (status === "ERROR") {
            Swal.fire("Error", msg, "error");
            return;
        }

        if (status === "EXISTS") {
            Swal.fire("Warning", msg, "warning");
            return;
        }

        if (status === "OK") {
            Swal.fire({
                icon: "success",
                title: "Saved Successfully!",
                text: msg,
                timer: 1500,
                timerProgressBar: true,
                showConfirmButton: false,
                heightAuto: false, 
            });

            // Do NOT CLOSE MODAL
            // $("#subjectModal").modal("hide");

            // Reload the subject list
            loadYearSem($("#prospectus_id").val());

            // Clear fields for fast entry
            $("#ps_sub_id").val("").trigger("change");
            $("#ps_lec_units").val(0);
            $("#ps_lab_units").val(0);
            $("#ps_sort_order").val(1);
            $("#ps_prerequisites").val([]).trigger("change");
        }
    });

});

    // -------------------------------------------
    // DELETE SUBJECT
    // -------------------------------------------
    $(document).on("click", ".btnDeleteSubject", function () {

        let ps_id = $(this).data("ps");

        Swal.fire({
            title: "Remove subject?",
            text: "This will permanently remove the subject.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#aaa",
            confirmButtonText: "Yes, remove it"
        }).then((result) => {

            if (result.isConfirmed) {

                $.post(
                    "../backend/query_prospectus.php",
                    { delete_prospectus_subject: 1, ps_id: ps_id },
                    function (res) {

                        let parts = res.split("|");
                        let status = parts[0];
                        let msg = parts[1];

                        if (status === "OK") {
                            Swal.fire("Removed", msg, "success");
                            loadYearSem($("#prospectus_id").val());
                        } else {
                            Swal.fire("Error", msg, "error");
                        }
                    }
                );
            }
        });
    });

});
</script>



</body>
</html>
