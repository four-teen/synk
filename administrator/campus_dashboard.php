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

// -----------------------------------------
// GET CAMPUS ID
// -----------------------------------------
$campusIdParam = $_GET['campus_id'] ?? null;

if ($campusIdParam === null) {
    // No campus id – go back to main dashboard
    header("Location: index.php");
    exit;
}

$isUniversitySummary = ($campusIdParam === 'all');

// -----------------------------------------
// LOAD CAMPUS INFO
// -----------------------------------------
$campusName = "University Summary";
$campusCode = "SKSU";

if (!$isUniversitySummary) {
    $campusId = (int)$campusIdParam;

    $stmt = $conn->prepare("
        SELECT campus_id, campus_code, campus_name
        FROM tbl_campus
        WHERE campus_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("i", $campusId);
    $stmt->execute();
    $resCampus = $stmt->get_result();

    if ($resCampus->num_rows === 0) {
        // Invalid campus – redirect back
        header("Location: index.php");
        exit;
    }

    $campusRow  = $resCampus->fetch_assoc();
    $campusName = $campusRow['campus_name'];
    $campusCode = $campusRow['campus_code'];
    $stmt->close();
}

// -----------------------------------------
// ROOMS & ROOM-BY-COLLEGE DATA
// -----------------------------------------
$rooms          = [];
$roomCount      = 0;
$collegeLabels  = [];
$collegeRoomCnt = [];

if ($isUniversitySummary) {
    // All campuses – aggregate per campus
    $roomQuery = "
        SELECT 
            c.campus_name AS label,
            COUNT(r.room_id) AS room_count
        FROM tbl_rooms r
        INNER JOIN tbl_college col ON col.college_id = r.college_id
        INNER JOIN tbl_campus c ON c.campus_id = col.campus_id
        GROUP BY c.campus_id
        ORDER BY c.campus_name ASC
    ";

    $rs = $conn->query($roomQuery);
    while ($row = $rs->fetch_assoc()) {
        $collegeLabels[]  = $row['label'];
        $collegeRoomCnt[] = (int)$row['room_count'];
        $roomCount       += (int)$row['room_count'];
    }

    // Load list of rooms (all campuses) – simple list
    $roomListQuery = "
        SELECT 
            r.room_id,
            r.room_name,
            r.room_code,
            col.college_name,
            c.campus_name
        FROM tbl_rooms r
        INNER JOIN tbl_college col ON col.college_id = r.college_id
        INNER JOIN tbl_campus c   ON c.campus_id = col.campus_id
        ORDER BY c.campus_name, col.college_name, r.room_name
    ";
} else {
    // Single campus – chart per college under this campus
    $roomQuery = "
        SELECT 
            col.college_name AS label,
            COUNT(r.room_id) AS room_count
        FROM tbl_rooms r
        INNER JOIN tbl_college col ON col.college_id = r.college_id
        WHERE col.campus_id = ?
        GROUP BY col.college_id
        ORDER BY col.college_name ASC
    ";

    $stmt = $conn->prepare($roomQuery);
    $stmt->bind_param("i", $campusId);
    $stmt->execute();
    $rs = $stmt->get_result();

    while ($row = $rs->fetch_assoc()) {
        $collegeLabels[]  = $row['label'];
        $collegeRoomCnt[] = (int)$row['room_count'];
        $roomCount       += (int)$row['room_count'];
    }
    $stmt->close();

    // Load list of rooms for this campus
    $roomListQuery = "
        SELECT 
            r.room_id,
            r.room_name,
            r.room_code,
            col.college_name
        FROM tbl_rooms r
        INNER JOIN tbl_college col ON col.college_id = r.college_id
        WHERE col.campus_id = {$campusId}
        ORDER BY col.college_name, r.room_name
    ";
}

// Execute the room list query (for table)
$rsRooms = $conn->query($roomListQuery);
while ($row = $rsRooms->fetch_assoc()) {
    $rooms[] = $row;
}


// -----------------------------------------
// CAMPUS METRICS (FACULTY, PROGRAMS, SECTIONS, ROOMS)
// -----------------------------------------

$facultyCount = 0;
$programCount = 0;
$sectionCount = 0;

if ($isUniversitySummary) {

    // ALL FACULTY
    $facultyCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_faculty
        WHERE status='active'
    ")->fetch_assoc()['cnt'];

    // ALL PROGRAMS
    $programCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_program
        WHERE status='active'
    ")->fetch_assoc()['cnt'];

    // ALL SECTIONS
    $sectionCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_sections
        WHERE status='active'
    ")->fetch_assoc()['cnt'];

} else {

    // FACULTY assigned to this campus
    $facultyCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_faculty f
        INNER JOIN tbl_college_faculty cf ON cf.faculty_id = f.faculty_id
        INNER JOIN tbl_college c ON c.college_id = cf.college_id
        WHERE c.campus_id = {$campusId}
          AND f.status = 'active'
          AND cf.status = 'active'
    ")->fetch_assoc()['cnt'];

    // PROGRAMS under this campus (via college)
    $programCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_program p
        INNER JOIN tbl_college c ON c.college_id = p.college_id
        WHERE c.campus_id = {$campusId}
          AND p.status = 'active'
    ")->fetch_assoc()['cnt'];

    // SECTIONS under this campus (via program → college → campus)
    $sectionCount = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM tbl_sections s
        INNER JOIN tbl_program p ON p.program_id = s.program_id
        INNER JOIN tbl_college c ON c.college_id = p.college_id
        WHERE c.campus_id = {$campusId}
          AND s.status = 'active'
    ")->fetch_assoc()['cnt'];
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
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
  />

  <title>Campus Dashboard | Synk</title>

  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>

  <style>
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
      color: #fff;
      margin: auto;
    }
    .kpi-value {
      font-size: 1.7rem;
      font-weight: 700;
    }

    .card-section-title {
      font-size: 0.9rem;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #888;
    }

    .table-sm td,
    .table-sm th {
      padding-top: 0.4rem !important;
      padding-bottom: 0.4rem !important;
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- Sidebar -->
      <?php include 'sidebar.php'; ?>
      <!-- /Sidebar -->

      <div class="layout-page">

        <!-- Navbar -->
        <?php include 'navbar.php'; ?>
        <!-- /Navbar -->

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">

            <!-- BREADCRUMB / TITLE -->
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <small class="text-muted d-block">Campus Dashboard</small>
                <h4 class="fw-bold mb-0">
                  <?= htmlspecialchars($campusName); ?>
                  <?php if (!$isUniversitySummary): ?>
                    <span class="text-muted"> (<?= htmlspecialchars($campusCode); ?>)</span>
                  <?php endif; ?>
                </h4>
              </div>
              <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                  <i class="bx bx-arrow-back"></i> Back to Institutional View
                </a>
              </div>
            </div>

            <div class="row">
              <!-- LEFT COLUMN -->
              <div class="col-lg-8 mb-4 order-0">

                <!-- CAMPUS HERO CARD -->
                <div class="card shadow-sm mb-4">
                  <div class="row align-items-center g-0">
                    <div class="col-sm-8 px-4 py-4">
                      <p class="card-section-title mb-1">
                        <?= $isUniversitySummary ? 'System-wide Overview' : 'Campus Overview'; ?>
                      </p>
                      <h4 class="text-primary fw-bold mb-2">
                        <?= $isUniversitySummary ? 'SKSU University Summary' : $campusName . ' Campus'; ?>
                      </h4>
                      <p class="mb-3 text-muted">
                        Welcome back, <strong><?= $_SESSION['username'] ?? 'Administrator'; ?></strong>! 
                        This page shows an executive snapshot of
                        <?= $isUniversitySummary ? 'all SKSU campuses combined' : 'academic operations and resources for this campus'; ?>.
                      </p>
                      <div class="d-flex flex-wrap gap-2">
                        <a href="#" class="btn btn-sm btn-primary">
                          <i class="bx bx-file"></i>
                          <?= $isUniversitySummary ? 'Download University Report' : 'Download Campus Report'; ?>
                        </a>
                        <a href="../scheduler/" class="btn btn-sm btn-outline-primary">
                          <i class="bx bx-calendar"></i> View Room Utilization
                        </a>
                      </div>
                    </div>
                    <div class="col-sm-4 text-center">
                      <img
                        src="../assets/img/illustrations/man-with-laptop-light.png"
                        height="130"
                        alt="Overview"
                        class="img-fluid p-2"
                        data-app-dark-img="illustrations/man-with-laptop-dark.png"
                        data-app-light-img="illustrations/man-with-laptop-light.png"
                      />
                    </div>
                  </div>
                </div>

                <!-- KPI ROW -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center pb-2">
                    <div>
                      <p class="card-section-title mb-1">Key Indicators</p>
                      <h5 class="m-0">Academic Operations Overview</h5>
                    </div>
                    <span class="badge bg-label-primary">KPI Summary</span>
                  </div>
                  <div class="card-body px-4 py-3">
                    <div class="row gy-4">

                      <!-- Total Faculty (placeholder for now) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-primary">
                            <i class="bx bx-user-voice"></i>
                          </div>
                          <h3 class="kpi-value text-info mt-2"><?= $facultyCount; ?></h3>
                          <small class="text-muted">
                            <?= $isUniversitySummary ? 'Faculty (All Campuses)' : 'Campus Faculty'; ?>
                          </small>
                        </div>
                      </div>

                      <!-- Programs Offered (placeholder) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-info">
                            <i class="bx bx-book-open"></i>
                          </div>
                          <h3 class="kpi-value text-info mt-2">0</h3>
                          <small class="text-muted">Programs Offered</small>
                        </div>
                      </div>

                      <!-- Sections (placeholder) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-success">
                            <i class="bx bx-group"></i>
                          </div>
                          <h3 class="kpi-value text-success mt-2">0</h3>
                          <small class="text-muted">Active Sections</small>
                        </div>
                      </div>

                      <!-- Rooms (real count) -->
                      <div class="col-md-3 col-6">
                        <div class="kpi-box text-center">
                          <div class="kpi-icon bg-warning">
                            <i class="bx bx-building-house"></i>
                          </div>
                          <h3 class="kpi-value text-warning mt-2">
                            <?= $roomCount; ?>
                          </h3>
                          <small class="text-muted">Total Rooms</small>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>

                <!-- ROOM-BY-COLLEGE CHART + TABLE -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                      <p class="card-section-title mb-1">Resource Utilization</p>
                      <h5 class="m-0">
                        <?= $isUniversitySummary ? 'Room Distribution by Campus' : 'Room Distribution by College'; ?>
                      </h5>
                    </div>
                    <span class="badge bg-label-info">Academic Analytics</span>
                  </div>
                  <div class="card-body">
                    <!-- Chart -->
                    <div id="roomsPerCollegeChart"></div>

                    <!-- Room List -->
                    <div class="mt-4">
                      <h6 class="fw-bold mb-2">Room Directory</h6>
                      <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                          <thead>
                            <tr>
                              <th style="width: 60px;">#</th>
                              <?php if ($isUniversitySummary): ?>
                                <th>Campus</th>
                              <?php endif; ?>
                              <th>College / Department</th>
                              <th>Room</th>
                              <th>Code</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (count($rooms) === 0): ?>
                              <tr>
                                <td colspan="<?= $isUniversitySummary ? 5 : 4; ?>" class="text-center text-muted">
                                  No rooms recorded for this campus yet.
                                </td>
                              </tr>
                            <?php else: ?>
                              <?php $i = 1; ?>
                              <?php foreach ($rooms as $r): ?>
                                <tr>
                                  <td><?= $i++; ?></td>
                                  <?php if ($isUniversitySummary): ?>
                                    <td><?= htmlspecialchars($r['campus_name']); ?></td>
                                  <?php endif; ?>
                                  <td><?= htmlspecialchars($r['college_name']); ?></td>
                                  <td><?= htmlspecialchars($r['room_name']); ?></td>
                                  <td><?= htmlspecialchars($r['room_code']); ?></td>
                                </tr>
                              <?php endforeach; ?>
                            <?php endif; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>

                  </div>
                </div>

                <!-- PROGRAMS / PROSPECTUS PLACEHOLDER -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                      <p class="card-section-title mb-1">Programs & Prospectus</p>
                      <h5 class="m-0">Program Offerings Overview</h5>
                    </div>
                    <a href="manage-prospectus.php" class="btn btn-sm btn-outline-primary">
                      Manage Prospectus
                    </a>
                  </div>
                  <div class="card-body">
                    <p class="text-muted mb-2">
                      This section will list all programs and prospectus versions for
                      <?= $isUniversitySummary ? 'the entire university.' : 'this campus.'; ?>
                      You can connect it later to <code>tbl_prospectus_header</code> and related tables.
                    </p>
                    <div class="alert alert-info mb-0">
                      <i class="bx bx-info-circle me-1"></i>
                      Placeholder only for now. Hook this card to your existing Prospectus Builder queries when ready.
                    </div>
                  </div>
                </div>

              </div>
              <!-- /LEFT COLUMN -->

              <!-- RIGHT COLUMN -->
              <div class="col-lg-4 order-1 order-lg-0 mb-4">

                <!-- CAMPUS QUICK FACTS -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <p class="card-section-title mb-1">Quick Facts</p>
                    <h5 class="m-0">
                      <?= $isUniversitySummary ? 'University Snapshot' : 'Campus Snapshot'; ?>
                    </h5>
                  </div>
                  <div class="card-body">
                    <ul class="list-unstyled mb-0">
                      <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Campus Code</span>
                        <strong><?= htmlspecialchars($campusCode); ?></strong>
                      </li>
                      <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Total Rooms</span>
                        <strong><?= $roomCount; ?></strong>
                      </li>
                      <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Faculty</span>
                        <strong>0</strong>
                      </li>
                      <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Programs</span>
                        <strong>0</strong>
                      </li>
                    </ul>
                  </div>
                </div>

                <!-- FACULTY DIRECTORY PLACEHOLDER -->
                <div class="card shadow-sm mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                      <p class="card-section-title mb-1">People</p>
                      <h5 class="m-0">Faculty Directory</h5>
                    </div>
                    <a href="manage-faculty.php" class="btn btn-sm btn-outline-primary">
                      Manage Faculty
                    </a>
                  </div>
                  <div class="card-body">
                    <p class="text-muted mb-2">
                      Here you can later show top faculty for this campus (e.g., by workload or department).
                    </p>
                    <div class="alert alert-secondary mb-0">
                      <i class="bx bx-user me-1"></i>
                      Connect this panel to your faculty assignment tables when ready.
                    </div>
                  </div>
                </div>

                <!-- NAVIGATION TO OTHER CAMPUSES -->
                <div class="card shadow-sm">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0">Switch Campus</h5>
                    <span class="badge bg-label-primary">Navigation</span>
                  </div>
                  <div class="card-body">
                    <div class="list-group list-group-flush">
                      <a href="campus_dashboard.php?campus_id=all" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bx bx-globe me-2"></i>University Summary</span>
                      </a>
                      <?php
                      // Reuse campus list for quick switcher
                      $campSql = $conn->query("SELECT campus_id, campus_name FROM tbl_campus WHERE status='active' ORDER BY campus_name ASC");
                      while ($cRow = $campSql->fetch_assoc()):
                      ?>
                        <a
                          href="campus_dashboard.php?campus_id=<?= $cRow['campus_id']; ?>"
                          class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                          <?= (!$isUniversitySummary && (int)$campusId === (int)$cRow['campus_id']) ? 'active' : ''; ?>"
                        >
                          <span>
                            <i class="bx bx-buildings me-2"></i><?= htmlspecialchars($cRow['campus_name']); ?>
                          </span>
                          <?php if (!$isUniversitySummary && (int)$campusId === (int)$cRow['campus_id']): ?>
                            <i class="bx bx-check-circle"></i>
                          <?php endif; ?>
                        </a>
                      <?php endwhile; ?>
                    </div>
                  </div>
                </div>

              </div>
              <!-- /RIGHT COLUMN -->
            </div>

          </div>

          <!-- Footer -->
          <?php include '../footer.php'; ?>
          <!-- /Footer -->

          <div class="content-backdrop fade"></div>
        </div>
      </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <!-- Core JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>

  <!-- Vendors JS -->
  <script src="../assets/vendor/libs/apex-charts/apex-charts.js"></script>

  <!-- Main JS -->
  <script src="../assets/js/main.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      var labels   = <?= json_encode($collegeLabels); ?>;
      var counts   = <?= json_encode($collegeRoomCnt); ?>;

      // Avoid Apex issue for single data point
      if (counts.length === 1) {
        labels = ["", labels[0]];
        counts = [0, counts[0]];
      }

      var options = {
        series: [{
          name: 'Rooms',
          data: counts
        }],
        chart: {
          type: 'line',
          height: 320,
          toolbar: { show: false }
        },
        stroke: {
          curve: 'smooth',
          width: 3,
          colors: ['#ff9800']
        },
        markers: {
          size: 6,
          colors: ['#fff'],
          strokeColors: '#ff9800',
          strokeWidth: 3
        },
        xaxis: {
          categories: labels,
          labels: {
            style: {
              fontSize: '12px',
              colors: '#777'
            }
          }
        },
        yaxis: {
          min: 0,
          max: counts.length ? Math.max.apply(null, counts) + 1 : 5,
          tickAmount: 5,
          labels: {
            style: { colors: '#777' }
          },
          title: {
            text: 'Total Rooms',
            style: { color: '#777' }
          }
        },
        grid: {
          borderColor: '#eee',
          strokeDashArray: 4
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val + " room" + (val === 1 ? "" : "s");
            }
          }
        }
      };

      var chart = new ApexCharts(document.querySelector("#roomsPerCollegeChart"), options);
      chart.render();
    });
  </script>

</body>
</html>
