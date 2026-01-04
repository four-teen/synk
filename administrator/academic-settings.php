<?php
/*
|--------------------------------------------------------------------------
| ACADEMIC SETTINGS – ADMIN
|--------------------------------------------------------------------------
| This page defines the CURRENT Academic Year and Semester
| used globally across:
| - Admin Dashboard
| - Campus Dashboard
| - Faculty Workload
| - Class Scheduling
| - Reports
|
| NOTE:
| - UI only for now
| - Logic will be wired after confirmation
|--------------------------------------------------------------------------
*/

session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD ACADEMIC YEARS (ACTIVE)
|--------------------------------------------------------------------------
| Source of truth for Academic Year dropdown
|--------------------------------------------------------------------------
*/
$academic_years = [];

$sql_ay = "
    SELECT ay_id, ay
    FROM tbl_academic_years
    WHERE status = 'active'
    ORDER BY ay_id ASC
";
$result_ay = mysqli_query($conn, $sql_ay);

while ($row = mysqli_fetch_assoc($result_ay)) {
    $academic_years[] = $row;
}

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
  <title>Academic Settings | Synk</title>
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

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../assets/vendor/js/helpers.js"></script>

     <script src="../assets/js/config.js"></script>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">

    <?php include 'sidebar.php'; ?>

    <div class="layout-page">
      <?php include 'navbar.php'; ?>

      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

          <!-- PAGE HEADER -->
          <div class="mb-4">
            <h4 class="fw-bold mb-1">Academic Settings</h4>
            <p class="text-muted mb-0">
              Defines the academic year and semester used across the entire system.
            </p>
          </div>

          <div class="row">

            <!-- CURRENT ACADEMIC TERM -->
            <div class="col-lg-6 mb-4">
              <div class="card shadow-sm">
                <div class="card-header">
                  <h5 class="m-0">Current Academic Term</h5>
                </div>
                <div class="card-body">
                  <p class="mb-2">
                    <strong>Academic Year:</strong>
                    <span class="text-primary" id="currentAcademicYear">—</span>
                  </p>
                  <p class="mb-0">
                    <strong>Semester:</strong>
                    <span class="text-primary" id="currentSemester">—</span>
                  </p>
                </div>
              </div>
            </div>

            <!-- CHANGE ACADEMIC TERM -->
            <div class="col-lg-6 mb-4">
              <div class="card shadow-sm">
                <div class="card-header">
                  <h5 class="m-0">Change Academic Term</h5>
                </div>
                <div class="card-body">

                  <div class="mb-3">
                    <label class="form-label">Academic Year</label>
                      <select class="form-select" id="academicYearSelect">
                        <option value="">-- Select Academic Year --</option>

                        <?php foreach ($academic_years as $ay): ?>
                          <option value="<?= (int)$ay['ay_id'] ?>">
                            <?= htmlspecialchars($ay['ay']) ?>
                          </option>
                        <?php endforeach; ?>

                      </select>

                  </div>


                <div class="mb-3">
                  <label class="form-label d-block">Semester</label>

                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="semester" value="1">
                    <label class="form-check-label">1st Semester</label>
                  </div>

                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="semester" value="2">
                    <label class="form-check-label">2nd Semester</label>
                  </div>

                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="semester" value="3">
                    <label class="form-check-label">Midyear</label>
                  </div>
                </div>


                  <div class="alert alert-warning mb-3">
                    Changing the academic term will update dashboards,
                    workloads, scheduling, and reports.
                  </div>

                  <button class="btn btn-primary">
                    Save Academic Settings
                  </button>

                </div>
              </div>
            </div>

          </div>

        </div>

        <?php include '../footer.php'; ?>
      </div>
    </div>
  </div>
</div>


    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>    
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



<script>
/*
|--------------------------------------------------------------------------
| ACADEMIC SETTINGS JS
|--------------------------------------------------------------------------
| - Saves academic settings
| - Shows SweetAlert2 feedback
| - Refreshes current academic term without reload
|--------------------------------------------------------------------------
*/

function loadCurrentAcademicSettings() {
    fetch('../backend/get_academic_settings.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('currentAcademicYear').innerText = data.academic_year;
                document.getElementById('currentSemester').innerText = data.semester;
            }
        });
}

// Initial load
loadCurrentAcademicSettings();

document.querySelector('.btn-primary').addEventListener('click', function () {

    const aySelect = document.getElementById('academicYearSelect');
    const semesterRadio = document.querySelector('input[name="semester"]:checked');

    if (!aySelect.value || !semesterRadio) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing selection',
            text: 'Please select Academic Year and Semester.',
            timer: 1000,
            showConfirmButton: false
        });
        return;
    }

    const formData = new FormData();
    formData.append('ay_id', aySelect.value);
    formData.append('semester', semesterRadio.value);

    fetch('../backend/save_academic_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {

        if (data.status === 'success') {

            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            });

            // Refresh current academic term display
            loadCurrentAcademicSettings();

        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }

    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'System Error',
            text: 'Please try again.'
        });
    });

});
</script>




</body>
</html>
