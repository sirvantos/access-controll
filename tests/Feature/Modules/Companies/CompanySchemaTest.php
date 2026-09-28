<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('backfills a name-only company with schedule versions and a normalized name', function () {
    $createdAt = '2026-01-15 10:00:00';

    $companyId = DB::table('companies')->insertGetId([
        'name' => 'Ёлка',
        'deactivated_at' => null,
        'bin' => null,
        'contact_person' => null,
        'phone' => null,
        'email' => null,
        'name_normalized' => 'placeholder',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $migration = require database_path('migrations/2026_09_28_171619_add_company_details_and_schedule_versions.php');
    $migration->backfillExistingCompanies();

    $company = DB::table('companies')->where('id', $companyId)->first();

    expect($company)->not->toBeNull()
        ->and($company->name)->toBe('Ёлка')
        ->and($company->name_normalized)->toBe('ёлка')
        ->and($company->bin)->toBeNull()
        ->and($company->contact_person)->toBeNull()
        ->and($company->phone)->toBeNull()
        ->and($company->email)->toBeNull();

    $timeZone = DB::table('company_time_zone_versions')->where('company_id', $companyId)->first();
    $settings = DB::table('company_working_day_setting_versions')->where('company_id', $companyId)->first();
    $companyIndexes = collect(Schema::getIndexes('companies'));
    $timeZoneIndexes = collect(Schema::getIndexes('company_time_zone_versions'));
    $settingsIndexes = collect(Schema::getIndexes('company_working_day_setting_versions'));

    expect(DB::table('company_time_zone_versions')->where('company_id', $companyId)->count())->toBe(1)
        ->and($timeZone)->not->toBeNull()
        ->and($timeZone->time_zone)->toBe('Asia/Almaty')
        ->and($timeZone->applies_from)->toBe('2026-01-14 19:00:00')
        ->and(Schema::hasColumn('company_time_zone_versions', 'updated_at'))->toBeFalse()
        ->and(DB::table('company_working_day_setting_versions')->where('company_id', $companyId)->count())->toBe(1)
        ->and($settings)->not->toBeNull()
        ->and($settings->start_time)->toBe('09:00')
        ->and($settings->end_time)->toBe('18:00')
        ->and(json_decode((string) $settings->working_days, true, 512, JSON_THROW_ON_ERROR))->toBe([
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
        ])
        ->and((int) $settings->break_duration_minutes)->toBe(60)
        ->and((bool) $settings->break_deducted)->toBeTrue()
        ->and((int) $settings->lateness_grace_minutes)->toBe(0)
        ->and($settings->applies_from)->toBe('2026-01-14 19:00:00')
        ->and(Schema::hasColumn('company_working_day_setting_versions', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumns('companies', ['bin', 'contact_person', 'phone', 'email', 'name_normalized']))->toBeTrue()
        ->and($companyIndexes->contains(fn (array $index): bool => $index['columns'] === ['name_normalized']))->toBeTrue()
        ->and($companyIndexes->contains(fn (array $index): bool => $index['columns'] === ['bin']))->toBeTrue()
        ->and($timeZoneIndexes->contains(
            fn (array $index): bool => $index['columns'] === ['company_id', 'applies_from', 'id'],
        ))->toBeTrue()
        ->and($settingsIndexes->contains(
            fn (array $index): bool => $index['columns'] === ['company_id', 'applies_from', 'id'],
        ))->toBeTrue();
});
