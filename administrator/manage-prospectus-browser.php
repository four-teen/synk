<?php
session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$role = $_SESSION['role'];
$collegeId = $_SESSION['college_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8" />
    <title>Prospectus Builder | Synk</title>

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
    /* ======================================================
       SELECT2 HEIGHT & ALIGNMENT FIX (GLOBAL)
       Matches Bootstrap .form-select height
    ====================================================== */

    /* Main single select */
    .select2-container--default .select2-selection--single {
        height: calc(2.25rem + 2px);      /* Bootstrap form-select height */
        padding: 0.375rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        display: flex;
        align-items: center;
    }

    /* Selected text alignment */
    .select2-container--default 
    .select2-selection--single 
    .select2-selection__rendered {
        line-height: normal;
        padding-left: 0;
        padding-right: 0;
        color: #566a7f;
    }

    /* Dropdown arrow alignment */
    .select2-container--default 
    .select2-selection--single 
    .select2-selection__arrow {
        height: 100%;
        top: 0;
    }

    /* Focus state (matches Sneat) */
    .select2-container--default.select2-container--focus 
    .select2-selection--single {
        border-color: #696cff;
        box-shadow: 0 0 0 0.15rem rgba(105,108,255,.25);
    }

    /* ======================================================
       MULTI-SELECT (PREREQUISITES)
    ====================================================== */

    .select2-container--default .select2-selection--multiple {
        min-height: calc(2.25rem + 2px);
        padding: 0.25rem 0.5rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
    }

    /* Chips (selected items) */
    .select2-container--default 
    .select2-selection--multiple 
    .select2-selection__choice {
        margin-top: 0.25rem;
        background-color: #e7e7ff;
        border: none;
        color: #696cff;
        font-size: 0.75rem;
        border-radius: 0.25rem;
    }

    /* ======================================================
       DISABLED STATE
    ====================================================== */

    .select2-container--default 
    .select2-selection--single.select2-selection--disabled {
        background-color: #f5f5f9;
        cursor: not-allowed;
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
<h4 class="fw-bold mb-4">
    <i class="bx bx-search-alt me-2"></i> Prospectus Browser
</h4>

<!-- FILTER -->
<div class="card mb-4">
    <div class="card-body">
        <label class="form-label">Select Program</label>
        <select id="filterProgram" class="form-select">
            <option value="">Select Program</option>

            <?php
            if ($role === 'admin') {
                /* ============================================================
                   LOAD PROGRAMS WITH AT LEAST ONE ENCODED PROSPECTUS SUBJECT
                   (ADMIN VIEW – ALL COLLEGES)
                ============================================================ */

                    $prog = $conn->query("
                        SELECT DISTINCT
                            p.program_id,
                            p.program_name,
                            p.program_code,
                            p.major,
                            ca.campus_code
                        FROM tbl_program p
                        LEFT JOIN tbl_college c
                            ON c.college_id = p.college_id
                        LEFT JOIN tbl_campus ca
                            ON ca.campus_id = c.campus_id
                        WHERE p.status = 'active'
                          AND EXISTS (
                              SELECT 1
                              FROM tbl_prospectus_header h
                              JOIN tbl_prospectus_year_sem ys
                                ON ys.prospectus_id = h.prospectus_id
                              JOIN tbl_prospectus_subjects ps
                                ON ps.pys_id = ys.pys_id
                              WHERE h.program_id = p.program_id
                          )
                        ORDER BY ca.campus_code, p.program_name, p.major
                    ");
            } else {
                /* ============================================================
                   LOAD PROGRAMS WITH AT LEAST ONE ENCODED PROSPECTUS SUBJECT
                   (COLLEGE-SCOPED VIEW)
                ============================================================ */

                $prog = $conn->query("
                    SELECT DISTINCT p.program_id, p.program_name, p.program_code, p.major
                    FROM tbl_program p
                    WHERE p.status = 'active'
                      AND p.college_id = '$collegeId'
                      AND EXISTS (
                          SELECT 1
                          FROM tbl_prospectus_header h
                          JOIN tbl_prospectus_year_sem ys
                            ON ys.prospectus_id = h.prospectus_id
                          JOIN tbl_prospectus_subjects ps
                            ON ps.pys_id = ys.pys_id
                          WHERE h.program_id = p.program_id
                      )
                    ORDER BY p.program_name, p.major
                ");
            }

            while ($p = $prog->fetch_assoc()) {
                /* ============================================================
                   BUILD PROGRAM LABEL (ADMIN – WITH CAMPUS CODE)
                ============================================================ */

                $programName = ucwords(strtolower($p['program_name']));
                $programCode = strtoupper($p['program_code']);
                $major       = trim($p['major']);
                $campus      = $p['campus_code'] ?? '?';

                if ($major !== '') {
                    $baseLabel = $programName . ' major in ' . $major . ' (' . $programCode . ')';
                } else {
                    $baseLabel = $programName . ' (' . $programCode . ')';
                }

                $finalLabel = $campus . ' – ' . $baseLabel;

                echo "<option value='{$p['program_id']}'>" . htmlspecialchars($finalLabel) . "</option>";

            }
            ?>
        </select>
    </div>
</div>

<!-- TABLE -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>CMO</th>
                        <th>Effectivity SY</th>
                        <th class="text-center">Year / Sem</th>
                        <th class="text-center">Subjects</th>
                        <th class="text-center">Total Units</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="prospectusTable">
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Select a program to view prospectus
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
<?php include '../footer.php'; ?>
</div>
</div>
</div>
</div>

<!-- JS -->
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
$('#filterProgram').select2({ width: '100%' });

// LOAD PROSPECTUS BY PROGRAM
$("#filterProgram").on("change", function () {

    let programId = $(this).val();
    if (!programId) return;

    $.post("../backend/query_prospectus_browser.php",
        { load_prospectus_by_program: 1, program_id: programId },
        function (res) {

            let tbody = $("#prospectusTable");
            tbody.empty();

            if (res.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            No prospectus found for this program
                        </td>
                    </tr>
                `);
                return;
            }

            res.forEach(p => {
                tbody.append(`
                    <tr>
                        <td>${p.cmo_no}</td>
                        <td>${p.effective_sy}</td>
                        <td class="text-center">${p.yearsem_count}</td>
                        <td class="text-center">${p.subject_count}</td>
                        <td class="text-center">${p.total_units ?? 0}</td>
                        <td class="text-center">
                            <a href="view-prospectus.php?pid=${p.prospectus_id}"
                               class="btn btn-sm btn-primary">
                               Open
                            </a>
                        </td>

                    </tr>
                `);
            });

        }, "json"
    );

});
</script>

</body>
</html>
