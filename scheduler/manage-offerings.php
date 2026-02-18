<?php
session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

if (!isset($_SESSION['college_id']) || intval($_SESSION['college_id']) <= 0) {
    echo "Scheduler error: missing college assignment.";
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
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

                    $stmtProg = $conn->prepare("
                        SELECT h.prospectus_id,
                               h.effective_sy,
                               p.program_code,
                               p.program_name
                        FROM tbl_prospectus_header h
                        JOIN tbl_program p ON p.program_id = h.program_id
                        WHERE p.college_id = ?
                        ORDER BY p.program_name
                    ");
                    $stmtProg->bind_param("i", $collegeId);
                    $stmtProg->execute();
                    $q = $stmtProg->get_result();

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

<div class="d-flex justify-content-end align-items-center gap-2 px-3 pb-2">
  <button class="btn btn-outline-primary btn-sm" id="btnViewProspectus">
    <i class="bx bx-book-content me-1"></i> View Prospectus
  </button>
  <span id="totalOfferingsBadge" class="badge bg-primary">
    Total Offerings: 0
  </span>
</div>


            <div class="table-responsive p-3">
              <table class="table table-bordered table-hover" id="offeringsTable">
                <thead>
                  <tr>
                    <th>Section</th>
                    <th>Subject Code</th>
                    <th>Description</th>
                    <th>LEC</th>
                    <th>LAB</th>
                    <th>Units</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="7" class="text-center text-muted">
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

<!-- Prospectus Preview Modal -->
<div class="modal fade" id="prospectusPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Program Prospectus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="prospectusPreviewBody">
        <div class="text-muted">Select a prospectus to view details.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

  const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;

  $.ajaxPrefilter(function (options) {
    const method = (options.type || options.method || "GET").toUpperCase();
    if (method !== "POST") return;

    if (typeof options.data === "string") {
      const tokenPair = "csrf_token=" + encodeURIComponent(CSRF_TOKEN);
      options.data = options.data ? (options.data + "&" + tokenPair) : tokenPair;
      return;
    }

    if (Array.isArray(options.data)) {
      options.data.push({ name: "csrf_token", value: CSRF_TOKEN });
      return;
    }

    if ($.isPlainObject(options.data)) {
      options.data.csrf_token = CSRF_TOKEN;
      return;
    }

    if (!options.data) {
      options.data = { csrf_token: CSRF_TOKEN };
    }
  });

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
// AUTO-LOAD OFFERINGS WHEN FILTERS ARE COMPLETE
// ---------------------
function tryAutoLoadOfferings() {

  let pid = $('#prospectus_id').val();
  let ay  = $('#ay_id').val();
  let sem = $('#semester').val();

  $('#totalOfferingsBadge').text('Total Offerings: 0');


  if (pid && ay && sem) {

    $('#offeringsTable tbody').html(
      "<tr><td colspan='7' class='text-center text-muted'>Loading offerings...</td></tr>"
    );

    loadOfferings(pid, ay, sem);
  }
}

// Bind change events
$('#prospectus_id').on('change', tryAutoLoadOfferings);
$('#ay_id').on('change', tryAutoLoadOfferings);
$('#semester').on('change', tryAutoLoadOfferings);


  // ---------------------
  // Generate Offerings
  // ---------------------
  function extractAjaxError(xhr, fallbackMsg) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
      return xhr.responseJSON.message;
    }

    const text = (xhr && xhr.responseText ? String(xhr.responseText) : "").trim();
    if (!text) return fallbackMsg;

    try {
      const parsed = JSON.parse(text);
      if (parsed && parsed.message) return parsed.message;
    } catch (e) {
      // Non-JSON response; use compact plain-text fallback below.
    }

    return text.length > 300 ? fallbackMsg : text;
  }

  function buildValidationHtml(v) {
    const summary = v.summary || {};
    const blockers = Array.isArray(v.blockers) ? v.blockers : [];
    const warnings = Array.isArray(v.warnings) ? v.warnings : [];

    let html = `
      <div class="text-start">
        <div><strong>Program:</strong> ${(v.program_code || "")} ${(v.program_name || "")}</div>
        <div><strong>AY:</strong> ${v.ay_label || "-"}</div>
        <div><strong>Semester:</strong> ${semLabel(v.semester || "")}</div>
        <hr class="my-2">
        <div><strong>Subject Rows:</strong> ${summary.total_subject_rows ?? 0}</div>
        <div><strong>Active Sections:</strong> ${summary.total_active_sections ?? 0}</div>
        <div><strong>Potential Offerings:</strong> ${summary.potential_offerings ?? 0}</div>
    `;

    if (blockers.length) {
      html += `<hr class="my-2"><div class="text-danger"><strong>Blockers:</strong><ul class="mb-0">`;
      blockers.forEach(x => { html += `<li>${x}</li>`; });
      html += `</ul></div>`;
    }

    if (warnings.length) {
      html += `<hr class="my-2"><div class="text-warning"><strong>Warnings:</strong><ul class="mb-0">`;
      warnings.forEach(x => { html += `<li>${x}</li>`; });
      html += `</ul></div>`;
    }

    html += `</div>`;
    return html;
  }

  function executeGenerate(pid, ay, sem) {
    $('#btnGenerateOfferings').prop('disabled', true);
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
          text: `Added: ${out.inserted}, Removed: ${out.deleted_offerings}, Kept (Scheduled): ${out.protected_scheduled || 0}`,
          confirmButtonText: "Done"
        }).then(() => {
          loadOfferings(pid, ay, sem);
        });
      },
      error: function (xhr) {
        Swal.fire("Error", extractAjaxError(xhr, "Generation failed."), "error");
      },
      complete: function () {
        $('#btnGenerateOfferings').prop('disabled', false);
      }
    });
  }

  $('#btnGenerateOfferings').on('click', function () {

    let pid = $('#prospectus_id').val();
    let ay  = $('#ay_id').val();
    let sem = $('#semester').val();

    if (!pid || !ay || !sem) {
      Swal.fire("Missing Data", "Fill all fields.", "warning");
      return;
    }

    Swal.fire({
      title: "Validating...",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });
    $('#btnGenerateOfferings').prop('disabled', true);

    $.ajax({
      url: "../backend/query_generate_offerings.php",
      type: "POST",
      dataType: "json",
      data: {
        validate_offerings_context: 1,
        prospectus_id: pid,
        ay_id: ay,
        semester: sem
      },
      success: function (v) {
        if (v.status !== "ok" || v.can_generate !== true) {
          $('#btnGenerateOfferings').prop('disabled', false);
          Swal.fire({
            icon: "error",
            title: "Cannot Generate",
            html: buildValidationHtml(v),
            confirmButtonText: "Close"
          });
          return;
        }

        Swal.fire({
          title: "Sync Offerings?",
          html: buildValidationHtml(v),
          icon: "question",
          showCancelButton: true,
          confirmButtonText: "Proceed"
        }).then((res) => {
          if (!res.isConfirmed) {
            $('#btnGenerateOfferings').prop('disabled', false);
            return;
          }
          executeGenerate(pid, ay, sem);
        });
      },
      error: function (xhr) {
        $('#btnGenerateOfferings').prop('disabled', false);
        Swal.fire("Error", extractAjaxError(xhr, "Validation failed."), "error");
      }
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

      // Count actual offerings rows
      let count = $('#offeringsTable tbody tr')
        .not(':has(td[colspan])') // exclude "No data" rows
        .length;

      $('#totalOfferingsBadge').text('Total Offerings: ' + count);
    }
  ).fail(function (xhr) {
    $('#offeringsTable tbody').html(
      "<tr><td colspan='7' class='text-danger text-center'>Error loading offerings.</td></tr>"
    );
    $('#totalOfferingsBadge').text('Total Offerings: 0');
    console.error(xhr.responseText);
  });
}

function semLabel(sem) {
  if (String(sem) === "1") return "First Semester";
  if (String(sem) === "2") return "Second Semester";
  if (String(sem) === "3") return "Midyear";
  return `Semester ${sem}`;
}

function yearLabel(year) {
  if (String(year) === "1") return "First Year";
  if (String(year) === "2") return "Second Year";
  if (String(year) === "3") return "Third Year";
  if (String(year) === "4") return "Fourth Year";
  return `Year ${year}`;
}

function renderProspectusPreview(data) {
  const header = data.header || {};
  const structure = data.structure || {};
  const subjects = data.subjects || {};

  const programName = header.program_name || "";
  const programCode = header.program_code || "";
  const major = (header.major || "").trim();
  const cmo = header.cmo_no || "-";
  const sy = header.effective_sy || "-";

  let title = `${programCode} - ${programName}`;
  if (major) title += ` (${major})`;

  let html = `
    <div class="mb-3">
      <h6 class="mb-1">${title}</h6>
      <div class="small text-muted">CMO: ${cmo} | Effectivity SY: ${sy}</div>
    </div>
  `;

  const years = Object.keys(structure).sort((a, b) => Number(a) - Number(b));

  if (!years.length) {
    html += `<div class="text-muted">No year/semester structure found.</div>`;
    return html;
  }

  years.forEach(year => {
    const semsObj = structure[year] || {};
    const sems = Object.keys(semsObj).sort((a, b) => Number(a) - Number(b));

    sems.forEach(sem => {
      const rows = (subjects[year] && subjects[year][sem]) ? subjects[year][sem] : [];

      html += `
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>${yearLabel(year)} - ${semLabel(sem)}</strong>
            <span class="badge bg-label-primary">Subjects: ${rows.length}</span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Description</th>
                  <th class="text-center">LEC</th>
                  <th class="text-center">LAB</th>
                  <th class="text-center">Units</th>
                  <th>Prerequisites</th>
                </tr>
              </thead>
              <tbody>
      `;

      if (!rows.length) {
        html += `<tr><td colspan="6" class="text-center text-muted">No subjects.</td></tr>`;
      } else {
        rows.forEach(r => {
          html += `
            <tr>
              <td>${r.sub_code || ""}</td>
              <td>${r.sub_description || ""}</td>
              <td class="text-center">${r.lec_units ?? 0}</td>
              <td class="text-center">${r.lab_units ?? 0}</td>
              <td class="text-center">${r.total_units ?? 0}</td>
              <td>${r.prerequisites || "None"}</td>
            </tr>
          `;
        });
      }

      html += `
              </tbody>
            </table>
          </div>
        </div>
      `;
    });
  });

  return html;
}

$('#btnViewProspectus').on('click', function () {
  const pid = $('#prospectus_id').val();
  if (!pid) {
    Swal.fire("Missing Data", "Select a prospectus first.", "warning");
    return;
  }

  const modalEl = document.getElementById('prospectusPreviewModal');
  const modal = new bootstrap.Modal(modalEl);
  $('#prospectusPreviewBody').html('<div class="text-muted">Loading prospectus...</div>');
  modal.show();

  $.ajax({
    url: "../backend/query_view_prospectus.php",
    type: "GET",
    dataType: "json",
    data: { prospectus_id: pid },
    success: function (res) {
      if (res.error) {
        $('#prospectusPreviewBody').html(`<div class="text-danger">${res.error}</div>`);
        return;
      }
      $('#prospectusPreviewBody').html(renderProspectusPreview(res));
    },
    error: function (xhr) {
      $('#prospectusPreviewBody').html(
        `<div class="text-danger">Failed to load prospectus preview.</div>`
      );
      console.error(xhr.responseText);
    }
  });
});


});
</script>

</body>
</html>
