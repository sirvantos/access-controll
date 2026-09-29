<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanySchedule;
use Illuminate\Support\Carbon;

it('saves valid settings and shows them immediately while today keeps the previous version', function () {
    $now = Carbon::parse('2026-06-15 10:00:00');
    Carbon::setTestNow($now);
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);
    $schedule = app(CompanySchedule::class);

    signedInAs($admin)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '08:00',
            'break_deducted' => false,
        ]))
        ->assertOk()
        ->assertJsonPath('data.start_time', '08:00')
        ->assertJsonPath('data.break_deducted', false);

    signedInAs($admin)
        ->getJson('/api/v1/company/working-day-settings')
        ->assertOk()
        ->assertJsonPath('data.start_time', '08:00')
        ->assertJsonPath('data.break_deducted', false);

    $latest = $company->workingDaySettingVersions()->orderByDesc('id')->first();
    $appliesFrom = $latest?->applies_from;

    expect($appliesFrom)->not->toBeNull()
        ->and($schedule->workingDaySettingsAt($company->id, $now)->startTime->format('H:i'))->toBe('09:00')
        ->and($schedule->workingDaySettingsAt($company->id, Carbon::parse('2026-06-15 00:00:00'))->startTime->format('H:i'))->toBe('09:00')
        ->and($schedule->workingDaySettingsAt($company->id, $appliesFrom)->startTime->format('H:i'))->toBe('08:00')
        ->and($schedule->workingDaySettingsAt($company->id, $appliesFrom)->breakDeducted)->toBeFalse();
});

it('explains invalid settings and stores no new version', function (array $payload, string $field) {
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);
    $versions = withCompanyContext($company->id, fn () => $company->workingDaySettingVersions()->count());

    signedInAs($admin)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload($payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(withCompanyContext($company->id, fn () => $company->workingDaySettingVersions()->count()))->toBe($versions);
})->with([
    'end not after start' => [['start_time' => '18:00', 'end_time' => '09:00'], 'end_time'],
    'no working days' => [['working_days' => []], 'working_days'],
    'break out of range' => [['break_duration_minutes' => 540], 'break_duration_minutes'],
    'grace out of range' => [['lateness_grace_minutes' => 540], 'lateness_grace_minutes'],
]);

it('inserts nothing when the settings match the latest row', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);

    signedInAs($admin)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload())
        ->assertOk()
        ->assertJsonPath('data.start_time', '09:00');

    expect($company->workingDaySettingVersions()->count())->toBe(1);
});

it('keeps only the last settings saved before midnight', function () {
    $now = Carbon::parse('2026-06-15 10:00:00');
    Carbon::setTestNow($now);
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);
    $schedule = app(CompanySchedule::class);

    signedInAs($admin)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '08:00',
        ]))
        ->assertOk();
    signedInAs($admin)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '10:00',
        ]))
        ->assertOk()
        ->assertJsonPath('data.start_time', '10:00');

    $latest = $company->workingDaySettingVersions()->orderByDesc('id')->first();

    expect($company->workingDaySettingVersions()->count())->toBe(3)
        ->and($latest?->start_time)->toBe('10:00')
        ->and($schedule->workingDaySettingsAt($company->id, $now)->startTime->format('H:i'))->toBe('09:00')
        ->and($schedule->workingDaySettingsAt($company->id, $latest?->applies_from ?? $now)->startTime->format('H:i'))->toBe('10:00');
});

it('refuses a company admin probing another company through an admin path', function () {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany([
        'name' => 'Globex',
        'bin' => sampleCompanyBin(),
    ]);
    $admin = acmeAdmin(['company_id' => $acme->id]);

    foreach (['GET', 'PATCH'] as $method) {
        $known = signedInAs($admin)->json($method, '/api/v1/admin/companies/'.$globex->id, defaultWorkingDaySettingsPayload());
        $unknown = signedInAs($admin)->json($method, '/api/v1/admin/companies/999999', defaultWorkingDaySettingsPayload());

        expect($known->status())->toBe($unknown->status())
            ->and($known->status())->toBeIn([403, 404, 405])
            ->and($known->json('message'))->not->toContain('Globex')
            ->and($known->json('message'))->not->toContain(sampleCompanyBin())
            ->and($unknown->json('message'))->not->toContain('Globex');
    }

    expect(withCompanyContext($globex->id, fn () => Company::query()->find($globex->id)?->name))->toBe('Globex')
        ->and(withCompanyContext($globex->id, fn () => $globex->workingDaySettingVersions()->count()))->toBe(1);
});

it('refuses a viewer settings change', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($viewer)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '08:00',
        ]))
        ->assertForbidden();

    expect(withCompanyContext($company->id, fn () => $company->workingDaySettingVersions()->count()))->toBe(1);
});

it('asks a super admin without a selected company to select one before changing settings', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '08:00',
        ]))
        ->assertConflict()
        ->assertJsonPath('error_code', 'company_not_selected');

    expect(withCompanyContext($company->id, fn () => $company->workingDaySettingVersions()->count()))->toBe(1);
});

it('lets a super admin with a selected company update working day settings', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $company->id)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '08:00',
        ]))
        ->assertOk()
        ->assertJsonPath('data.start_time', '08:00');
});
