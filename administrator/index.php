<?php
    session_start();
    ob_start();
    include '../backend/db.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit;
    }

    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../index.php");
        exit;
    }

    // Load all campuses
    $campus_sql = $conn->query("SELECT campus_id, campus_code, campus_name FROM tbl_campus WHERE status = 'active' ORDER BY campus_id ASC");
    $campuses = [];
    while ($row = $campus_sql->fetch_assoc()) {
        $campuses[] = $row;
    }

    // --- ROOMS PER CAMPUS CHART DATA ---
    $roomDataQuery = "
        SELECT 
            tbl_campus.campus_name,
            COUNT(tbl_rooms.room_id) AS room_count
        FROM tbl_rooms
        INNER JOIN tbl_college ON tbl_college.college_id = tbl_rooms.college_id
        INNER JOIN tbl_campus ON tbl_campus.campus_id = tbl_college.campus_id
        GROUP BY tbl_campus.campus_id
        ORDER BY tbl_campus.campus_name ASC
    ";

    $roomData = [];
    $res = $conn->query($roomDataQuery);
    while ($row = $res->fetch_assoc()) {
        $roomData[] = $row;
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
      <style>
.campus-scroll-area {
  max-height: 700px;
  overflow-y: auto;
  padding-right: 4px;
}

.campus-card {
  border: 1px solid #e4e4e4;
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 14px;
  background: white;
  transition: 0.2s ease-in-out;
}

.campus-card:hover {
  transform: translateX(4px);
  box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}

.campus-card-link {
  text-decoration: none !important;
}

.campus-icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
}

.kpi-box {
  padding: 10px;
}

.kpi-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 22px;
  color: white;
  margin: auto;
}

