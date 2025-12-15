<?php
    session_start();
    ob_start();
    include '../backend/db.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit;
    }

    if ($_SESSION['role'] !== 'scheduler') {
        header("Location: ../index.php");
        exit;
    }

    // scheduler must belong to a college
    if (!isset($_SESSION['college_id']) || $_SESSION['college_id'] === null) {
        echo "Scheduler error: missing college assignment.";
        exit;
    }
?>

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

    <title>Admin Dashboard</title>

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
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />
    <link rel="stylesheet" type="text/css" href="custom_css.css">
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <style>
      .card-hover:hover {
          background: #f5f5f9;
          transition: 0.2s;
      }
      .cursor-pointer {
          cursor: pointer;
      }

.dashboard-card {
    padding: 22px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #dfe4ea;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    text-align: center;
    transition: 0.25s ease;
    cursor: pointer;
}

.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 18px rgba(0,0,0,0.12);
}

.dashboard-card .title {
    font-size: 15px;
    color: #6c757d;
}

.dashboard-card .count {
    font-size: 34px;
    font-weight: 700;
    color: #333;
    margin-top: 6px;
}

/* ORANGE WARNING */
.card-warning {
    border-color: #ff6b6b !important;
    box-shadow: 0 4px 16px rgba(255, 57, 57, 0.35) !important;
}

    </style>

  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->

          <?php 
            include 'sidebar.php';
          ?>
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->

          <?php 
            include 'navbar.php';
           ?>

          <!-- / Navbar -->

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->

            <div class="container-xxl flex-grow-1 container-p-y">

              <!-- Greeting -->
              <div class="row mb-4">
                <div class="col-lg-12">
                  <div class="card">
                    <div class="card-body d-flex align-items-center">
                      <img src="../assets/img/illustrations/man-with-laptop-light.png" height="120" class="me-3" />
                      <div>
                        <h4 class="fw-bold mb-1">Welcome Scheduler! 👋</h4>
                        <p class="text-muted mb-0">
                          You are managing schedules for 
                          <strong><?= $_SESSION['college_name'] ?></strong>.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Quick Stats -->
<div class="row g-3 mb-4">

  <!-- Programs -->
  <div class="col-md-3">
    <div class="dashboard-card" id="cardPrograms">
      <div class="title">Programs</div>
      <div class="count" id="countPrograms">--</div>
    </div>
  </div>

  <!-- Faculty -->
  <div class="col-md-3">
    <div class="dashboard-card" id="cardFaculty">
      <div class="title">Faculty</div>
      <div class="count" id="countFaculty">--</div>
    </div>
  </div>

  <!-- Prospectus Items -->
  <div class="col-md-3">
    <div class="dashboard-card" id="cardProspectus">
      <div class="title">Prospectus Items</div>
      <div class="count" id="countProspectus">--</div>
    </div>
  </div>

  <!-- Unscheduled Classes -->
  <div class="col-md-3">
    <div class="dashboard-card" id="cardUnscheduled">
      <div class="title">Unscheduled Classes</div>
      <div class="count" id="countUnscheduled">--</div>
    </div>
  </div>

</div>


              <!-- Quick Actions -->
              <div class="row mb-4">
                  <div class="col-md-3">
                    <a href="manage-prospectus.php">
                      <div class="card card-hover cursor-pointer">
                        <div class="card-body text-center">
                          <i class="bx bx-book-bookmark text-primary fs-1 mb-2"></i>
                          <h6 class="fw-bold">Prospectus Builder</h6>
                          <p class="text-muted small">Courses per year/semester</p>
                        </div>
                      </div>
                    </a>
                  </div>

                  <div class="col-md-3">
                    <a href="manage-workload.php">
                      <div class="card card-hover cursor-pointer">
                        <div class="card-body text-center">
                          <i class="bx bx-user-check text-success fs-1 mb-2"></i>
                          <h6 class="fw-bold">Faculty Workload</h6>
                          <p class="text-muted small">Assigned units & teaching load</p>
                        </div>
                      </div>
                    </a>
                  </div>

                  <div class="col-md-3">
                    <a href="manage-class-schedule.php">
                      <div class="card card-hover cursor-pointer">
                        <div class="card-body text-center">
                          <i class="bx bx-time-five text-warning fs-1 mb-2"></i>
                          <h6 class="fw-bold">Class Scheduling</h6>
                          <p class="text-muted small">Add time, days, rooms</p>
                        </div>
                      </div>
                    </a>
                  </div>

                  <div class="col-md-3">
                    <a href="manage-room-utilization.php">
                      <div class="card card-hover cursor-pointer">
                        <div class="card-body text-center">
                          <i class="bx bx-building-house text-info fs-1 mb-2"></i>
                          <h6 class="fw-bold">Room Utilization</h6>
                          <p class="text-muted small">Monitor room assignments</p>
                        </div>
                      </div>
                    </a>
                  </div>
              </div>

              <!-- Alerts Panel -->
              <div class="row">
                <div class="col-md-12">
