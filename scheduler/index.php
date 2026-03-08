<?php
session_start();
ob_start();
include '../backend/db.php';
require_once '../backend/academic_term_helper.php';
require_once '../backend/scheduler_access_helper.php';

synk_scheduler_bootstrap_session_scope($conn);

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

$currentTerm = synk_fetch_current_academic_term($conn);
$academicTermText = $currentTerm['term_text'];

$collegeName = htmlspecialchars($_SESSION['college_name'] ?? 'Assigned College', ENT_QUOTES, 'UTF-8');
$campusName = htmlspecialchars($_SESSION['campus_name'] ?? 'Current Campus', ENT_QUOTES, 'UTF-8');
$schedulerName = htmlspecialchars($_SESSION['username'] ?? 'Scheduler', ENT_QUOTES, 'UTF-8');
$academicTermTextEscaped = htmlspecialchars($academicTermText, ENT_QUOTES, 'UTF-8');
$campusViewEnabled = (int)($_SESSION['campus_id'] ?? 0) > 0;
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
        min-height: 340px;
      }

      .chart-shell-compact {
        min-height: 340px;
      }

      .chart-fallback {
        min-height: 340px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #8592a3;
        text-align: center;
      }

      .chart-loader {
        min-height: 340px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #8592a3;
        text-align: center;
      }

      .chart-loader .loader-inline {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        font-weight: 600;
      }

      .hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      .chart-insight {
        min-height: 40px;
        margin-top: 0.75rem;
        font-size: 0.84rem;
        color: #8592a3;
      }

      .dashboard-scope-toggle .btn {
        min-width: 7.5rem;
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
                              <span class="badge bg-label-info"><?php echo $campusName; ?></span>
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
                            <small class="text-muted" id="operationsOverviewSubtitle">College-level scheduling indicators</small>
                          </div>
                          <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="btn-group btn-group-sm dashboard-scope-toggle" role="group" aria-label="Dashboard scope toggle">
                              <button type="button" class="btn btn-outline-primary active" data-dashboard-scope="college">Current College</button>
                              <?php if ($campusViewEnabled): ?>
                                <button type="button" class="btn btn-outline-primary" data-dashboard-scope="campus">Current Campus</button>
                              <?php endif; ?>
                            </div>
                            <span class="badge bg-label-primary" id="dashboardScopeBadge">College View</span>
                          </div>
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
                                <div class="metric-note">Programs with offerings this term</div>
                              </div>
                            </div>

                            <div class="col-md-3 col-6">
                              <div class="kpi-box text-center">
                                <div class="kpi-icon bg-success">
                                  <i class="bx bx-user-voice"></i>
                                </div>
                                <h3 class="kpi-value text-success mt-2" id="countFaculty">--</h3>
                                <small class="text-muted">Assigned Faculty</small>
                                <div class="metric-note">With workload in the active term</div>
                              </div>
                            </div>

                            <div class="col-md-3 col-6">
                              <div class="kpi-box text-center">
                                <div class="kpi-icon bg-info">
                                  <i class="bx bx-detail"></i>
                                </div>
                                <h3 class="kpi-value text-info mt-2" id="countProspectus">--</h3>
                                <small class="text-muted">Class Offerings</small>
                                <div class="metric-note">Generated for the active term</div>
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
                            <h5 class="m-0" id="programChartTitle">Scheduling Progress by Program</h5>
                            <small class="text-muted" id="programChartSubtitle">Each line shows how many classes a program has, how many are already scheduled, and how many still need schedule assignment.</small>
                          </div>
                          <span class="badge bg-label-primary" id="programChartBadge">Scheduler Focus</span>
                        </div>

                        <div class="card-body">
                          <div id="programSchedulingChart" class="chart-shell"></div>
                          <p class="chart-insight mb-0" id="programProgressInsight">
                            This graph helps you see which program still has the most unscheduled classes.
                          </p>
                        </div>
                      </div>
                    </div>

                    <div class="col-lg-12 order-1 mb-4">
                      <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                          <div>
                            <h5 class="m-0" id="weeklyPressureTitle">Weekly Scheduling Pressure</h5>
                            <small class="text-muted" id="weeklyPressureSubtitle">Higher lines mean a busier day. It shows how many classes are scheduled, how many rooms are used, and how many faculty are active each weekday.</small>
                          </div>
                          <span class="badge bg-label-info" id="weeklyPressureBadge">Demand Map</span>
                        </div>

                        <div class="card-body">
                          <div id="weeklyPressureChart" class="chart-shell chart-shell-compact"></div>
                          <p class="chart-insight mb-0" id="weeklyPressureInsight">
                            This graph helps you spot which days are crowded and may need balancing.
                          </p>
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
                                <h6 class="mb-1 fw-bold text-dark">Prospectus Viewer</h6>
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
                            <span class="fw-semibold">Faculty with workload</span>
                            <span class="badge bg-success rounded-pill" id="summaryFaculty">--</span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Class offerings</span>
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
        let schedulingProgressChart = null;
        let weeklyPressureChart = null;
        let dashboardScope = "college";
        const campusViewEnabled = <?php echo $campusViewEnabled ? 'true' : 'false'; ?>;

        function buildInlineLoader(message) {
          return (
            '<div class="loader-inline">' +
              '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' +
              '<span>' + message + "</span>" +
            "</div>"
          );
        }

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

        function setChartFallback(containerId, message) {
          const chartContainer = document.getElementById(containerId);

          if (!chartContainer) {
            return;
          }

          chartContainer.innerHTML =
            '<div class="chart-fallback"><p class="mb-0">' + message + "</p></div>";
        }

        function setChartLoading(containerId, message) {
          const chartContainer = document.getElementById(containerId);

          if (!chartContainer) {
            return;
          }

          chartContainer.innerHTML =
            '<div class="chart-loader">' + buildInlineLoader(message) + "</div>";
        }

        function setInsight(elementId, message) {
          const element = document.getElementById(elementId);

          if (element) {
            element.textContent = message;
          }
        }

        function setScopeButtons() {
          document.querySelectorAll("[data-dashboard-scope]").forEach(function (button) {
            const isActive = button.getAttribute("data-dashboard-scope") === dashboardScope;
            button.classList.toggle("active", isActive);
            button.classList.toggle("btn-primary", isActive);
            button.classList.toggle("btn-outline-primary", !isActive);
          });
        }

        function applyDashboardScopeMeta(meta) {
          const scopeType = meta && meta.scope === "campus" && campusViewEnabled ? "campus" : "college";
          const scopeLabel = meta && meta.scope_label ? meta.scope_label : (scopeType === "campus" ? <?php echo json_encode($_SESSION['campus_name'] ?? 'Current Campus'); ?> : <?php echo json_encode($_SESSION['college_name'] ?? 'Assigned College'); ?>);
          const scopeBadge = document.getElementById("dashboardScopeBadge");
          const overviewSubtitle = document.getElementById("operationsOverviewSubtitle");
          const programChartTitle = document.getElementById("programChartTitle");
          const programChartSubtitle = document.getElementById("programChartSubtitle");
          const programChartBadge = document.getElementById("programChartBadge");
          const weeklyPressureTitle = document.getElementById("weeklyPressureTitle");
          const weeklyPressureSubtitle = document.getElementById("weeklyPressureSubtitle");
          const weeklyPressureBadge = document.getElementById("weeklyPressureBadge");

          if (scopeBadge) {
            scopeBadge.textContent = scopeType === "campus" ? "Campus View" : "College View";
          }

          if (overviewSubtitle) {
            overviewSubtitle.textContent = scopeType === "campus"
              ? "Read-only scheduling indicators across all colleges in " + scopeLabel
              : "College-level scheduling indicators";
          }

          if (programChartTitle) {
            programChartTitle.textContent = scopeType === "campus"
              ? "Scheduling Progress by College"
              : "Scheduling Progress by Program";
          }

          if (programChartSubtitle) {
            programChartSubtitle.textContent = scopeType === "campus"
              ? "Each line shows total classes, scheduled classes, and remaining unscheduled classes for every college in the current campus."
              : "Each line shows how many classes a program has, how many are already scheduled, and how many still need schedule assignment.";
          }

          if (programChartBadge) {
            programChartBadge.textContent = scopeType === "campus" ? "Campus Monitor" : "Scheduler Focus";
          }

          if (weeklyPressureTitle) {
            weeklyPressureTitle.textContent = scopeType === "campus"
              ? "Weekly Scheduling Pressure Across Campus"
              : "Weekly Scheduling Pressure";
          }

          if (weeklyPressureSubtitle) {
            weeklyPressureSubtitle.textContent = scopeType === "campus"
              ? "Higher lines mean a busier campus day. It combines scheduled classes, rooms in use, and faculty active across all colleges in the current campus."
              : "Higher lines mean a busier day. It shows how many classes are scheduled, how many rooms are used, and how many faculty are active each weekday.";
          }

          if (weeklyPressureBadge) {
            weeklyPressureBadge.textContent = scopeType === "campus" ? "Campus Demand" : "Demand Map";
          }
        }

        function setCountsLoadingState() {
          ["countPrograms", "countFaculty", "countProspectus", "countUnscheduled"].forEach(function (id) {
            const element = document.getElementById(id);
            if (element) {
              element.textContent = "--";
            }
          });

          ["summaryPrograms", "summaryFaculty", "summaryProspectus", "summaryUnscheduled"].forEach(function (id) {
            const element = document.getElementById(id);
            if (element) {
              element.textContent = "--";
            }
          });

          const unscheduledCard = document.getElementById("cardUnscheduled");
          const unscheduledNote = document.getElementById("unscheduledNote");
          const schedulerStatusMessage = document.getElementById("schedulerStatusMessage");

          if (unscheduledCard) {
            unscheduledCard.classList.remove("kpi-warning-active");
          }
          if (unscheduledNote) {
            unscheduledNote.textContent = "Loading dashboard counts";
          }
          if (schedulerStatusMessage) {
            schedulerStatusMessage.textContent = "Loading dashboard data...";
          }
        }

        function renderProgramSchedulingChart(data) {
          if (!Array.isArray(data) || data.length === 0) {
            setChartFallback("programSchedulingChart", "No program scheduling data available.");
            setInsight("programProgressInsight", "No class offerings were found for the current term, so there is nothing to compare yet.");
            return;
          }

          const categories = [];
          const totalValues = [];
          const scheduledValues = [];
          const pendingValues = [];
          let highestPending = null;
          let totalPending = 0;

          data.forEach(function (row, index) {
            const programLabel = row.program || "Program " + (index + 1);
            const total = Number(row.total) || 0;
            const scheduled = Number(row.scheduled) || 0;
            const pending = Number(row.pending);
            const safePending = Number.isFinite(pending) ? pending : Math.max(0, total - scheduled);

            categories.push(programLabel);
            totalValues.push(total);
            scheduledValues.push(scheduled);
            pendingValues.push(safePending);
            totalPending += safePending;

            if (!highestPending || safePending > highestPending.pending || (safePending === highestPending.pending && total > highestPending.total)) {
              highestPending = {
                program: programLabel,
                pending: safePending,
                total: total
              };
            }
          });

          const chartContainer = document.querySelector("#programSchedulingChart");

          if (!chartContainer) {
            return;
          }

          if (schedulingProgressChart) {
            schedulingProgressChart.destroy();
          }

          chartContainer.innerHTML = "";

          schedulingProgressChart = new ApexCharts(chartContainer, {
            chart: {
              type: "line",
              height: 340,
              toolbar: { show: false },
              zoom: { enabled: false }
            },
            series: [
              {
                name: "All Classes",
                data: totalValues
              },
              {
                name: "Already Scheduled",
                data: scheduledValues
              },
              {
                name: "Still Unscheduled",
                data: pendingValues
              }
            ],
            colors: ["#696cff", "#71dd37", "#ffab00"],
            stroke: {
              curve: "smooth",
              width: [3, 4, 3]
            },
            fill: {
              type: "gradient",
              gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.2,
                opacityTo: 0.03,
                stops: [0, 90, 100]
              }
            },
            markers: {
              size: 0,
              hover: {
                sizeOffset: 5
              }
            },
            dataLabels: {
              enabled: false
            },
            legend: {
              position: "top",
              horizontalAlign: "left",
              fontSize: "12px"
            },
            grid: {
              borderColor: "#eceef1",
              strokeDashArray: 4
            },
            xaxis: {
              categories: categories,
              labels: {
                rotate: -25,
                trim: true,
                style: {
                  fontSize: "12px",
                  colors: "#697a8d"
                }
              }
            },
            yaxis: {
              min: 0,
              forceNiceScale: true,
              title: {
                text: "Offerings",
                style: {
                  color: "#697a8d"
                }
              }
            },
            tooltip: {
              shared: true,
              intersect: false,
              y: {
                formatter: function (value, context) {
                  const labels = [" total classes", " already scheduled", " still unscheduled"];
                  return value + labels[context.seriesIndex];
                }
              }
            },
            responsive: [
              {
                breakpoint: 768,
                options: {
                  chart: {
                    height: 320
                  },
                  xaxis: {
                    labels: {
                      rotate: -40
                    }
                  }
                }
              }
            ]
          });

          schedulingProgressChart.render();

          if (totalPending === 0) {
            setInsight("programProgressInsight", "All programs are fully scheduled. The unscheduled line is at zero across the chart.");
            return;
          }

          setInsight(
            "programProgressInsight",
            "The orange line shows classes that still need schedule assignment. " +
              highestPending.program +
              " has the highest unscheduled count with " +
              highestPending.pending +
              " class" +
              (highestPending.pending === 1 ? "" : "s") +
              " still not scheduled out of " +
              highestPending.total +
              " total."
          );
        }

        function renderWeeklyPressureChart(data) {
          if (!Array.isArray(data) || data.length === 0) {
            setChartFallback("weeklyPressureChart", "No weekday scheduling pressure data available.");
            setInsight("weeklyPressureInsight", "No scheduled classes were found for the current term, so weekday pressure cannot be measured yet.");
            return;
          }

          const days = [];
          const meetings = [];
          const rooms = [];
          const faculty = [];
          let busiestDay = null;

          data.forEach(function (row) {
            const dayLabel = row.day || "--";
            const meetingCount = Number(row.meetings) || 0;
            const roomCount = Number(row.rooms) || 0;
            const facultyCount = Number(row.faculty) || 0;

            days.push(dayLabel);
            meetings.push(meetingCount);
            rooms.push(roomCount);
            faculty.push(facultyCount);

            if (!busiestDay || meetingCount > busiestDay.meetings || (meetingCount === busiestDay.meetings && roomCount > busiestDay.rooms)) {
              busiestDay = {
                day: dayLabel,
                meetings: meetingCount,
                rooms: roomCount
              };
            }
          });

          const chartContainer = document.querySelector("#weeklyPressureChart");

          if (!chartContainer) {
            return;
          }

          if (weeklyPressureChart) {
            weeklyPressureChart.destroy();
          }

          chartContainer.innerHTML = "";

          weeklyPressureChart = new ApexCharts(chartContainer, {
            chart: {
              type: "line",
              height: 340,
              toolbar: { show: false },
              zoom: { enabled: false }
            },
            series: [
              {
                name: "Scheduled Classes",
                data: meetings
              },
              {
                name: "Rooms In Use",
                data: rooms
              },
              {
                name: "Faculty Active",
                data: faculty
              }
            ],
            colors: ["#03c3ec", "#696cff", "#71dd37"],
            stroke: {
              curve: "smooth",
              width: [4, 3, 3]
            },
            fill: {
              type: "gradient",
              gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.18,
                opacityTo: 0.02,
                stops: [0, 90, 100]
              }
            },
            markers: {
              size: 4,
              strokeWidth: 0,
              hover: {
                sizeOffset: 2
              }
            },
            dataLabels: {
              enabled: false
            },
            legend: {
              position: "top",
              horizontalAlign: "left",
              fontSize: "12px"
            },
            grid: {
              borderColor: "#eceef1",
              strokeDashArray: 4
            },
            xaxis: {
              categories: days,
              labels: {
                style: {
                  fontSize: "12px",
                  colors: "#697a8d"
                }
              }
            },
            yaxis: {
              min: 0,
              forceNiceScale: true,
              title: {
                text: "Daily Load",
                style: {
                  color: "#697a8d"
                }
              }
            },
            tooltip: {
              shared: true,
              intersect: false,
              y: {
                formatter: function (value, context) {
                  const labels = [" scheduled classes", " rooms in use", " faculty active"];
                  return value + labels[context.seriesIndex];
                }
              }
            }
          });

          weeklyPressureChart.render();

          if (!busiestDay || busiestDay.meetings === 0) {
            setInsight("weeklyPressureInsight", "No scheduled classes are mapped to weekdays yet.");
            return;
          }

          setInsight(
            "weeklyPressureInsight",
            "Higher lines mean a busier day. " +
              busiestDay.day +
              " is currently the busiest with " +
              busiestDay.meetings +
              " scheduled class" +
              (busiestDay.meetings === 1 ? "" : "s") +
              " using " +
              busiestDay.rooms +
              " room" +
              (busiestDay.rooms === 1 ? "" : "s") +
              "."
          );
        }
        function updateDashboardCounts(data) {
          const programs = Number(data.programs) || 0;
          const faculty = Number(data.faculty) || 0;
          const prospectus = Number(data.prospectus_items) || 0;
          const unscheduled = Number(data.unscheduled_classes) || 0;
          const scopeType = data && data.scope === "campus" && campusViewEnabled ? "campus" : "college";
          const scopeLabel = data && data.scope_label ? data.scope_label : (scopeType === "campus" ? <?php echo json_encode($_SESSION['campus_name'] ?? 'Current Campus'); ?> : <?php echo json_encode($_SESSION['college_name'] ?? 'Assigned College'); ?>);

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
            unscheduledNote.textContent = scopeType === "campus" ? "Need schedule assignment across campus" : "Needs schedule assignment";
            unscheduledBadge.className = "badge bg-danger rounded-pill";
            schedulerStatusMessage.textContent =
              scopeType === "campus"
                ? unscheduled + " classes still need time or room assignment across " + scopeLabel + "."
                : unscheduled + " classes still need time or room assignment.";
            return;
          }

          unscheduledCard.classList.remove("kpi-warning-active");
          unscheduledNote.textContent = scopeType === "campus" ? "All campus classes scheduled" : "All classes scheduled";
          unscheduledBadge.className = "badge bg-success rounded-pill";
          schedulerStatusMessage.textContent =
            scopeType === "campus"
              ? "Scheduling looks clear across " + scopeLabel + "."
              : "Scheduling looks clear for this workspace.";
        }

        function loadDashboardData() {
          applyDashboardScopeMeta({
            scope: dashboardScope,
            scope_label: dashboardScope === "campus"
              ? <?php echo json_encode($_SESSION['campus_name'] ?? 'Current Campus'); ?>
              : <?php echo json_encode($_SESSION['college_name'] ?? 'Assigned College'); ?>
          });
          setCountsLoadingState();
          setChartLoading("programSchedulingChart", "Loading program scheduling progress...");
          setChartLoading("weeklyPressureChart", "Loading weekday scheduling pressure...");
          setInsight(
            "programProgressInsight",
            dashboardScope === "campus"
              ? "Loading the comparison of total classes, scheduled classes, and unscheduled classes for each college in the current campus."
              : "Loading the comparison of total classes, scheduled classes, and unscheduled classes per program."
          );
          setInsight(
            "weeklyPressureInsight",
            dashboardScope === "campus"
              ? "Loading which weekdays are busiest across all colleges in the current campus."
              : "Loading which weekdays are busiest based on classes, rooms, and faculty activity."
          );

          $.ajax({
            url: "../backend/dashboard_scheduler_charts.php",
            type: "POST",
            dataType: "json",
            data: { scope: dashboardScope },
            success: function (data) {
              applyDashboardScopeMeta(data || {});
              renderProgramSchedulingChart(data.program_progress || []);
              renderWeeklyPressureChart(data.weekly_pressure || []);
            },
            error: function (xhr) {
              console.error("Scheduler charts error:", xhr.responseText);
              setChartFallback("programSchedulingChart", "Unable to load program scheduling progress.");
              setChartFallback("weeklyPressureChart", "Unable to load weekday scheduling pressure.");
              setInsight("programProgressInsight", "Program comparison data could not be loaded.");
              setInsight("weeklyPressureInsight", "Weekday load data could not be loaded.");
            }
          });

          $.ajax({
            url: "../backend/dashboard_counts.php",
            type: "POST",
            dataType: "json",
            data: { scope: dashboardScope },
            success: function (data) {
              updateDashboardCounts(data || {});
            },
            error: function (xhr) {
              console.error("Dashboard counts error:", xhr.responseText);
              document.getElementById("schedulerStatusMessage").textContent =
                "Unable to load dashboard counts.";
            }
          });
        }

        document.querySelectorAll("[data-dashboard-scope]").forEach(function (button) {
          button.addEventListener("click", function () {
            const requestedScope = button.getAttribute("data-dashboard-scope");
            if (requestedScope === dashboardScope) {
              return;
            }

            if (requestedScope === "campus" && !campusViewEnabled) {
              return;
            }

            dashboardScope = requestedScope;
            setScopeButtons();
            loadDashboardData();
          });
        });

        setScopeButtons();
        loadDashboardData();
      });
    </script>
  </body>
</html>
