<?php
/**
 * ============================================================================
 * File Path : synk/backend/query_generate_offerings.php
 * Page Name : Generate Prospectus Offerings (AJAX)
 * ============================================================================
 * PURPOSE:
 * - Incrementally sync offerings for a selected prospectus + AY + semester
 * - Add offerings for active sections (scoped by ay_id + semester)
 * - Remove offerings only for inactive / removed sections (excluding locked)
 * - Preserve locked offerings and their schedules
 * ============================================================================
 */
session_start();
header('Content-Type: application/json');
include 'db.php';

function get_offering_context($conn, $prospectus_id, $ay_id, $semester, $college_id) {
    $ctx = [
        "program_id" => 0,
        "program_code" => "",
        "program_name" => "",
        "ay_label" => "",
        "subjectsByYear" => [],
        "sectionsByYear" => [],
        "summary" => [
            "total_subject_rows" => 0,
            "total_active_sections" => 0,
            "potential_offerings" => 0
        ],
        "blockers" => [],
        "warnings" => []
    ];

    $ayStmt = $conn->prepare("SELECT ay FROM tbl_academic_years WHERE ay_id = ? LIMIT 1");
    $ayStmt->bind_param("i", $ay_id);
    $ayStmt->execute();
    $ctx["ay_label"] = $ayStmt->get_result()->fetch_assoc()['ay'] ?? "";
    $ayStmt->close();

    if ($ctx["ay_label"] === "") {
        $ctx["blockers"][] = "Selected academic year was not found.";
        return $ctx;
    }

    $progStmt = $conn->prepare("
        SELECT h.program_id, p.program_code, p.program_name
        FROM tbl_prospectus_header h
        INNER JOIN tbl_program p ON p.program_id = h.program_id
        WHERE h.prospectus_id = ?
          AND p.college_id = ?
        LIMIT 1
    ");
    $progStmt->bind_param("ii", $prospectus_id, $college_id);
    $progStmt->execute();
    $prog = $progStmt->get_result()->fetch_assoc();
    $progStmt->close();

    if (!$prog) {
        $ctx["blockers"][] = "Prospectus not found or not allowed for your college.";
        return $ctx;
    }

    $ctx["program_id"] = (int)$prog['program_id'];
    $ctx["program_code"] = (string)$prog['program_code'];
    $ctx["program_name"] = (string)$prog['program_name'];

    $sub = $conn->prepare("
        SELECT ps.ps_id, pys.year_level
        FROM tbl_prospectus_year_sem pys
        JOIN tbl_prospectus_subjects ps ON ps.pys_id = pys.pys_id
        WHERE pys.prospectus_id = ?
          AND pys.semester = ?
    ");
    $sub->bind_param("ii", $prospectus_id, $semester);
    $sub->execute();
    $subjectsRes = $sub->get_result();
    while ($s = $subjectsRes->fetch_assoc()) {
        $yearLevel = (int)$s['year_level'];
        $ctx["subjectsByYear"][$yearLevel][] = (int)$s['ps_id'];
        $ctx["summary"]["total_subject_rows"]++;
    }
    $sub->close();

    if (empty($ctx["subjectsByYear"])) {
        $ctx["blockers"][] = "No prospectus subjects found for the selected semester.";
        return $ctx;
    }

    $sec = $conn->prepare("
        SELECT section_id, year_level
        FROM tbl_sections
        WHERE program_id = ?
          AND ay_id = ?
          AND semester = ?
          AND status = 'active'
    ");
    $sec->bind_param("iii", $ctx["program_id"], $ay_id, $semester);
    $sec->execute();
    $sectionsRes = $sec->get_result();
    while ($row = $sectionsRes->fetch_assoc()) {
        $yearLevel = (int)$row['year_level'];
        $sectionId = (int)$row['section_id'];
        if ($sectionId <= 0) {
            continue;
        }
        $ctx["sectionsByYear"][$yearLevel][] = $sectionId;
        $ctx["summary"]["total_active_sections"]++;
    }
    $sec->close();

    if (empty($ctx["sectionsByYear"])) {
        $ctx["warnings"][] = "No active sections found for selected AY and semester. Generation will run in cleanup-only mode.";
    }

    foreach ($ctx["subjectsByYear"] as $yearLevel => $psIds) {
        $subjectCount = count($psIds);
        $sectionCount = count($ctx["sectionsByYear"][$yearLevel] ?? []);

        if ($sectionCount === 0) {
            $ctx["warnings"][] = "Year level {$yearLevel} has prospectus subjects but no active sections.";
            continue;
        }

        $ctx["summary"]["potential_offerings"] += ($subjectCount * $sectionCount);
    }

    foreach ($ctx["sectionsByYear"] as $yearLevel => $sectionIds) {
        if (empty($ctx["subjectsByYear"][$yearLevel])) {
            $ctx["warnings"][] = "Year level {$yearLevel} has active sections but no subjects in selected semester.";
        }
    }

    return $ctx;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'scheduler') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

$college_id = intval($_SESSION['college_id'] ?? 0);
if ($college_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing college context"]);
    exit;
}

if (!isset($_POST['generate_offerings']) && !isset($_POST['validate_offerings_context'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(["status" => "error", "message" => "CSRF validation failed"]);
    exit;
}

$prospectus_id = intval($_POST['prospectus_id'] ?? 0);
$ay_id         = intval($_POST['ay_id'] ?? 0);
$semester      = intval($_POST['semester'] ?? 0);

if (!$prospectus_id || !$ay_id || !$semester) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

if (!in_array($semester, [1, 2, 3], true)) {
    echo json_encode(["status" => "error", "message" => "Invalid semester value"]);
    exit;
}

$context = get_offering_context($conn, $prospectus_id, $ay_id, $semester, $college_id);

if (isset($_POST['validate_offerings_context'])) {
    echo json_encode([
        "status" => empty($context["blockers"]) ? "ok" : "error",
        "can_generate" => empty($context["blockers"]),
        "message" => empty($context["blockers"])
            ? "Validation passed."
            : "Validation failed. Please fix required data before generating.",
        "program_code" => $context["program_code"],
        "program_name" => $context["program_name"],
        "ay_label" => $context["ay_label"],
        "semester" => $semester,
        "summary" => $context["summary"],
        "blockers" => $context["blockers"],
        "warnings" => $context["warnings"]
    ]);
    exit;
}

if (!empty($context["blockers"])) {
    echo json_encode([
        "status" => "error",
        "message" => implode(" ", $context["blockers"]),
        "blockers" => $context["blockers"]
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $program_id = (int)$context["program_id"];
    $subjectsByYear = $context["subjectsByYear"];
    $sectionsByYear = $context["sectionsByYear"];

    /* ============================
       LOAD EXISTING OFFERINGS (ONE QUERY)
    ============================ */
    $existsStmt = $conn->prepare("\n        SELECT ps_id, section_id\n        FROM tbl_prospectus_offering\n        WHERE prospectus_id = ?\n          AND ay_id = ?\n          AND semester = ?\n    ");
    $existsStmt->bind_param("iii", $prospectus_id, $ay_id, $semester);
    $existsStmt->execute();
    $existingRes = $existsStmt->get_result();

    $existingMap = [];
    while ($row = $existingRes->fetch_assoc()) {
        $existingSectionId = (int)$row['section_id'];
        if ($existingSectionId <= 0) {
            continue;
        }
        $existingMap[(int)$row['ps_id'] . '|' . $existingSectionId] = true;
    }
    $existsStmt->close();

    /* ============================
       PREPARE INSERT
    ============================ */
    $ins = $conn->prepare("\n        INSERT INTO tbl_prospectus_offering\n        (program_id, prospectus_id, ps_id, year_level, semester, ay_id, section_id, status, date_created)\n        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())\n    ");

    $inserted = 0;

    /* ============================
       ADD MISSING OFFERINGS
    ============================ */
    foreach ($subjectsByYear as $yearLevel => $psIds) {

        if (empty($sectionsByYear[$yearLevel])) {
            continue;
        }

        foreach ($psIds as $psId) {
            foreach ($sectionsByYear[$yearLevel] as $sectionId) {
                if ((int)$sectionId <= 0) {
                    continue;
                }
                $key = $psId . '|' . $sectionId;
                if (isset($existingMap[$key])) {
                    continue;
                }

                $ins->bind_param(
                    "iiiiiii",
                    $program_id,
                    $prospectus_id,
                    $psId,
                    $yearLevel,
                    $semester,
                    $ay_id,
                    $sectionId
                );
                $ins->execute();

                $existingMap[$key] = true;
                $inserted++;
            }
        }
    }

    $ins->close();

    /* ============================
       CLEAN UP INACTIVE SECTIONS
    ============================ */

    $old = $conn->prepare("\n        SELECT o.offering_id, s.section_id AS live_section_id\n        FROM tbl_prospectus_offering o\n        LEFT JOIN tbl_sections s ON s.section_id = o.section_id\n        WHERE o.prospectus_id = ?\n          AND o.ay_id = ?\n          AND o.semester = ?\n          AND (o.status IS NULL OR o.status != 'locked')\n          AND (\n                s.section_id IS NULL\n                OR s.status = 'inactive'\n                OR s.ay_id <> o.ay_id\n                OR s.semester <> o.semester\n              )\n    ");

    $old->bind_param("iii", $prospectus_id, $ay_id, $semester);
    $old->execute();
    $oldRes = $old->get_result();

    $deleted_offerings = 0;
    $deleted_schedules = 0;
    $protected_scheduled = 0;

    if ($oldRes->num_rows > 0) {

        $delSched = $conn->prepare("\n            DELETE FROM tbl_class_schedule\n            WHERE offering_id = ?\n        ");
        $delOff = $conn->prepare("\n            DELETE FROM tbl_prospectus_offering\n            WHERE offering_id = ?\n        ");

        while ($row = $oldRes->fetch_assoc()) {

            $oid = (int)$row['offering_id'];
            $hasLiveSection = ((int)($row['live_section_id'] ?? 0) > 0);
            if ($oid <= 0) {
                continue;
            }

            $hasSchedStmt = $conn->prepare("\n                SELECT 1\n                FROM tbl_class_schedule\n                WHERE offering_id = ?\n                LIMIT 1\n            ");
            $hasSchedStmt->bind_param("i", $oid);
            $hasSchedStmt->execute();
            $hasSched = $hasSchedStmt->get_result()->num_rows > 0;
            $hasSchedStmt->close();

            // New rule: preserve scheduled offerings only when section record still exists.
            if ($hasSched && $hasLiveSection) {
                $protected_scheduled++;
                continue;
            }

            $delSched->bind_param("i", $oid);
            $delSched->execute();
            $deleted_schedules += $delSched->affected_rows;

            $delOff->bind_param("i", $oid);
            $delOff->execute();
            $deleted_offerings++;
        }

        $delSched->close();
        $delOff->close();
    }

    $old->close();

    /* ============================
       STATUS SYNC (PENDING <-> SCHEDULED)
    ============================ */
    $toScheduled = $conn->prepare("
        UPDATE tbl_prospectus_offering o
        SET o.status = 'scheduled'
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND (o.status IS NULL OR o.status != 'locked')
          AND EXISTS (
                SELECT 1
                FROM tbl_class_schedule cs
                WHERE cs.offering_id = o.offering_id
          )
    ");
    $toScheduled->bind_param("iii", $prospectus_id, $ay_id, $semester);
    $toScheduled->execute();
    $updated_scheduled = $toScheduled->affected_rows;
    $toScheduled->close();

    $toPending = $conn->prepare("
        UPDATE tbl_prospectus_offering o
        SET o.status = 'pending'
        WHERE o.prospectus_id = ?
          AND o.ay_id = ?
          AND o.semester = ?
          AND o.status = 'scheduled'
          AND NOT EXISTS (
                SELECT 1
                FROM tbl_class_schedule cs
                WHERE cs.offering_id = o.offering_id
          )
    ");
    $toPending->bind_param("iii", $prospectus_id, $ay_id, $semester);
    $toPending->execute();
    $updated_pending = $toPending->affected_rows;
    $toPending->close();

    $conn->commit();

    echo json_encode([
        "status"             => "ok",
        "inserted"           => $inserted,
        "deleted_offerings"  => $deleted_offerings,
        "deleted_schedules"  => $deleted_schedules,
        "protected_scheduled" => $protected_scheduled,
        "updated_scheduled"  => $updated_scheduled,
        "updated_pending"    => $updated_pending
    ]);

} catch (Throwable $e) {
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    error_log("query_generate_offerings.php error: " . $e->getMessage());
    echo json_encode([
        "status"  => "error",
        "message" => "Failed to generate offerings. Please contact administrator if this persists."
    ]);
}
