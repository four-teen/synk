<?php
    session_start();
    ob_start();
    include '../backend/db.php';

    /*
    |--------------------------------------------------------------------------
    | LOAD CURRENT ACADEMIC SETTINGS
    |--------------------------------------------------------------------------
    */
    $currentAyId   = null;
    $currentSem    = null;

    $termRes = $conn->query("
        SELECT current_ay_id, current_semester
        FROM tbl_academic_settings
        LIMIT 1
    ");

    if ($termRes && $termRes->num_rows > 0) {
        $termRow     = $termRes->fetch_assoc();
        $currentAyId = (int)$termRow['current_ay_id'];
        $currentSem  = (int)$termRow['current_semester'];
    }


    /* =====================================================
      RESOLVE ACADEMIC TERM LABEL (FOR DISPLAY ONLY)
    ===================================================== */

    // 1️⃣ Resolve Academic Year label (e.g., 2025–2026)
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

    // 2️⃣ Resolve Semester label
    $semesterLabel = '';
    if ($currentSem == 1) {
        $semesterLabel = '1st Semester';
    } elseif ($currentSem == 2) {
        $semesterLabel = '2nd Semester';
    } elseif ($currentSem == 3) {
        $semesterLabel = 'Midyear';
    }

    // 3️⃣ Final text used by modals
    $academicTermText = "AY: {$academicYearLabel} · {$semesterLabel}";





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
    
/*
|--------------------------------------------------------------------------
| FACULTY COUNT PER CAMPUS (CURRENT TERM ONLY)
|--------------------------------------------------------------------------
| Source:
| - tbl_faculty_workload_sched (term-based workload)
| - resolved via class_schedule → prospectus_offering → sections → program → college → campus
|--------------------------------------------------------------------------
*/

$facultyPerCampus = [];

if ($currentAyId && $currentSem) {

    $facultySql = "
        SELECT
            camp.campus_id,
            COUNT(DISTINCT fws.faculty_id) AS faculty_count
        FROM tbl_faculty_workload_sched fws

        INNER JOIN tbl_class_schedule cs
            ON cs.schedule_id = fws.schedule_id

        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id

        INNER JOIN tbl_sections s
            ON s.section_id = po.section_id

        INNER JOIN tbl_program p
            ON p.program_id = s.program_id

        INNER JOIN tbl_college col
            ON col.college_id = p.college_id

        INNER JOIN tbl_campus camp
            ON camp.campus_id = col.campus_id

        WHERE fws.ay_id = ?
          AND fws.semester = ?

        GROUP BY camp.campus_id
    ";

    $stmt = $conn->prepare($facultySql);
    $stmt->bind_param("ii", $currentAyId, $currentSem);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $facultyPerCampus[(int)$row['campus_id']] = (int)$row['faculty_count'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| TOTAL FACULTY (INSTITUTION-WIDE, CURRENT TERM)
|--------------------------------------------------------------------------
| Definition:
| - Counts DISTINCT faculty_id
| - From tbl_faculty_workload_sched
| - Filtered by current academic year and semester
|--------------------------------------------------------------------------
*/

$totalFaculty = 0;

if ($currentAyId && $currentSem) {

    $totalFacultySql = "
        SELECT COUNT(DISTINCT faculty_id) AS total_faculty
        FROM tbl_faculty_workload_sched
        WHERE ay_id = ?
          AND semester = ?
    ";

    $stmt = $conn->prepare($totalFacultySql);
    $stmt->bind_param("ii", $currentAyId, $currentSem);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $totalFaculty = (int)$row['total_faculty'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| TOTAL PROGRAMS OFFERED (CURRENT TERM)
|--------------------------------------------------------------------------
| Definition:
| - DISTINCT programs with at least one offering
| - Based on tbl_prospectus_offering
| - Filtered by current academic year and semester
|--------------------------------------------------------------------------
*/

$totalPrograms = 0;

if ($currentAyId && $currentSem) {

    $programSql = "
        SELECT COUNT(DISTINCT p.program_id) AS total_programs
        FROM tbl_prospectus_offering po

        INNER JOIN tbl_sections s
            ON s.section_id = po.section_id

        INNER JOIN tbl_program p
            ON p.program_id = s.program_id

        WHERE po.ay_id = ?
          AND po.semester = ?
          AND p.status = 'active'
    ";

    $stmt = $conn->prepare($programSql);
    $stmt->bind_param("ii", $currentAyId, $currentSem);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $totalPrograms = (int)$row['total_programs'];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| TOTAL ACTIVE SECTIONS (INSTITUTION-WIDE, CURRENT TERM)
|--------------------------------------------------------------------------
| Definition:
| - DISTINCT sections with at least one prospectus offering
| - Based on tbl_prospectus_offering
| - Filtered by current academic year and semester
| - Option A: Offering-based (no schedule/faculty required)
|--------------------------------------------------------------------------
*/

$totalSections = 0;

if ($currentAyId && $currentSem) {

    $sectionSql = "
        SELECT COUNT(DISTINCT s.section_id) AS total_sections
        FROM tbl_prospectus_offering po

        INNER JOIN tbl_sections s
            ON s.section_id = po.section_id

        INNER JOIN tbl_program p
            ON p.program_id = s.program_id

        WHERE po.ay_id = ?
          AND po.semester = ?
          AND p.status = 'active'
    ";

    $stmt = $conn->prepare($sectionSql);
    $stmt->bind_param("ii", $currentAyId, $currentSem);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $totalSections = (int)$row['total_sections'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| TOTAL FACULTY WORKLOADS (SCHEDULED CLASSES)
|--------------------------------------------------------------------------
| Definition:
| - Counts class schedules
| - Includes unassigned faculty
| - Includes no room
| - Schedule belongs to an offering
| - Offering defines ay_id and semester
|--------------------------------------------------------------------------
*/

$totalWorkloads = 0;

if ($currentAyId && $currentSem) {

    $workloadSql = "
        SELECT COUNT(cs.schedule_id) AS total_workloads
        FROM tbl_class_schedule cs
        INNER JOIN tbl_prospectus_offering po
            ON po.offering_id = cs.offering_id
        WHERE po.ay_id = ?
          AND po.semester = ?
    ";

    $stmt = $conn->prepare($workloadSql);
    $stmt->bind_param("ii", $currentAyId, $currentSem);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $totalWorkloads = (int)$row['total_workloads'];
    }

    $stmt->close();
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

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

.cursor-pointer {
  cursor: pointer;
}

.kpi-box:hover {
  transform: translateY(-3px);
  transition: 0.2s ease;
  box-shadow: 0 6px 18px rgba(0,0,0,0.08);
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
              <h3 class="kpi-value text-primary mt-2"><?php echo $totalFaculty; ?></h3>
              <small class="text-muted">Total Faculty</small>
            </div>
          </div>

<!-- TOTAL PROGRAMS -->
<div class="col-md-3 col-6">
  <div class="kpi-box text-center cursor-pointer" id="kpiProgramsOffered">
    <div class="kpi-icon bg-info">
      <i class="bx bx-book-open"></i>
    </div>
    <h3 class="kpi-value text-info mt-2">5</h3>
    <small class="text-muted">Programs Offered</small>
  </div>
</div>


          <!-- TOTAL SECTIONS -->
          <div class="col-md-3 col-6">
            <div class="kpi-box text-center cursor-pointer">
              <div class="kpi-icon bg-success">
                <i class="bx bx-group"></i>
              </div>
              <h3 class="kpi-value text-success mt-2">
                <?php echo $totalSections; ?>
              </h3>
              <small class="text-muted">Active Sections</small>
            </div>
          </div>

          <!-- TOTAL WORKLOADS -->
          <div class="col-md-3 col-6">
            <div class="kpi-box text-center cursor-pointer" id="kpiFacultyWorkloads">
              <div class="kpi-icon bg-warning">
                <i class="bx bx-calendar"></i>
              </div>
              <h3 class="kpi-value text-warning mt-2">
                <?php echo $totalWorkloads; ?>
              </h3>
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
                                <?php
                                $facCount = $facultyPerCampus[$c['campus_id']] ?? 0;
                                ?>
                                Faculty: <span class="fw-semibold text-primary"><?= $facCount ?></span> <!-- placeholder -->
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


<!-- ======================================================
     PROGRAMS OFFERED MODAL (KPI DRILL-DOWN)
====================================================== -->
<div class="modal fade" id="programsOfferedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <!-- MODAL HEADER -->
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bx bx-book-open me-2 text-info"></i>
          Programs Offered
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- MODAL BODY -->
      <div class="modal-body">

        <!-- TERM CONTEXT -->
        <div class="alert alert-light border d-flex align-items-center mb-3">
          <i class="bx bx-calendar me-2 text-primary"></i>
          <strong class="me-2">Academic Term:</strong>
          <span id="modalAcademicTerm">
            2025–2026 · 2nd Semester
          </span>
        </div>

        <!-- PROGRAM TABLE -->
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle">

            <thead class="table-light">
              <tr>
                <th>Program Code</th>
                <th>Program Name</th>
                <th>Major</th>
                <th>College</th>
                <th>Campus</th>
                <th class="text-center">Sections</th>
              </tr>
            </thead>

            <tbody id="programsOfferedTable">
              <!-- DATA WILL BE INJECTED HERE -->
              <tr>
                <td colspan="6" class="text-center text-muted py-4">
                  <i class="bx bx-loader-circle bx-spin me-1"></i>
                  Loading programs offered...
                </td>
              </tr>
            </tbody>

          </table>
        </div>

      </div>

      <!-- MODAL FOOTER -->
      <div class="modal-footer justify-content-between">
        <small class="text-muted">
          *Programs listed here have at least one active section this term.
        </small>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Close
        </button>
      </div>

    </div>
  </div>
</div>

<!-- ======================================================
     ACTIVE SECTIONS MODAL (KPI DRILL-DOWN)
====================================================== -->
<div class="modal fade" id="activeSectionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <!-- MODAL HEADER -->
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bx bx-group me-2 text-success"></i>
          Active Sections
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- MODAL BODY -->
      <div class="modal-body">

        <!-- TERM CONTEXT -->
        <div class="alert alert-light border d-flex align-items-center mb-3">
          <i class="bx bx-calendar me-2 text-primary"></i>
          <strong class="me-2">Academic Term:</strong>
          <span id="sectionsAcademicTerm">
            2025–2026 · 2nd Semester
          </span>
        </div>

        <!-- TABLE -->
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle" id="activeSectionsTable">

            <thead class="table-light">
              <tr>
                <th>Section</th>
                <th>Program</th>
                <th>College</th>
                <th>Campus</th>
                <th class="text-center">Offerings</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">
                  <i class="bx bx-loader-circle bx-spin me-1"></i>
                  Loading active sections...
                </td>
              </tr>
            </tbody>

          </table>
        </div>

      </div>

      <!-- MODAL FOOTER -->
      <div class="modal-footer justify-content-between">
        <small class="text-muted">
          *Sections listed here are offered in the current academic term.
        </small>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Close
        </button>
      </div>

    </div>
  </div>
</div>

<!-- ======================================================
     SUBJECT OFFERINGS MODAL (LEVEL 3)
====================================================== -->
<div class="modal fade" id="sectionOfferingsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bx bx-book-alt me-2 text-success"></i>
          Subjects Offered — <span id="sectionTitle"></span>
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

      <div class="alert alert-light border d-flex align-items-center mb-3">
        <i class="bx bx-calendar me-2 text-primary"></i>
        <strong class="me-2">Academic Term:</strong>
        <span><?= htmlspecialchars($academicTermText) ?></span>
      </div>

        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle" id="sectionOfferingsTable">
            <thead class="table-light">
              <tr>
                <th>Subject Code</th>
                <th>Subject Title</th>
                <th class="text-center">Units</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="4" class="text-center text-muted py-4">
                  <i class="bx bx-loader-circle bx-spin me-1"></i>
                  Loading subjects...
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<!-- ======================================================
     FACULTY WORKLOADS MODAL (KPI C)
====================================================== -->
<div class="modal fade" id="facultyWorkloadsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bx bx-calendar me-2 text-warning"></i>
          Faculty Workloads (Scheduled Classes)
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- TERM CONTEXT -->
        <div class="alert alert-light border d-flex align-items-center mb-3">
          <i class="bx bx-calendar me-2 text-primary"></i>
          <strong class="me-2">Academic Term:</strong>
          <span><?= htmlspecialchars($academicTermText) ?></span>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle" id="facultyWorkloadsTable">
            <thead class="table-light">
              <tr>
                <th>Subject</th>
                <th>Section</th>
                <th>Program</th>
                <th>College</th>
                <th>Campus</th>
                <th>Schedule</th>
                <th>Room</th>
                <th>Faculty</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">
                  <i class="bx bx-loader-circle bx-spin me-1"></i>
                  Loading scheduled classes...
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <div class="modal-footer">
        <small class="text-muted">
          *Includes classes without faculty or room assignment.
        </small>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>


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


  let programsDataTable = null;


  const programsKpi = document.getElementById("kpiProgramsOffered");
  const tableBody   = document.getElementById("programsOfferedTable");

  if (!programsKpi) return;

  programsKpi.addEventListener("click", function () {

    // Reset table with loading state
    tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center text-muted py-4">
          <i class="bx bx-loader-circle bx-spin me-1"></i>
          Loading programs offered...
        </td>
      </tr>
    `;

    // Show modal
    const modal = new bootstrap.Modal(
      document.getElementById("programsOfferedModal")
    );
    modal.show();

    // AJAX fetch
    fetch("../backend/get_programs_offered.php")
      .then(response => response.json())
      .then(result => {

        if (result.status !== 'success') {
          tableBody.innerHTML = `
            <tr>
              <td colspan="6" class="text-center text-danger py-4">
                Failed to load programs.
              </td>
            </tr>
          `;
          return;
        }

        if (result.data.length === 0) {
          tableBody.innerHTML = `
            <tr>
              <td colspan="6" class="text-center text-muted py-4">
                No programs offered this term.
              </td>
            </tr>
          `;
          return;
        }

        // Build rows
        let rows = '';
        result.data.forEach(p => {
          rows += `
            <tr>
              <td><strong>${p.program_code}</strong></td>
              <td>${p.program_name}</td>
              <td>${p.major ?? '-'}</td>
              <td>${p.college_name}</td>
              <td>${p.campus_name}</td>
              <td class="text-center">
                <span class="badge bg-primary">${p.section_count}</span>
              </td>
            </tr>
          `;
        });

        tableBody.innerHTML = rows;
/* =====================================================
   DATATABLE-AWARE INITIALIZATION
===================================================== */

// Destroy old DataTable if exists
if (programsDataTable) {
  programsDataTable.destroy();
}

// Initialize DataTable AFTER rows are ready
programsDataTable = $('#programsOfferedModal table').DataTable({
  pageLength: 10,
  lengthChange: true,
  searching: true,
  ordering: true,
  responsive: true,
  dom: 'Bfrtip',
  buttons: [
    { extend: 'copy',  className: 'btn btn-sm btn-outline-secondary' },
    { extend: 'excel', className: 'btn btn-sm btn-outline-success' },
    { extend: 'pdf',   className: 'btn btn-sm btn-outline-danger' },
    { extend: 'print', className: 'btn btn-sm btn-outline-primary' }
  ]
});


      })
      .catch(() => {
        tableBody.innerHTML = `
          <tr>
            <td colspan="6" class="text-center text-danger py-4">
              AJAX error occurred.
            </td>
          </tr>
        `;
      });

  });

/* =====================================================
   ACTIVE SECTIONS KPI → MODAL → AJAX → DATATABLE
===================================================== */

let sectionsDataTable = null;

const sectionsKpi = document.querySelector('.kpi-icon.bg-success')?.closest('.kpi-box');

if (sectionsKpi) {

  sectionsKpi.addEventListener("click", function () {

    const tableBody = document.querySelector("#activeSectionsTable tbody");

    tableBody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center text-muted py-4">
          <i class="bx bx-loader-circle bx-spin me-1"></i>
          Loading active sections...
        </td>
      </tr>
    `;

    const modal = new bootstrap.Modal(
      document.getElementById("activeSectionsModal")
    );
    modal.show();

    fetch("../backend/get_active_sections.php")
      .then(res => res.json())
      .then(result => {

        if (result.status !== 'success') {
          tableBody.innerHTML = `
            <tr>
              <td colspan="5" class="text-center text-danger py-4">
                Failed to load active sections.
              </td>
            </tr>
          `;
          return;
        }

        if (result.data.length === 0) {
          tableBody.innerHTML = `
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                No active sections this term.
              </td>
            </tr>
          `;
          return;
        }

        let rows = '';
        result.data.forEach(s => {
          rows += `
            <tr>
              <td><strong>${s.section_name}</strong></td>
              <td>${s.program_code} – ${s.program_name}</td>
              <td>${s.college_name}</td>
              <td>${s.campus_name}</td>
      <td class="text-center">
        <span
          class="badge bg-success cursor-pointer offerings-badge"
          data-section-id="${s.section_id}"
          data-section-name="${s.section_name}"
        >
          ${s.offering_count}
        </span>
      </td>
            </tr>
          `;
        });

        tableBody.innerHTML = rows;

        // Datatable re-init
        if (sectionsDataTable) {
          sectionsDataTable.destroy();
        }

        sectionsDataTable = $('#activeSectionsTable').DataTable({
          pageLength: 10,
          searching: true,
          ordering: true,
          responsive: true,
          dom: 'Bfrtip',
          buttons: [
            { extend: 'copy',  className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'excel', className: 'btn btn-sm btn-outline-success' },
            { extend: 'pdf',   className: 'btn btn-sm btn-outline-danger' },
            { extend: 'print', className: 'btn btn-sm btn-outline-primary' }
          ]
        });

      })
      .catch(() => {
        tableBody.innerHTML = `
          <tr>
            <td colspan="5" class="text-center text-danger py-4">
              AJAX error occurred.
            </td>
          </tr>
        `;
      });

  });

}

/* =====================================================
   LEVEL 3 DRILL-DOWN: SECTION → SUBJECT OFFERINGS
   -----------------------------------------------------
   FIX:
   - Completely resets DataTable state
   - Prevents old data from persisting
   - Guarantees fresh data per section
===================================================== */

let sectionOfferingsDT = null;

$(document)
  .off("click", ".offerings-badge")
  .on("click", ".offerings-badge", function () {

    const sectionId   = this.dataset.sectionId;
    const sectionName = this.dataset.sectionName;

    $("#sectionTitle").text(sectionName);

    const table = $("#sectionOfferingsTable");
    const tbody = table.find("tbody");

    /* -----------------------------------------
       🔥 HARD RESET (THIS IS THE KEY)
    ----------------------------------------- */
    if ($.fn.DataTable.isDataTable(table)) {
      table.DataTable().clear().destroy();
    }

    tbody.empty(); // REMOVE old rows completely

    tbody.html(`
      <tr>
        <td colspan="3" class="text-center text-muted py-4">
          <i class="bx bx-loader-circle bx-spin me-1"></i>
          Loading subjects...
        </td>
      </tr>
    `);

    new bootstrap.Modal(
      document.getElementById("sectionOfferingsModal")
    ).show();

    const CURRENT_AY_ID = <?= (int)$currentAyId ?>;
    const CURRENT_SEM   = <?= (int)$currentSem ?>;

    fetch(
      `../backend/get_section_offerings.php?section_id=${sectionId}&ay_id=${CURRENT_AY_ID}&semester=${CURRENT_SEM}`,
      { cache: "no-store" } // 🔒 prevent browser caching
    )
      .then(res => res.json())
      .then(result => {

        if (result.status !== "success") {
          tbody.html(`
            <tr>
              <td colspan="3" class="text-center text-danger py-4">
                Failed to load subjects.
              </td>
            </tr>
          `);
          return;
        }

        if (!result.data || result.data.length === 0) {
          tbody.html(`
            <tr>
              <td colspan="3" class="text-center text-muted py-4">
                No subjects found.
              </td>
            </tr>
          `);
          return;
        }

        /* -----------------------------------------
           RENDER FRESH ROWS ONLY
        ----------------------------------------- */
        let rows = "";
        result.data.forEach(r => {
          rows += `
            <tr>
              <td><strong>${r.sub_code}</strong></td>
              <td>${r.sub_description}</td>
              <td class="text-center">${r.total_units}</td>
            </tr>
          `;
        });

        tbody.html(rows);

        /* -----------------------------------------
           RE-INIT DATATABLE (AFTER DATA IS IN DOM)
        ----------------------------------------- */
        sectionOfferingsDT = table.DataTable({
          destroy: true,
          pageLength: 10,
          searching: true,
          ordering: true,
          responsive: true,
          dom: "Bfrtip",
          buttons: ["copy", "excel", "pdf", "print"]
        });

      })
      .catch(err => {
        console.error("Section offerings error:", err);
        tbody.html(`
          <tr>
            <td colspan="3" class="text-center text-danger py-4">
              Failed to load subjects.
            </td>
          </tr>
        `);
      });

  });


/* =====================================================
   FACULTY WORKLOADS KPI → MODAL → AJAX
===================================================== */

let facultyWorkloadsDT = null;

document
  .getElementById("kpiFacultyWorkloads")
  .addEventListener("click", function () {

    const table = $("#facultyWorkloadsTable");
    const tbody = table.find("tbody");

    if ($.fn.DataTable.isDataTable(table)) {
      table.DataTable().clear().destroy();
    }

    tbody.html(`
      <tr>
        <td colspan="5" class="text-center text-muted py-4">
          <i class="bx bx-loader-circle bx-spin me-1"></i>
          Loading scheduled classes...
        </td>
      </tr>
    `);

    new bootstrap.Modal(
      document.getElementById("facultyWorkloadsModal")
    ).show();

    fetch(`../backend/get_faculty_workloads.php?ay_id=<?= (int)$currentAyId ?>&semester=<?= (int)$currentSem ?>`)
      .then(res => res.json())
      .then(result => {

        if (result.status !== "success") {
          tbody.html(`
            <tr>
              <td colspan="5" class="text-center text-danger py-4">
                Failed to load workloads.
              </td>
            </tr>
          `);
          return;
        }

let rows = "";
result.data.forEach(r => {

  const schedule = `${r.days_json} · ${r.time_start} – ${r.time_end}`;
  const room     = r.room_name ?? "TBA";
  const faculty  = r.faculty_name ?? "Unassigned";

  rows += `
    <tr>
      <td><strong>${r.sub_code}</strong></td>
      <td>${r.section_name}</td>
      <td>
        <strong>${r.program_code}</strong><br>
        <small class="text-muted">${r.program_name}</small>
      </td>
      <td>${r.college_name}</td>
      <td>${r.campus_name}</td>
      <td>${schedule}</td>
      <td>${room}</td>
      <td>${faculty}</td>
    </tr>
  `;
});

        tbody.html(rows);

        facultyWorkloadsDT = table.DataTable({
          pageLength: 10,
          ordering: true,
          order: [[2, 'asc']],
          dom: "Bfrtip",
          buttons: ["copy", "excel", "pdf", "print"]
        });

      });

  });


});
</script>



  </body>
</html>
