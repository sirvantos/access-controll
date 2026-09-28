<?php

declare(strict_types=1);

it('shows the company admin the default working day settings', function () {
    $admin = acmeAdmin();

    signedInAs($admin)
        ->getJson('/api/v1/company/working-day-settings')
        ->assertOk()
        ->assertJsonPath('data.start_time', '09:00')
        ->assertJsonPath('data.end_time', '18:00')
        ->assertJsonPath('data.working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->assertJsonPath('data.break_duration_minutes', 60)
        ->assertJsonPath('data.break_deducted', true)
        ->assertJsonPath('data.lateness_grace_minutes', 0);
});

it('shows a viewer every working day setting', function () {
    $viewer = acmeViewer();

    signedInAs($viewer)
        ->getJson('/api/v1/company/working-day-settings')
        ->assertOk()
        ->assertJsonPath('data.start_time', '09:00')
        ->assertJsonPath('data.end_time', '18:00')
        ->assertJsonPath('data.working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->assertJsonPath('data.break_duration_minutes', 60)
        ->assertJsonPath('data.break_deducted', true)
        ->assertJsonPath('data.lateness_grace_minutes', 0);
});

it('refuses a viewer settings change and leaves the settings unchanged', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($viewer)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '08:00',
            'break_deducted' => false,
        ]))
        ->assertForbidden();

    $version = $company->workingDaySettingVersions()->sole();

    expect($company->workingDaySettingVersions()->count())->toBe(1)
        ->and($version->start_time)->toBe('09:00')
        ->and($version->break_deducted)->toBeTrue();
});

it('refuses a super admin', function () {
    acmeCompany();

    signedInAs(ownerSuperAdmin())
        ->getJson('/api/v1/company/working-day-settings')
        ->assertForbidden();
});
