<?php
// Run: php tests/professor-workload.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/professor_workload_view_helper.php';
$checks = 0;
function workload_check(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($description);
    }
    $checks++;
}
$terms = [
    ['ay_id' => 99, 'academic_year_label' => '2024-2025', 'semester' => 1],
    ['ay_id' => 77, 'academic_year_label' => '2026-2027', 'semester' => 2],
    ['ay_id' => 77, 'academic_year_label' => '2026-2027', 'semester' => 1],
    ['ay_id' => 1, 'academic_year_label' => '2028-2029', 'semester' => 1],
    ['ay_id' => 1, 'academic_year_label' => '2028-2029', 'semester' => 2],
    ['ay_id' => 1, 'academic_year_label' => '2028-2029', 'semester' => 2],
];
$current = ['ay_id' => 77, 'semester' => 1];
$navigation = synk_professor_workload_navigation($terms, $current);
workload_check(array_keys($navigation['years']) === [1, 77, 99], 'Sort actual academic years newest first, independently of database IDs.');
workload_check($navigation['ay_id'] === 1 && $navigation['semester'] === 2, 'Default to the latest available year and latest semester when it is not the current year.');
workload_check($navigation['years'][1]['semesters'] === [1, 2], 'Semester filters are ordered and deduplicated.');
$currentSelection = synk_professor_workload_navigation($terms, $current, 77);
workload_check($currentSelection['semester'] === 1, 'Prefer the active semester when opening the current year.');
$explicit = synk_professor_workload_navigation($terms, $current, 77, 2);
workload_check($explicit['ay_id'] === 77 && $explicit['semester'] === 2, 'Honor an explicit year and semester from dashboard links.');
$all = synk_professor_workload_navigation($terms, $current, 77, 0);
workload_check($all['semester'] === 0 && $all['ay_id'] === 77, 'All semesters keeps the requested year.');
$stale = synk_professor_workload_navigation($terms, $current, 99, 2);
workload_check($stale['ay_id'] === 99 && $stale['semester'] === 0, 'A missing semester shows all available classes for the requested year.');
$invalidYear = synk_professor_workload_navigation($terms, $current, 12345);
workload_check($invalidYear['ay_id'] === 1, 'An unknown year falls back to the latest available year.');
$empty = synk_professor_workload_navigation([], $current);
workload_check($empty['selected_year'] === null && $empty['ay_id'] === 0 && $empty['semester'] === 0, 'Accounts without assignments have a safe empty selection.');
$growth = $terms;
$growth[] = ['ay_id' => 5, 'academic_year_label' => '2029-2030', 'semester' => 1];
$newest = synk_professor_workload_navigation($growth, $current);
workload_check($newest['latest_id'] === 5 && count($newest['years']) === 4, 'A new academic year automatically appears first without removing history.');
foreach ([null, [], ['1'], '-1', '1 OR 1=1', '1.2', '99999999999999'] as $malformed) {
    workload_check(synk_professor_workload_query_integer($malformed) === null, 'Malformed filter parameters are ignored safely.');
}
workload_check(synk_professor_workload_query_integer('0') === 0 && synk_professor_workload_query_integer('77') === 77, 'Parse all-semester and academic-year selections.');
$lab = synk_professor_meeting_details(['schedule_type' => 'LAB', 'days_json' => '["Th","T","T"]', 'time_start' => '07:30:00', 'time_end' => '09:00:00', 'room_name' => 'Lab 309']);
$lecture = synk_professor_meeting_details(['schedule_type' => 'LEC', 'days_json' => '["W","M"]', 'time_start' => '09:00:00', 'time_end' => '10:00:00', 'room_name' => 'Room 304']);
workload_check($lab['days'] === 'Tue, Thu' && $lab['type_label'] === 'Lab' && $lab['room'] === 'Lab 309', 'Keep lab days and their room together.');
workload_check($lecture['days'] === 'Mon, Wed' && $lecture['type_label'] === 'Lecture' && $lecture['room'] === 'Room 304', 'Keep lecture days and their room together.');
workload_check($lab['time'] === '7:30 AM - 9:00 AM' && $lecture['time'] === '9:00 AM - 10:00 AM', 'Format each meeting time independently.');
$missingMeeting = synk_professor_meeting_details(['days_json' => 'invalid']);
workload_check($missingMeeting['days'] === '' && $missingMeeting['time'] === '' && $missingMeeting['room'] === '', 'Missing schedule data stays empty for honest pending labels.');
echo 'Professor workload: ', $checks, ' checks passed.', PHP_EOL;
