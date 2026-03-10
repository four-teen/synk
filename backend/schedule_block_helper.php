<?php

if (!defined('SYNK_LAB_LOAD_MULTIPLIER')) {
    define('SYNK_LAB_LOAD_MULTIPLIER', 0.75);
}

if (!defined('SYNK_LAB_CONTACT_HOURS_PER_UNIT')) {
    define('SYNK_LAB_CONTACT_HOURS_PER_UNIT', 3.0);
}

function synk_normalize_schedule_type(string $value): string
{
    $type = strtoupper(trim($value));
    return $type === 'LAB' ? 'LAB' : 'LEC';
}

function synk_normalize_schedule_days($days): array
{
    if (!is_array($days)) {
        return [];
    }

    $order = ['M', 'T', 'W', 'Th', 'F', 'S'];
    $seen = [];

    foreach ($days as $day) {
        $token = trim((string)$day);
        if ($token === 'TH') {
            $token = 'Th';
        }

        if ($token !== '' && in_array($token, $order, true)) {
            $seen[$token] = true;
        }
    }

    $result = [];
    foreach ($order as $token) {
        if (isset($seen[$token])) {
            $result[] = $token;
        }
    }

    return $result;
}

function synk_schedule_minutes_between(?string $timeStart, ?string $timeEnd): int
{
    $timeStart = trim((string)$timeStart);
    $timeEnd = trim((string)$timeEnd);

    if ($timeStart === '' || $timeEnd === '' || $timeEnd <= $timeStart) {
        return 0;
    }

    $start = strtotime('1970-01-01 ' . $timeStart);
    $end = strtotime('1970-01-01 ' . $timeEnd);
    if ($start === false || $end === false || $end <= $start) {
        return 0;
    }

    return (int)round(($end - $start) / 60);
}

function synk_schedule_weekly_minutes($days, ?string $timeStart, ?string $timeEnd): int
{
    $normalizedDays = synk_normalize_schedule_days($days);
    if (empty($normalizedDays)) {
        return 0;
    }

    return count($normalizedDays) * synk_schedule_minutes_between($timeStart, $timeEnd);
}

function synk_lab_is_credit(float $lecUnits, float $labValue, float $totalUnits): bool
{
    return $labValue > 0 && abs(($lecUnits + $labValue) - $totalUnits) < 0.0001;
}

function synk_lab_contact_hours(float $lecUnits, float $labValue, float $totalUnits): float
{
    if ($labValue <= 0) {
        return 0.0;
    }

    return synk_lab_is_credit($lecUnits, $labValue, $totalUnits)
        ? ($labValue * SYNK_LAB_CONTACT_HOURS_PER_UNIT)
        : $labValue;
}

function synk_required_minutes_by_type(float $lecUnits, float $labValue, float $totalUnits): array
{
    return [
        'LEC' => max(0, (int)round($lecUnits * 60)),
        'LAB' => max(0, (int)round(synk_lab_contact_hours($lecUnits, $labValue, $totalUnits) * 60))
    ];
}

function synk_subject_units_total(float $lecUnits, float $labValue, float $totalUnits): float
{
    if ($totalUnits > 0) {
        return $totalUnits;
    }

    return $lecUnits + (synk_lab_is_credit($lecUnits, $labValue, $totalUnits) ? $labValue : 0);
}

function synk_schedule_component_totals(float $lecUnits, float $labValue, float $totalUnits): array
{
    $labIsCredit = synk_lab_is_credit($lecUnits, $labValue, $totalUnits);
    $labHours = synk_lab_contact_hours($lecUnits, $labValue, $totalUnits);

    return [
        'LEC' => [
            'units' => $lecUnits,
            'hours_lec' => $lecUnits,
            'hours_lab' => 0.0,
            'faculty_load' => $lecUnits
        ],
        'LAB' => [
            'units' => $labIsCredit ? $labValue : 0.0,
            'hours_lec' => 0.0,
            'hours_lab' => $labHours,
            'faculty_load' => $labHours * SYNK_LAB_LOAD_MULTIPLIER
        ]
    ];
}

function synk_schedule_block_metrics(
    string $scheduleType,
    int $weeklyMinutes,
    float $lecUnits,
    float $labValue,
    float $totalUnits
): array {
    $scheduleType = synk_normalize_schedule_type($scheduleType);
    $requiredMinutes = synk_required_minutes_by_type($lecUnits, $labValue, $totalUnits);
    $componentTotals = synk_schedule_component_totals($lecUnits, $labValue, $totalUnits);
    $typeRequiredMinutes = max(0, (int)($requiredMinutes[$scheduleType] ?? 0));
    $ratio = $typeRequiredMinutes > 0 ? ($weeklyMinutes / $typeRequiredMinutes) : 0.0;
    $component = $componentTotals[$scheduleType] ?? [
        'units' => 0.0,
        'hours_lec' => 0.0,
        'hours_lab' => 0.0,
        'faculty_load' => 0.0
    ];

    return [
        'units' => round(((float)$component['units']) * $ratio, 2),
        'hours_lec' => round(((float)$component['hours_lec']) * $ratio, 2),
        'hours_lab' => round(((float)$component['hours_lab']) * $ratio, 2),
        'faculty_load' => round(((float)$component['faculty_load']) * $ratio, 2),
        'weekly_minutes' => $weeklyMinutes,
        'required_minutes' => $typeRequiredMinutes,
        'coverage_ratio' => $typeRequiredMinutes > 0 ? round($weeklyMinutes / $typeRequiredMinutes, 4) : 0.0
    ];
}

function synk_schedule_block_metrics_from_row(array $row): array
{
    $days = [];

    if (isset($row['days']) && is_array($row['days'])) {
        $days = $row['days'];
    } elseif (isset($row['days_json'])) {
        $decoded = json_decode((string)$row['days_json'], true);
        $days = is_array($decoded) ? $decoded : [];
    }

    $weeklyMinutes = synk_schedule_weekly_minutes(
        $days,
        (string)($row['time_start'] ?? ''),
        (string)($row['time_end'] ?? '')
    );

    return synk_schedule_block_metrics(
        (string)($row['schedule_type'] ?? 'LEC'),
        $weeklyMinutes,
        (float)($row['lec_units'] ?? 0),
        (float)($row['lab_units'] ?? 0),
        (float)($row['total_units'] ?? 0)
    );
}

function synk_sum_scheduled_minutes_by_type(array $rows): array
{
    $totals = ['LEC' => 0, 'LAB' => 0];

    foreach ($rows as $row) {
        $type = synk_normalize_schedule_type((string)($row['schedule_type'] ?? 'LEC'));
        $days = [];

        if (isset($row['days']) && is_array($row['days'])) {
            $days = $row['days'];
        } elseif (isset($row['days_json'])) {
            $decoded = json_decode((string)$row['days_json'], true);
            $days = is_array($decoded) ? $decoded : [];
        }

        $totals[$type] += synk_schedule_weekly_minutes(
            $days,
            (string)($row['time_start'] ?? ''),
            (string)($row['time_end'] ?? '')
        );
    }

    return $totals;
}