<div class="card">
  <div class="card-header fw-bold">
    📈 Faculty Teaching Load (Units)
  </div>
  <div class="card-body">
    <div id="facultyLoadChart" style="height: 320px;"></div>
  </div>
</div>

                </div>
              </div>

            </div>

            <!-- / Content -->

            <!-- Footer -->
              <?php 
                include '../footer.php';

              ?>
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->



    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
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

$(document).ready(function(){

//dasboard line graph based on the number of units faculty earned
    $.ajax({
        url: "../backend/dashboard_faculty_load.php",
        type: "POST",
        dataType: "json",
        success: function (data) {

            if (!Array.isArray(data) || data.length === 0) {
                $("#facultyLoadChart").html(
                    "<p class='text-muted text-center'>No workload data available.</p>"
                );
                return;
            }

            let facultyNames = [];
            let unitValues   = [];
            let markersColor = [];

            data.forEach((row, index) => {

                facultyNames.push(row.faculty);
                unitValues.push(row.units);

                // 🔴 OVERLOAD ≥ 21
                if (row.units >= 21) {
                    markersColor.push({
                        seriesIndex: 0,
                        dataPointIndex: index,
                        fillColor: '#ff4d4f',
                        strokeColor: '#ff4d4f',
                        size: 7
                    });
                }
                // 🟡 UNDERLOAD < 18
                else if (row.units < 18) {
                    markersColor.push({
                        seriesIndex: 0,
                        dataPointIndex: index,
                        fillColor: '#faad14',
                        strokeColor: '#faad14',
                        size: 7
                    });
                }
                // 🟢 NORMAL 18–20
                else {
                    markersColor.push({
                        seriesIndex: 0,
                        dataPointIndex: index,
                        fillColor: '#52c41a',
                        strokeColor: '#52c41a',
                        size: 6
                    });
                }

            });

            let options = {
                chart: {
                    type: 'line',
                    height: 320,
                    toolbar: { show: false }
                },
                series: [{
                    name: 'Total Units',
                    data: unitValues
                }],
                xaxis: {
                    categories: facultyNames,
                    labels: { rotate: -45 }
                },
                yaxis: {
                    min: 0,
                    title: { text: 'Units' }
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                markers: {
                    size: 5,
                    discrete: markersColor
                },
                colors: ['#696cff'],
                tooltip: {
                    y: {
                        formatter: function (val) {
                            if (val >= 21) return val + " units (Overload)";
                            if (val < 18) return val + " units (Underload)";
                            return val + " units (Normal)";
                        }
                    }
                }
            };

            let chart = new ApexCharts(
                document.querySelector("#facultyLoadChart"),
                options
            );
            chart.render();
        },
        error: function (xhr) {
            console.error("Faculty load chart error:", xhr.responseText);
        }
    });


$.ajax({
    url: "../backend/dashboard_counts.php",
    type: "POST",
    dataType: "json",

    success: function(res) {
        console.log("Dashboard Output:", res);  // 💥 CHECK HERE
    },

    error: function(xhr) {
        console.error("AJAX ERROR:", xhr.responseText);
    }
});


    loadDashboardCounts();

});

function loadDashboardCounts() {

$.ajax({
    url: "../backend/dashboard_counts.php",
    type: "POST",
    dataType: "json",

    success: function(res) {
        console.log("Dashboard Output:", res);  // 💥 CHECK HERE
    },

    error: function(xhr) {
        console.error("AJAX ERROR:", xhr.responseText);
    }
});

}
 
$(document).ready(function () {

    function animateCount(elementId, target) {
        let start = 0;
        let speed = 20;
        let step = Math.ceil(target / 20);

        let timer = setInterval(function () {
            start += step;
            if (start >= target) {
                start = target;
                clearInterval(timer);
            }
            $("#" + elementId).text(start);
        }, speed);
    }

    // Load dashboard data
    $.ajax({
        url: "../backend/dashboard_counts.php",
        type: "POST",
        dataType: "json",
        success: function (data) {

            console.log("Dashboard Output:", data);

            // Programs
            animateCount("countPrograms", data.programs);

            // Faculty
            animateCount("countFaculty", data.faculty);

            // Prospectus Items
            animateCount("countProspectus", data.prospectus_items);

            // Unscheduled Classes
            animateCount("countUnscheduled", data.unscheduled_classes);

            // If unscheduled > 0, highlight card
            if (data.unscheduled_classes > 0) {
                $("#cardUnscheduled").addClass("card-warning");
            }

        },
        error: function (xhr) {
            console.error("AJAX ERROR:", xhr.responseText);
        }
    });

});

</script>
  </body>
</html>
