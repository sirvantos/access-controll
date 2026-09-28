<?php

declare(strict_types=1);

use Carbon\Carbon;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function defaultWorkingDaySettingsPayload(array $overrides = []): array
{
    return array_replace([
        'start_time' => '09:00',
        'end_time' => '18:00',
        'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'break_duration_minutes' => 60,
        'break_deducted' => true,
        'lateness_grace_minutes' => 0,
    ], $overrides);
}

function sampleCompanyBin(): string
{
    return '123456789012';
}

function sampleCompanyPhone(): string
{
    return '+7 (700) 123-45-67';
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function companyCreateOptionalDetails(array $overrides = []): array
{
    return array_replace([
        'bin' => sampleCompanyBin(),
        'contact_person' => 'Acme Contact',
        'phone' => sampleCompanyPhone(),
        'email' => 'office@acme.test',
    ], $overrides);
}

/**
 * @param  iterable<int, object>  $rows
 * @return list<array{id: int, applies_from: string|null, time_zone: string|null}>
 */
function companyVersionSnapshots(iterable $rows): array
{
    $snapshots = [];

    foreach ($rows as $row) {
        $appliesFrom = $row->applies_from ?? null;
        $timeZone = $row->time_zone ?? null;

        $snapshots[] = [
            'id' => $row->id,
            'applies_from' => $appliesFrom instanceof DateTimeInterface
                ? Carbon::parse($appliesFrom)->utc()->toIso8601String()
                : null,
            'time_zone' => is_string($timeZone) ? $timeZone : null,
        ];
    }

    return $snapshots;
}
