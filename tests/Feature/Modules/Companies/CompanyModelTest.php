<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyTimeZoneVersion;
use App\Modules\Companies\Models\CompanyWorkingDaySettingVersion;
use App\Modules\Companies\PublicApi\WeekDay;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

it('casts every companies column', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
        'deactivated_at' => '2026-01-15 12:00:00',
    ]);

    expect($company->getCasts())->toMatchArray([
        'id' => 'integer',
        'name' => 'string',
        'bin' => 'string',
        'contact_person' => 'string',
        'phone' => 'string',
        'email' => 'string',
        'name_normalized' => 'string',
        'deactivated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ])
        ->and($company->id)->toBeInt()
        ->and($company->name)->toBe('Acme')
        ->and($company->deactivated_at)->toEqual(Carbon::parse('2026-01-15 12:00:00'))
        ->and($company->created_at)->toBeInstanceOf(Carbon::class)
        ->and($company->updated_at)->toBeInstanceOf(Carbon::class);
});

it('is active only while deactivated_at is null', function () {
    $active = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $deactivated = Company::factory()->create([
        'name' => 'Acme',
        'deactivated_at' => '2026-01-15 12:00:00',
    ]);

    expect($active->isActive())->toBeTrue()
        ->and($active->deactivated_at)->toBeNull()
        ->and($deactivated->isActive())->toBeFalse();
});

it('builds an active Acme company by default and a deactivated one from the factory state', function () {
    $active = Company::factory()->create();
    $deactivated = Company::factory()->deactivated()->create();

    expect($active->name)->toBe('Acme')
        ->and($active->deactivated_at)->toBeNull()
        ->and($active->isActive())->toBeTrue()
        ->and($deactivated->name)->toBe('Acme')
        ->and($deactivated->deactivated_at)->not->toBeNull()
        ->and($deactivated->isActive())->toBeFalse();
});

it('stores a normalized name and one default version in each schedule table', function () {
    $company = Company::factory()->create([
        'name' => 'Ёлка',
    ]);

    withCompanyContext($company->id, function () use ($company): void {
        $timeZone = $company->timeZoneVersions()->first();
        $settings = $company->workingDaySettingVersions()->first();

        expect($company->name_normalized)->toBe('ёлка')
            ->and($company->bin)->toBeNull()
            ->and($company->timeZoneVersions)->toHaveCount(1)
            ->and($company->workingDaySettingVersions)->toHaveCount(1)
            ->and($timeZone)->toBeInstanceOf(CompanyTimeZoneVersion::class)
            ->and($timeZone->time_zone)->toBe('Asia/Almaty')
            ->and($timeZone->company)->toBeInstanceOf(Company::class)
            ->and($timeZone->getCasts())->toMatchArray([
                'id' => 'integer',
                'company_id' => 'integer',
                'time_zone' => 'string',
                'applies_from' => 'datetime',
                'created_at' => 'datetime',
            ])
            ->and($settings)->toBeInstanceOf(CompanyWorkingDaySettingVersion::class)
            ->and($settings->start_time)->toStartWith('09:00')
            ->and($settings->end_time)->toStartWith('18:00')
            ->and($settings->break_duration_minutes)->toBe(60)
            ->and($settings->break_deducted)->toBeTrue()
            ->and($settings->lateness_grace_minutes)->toBe(0)
            ->and($settings->working_days->map(fn (WeekDay $day): string => $day->value)->all())->toBe([
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
            ])
            ->and($settings->company)->toBeInstanceOf(Company::class)
            ->and($settings->getCasts())->toMatchArray([
                'id' => 'integer',
                'company_id' => 'integer',
                'start_time' => 'string',
                'end_time' => 'string',
                'working_days' => AsEnumCollection::of(WeekDay::class),
                'break_duration_minutes' => 'integer',
                'break_deducted' => 'boolean',
                'lateness_grace_minutes' => 'integer',
                'applies_from' => 'datetime',
                'created_at' => 'datetime',
            ])
            ->and(Schema::hasColumn('company_time_zone_versions', 'updated_at'))->toBeFalse();

        $company->update(['name' => 'Globex']);

        expect($company->fresh()->name_normalized)->toBe('globex')
            ->and($company->timeZoneVersions()->count())->toBe(1)
            ->and($company->workingDaySettingVersions()->count())->toBe(1);
    });
});
