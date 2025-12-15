<?php 
session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$college_id = $_SESSION['college_id'];

// FETCH COLLEGE NAME
$col = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT college_name FROM tbl_college WHERE college_id='$college_id'"
));
$college_name = $col['college_name'];
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8" />
    <title>Section Generator | Synk Scheduler</title>
    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" type="text/css" href="custom_css.css">
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
        #previewCard { display:none; }
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

    <!-- Title -->
    <h4 class="fw-bold mb-3">
        <i class="bx bx-layer me-2"></i> Section Generator
        <small class="text-muted">(<?= $college_name ?>)</small>
    </h4>

    <!-- Generator Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="m-0">Create Sections</h5>
            <small class="text-muted">Generate sections for a program and year level.</small>
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
                                echo "<option value='{$r['program_id']}' data-code='{$r['program_code']}'>
                                        {$r['program_code']} — {$r['program_name']}
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

// DELETE SECTION
$(document).on("click", ".btnDeleteSection", function () {

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
                    Swal.fire("Error", "Unable to delete section.", "error");
                }

            });

        }
    });

});


loadGroupedSections();

// Load grouped sections into UI
function loadGroupedSections() {
    $.post("../backend/query_sections.php", { load_grouped_sections: 1 }, function(res) {

        let data = JSON.parse(res);
        let html = "";

        for (let program in data) {

            html += `
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="m-0">${program}</h5>
                    </div>
                    <div class="table-responsive p-3">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Year</th>
                                    <th>Section</th>
                                    <th>Full Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            data[program].forEach((s, i) => {
                html += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${s.year_level}</td>
                        <td>${s.section_name}</td>
                        <td>${s.full_section}</td>
                        <td>
                            <span class="badge bg-success">${s.status}</span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-danger btnDeleteSection"
                                data-id="${s.section_id}">
                                <i class="bx bx-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;

            });

            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        $("#groupedSections").html(html);

    });
}


// Generate Preview
$("#btnGenerate").click(function () {

    let program_id   = $("#program_id").val();
    let program_code = $("#program_id option:selected").data("code");
    let year         = $("#year_level").val();
    let count        = $("#section_count").val();

    if (!program_id || !year || count <= 0) {
        Swal.fire("Missing Data", "Please fill all fields.", "warning");
        return;
    }

    let letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
    $("#previewList").html("");

    for (let i = 0; i < count; i++) {
        let letter = letters[i];
        let section_name = year + letter;
        let full_section = program_code + " " + section_name;

        $("#previewList").append(`
            <li class="list-group-item d-flex justify-content-between">
                ${full_section}
                <span class="badge bg-primary">${section_name}</span>
            </li>
        `);
    }

    $("#previewCard").slideDown();
});

// Save Sections
$("#btnSaveSections").click(function () {

    let program_id   = $("#program_id").val();
    let program_code = $("#program_id option:selected").data("code");
    let year         = $("#year_level").val();
    let count        = $("#section_count").val();

    $.post("../backend/query_sections.php",
    {
        save_sections: 1,
        program_id: program_id,
        program_code: program_code,
        year_level: year,
        count: count
    },
    function(response){

        if (response.trim() === "success") {
            Swal.fire("Saved!", "Sections created successfully.", "success");
            $("#previewCard").hide();
            loadGroupedSections(); // REFRESH GROUPED LIST
        } else {
            Swal.fire("Error", response, "error");
        }
    });
});

</script>

</body>
</html>
