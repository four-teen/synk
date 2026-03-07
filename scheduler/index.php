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

if (!isset($_SESSION['college_id']) || $_SESSION['college_id'] === null) {
    echo "Scheduler error: missing college assignment.";
    exit;
}

$currentAyId = null;
$currentSem = null;

$termRes = $conn->query("
    SELECT current_ay_id, current_semester
    FROM tbl_academic_settings
    LIMIT 1
");

if ($termRes && $termRes->num_rows > 0) {
    $termRow = $termRes->fetch_assoc();
    $currentAyId = (int) $termRow['current_ay_id'];
    $currentSem = (int) $termRow['current_semester'];
}

$academicYearLabel = '';

if ($currentAyId) {
    $ayStmt = $conn->prepare("
        SELECT ay
        FROM tbl_academic_years
        WHERE ay_id = ?
        LIMIT 1
    ");
    $ayStmt->bind_param("i", $currentAyId);
    $ayStmt->execute();
    $ayRes = $ayStmt->get_result();

    if ($ayRow = $ayRes->fetch_assoc()) {
        $academicYearLabel = $ayRow['ay'];
    }

    $ayStmt->close();
}

$semesterLabel = '';
if ($currentSem === 1) {
    $semesterLabel = '1st Semester';
} elseif ($currentSem === 2) {
    $semesterLabel = '2nd Semester';
} elseif ($currentSem === 3) {
    $semesterLabel = 'Midyear';
}

$termParts = array_filter([$academicYearLabel, $semesterLabel]);
$academicTermText = !empty($termParts) ? implode(' - ', $termParts) : 'Current academic term';

$collegeName = htmlspecialchars($_SESSION['college_name'] ?? 'Assigned College', ENT_QUOTES, 'UTF-8');
$schedulerName = htmlspecialchars($_SESSION['username'] ?? 'Scheduler', ENT_QUOTES, 'UTF-8');
$academicTermTextEscaped = htmlspecialchars($academicTermText, ENT_QUOTES, 'UTF-8');
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

    <title>Scheduler Dashboard</title>

    <meta name="description" content="" />

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

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
    <link rel="stylesheet" type="text/css" href="custom_css.css" />

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
      .hero-card {
        overflow: hidden;
      }

      .kpi-box {
        padding: 10px;
        border-radius: 14px;
        transition: 0.2s ease;
      }

      .kpi-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
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
        font-size: 1.75rem;
        font-weight: 700;
      }

      .metric-note {
        min-height: 18px;
        font-size: 0.82rem;
        color: #8592a3;
      }

      .kpi-warning {
        background: #fff8f3;
        border: 1px solid #ffd6bf;
      }

      .kpi-warning-active {
        background: #fff2f0;
        border-color: #ffccc7;
        box-shadow: 0 8px 20px rgba(255, 77, 79, 0.12);
      }

      .workspace-scroll-area {
        max-height: 700px;
        overflow-y: auto;
        padding-left: 1.5rem;
        padding-right: 1.5rem;
      }

      .workspace-card-link {
        text-decoration: none !important;
      }

      .workspace-card {
        border: 1px solid #e4e4e4;
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 14px;
        background: #fff;
        transition: 0.2s ease-in-out;
      }

      .workspace-card:hover {
        transform: translateX(4px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      }

      .workspace-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-shrink: 0;
        font-size: 20px;
        color: #fff;
      }

      .status-list .list-group-item {
        padding-left: 0;
        padding-right: 0;
      }

      .status-list .list-group-item:first-child {
        border-top: 0;
      }

      .chart-shell {
        min-height: 320px;
      }

      .chart-fallback {
        min-height: 320px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #8592a3;
        text-align: center;
      }

      .hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
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
              <div class="row">
                <div class="col-lg-8 mb-4 order-0">
                  <div class="row">
                    <div class="col-lg-12 order-0 mb-4">
                      <div class="card shadow-sm hero-card">
                        <div class="d-flex align-items-center row">
                          <div class="col-sm-8 px-4 py-4">
                            <h4 class="text-primary fw-bold mb-2">Scheduler Dashboard</h4>

                            <p class="mb-3 text-muted">
                              Welcome back, <strong><?php echo $schedulerName; ?></strong>.
                              You are managing the scheduling workspace for
                              <strong><?php echo $collegeName; ?></strong> during
                              <strong><?php echo $academicTermTextEscaped; ?></strong>.
                            </p>

                            <div class="hero-meta mb-3">
                              <span class="badge bg-label-primary"><?php echo $collegeName; ?></span>
                              <span class="badge bg-label-info"><?php echo $academicTermTextEscaped; ?></span>
                            </div>

                            <a href="manage-class-schedule.php" class="btn btn-sm btn-primary">
                              Open Class Scheduling
                            </a>
                          </div>

                          <div class="col-sm-4 text-center">
                            <img
                              src="../assets/img/illustrations/man-with-laptop-light.png"
                              height="130"
                              alt="Scheduler Overview"
                              class="img-fluid p-2"
                            />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-lg-12 order-0 mb-4">
                      <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex align-items-center justify-content-between pb-0">
                          <div class="card-title mb-0">
                            <h5 class="m-0 me-2">Scheduling Operations Overview</h5>
                            <small class="text-muted">College-level scheduling indicators</small>
                          </div>
                          <span class="badge bg-label-primary">KPI Summary</span>
                        </div>

                        <div class="card-body px-4 py-3">
                          <div class="row gy-4">
                            <div class="col-md-3 col-6">
                              <div class="kpi-box text-center">
                                <div class="kpi-icon bg-primary">
                                  <i class="bx bx-book-open"></i>
                                </div>
                                <h3 class="kpi-value text-primary mt-2" id="countPrograms">--</h3>
                                <small class="text-muted">Programs</small>
                                <div class="metric-note">Active degree offerings</div>
                              </div>
                            </div>

                            <div class="col-md-3 col-6">
                              <div class="kpi-box text-center">
                                <div class="kpi-icon bg-success">
                                  <i class="bx bx-user-voice"></i>
                                </div>
                                <h3 class="kpi-value text-success mt-2" id="countFaculty">--</h3>
                                <small class="text-muted">Faculty</small>
                                <div class="metric-note">College roster assigned</div>
                              </div>
                            </div>

                            <div class="col-md-3 col-6">
                              <div class="kpi-box text-center">
                                <div class="kpi-icon bg-info">
                                  <i class="bx bx-detail"></i>
                                </div>
                                <h3 class="kpi-value text-info mt-2" id="countProspectus">--</h3>
                                <small class="text-muted">Prospectus Items</small>
                                <div class="metric-note">Subjects mapped for planning</div>
                              </div>
                            </div>

                            <div class="col-md-3 col-6">
                              <div class="kpi-box kpi-warning text-center" id="cardUnscheduled">
                                <div class="kpi-icon bg-warning">
                                  <i class="bx bx-error-circle"></i>
                                </div>
                                <h3 class="kpi-value text-warning mt-2" id="countUnscheduled">--</h3>
                                <small class="text-muted">Unscheduled Classes</small>
                                <div class="metric-note" id="unscheduledNote">Awaiting dashboard data</div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="col-lg-12 order-1 mb-4">
                      <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                          <div>
                            <h5 class="m-0">Faculty Teaching Load</h5>
                            <small class="text-muted">Unit distribution across assigned faculty</small>
                          </div>
                          <span class="badge bg-label-warning">College Analytics</span>
                        </div>

                        <div class="card-body">
                          <div id="facultyLoadChart" class="chart-shell"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-md-6 col-lg-4 order-2 mb-4">
                  <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex align-items-center justify-content-between">
                      <h5 class="card-title m-0">Scheduler Workspace</h5>
                      <span class="badge bg-label-primary">Action Center</span>
                    </div>

                    <div class="card-body workspace-scroll-area">
                      <a href="manage-prospectus.php" class="workspace-card-link">
                        <div class="workspace-card shadow-sm">
                          <div class="d-flex justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                              <div class="workspace-icon bg-primary">
                                <i class="bx bx-book-bookmark"></i>
                              </div>
                              <div>
                                <h6 class="mb-1 fw-bold text-dark">Prospectus Builder</h6>
                                <small class="text-muted">Review subjects by year and semester</small>
                              </div>
                            </div>
                            <i class="bx bx-chevron-right text-primary fs-4"></i>
                          </div>
                        </div>
                      </a>

                      <a href="manage-workload.php" class="workspace-card-link">
                        <div class="workspace-card shadow-sm">
                          <div class="d-flex justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                              <div class="workspace-icon bg-success">
                                <i class="bx bx-user-check"></i>
                              </div>
                              <div>
                                <h6 class="mb-1 fw-bold text-dark">Faculty Workload</h6>
                                <small class="text-muted">Align units and teaching assignments</small>
                              </div>
                            </div>
                            <i class="bx bx-chevron-right text-success fs-4"></i>
                          </div>
                        </div>
                      </a>

                      <a href="manage-class-schedule.php" class="workspace-card-link">
                        <div class="workspace-card shadow-sm">
                          <div class="d-flex justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                              <div class="workspace-icon bg-warning">
                                <i class="bx bx-time-five"></i>
                              </div>
                              <div>
                                <h6 class="mb-1 fw-bold text-dark">Class Scheduling</h6>
                                <small class="text-muted">Set time, days, and room assignments</small>
                              </div>
                            </div>
                            <i class="bx bx-chevron-right text-warning fs-4"></i>
                          </div>
                        </div>
                      </a>

                      <a href="manage-room-utilization.php" class="workspace-card-link">
                        <div class="workspace-card shadow-sm">
                          <div class="d-flex justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                              <div class="workspace-icon bg-info">
                                <i class="bx bx-building-house"></i>
                              </div>
                              <div>
                                <h6 class="mb-1 fw-bold text-dark">Room Utilization</h6>
                                <small class="text-muted">Inspect room usage and scheduling pressure</small>
                              </div>
                            </div>
                            <i class="bx bx-chevron-right text-info fs-4"></i>
                          </div>
                        </div>
                      </a>

                      <div class="border-top pt-3 mt-3">
                        <h6 class="fw-bold mb-3">Current Checks</h6>

                        <ul class="list-group list-group-flush status-list">
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Programs available</span>
                            <span class="badge bg-primary rounded-pill" id="summaryPrograms">--</span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Faculty roster</span>
                            <span class="badge bg-success rounded-pill" id="summaryFaculty">--</span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Prospectus subjects</span>
                            <span class="badge bg-info rounded-pill" id="summaryProspectus">--</span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Classes still unscheduled</span>
                            <span class="badge bg-success rounded-pill" id="summaryUnscheduled">--</span>
                          </li>
                        </ul>

                        <p class="text-muted small mb-0 mt-3" id="schedulerStatusMessage">
                          Scheduling looks clear for this workspace.
                        </p>
                      </div>
                    </div>
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

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>

    <script>
      document.addEventListener("DOMContentLoaded", function () {
        let facultyLoadChart = null;

        function animateCount(elementId, target) {
          const element = document.getElementById(elementId);
          const safeTarget = Number(target) || 0;

          if (!element) {
            return;
          }

          let current = 0;
          const increment = Math.max(1, Math.ceil(safeTarget / 20));

          const timer = window.setInterval(function () {
            current += increment;

            if (current >= safeTarget) {
              current = safeTarget;
              window.clearInterval(timer);
            }

            element.textContent = current;
          }, 20);
        }

        function setChartFallback(message) {
          const chartContainer = document.getElementById("facultyLoadChart");

          if (!chartContainer) {
            return;
          }

          chartContainer.innerHTML =
            '<div class="chart-fallback"><p class="mb-0">' + message + "</p></div>";
        }

        function renderFacultyLoadChart(data) {
          if (!Array.isArray(data) || data.length === 0) {
            setChartFallback("No workload data available.");
            return;
          }

          const facultyNames = [];
          const unitValues = [];
          const markers = [];

          data.forEach(function (row, index) {
            const units = Number(row.units) || 0;

            facultyNames.push(row.faculty || "Faculty " + (index + 1));
            unitValues.push(units);

            let markerColor = "#52c41a";
            let markerSize = 6;

            if (units >= 21) {
              markerColor = "#ff4d4f";
              markerSize = 7;
            } else if (units < 18) {
              markerColor = "#faad14";
              markerSize = 7;
            }

            markers.push({
              seriesIndex: 0,
              dataPointIndex: index,
              fillColor: markerColor,
              strokeColor: markerColor,
              size: markerSize
            });
          });

          const chartContainer = document.querySelector("#facultyLoadChart");

          if (!chartContainer) {
            return;
          }

          if (facultyLoadChart) {
            facultyLoadChart.destroy();
          }

          facultyLoadChart = new ApexCharts(chartContainer, {
            chart: {
              type: "line",
              height: 320,
              toolbar: { show: false }
            },
            series: [
              {
                name: "Total Units",
                data: unitValues
              }
            ],
            xaxis: {
              categories: facultyNames,
              labels: {
                rotate: -35,
                style: {
                  fontSize: "12px",
                  colors: "#697a8d"
                }
              }
            },
            yaxis: {
              min: 0,
              title: {
                text: "Units",
                style: {
                  color: "#697a8d"
                }
              }
            },
            stroke: {
              curve: "smooth",
              width: 3
            },
            markers: {
              size: 5,
              discrete: markers
            },
            grid: {
              borderColor: "#eceef1",
              strokeDashArray: 4
            },
            colors: ["#696cff"],
            tooltip: {
              y: {
                formatter: function (value) {
                  if (value >= 21) {
                    return value + " units (Overload)";
                  }

                  if (value < 18) {
                    return value + " units (Underload)";
                  }

                  return value + " units (Normal)";
                }
              }
            }
          });

          facultyLoadChart.render();
        }

        function updateDashboardCounts(data) {
          const programs = Number(data.programs) || 0;
          const faculty = Number(data.faculty) || 0;
          const prospectus = Number(data.prospectus_items) || 0;
          const unscheduled = Number(data.unscheduled_classes) || 0;

          animateCount("countPrograms", programs);
          animateCount("countFaculty", faculty);
          animateCount("countProspectus", prospectus);
          animateCount("countUnscheduled", unscheduled);

          document.getElementById("summaryPrograms").textContent = programs;
          document.getElementById("summaryFaculty").textContent = faculty;
          document.getElementById("summaryProspectus").textContent = prospectus;
          document.getElementById("summaryUnscheduled").textContent = unscheduled;

          const unscheduledCard = document.getElementById("cardUnscheduled");
          const unscheduledNote = document.getElementById("unscheduledNote");
          const unscheduledBadge = document.getElementById("summaryUnscheduled");
          const schedulerStatusMessage = document.getElementById("schedulerStatusMessage");

          if (unscheduled > 0) {
            unscheduledCard.classList.add("kpi-warning-active");
            unscheduledNote.textContent = "Needs schedule assignment";
            unscheduledBadge.className = "badge bg-danger rounded-pill";
            schedulerStatusMessage.textContent =
              unscheduled + " classes still need time or room assignment.";
            return;
          }

          unscheduledCard.classList.remove("kpi-warning-active");
          unscheduledNote.textContent = "All classes scheduled";
          unscheduledBadge.className = "badge bg-success rounded-pill";
          schedulerStatusMessage.textContent =
            "Scheduling looks clear for this workspace.";
        }

        $.ajax({
          url: "../backend/dashboard_faculty_load.php",
          type: "POST",
          dataType: "json",
          success: function (data) {
            renderFacultyLoadChart(data);
          },
          error: function (xhr) {
            console.error("Faculty load chart error:", xhr.responseText);
            setChartFallback("Unable to load faculty workload data.");
          }
        });

        $.ajax({
          url: "../backend/dashboard_counts.php",
          type: "POST",
          dataType: "json",
          success: function (data) {
            updateDashboardCounts(data);
          },
          error: function (xhr) {
            console.error("Dashboard counts error:", xhr.responseText);
            document.getElementById("schedulerStatusMessage").textContent =
              "Unable to load dashboard counts.";
          }
        });
      });
    </script>
  </body>
</html>
