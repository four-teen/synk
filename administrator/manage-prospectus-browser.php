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
    <title>Prospectus Browser | Synk</title>

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
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
                $prog = $conn->query("
                    SELECT program_id, program_name, program_code, major
                    FROM tbl_program
                    WHERE status='active'
                    ORDER BY program_name, major
                ");
            } else {
                $prog = $conn->query("
                    SELECT program_id, program_name, program_code, major
                    FROM tbl_program
                    WHERE status='active'
                      AND college_id = '$collegeId'
                    ORDER BY program_name, major
                ");
            }

            while ($p = $prog->fetch_assoc()) {
                $label = $p['program_name'];
                if ($p['major']) {
                    $label .= ' major in ' . $p['major'];
                }
                $label .= ' (' . $p['program_code'] . ')';

                echo "<option value='{$p['program_id']}'>" . htmlspecialchars($label) . "</option>";
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

<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
