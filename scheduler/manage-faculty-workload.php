<?php
session_start();
ob_start();
include '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scheduler') {
    header("Location: ../index.php");
    exit;
}

$collegeId = $_SESSION['college_id'] ?? null;

/* ===============================
   GET FILTERS
================================ */
$prospectus_id = $_GET['prospectus_id'] ?? '';
$ay_id         = $_GET['ay_id'] ?? '';
$semester      = $_GET['semester'] ?? '';
$doPrint       = isset($_GET['print']) && $_GET['print'] == '1';

/* ===============================
   HELPERS
================================ */
function semesterLabel($sem) {
    switch ((string)$sem) {
        case "1":
            return "FIRST SEMESTER";
        case "2":
            return "SECOND SEMESTER";
        case "3":
            return "MIDYEAR";
        default:
            return "SEMESTER";
    }
}


/* ===============================
   CAMPUS LABEL
================================ */
$campusLabel = "CAMPUS";
if ($collegeId) {
    $cstmt = $conn->prepare("
        SELECT tc.campus_name
        FROM tbl_college col
        INNER JOIN tbl_campus tc ON tc.campus_id = col.campus_id
        WHERE col.college_id = ?
        LIMIT 1
    ");
    $cstmt->bind_param("i", $collegeId);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    if ($cres->num_rows > 0) {
        $campusLabel = strtoupper($cres->fetch_assoc()['campus_name'])." CAMPUS";
    }
}

/* ===============================
   AY STRING (IMPORTANT FIX)
================================ */
$ayLabel = "";
if ($ay_id) {
    $ayStmt = $conn->prepare("
        SELECT ay
        FROM tbl_academic_years
        WHERE ay_id = ?
        LIMIT 1
    ");
    $ayStmt->bind_param("i", $ay_id);
    $ayStmt->execute();
    $ayRes = $ayStmt->get_result();
    if ($ayRes->num_rows > 0) {
        $ayLabel = $ayRes->fetch_assoc()['ay']; // e.g. 2024-2025
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
<meta charset="utf-8">
<title>Faculty Workload | Synk</title>

<link rel="stylesheet" href="../assets/vendor/css/core.css">
<link rel="stylesheet" href="../assets/vendor/css/theme-default.css">
<link rel="stylesheet" href="../assets/css/demo.css">

<style>
.report-card { border:1px solid #e6e9ef; border-radius:10px; }
.print-area { background:#fff; padding:18px; }

.print-header {
  display:flex; gap:14px; align-items:center;
  border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:14px;
}
.print-logos img { height:62px; }
.print-title { flex:1; text-align:center; }
.print-title .uni { font-weight:800; font-size:18px; }
.print-title .main { font-weight:900; margin-top:8px; }

.group-title {
  background:#eaf2fb; font-weight:800; text-transform:uppercase;
  border:1px solid #000; border-bottom:none; padding:6px 8px;
}

table.report-table {
  width:100%; border-collapse:collapse; font-size:12px;
}
table.report-table th, table.report-table td {
  border:1px solid #000; padding:6px 8px;
}
table.report-table th { background:#f7f7f7; text-align:center; }

.course-code { font-weight:800; width:140px; }
.course-desc { font-weight:700; }
.indent-row td { border-top:none; }

@media print {
  .no-print { display:none !important; }
  @page { size:A4 landscape; margin:10mm; }
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
<div class="container-xxl container-p-y">

<div class="no-print">
<h4 class="fw-bold mb-2">Faculty Workload (Read-Only Report)</h4>

<div class="card report-card mb-4">
<div class="card-body">
<form method="GET" class="row g-3 align-items-end">

<div class="col-md-5">
<label class="form-label">Prospectus</label>
<select name="prospectus_id" class="form-select" required>
<option value="">Select...</option>
<?php
$q = $conn->query("
    SELECT h.prospectus_id, p.program_code, p.program_name, h.effective_sy
    FROM tbl_prospectus_header h
    JOIN tbl_program p ON p.program_id = h.program_id
    WHERE p.college_id = {$collegeId}
");
while ($r = $q->fetch_assoc()) {
    $sel = ($prospectus_id == $r['prospectus_id']) ? "selected" : "";
    echo "<option value='{$r['prospectus_id']}' $sel>
          {$r['program_code']} — {$r['program_name']} (SY {$r['effective_sy']})
          </option>";
}
?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Academic Year</label>
<select name="ay_id" class="form-select" required>
<option value="">Select...</option>
<?php
$ayQ = $conn->query("SELECT ay_id, ay FROM tbl_academic_years WHERE status='active'");
while ($ay = $ayQ->fetch_assoc()) {
    $sel = ($ay_id == $ay['ay_id']) ? "selected" : "";
    echo "<option value='{$ay['ay_id']}' $sel>{$ay['ay']}</option>";
}
?>
</select>
</div>

<div class="col-md-2">
<label class="form-label">Semester</label>
<select name="semester" class="form-select" required>
<option value="">Select...</option>
<option value="1" <?=($semester=="1"?"selected":"")?>>First</option>
<option value="2" <?=($semester=="2"?"selected":"")?>>Second</option>
<option value="3" <?=($semester=="3"?"selected":"")?>>Midyear</option>
</select>
</div>

<div class="col-md-2 d-grid">
<button class="btn btn-primary">Generate</button>
<?php if ($prospectus_id && $ay_id && $semester): ?>
<a class="btn btn-outline-primary"
   href="?prospectus_id=<?=$prospectus_id?>&ay_id=<?=$ay_id?>&semester=<?=$semester?>&print=1"
   target="_blank">Print View</a>
<?php endif; ?>
</div>

</form>
</div>
</div>
</div>

<?php
if ($prospectus_id && $ay_id && $semester):

$sql = "
SELECT
  sm.sub_code,
  sm.sub_description,
  sec.section_name,
  cs.days_json,
  cs.time_start,
  cs.time_end,
  r.room_name
FROM tbl_prospectus_offering o
JOIN tbl_sections sec ON sec.section_id = o.section_id
JOIN tbl_prospectus_subjects ps ON ps.ps_id = o.ps_id
JOIN tbl_subject_masterlist sm ON sm.sub_id = ps.sub_id
LEFT JOIN tbl_class_schedule cs ON cs.offering_id = o.offering_id
LEFT JOIN tbl_rooms r ON r.room_id = cs.room_id
WHERE o.prospectus_id = ?
AND o.ay_id = ?
AND o.semester = ?
ORDER BY sm.sub_code, sec.section_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $prospectus_id, $ay_id, $semester);
$stmt->execute();
$res = $stmt->get_result();

$courses = [];
while ($row = $res->fetch_assoc()) {
    $courses[$row['sub_code']]['desc'] = $row['sub_description'];
    $courses[$row['sub_code']]['rows'][] = $row;
}
?>

<div class="print-area">
<div class="print-header">
<div class="print-title">
<div class="uni">SULTAN KUDARAT STATE UNIVERSITY</div>
<div class="main">ALPHABETICAL LIST OF COURSES</div>
<div><?= $campusLabel ?></div>
<div><?= semesterLabel($semester) ?>, AY <?= $ayLabel ?></div>
</div>
</div>

<div class="group-title">PROGRAM OFFERINGS</div>

<table class="report-table">
<thead>
<tr>
<th>Course Code</th>
<th>Section</th>
<th>Class Schedule</th>
<th>Room</th>
</tr>
</thead>
<tbody>
<?php foreach ($courses as $code=>$data): ?>
<tr>
<td class="course-code"><?=$code?></td>
<td colspan="3" class="course-desc"><?=$data['desc']?></td>
</tr>
<?php foreach ($data['rows'] as $x):
$days = $x['days_json'] ? implode("", json_decode($x['days_json'],true)) : "—";
$time = ($x['time_start']&&$x['time_end']) ? date("h:iA",strtotime($x['time_start']))."-".date("h:iA",strtotime($x['time_end'])) : "—";
?>
<tr class="indent-row">
<td></td>
<td><?=$x['section_name']?></td>
<td><?=$days?> <?=$time?></td>
<td><?=$x['room_name']??"—"?></td>
</tr>
<?php endforeach; endforeach; ?>
</tbody>
</table>
</div>

<?php if ($doPrint): ?><script>window.print();</script><?php endif; endif; ?>

</div>
</div>
</div>
</div>
</body>
</html>
