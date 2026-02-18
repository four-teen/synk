<?php
/**
 * ======================================================================
 * Page Name : Manage Sections (Section Generator)
 * File Path : synk/scheduler/manage-sections.php
 * ======================================================================
 * PURPOSE:
 * - Generate sections scoped by Academic Year + Semester
 * - Preview and save generated sections
 * - Load grouped sections filtered by AY + Semester (auto load on selection)
 * ======================================================================
 */

session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$college_id = (int)($_SESSION['college_id'] ?? 0);

// FETCH COLLEGE NAME
$col = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT college_name FROM tbl_college WHERE college_id='$college_id'"
));
$college_name = $col['college_name'] ?? '';

// Default AY/Semester from academic settings (if available)
$default_ay_id = 0;
$default_semester = 0;
$settingsQ = $conn->query("SELECT current_ay_id, current_semester FROM tbl_academic_settings LIMIT 1");
if ($settingsQ && $settingsQ->num_rows > 0) {
    $settingsRow = $settingsQ->fetch_assoc();
    $default_ay_id = (int)($settingsRow['current_ay_id'] ?? 0);
    $default_semester = (int)($settingsRow['current_semester'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8" />
    <title>Section Generator | Synk Scheduler</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" type="text/css" href="custom_css.css">

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
        #previewCard { display:none; }
        #groupedSections { display:none; } /* hidden until user loads AY+Sem */
        .year-level-card {
            border: 1px solid #cfd6ea;
            border-radius: .5rem;
            box-shadow: 0 1px 2px rgba(67, 89, 113, 0.08);
        }
        .year-level-card .year-header {
            background: linear-gradient(90deg, #eef3ff 0%, #e8f0ff 100%);
            border-bottom: 1px solid #cfd6ea;
            color: #223053;
        }
        .section-chip {
            border: 1px solid #d4dced;
            border-radius: .5rem;
            padding: .5rem .6rem;
            background: #fcfdff;
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
        <i class="bx bx-layer me-2"></i> Section Generator
        <small class="text-muted">(<?= htmlspecialchars($college_name) ?>)</small>
    </h4>

    <!-- ===============================
         AY + SEMESTER FILTER (REQUIRED)
         Manual load (Q3 = B)
    ================================ -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="m-0">Academic Context</h5>
            <small class="text-muted">Select Academic Year and Semester before loading or generating sections.</small>
        </div>

        <div class="card-body">
            <div class="row g-3 align-items-end">

                <div class="col-md-6">
                    <label class="form-label">Academic Year</label>
                    <select id="ay_id" class="form-select">
                        <option value="">Select...</option>
                        <?php
                            $ayQ = $conn->query("SELECT ay_id, ay FROM tbl_academic_years WHERE status='active' ORDER BY ay DESC");
                            while ($ay = $ayQ->fetch_assoc()) {
                                $selected = ((int)$ay['ay_id'] === $default_ay_id) ? " selected" : "";
                                echo "<option value='".(int)$ay['ay_id']."'{$selected}>".htmlspecialchars($ay['ay'])."</option>";
                            }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Semester</label>
                    <select id="semester" class="form-select">
                        <option value="">Select...</option>
                        <option value="1" <?= $default_semester === 1 ? 'selected' : '' ?>>First Semester</option>
                        <option value="2" <?= $default_semester === 2 ? 'selected' : '' ?>>Second Semester</option>
                        <option value="3" <?= $default_semester === 3 ? 'selected' : '' ?>>Midyear</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ===============================
         GENERATOR FORM
    ================================ -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="m-0">Create Sections</h5>
            <small class="text-muted">Generate sections for a program and year level (scoped by AY + Semester).</small>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Program</label>
                    <select id="program_id" class="form-select">
                        <option value="">Select Program</option>
                        <?php
                            $q = mysqli_query($conn, "
                                SELECT program_id, program_code, program_name
                                FROM tbl_program
                                WHERE college_id='$college_id'
                                ORDER BY program_code ASC
                            ");
                            while ($r = mysqli_fetch_assoc($q)) {
                                echo "<option value='".(int)$r['program_id']."' data-code='".htmlspecialchars($r['program_code'], ENT_QUOTES)."'>
                                        ".htmlspecialchars($r['program_code'])." — ".htmlspecialchars($r['program_name'])."
                                      </option>";
                            }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Year Level</label>
                    <select id="year_level" class="form-select">
                        <option value="">Select Year Level</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                        <option value="5">5th Year</option>
                        <option value="6">6th Year</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Number of Sections</label>
                    <input type="number" id="section_count" class="form-control" value="1" min="1">
                </div>

            </div>

            <button class="btn btn-primary mt-3" id="btnGenerate">
                <i class="bx bx-cog"></i> Generate Preview
            </button>

        </div>
    </div>

    <!-- PREVIEW CARD -->
    <div class="card" id="previewCard">
        <div class="card-header d-flex justify-content-between">
            <h5 class="m-0">Generated Sections Preview</h5>
            <button class="btn btn-success btn-sm" id="btnSaveSections">
                <i class="bx bx-save"></i> Save Sections
            </button>
        </div>
        <ul class="list-group list-group-flush" id="previewList"></ul>
    </div>

    <!-- GROUPED SECTION LIST -->
    <div id="groupedSections"></div>

</div>

<?php include '../footer.php'; ?>

</div>
</div>
</div>

<!-- JS LIBS -->
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
let sectionStartIndex = 0;

function saveSectionsRequest(ctx, program_id, program_code, year, count) {
    $.post("../backend/query_sections.php",
    {
        save_sections: 1,
        program_id: program_id,
        program_code: program_code,
        ay_id: ctx.ay_id,
        semester: ctx.semester,
        year_level: year,
        count: count,
        start_index: sectionStartIndex
    },
    function(response){

        if (response.trim() === "success") {
            Swal.fire("Saved!", "Sections created successfully.", "success");
            $("#previewCard").hide();
            loadGroupedSections(); // refresh with same AY+Sem
        } else {
            Swal.fire("Error", response, "error");
        }
    });
}

/* =========================
   Helper: validate AY + Sem
========================= */
function getContextOrWarn() {
    const ay  = $("#ay_id").val();
    const sem = $("#semester").val();

    if (!ay || !sem) {
        Swal.fire("Missing Filters", "Please select Academic Year and Semester first.", "warning");
        return null;
    }
    return { ay_id: ay, semester: sem };
}

function yearLevelLabel(year) {
    const y = String(year);
    if (y === "1") return "1st Year";
    if (y === "2") return "2nd Year";
    if (y === "3") return "3rd Year";
    if (y === "4") return "4th Year";
    if (y === "5") return "5th Year";
    if (y === "6") return "6th Year";
    return `Year ${y}`;
}

/* =========================
   DELETE SECTION
========================= */
$(document).on("click", ".btnDeleteSection", function () {

    const ctx = getContextOrWarn();
    if (!ctx) return;

    let id = $(this).data("id");

    Swal.fire({
        icon: "warning",
        title: "Delete Section?",
        text: "This section will be permanently removed.",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Delete",
        cancelButtonText: "Cancel"
    }).then(result => {
        if (result.isConfirmed) {

            $.post("../backend/query_sections.php",
            { delete_section: 1, section_id: id },
            function(res){

                if (res.trim() === "success") {
                    Swal.fire("Deleted!", "Section removed successfully.", "success");
                    loadGroupedSections(); // refresh UI
                }
                else if (res.trim() === "forbidden") {
                    Swal.fire("Access Denied", "You cannot delete this section.", "error");
                }
                else {
                    Swal.fire("Error", res, "error");
                }

            });

        }
    });

});

/* =========================
   Load grouped sections into UI (filtered by AY + Sem)
========================= */
function loadGroupedSections() {

    const ctx = getContextOrWarn();
    if (!ctx) return;

    $("#groupedSections").show().html(`
        <div class="text-center text-muted py-4">
            Loading sections...
        </div>
    `);

    $.post("../backend/query_sections.php",
        {
            load_grouped_sections: 1,
            ay_id: ctx.ay_id,
            semester: ctx.semester
        },
        function(res) {

            let data;
            try {
                data = JSON.parse(res);
            } catch(e) {
                $("#groupedSections").html(`<div class="alert alert-danger">Invalid response: ${res}</div>`);
                return;
            }

            let html = "";

            const programs = Object.keys(data);
            if (programs.length === 0) {
                $("#groupedSections").html(`
                    <div class="alert alert-info">
                        No sections found for the selected Academic Year and Semester.
                    </div>
                `);
                return;
            }

            for (let program in data) {
                const byYear = {};
                data[program].forEach((s) => {
                    const y = String(s.year_level || "");
                    if (!byYear[y]) byYear[y] = [];
                    byYear[y].push(s);
                });
                const yearKeys = Object.keys(byYear).sort((a, b) => Number(a) - Number(b));

                html += `
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="m-0">${program}</h5>
                            <span class="badge bg-label-primary">Total Sections: ${data[program].length}</span>
                        </div>
                        <div class="card-body">
                `;

                yearKeys.forEach((year) => {
                    const rows = byYear[year];
                    html += `
                        <div class="year-level-card mb-3">
                            <div class="year-header px-3 py-2 d-flex justify-content-between align-items-center">
                                <strong>${yearLevelLabel(year)}</strong>
                                <span class="badge bg-label-secondary">${rows.length} section(s)</span>
                            </div>
                            <div class="p-3">
                                <div class="row g-2">
                    `;

                    rows.forEach((s) => {
                        const statusClass = (s.status === 'active') ? 'bg-success' : 'bg-secondary';
                        const shortCode = String(s.section_name || "").replace(String(year), "");
                        html += `
                            <div class="col-12 col-sm-6 col-lg-4">
                                <div class="section-chip d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">${shortCode || s.section_name}</div>
                                        <div class="small text-muted">${s.full_section}</div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge ${statusClass}">${s.status}</span>
                                        <button class="btn btn-sm btn-danger btnDeleteSection" data-id="${s.section_id}" title="Delete">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            }

            $("#groupedSections").html(html);

        }
    );
}

/* =========================
   Generate Preview
========================= */
$("#btnGenerate").click(function () {

    const ctx = getContextOrWarn();
    if (!ctx) return;

    let program_id   = $("#program_id").val();
    let program_code = $("#program_id option:selected").data("code");
    let year         = $("#year_level").val();
    let count        = parseInt($("#section_count").val(), 10);

    if (!program_id || !year || !count || count <= 0) {
        Swal.fire("Missing Data", "Please fill Program, Year Level, and Number of Sections.", "warning");
        return;
    }

    $.post("../backend/query_sections.php", {
        get_next_section_index: 1,
        program_id: program_id,
        year_level: year,
        ay_id: ctx.ay_id,
        semester: ctx.semester
    }, function (res) {

        let data;
        try {
            data = JSON.parse(res);
        } catch(e) {
            Swal.fire("Error", "Invalid response: " + res, "error");
            return;
        }

        let startIndex = parseInt(data.next_index || 0, 10);
        sectionStartIndex = startIndex;

        let letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
        let previewRows = [];

        for (let i = 0; i < count; i++) {

            let idx = startIndex + i;

            if (idx >= letters.length) {
                Swal.fire("Limit Reached", "Maximum section limit (A-Z) reached for this scope.", "warning");
                break;
            }

            let letter = letters[idx];
            let section_name = year + letter;                 // e.g., 1A
            let full_section  = program_code + " " + section_name;
            previewRows.push({ section_name, full_section });
        }
        
        if (previewRows.length === 0) {
            Swal.fire("No Preview", "No sections available to preview in this scope.", "info");
            return;
        }

        let previewHtml = `<div class="text-start" style="max-height:300px;overflow:auto;">`;
        previewRows.forEach((r) => {
            previewHtml += `
                <div class="d-flex justify-content-between align-items-center border rounded px-2 py-2 mb-2">
                    <span>${r.full_section}</span>
                    <span class="badge bg-primary">${r.section_name}</span>
                </div>
            `;
        });
        previewHtml += `</div>`;

        Swal.fire({
            title: "Generated Sections Preview",
            html: previewHtml,
            width: 700,
            showCancelButton: true,
            confirmButtonText: "Save Sections",
            cancelButtonText: "Close"
        }).then((result) => {
            if (!result.isConfirmed) return;
            saveSectionsRequest(ctx, program_id, program_code, year, count);
        });

    });

});

/* =========================
   Save Sections
========================= */
$("#btnSaveSections").click(function () {

    const ctx = getContextOrWarn();
    if (!ctx) return;

    let program_id   = $("#program_id").val();
    let program_code = $("#program_id option:selected").data("code");
    let year         = $("#year_level").val();
    let count        = parseInt($("#section_count").val(), 10);

    if (!program_id || !year || !count || count <= 0) {
        Swal.fire("Missing Data", "Please fill Program, Year Level, and Number of Sections.", "warning");
        return;
    }

    saveSectionsRequest(ctx, program_id, program_code, year, count);
});

// Auto-load whenever AY/Semester changes
$("#ay_id, #semester").on("change", function () {
    if ($("#ay_id").val() && $("#semester").val()) {
        loadGroupedSections();
    } else {
        $("#groupedSections").show().html(`
            <div class="alert alert-info">
                Select Academic Year and Semester to view sections.
            </div>
        `);
    }
});

// Initial auto-load if defaults are already selected
if ($("#ay_id").val() && $("#semester").val()) loadGroupedSections();
</script>

</body>
</html>
