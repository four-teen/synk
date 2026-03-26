<?php
require_once __DIR__ . '/bootstrap.php';

function student_management_directory_api_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if ($studentManagementPageError !== '') {
    student_management_directory_api_response([
        'status' => 'error',
        'message' => $studentManagementPageError,
    ], 500);
}

try {
    $action = (string)($_GET['action'] ?? '');

    if ($action === 'list') {
        $filters = [
            'search' => trim((string)($_GET['search'] ?? '')),
            'year_level' => max(0, (int)($_GET['year_level'] ?? 0)),
            'source_program_name' => trim((string)($_GET['source_program_name'] ?? '')),
            'academic_year_label' => trim((string)($_GET['academic_year_label'] ?? '')),
            'semester_label' => trim((string)($_GET['semester_label'] ?? '')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 40;
        $offset = ($page - 1) * $limit;

        $total = synk_student_management_count_records($conn, $filters);
        $records = synk_student_management_fetch_records($conn, $filters, $limit, $offset);

        $items = array_map(static function ($row) {
            return [
                'student_id' => (int)($row['student_id'] ?? 0),
                'student_number' => (int)($row['student_number'] ?? 0),
                'last_name' => (string)($row['last_name'] ?? ''),
                'first_name' => (string)($row['first_name'] ?? ''),
                'middle_name' => (string)($row['middle_name'] ?? ''),
                'suffix_name' => (string)($row['suffix_name'] ?? ''),
                'email_address' => (string)($row['email_address'] ?? ''),
            ];
        }, $records);

        student_management_directory_api_response([
            'status' => 'success',
            'items' => $items,
            'total' => $total,
            'has_more' => ($offset + count($items)) < $total,
        ]);
    }

    if ($action === 'get') {
        $studentId = (int)($_GET['student_id'] ?? 0);
        $record = synk_student_management_fetch_record_by_id($conn, $studentId);
        if (!$record) {
            throw new RuntimeException('Student record not found.');
        }

        student_management_directory_api_response([
            'status' => 'success',
            'record' => $record,
        ]);
    }

    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new RuntimeException('Invalid save request.');
        }

        $studentId = (int)($_POST['student_id'] ?? 0);
        $payload = [
            'academic_year_label' => (string)($_POST['academic_year_label'] ?? ''),
            'semester_label' => (string)($_POST['semester_label'] ?? ''),
            'college_name' => (string)($_POST['college_name'] ?? ''),
            'campus_name' => (string)($_POST['campus_name'] ?? ''),
            'source_program_name' => (string)($_POST['source_program_name'] ?? ''),
            'year_level' => (int)($_POST['year_level'] ?? 0),
            'student_number' => (int)($_POST['student_number'] ?? 0),
            'last_name' => (string)($_POST['last_name'] ?? ''),
            'first_name' => (string)($_POST['first_name'] ?? ''),
            'middle_name' => (string)($_POST['middle_name'] ?? ''),
            'suffix_name' => (string)($_POST['suffix_name'] ?? ''),
            'email_address' => (string)($_POST['email_address'] ?? ''),
            'program_id' => (int)($_POST['program_id'] ?? 0),
        ];

        if ($studentId > 0) {
            synk_student_management_update_record($conn, $studentId, $payload);
            $message = 'Student record updated.';
        } else {
            $studentId = synk_student_management_create_record(
                $conn,
                $payload,
                (int)($_SESSION['user_id'] ?? 0)
            );
            $message = 'Student record added.';
        }

        $record = synk_student_management_fetch_record_by_id($conn, $studentId);

        student_management_directory_api_response([
            'status' => 'success',
            'message' => $message,
            'record' => $record,
        ]);
    }

    if ($action === 'delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new RuntimeException('Invalid delete request.');
        }

        $studentId = (int)($_POST['student_id'] ?? 0);
        synk_student_management_delete_record($conn, $studentId);

        student_management_directory_api_response([
            'status' => 'success',
            'message' => 'Student record deleted.',
        ]);
    }

    throw new RuntimeException('Unsupported student management action.');
} catch (Throwable $e) {
    student_management_directory_api_response([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], 400);
}