.kpi-value {
  font-size: 1.75rem;
  font-weight: 700;
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
              <div class="row">
                <div class="col-lg-8 mb-4 order-0">
                    <div class="row">

<div class="col-lg-12 order-0 mb-4">
  <div class="card shadow-sm institutional-card">
    <div class="d-flex align-items-center row">
      
      <!-- LEFT TEXT SECTION -->
      <div class="col-sm-8 px-4 py-4">
        <h4 class="text-primary fw-bold mb-2">
          SKSU Institutional Dashboard
        </h4>

        <p class="mb-3 text-muted">
          Welcome back, <strong><?php echo $_SESSION['username'] ?? 'Administrator'; ?></strong>!  
          Below is a consolidated overview of all SKSU campuses, academic operations, 
          and faculty distribution for this term.
        </p>

        <a href="institutional_report.php" class="btn btn-sm btn-primary">
          View Full Institutional Reports
        </a>
      </div>

      <!-- RIGHT IMAGE -->
      <div class="col-sm-4 text-center">
        <img 
          src="../assets/img/illustrations/man-with-laptop-light.png"
          height="130"
          alt="Institutional Overview"
          class="img-fluid p-2"
          data-app-dark-img="illustrations/man-with-laptop-dark.png"
          data-app-light-img="illustrations/man-with-laptop-light.png"
        />
      </div>

    </div>
  </div>
</div>



                    </div>
<div class="row">
  <!-- Academic Operations Overview -->
  <div class="col-lg-12 order-0 mb-4">
    <div class="card h-100 shadow-sm">
      
      <div class="card-header d-flex align-items-center justify-content-between pb-0">
        <div class="card-title mb-0">
          <h5 class="m-0 me-2">Academic Operations Overview</h5>
          <small class="text-muted">Institution-wide academic indicators</small>
        </div>
        <span class="badge bg-label-primary">KPI Summary</span>
      </div>

      <div class="card-body px-4 py-3">
        <div class="row gy-4">

          <!-- TOTAL FACULTY -->
          <div class="col-md-3 col-6">
            <div class="kpi-box text-center">
              <div class="kpi-icon bg-primary">
                <i class="bx bx-user-voice"></i>
              </div>
              <h3 class="kpi-value text-primary mt-2">0</h3>
              <small class="text-muted">Total Faculty</small>
            </div>
          </div>

          <!-- TOTAL PROGRAMS -->
          <div class="col-md-3 col-6">
            <div class="kpi-box text-center">
              <div class="kpi-icon bg-info">
                <i class="bx bx-book-open"></i>
              </div>
              <h3 class="kpi-value text-info mt-2">0</h3>
              <small class="text-muted">Programs Offered</small>
            </div>
          </div>

          <!-- TOTAL SECTIONS -->
          <div class="col-md-3 col-6">
            <div class="kpi-box text-center">
              <div class="kpi-icon bg-success">
                <i class="bx bx-group"></i>
              </div>
              <h3 class="kpi-value text-success mt-2">0</h3>
              <small class="text-muted">Active Sections</small>
            </div>
          </div>

          <!-- TOTAL WORKLOADS -->
          <div class="col-md-3 col-6">
            <div class="kpi-box text-center">
              <div class="kpi-icon bg-warning">
                <i class="bx bx-calendar"></i>
              </div>
              <h3 class="kpi-value text-warning mt-2">0</h3>
              <small class="text-muted">Faculty Workloads</small>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>

                <!-- Expense Overview -->
<!-- Rooms per Campus Overview -->
<div class="col-lg-12 order-1 mb-4">
  <div class="card h-100 shadow-sm">

    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="m-0">Room Distribution per Campus</h5>
      <span class="badge bg-label-info">Academic Analytics</span>
    </div>

    <div class="card-body">

      <!-- Chart Container -->
      <div id="roomsPerCampusChart"></div>

      <!-- Summary List -->
      <div class="mt-4">
        <h6 class="fw-bold mb-2">Campus Breakdown</h6>
        <ul class="list-group">

          <?php foreach ($roomData as $room): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?php echo $room['campus_name']; ?></span>
            <span class="badge bg-primary rounded-pill"><?php echo $room['room_count']; ?> Rooms</span>
          </li>
          <?php endforeach; ?>

        </ul>
      </div>

    </div>
  </div>
</div>



                <!--/ Expense Overview -->

</div>

                <!--/ Order Statistics -->
                </div>


                <!-- Transactions -->
                <!-- RIGHT COLUMN : EXECUTIVE CAMPUS SNAPSHOT -->
                <div class="col-md-6 col-lg-4 order-2 mb-4">
                  <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex align-items-center justify-content-between">
                      <h5 class="card-title m-0">Campus Snapshot</h5>
                      <span class="badge bg-label-primary">Executive View</span>
                    </div>

                    <div class="card-body campus-scroll-area">

                      <?php foreach ($campuses as $c): ?>
                      <a href="campus_dashboard.php?campus_id=<?php echo $c['campus_id']; ?>" class="campus-card-link">
                        <div class="campus-card shadow-sm">
                          <div class="d-flex justify-content-between align-items-center">
                            
                            <div class="campus-info">
                              <h6 class="mb-1 fw-bold text-dark">
                                <?php echo $c['campus_name']; ?>
                              </h6>
                              <small class="text-muted">
                                Faculty: <span class="fw-semibold text-primary">0</span> <!-- placeholder -->
                              </small>
                            </div>

                            <div class="campus-icon bg-primary">
                              <i class="bx bx-chevron-right text-white"></i>
                            </div>

                          </div>
                        </div>
                      </a>
                      <?php endforeach; ?>

                      <!-- ALL CAMPUS SUMMARY -->
                      <a href="campus_dashboard.php?campus_id=all" class="campus-card-link">
                        <div class="campus-card shadow-sm bg-light">
                          <div class="d-flex justify-content-between align-items-center">
                            <div class="campus-info">
                              <h6 class="mb-1 fw-bold text-dark">University Summary</h6>
                              <small class="text-muted">Overall institutional performance</small>
                            </div>
                            <div class="campus-icon bg-success">
                              <i class="bx bx-globe text-white"></i>
                            </div>
                          </div>
                        </div>
                      </a>

                    </div>
                  </div>
                </div>
                <!--/ Transactions -->

              </div>
              <div class="row">

              </div>
            </div>
            <!-- / Content -->

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                  ©
                  <script>
                    document.write(new Date().getFullYear());
                  </script>
                  , made with ❤️ by
                  <a href="https://themeselection.com" target="_blank" class="footer-link fw-bolder">ThemeSelection</a>
                </div>
                <div>
                  <a href="https://themeselection.com/license/" class="footer-link me-4" target="_blank">License</a>
                  <a href="https://themeselection.com/" target="_blank" class="footer-link me-4">More Themes</a>

                  <a
                    href="https://themeselection.com/demo/sneat-bootstrap-html-admin-template/documentation/"
                    target="_blank"
                    class="footer-link me-4"
                    >Documentation</a
                  >

                  <a
                    href="https://github.com/themeselection/sneat-html-admin-template-free/issues"
                    target="_blank"
                    class="footer-link me-4"
                    >Support</a
                  >
                </div>
              </div>
            </footer>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>    
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

  var campusNames = <?= json_encode(array_column($roomData, 'campus_name')); ?>;
  var roomCounts  = <?= json_encode(array_column($roomData, 'room_count')); ?>;

  // Prevent flat line (all zero) or ApexCharts single-point issue
  if (roomCounts.length < 2) {
      roomCounts = [0, ...roomCounts]; 
      campusNames = ["", ...campusNames];
  }

  var options = {
    series: [{
      name: 'Total Rooms',
      data: roomCounts
    }],

    chart: {
      type: 'line',
      height: 330,
      toolbar: { show: false }
    },

stroke: {
  curve: 'smooth',
  width: 2,
  colors: ['#ff9800'] // darker orange
},

// fill: {
//   type: 'gradient',
//   gradient: {
//     shadeIntensity: 0.4,
//     opacityFrom: 0.6,
//     opacityTo: 0.1,
//     stops: [0, 90, 100],
//     gradientToColors: ['#ffc266'] // strong orange fade
//   }
// },

markers: {
  size: 6,
  colors: ['#fff'],
  strokeColors: '#ff9800',
  strokeWidth: 3
},
    xaxis: {
      categories: campusNames,
      labels: {
        style: { fontSize: '13px', colors: '#777' }
      }
    },

    yaxis: {
      min: 0,
      max: Math.max(...roomCounts) + 2, // force visible line
      tickAmount: 5,
      labels: { style: { colors: '#777' } },
      title: {
        text: 'Total Rooms',
        style: { color: '#777' }
      }
    },

    grid: {
      borderColor: '#eee',
      strokeDashArray: 4
    }
  };

  var chart = new ApexCharts(document.querySelector("#roomsPerCampusChart"), options);
  chart.render();

});
</script>



  </body>
</html>
