<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyTimeZoneVersion;
use App\Modules\Companies\Models\CompanyWorkingDaySettingVersion;
use App\Modules\Companies\PublicApi\CompanySchedule;
use App\Modules\Companies\PublicApi\WeekDay;
use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use App\Modules\Companies\Services\CompanyScheduleService;
use Illuminate\Support\Carbon;

it('applies a later version from the next local midnight and keeps today on the previous one', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $company = Company::factory()->create(['name' => 'Acme']);
    $service = app(CompanyScheduleService::class);
    $nextMidnight = Carbon::parse('2026-01-16 00:00:00', 'Asia/Almaty');
    $startOfToday = Carbon::parse('2026-01-15 00:00:00', 'Asia/Almaty');

    expect(app(CompanySchedule::class))->toBeInstanceOf(CompanyScheduleService::class)
        ->and($service->appliesFromNextLocalMidnight('Asia/Almaty', now())->equalTo($nextMidnight))->toBeTrue()
        ->and($service->appliesFromStartOfLocalDay('Asia/Almaty', now())->equalTo($startOfToday))->toBeTrue();

    $service->saveTimeZone($company->id, 'Europe/Moscow', now());
    $service->saveWorkingDaySettings($company->id, scheduleSettings('10:00', true), now());

    $timeZone = $company->timeZoneVersions()->orderByDesc('id')->first();
    $settings = $company->workingDaySettingVersions()->orderByDesc('id')->first();

    expect($timeZone)->toBeInstanceOf(CompanyTimeZoneVersion::class)
        ->and($settings)->toBeInstanceOf(CompanyWorkingDaySettingVersion::class)
        ->and($timeZone->applies_from->equalTo($nextMidnight))->toBeTrue()
        ->and($settings->applies_from->equalTo($nextMidnight))->toBeTrue()
        ->and($service->timeZoneIdentifierAt($company->id, now()))->toBe('Asia/Almaty')
        ->and($service->timeZoneIdentifierAt($company->id, Carbon::parse('2026-01-15 00:00:00')))->toBe('Asia/Almaty')
        ->and($service->timeZoneIdentifierAt($company->id, $nextMidnight))->toBe('Europe/Moscow')
        ->and($service->workingDaySettingsAt($company->id, now())->startTime->format('H:i'))->toBe('09:00')
        ->and($service->workingDaySettingsAt($company->id, $nextMidnight)->startTime->format('H:i'))->toBe('10:00');
});

it('inserts nothing when the payload matches the latest version', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $company = Company::factory()->create(['name' => 'Acme']);
    $service = app(CompanyScheduleService::class);

    $service->saveTimeZone($company->id, 'Asia/Almaty', now());
    $service->saveWorkingDaySettings($company->id, scheduleSettings('09:00', true), now());

    expect($company->timeZoneVersions()->count())->toBe(1)
        ->and($company->workingDaySettingVersions()->count())->toBe(1);
});

it('keeps every same-day change and applies the highest id from that midnight', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $company = Company::factory()->create(['name' => 'Acme']);
    $service = app(CompanyScheduleService::class);
    $nextMidnight = Carbon::parse('2026-01-16 00:00:00', 'Asia/Almaty');

    $service->saveTimeZone($company->id, 'Europe/Moscow', now());
    $service->saveTimeZone($company->id, 'Asia/Aqtobe', now());
    $service->saveWorkingDaySettings($company->id, scheduleSettings('10:00', true), now());
    $service->saveWorkingDaySettings($company->id, scheduleSettings('11:00', false), now());

    $timeZones = $company->timeZoneVersions()->orderBy('id')->get();
    $settings = $company->workingDaySettingVersions()->orderBy('id')->get();
    $applied = $service->workingDaySettingsAt($company->id, $nextMidnight);

    expect($timeZones)->toHaveCount(3)
        ->and($settings)->toHaveCount(3)
        ->and($timeZones->get(1)?->applies_from->equalTo($nextMidnight))->toBeTrue()
        ->and($timeZones->get(2)?->applies_from->equalTo($nextMidnight))->toBeTrue()
        ->and($settings->get(1)?->applies_from->equalTo($nextMidnight))->toBeTrue()
        ->and($settings->get(2)?->applies_from->equalTo($nextMidnight))->toBeTrue()
        ->and($service->timeZoneIdentifierAt($company->id, $nextMidnight))->toBe('Asia/Aqtobe')
        ->and($service->timeZoneIdentifierAt($company->id, now()))->toBe('Asia/Almaty')
        ->and($applied->startTime->format('H:i'))->toBe('11:00')
        ->and($applied->breakDeducted)->toBeFalse()
        ->and($service->workingDaySettingsAt($company->id, now())->startTime->format('H:i'))->toBe('09:00');
});

function scheduleSettings(string $startTime, bool $breakDeducted): WorkingDaySettingsView
{
    return new WorkingDaySettingsView(
        startTime: Carbon::parse($startTime),
        endTime: Carbon::parse('18:00'),
        workingDays: [
            WeekDay::Monday,
            WeekDay::Tuesday,
            WeekDay::Wednesday,
            WeekDay::Thursday,
            WeekDay::Friday,
        ],
        breakDurationMinutes: 60,
        breakDeducted: $breakDeducted,
        latenessGraceMinutes: 0,
    );
}
