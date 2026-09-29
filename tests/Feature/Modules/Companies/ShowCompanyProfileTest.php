<?php

declare(strict_types=1);

use App\Modules\Companies\Data\WorkingDaySettingDefaults;

it('shows the company admin their company and the latest time zone', function () {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => '123456789012',
        'contact_person' => 'Ada Contact',
        'phone' => '+7 (700) 123-45-67',
        'email' => 'office@acme.test',
    ]);
    withCompanyContext($company->id, fn () => $company->timeZoneVersions()->create([
        'time_zone' => 'Europe/Moscow',
        'applies_from' => '2026-02-01 00:00:00',
    ]));
    $admin = acmeAdmin(['company_id' => $company->id]);

    signedInAs($admin)
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.time_zone', 'Europe/Moscow')
        ->assertJsonPath('data.bin', '123456789012')
        ->assertJsonPath('data.contact_person', 'Ada Contact')
        ->assertJsonPath('data.phone', '+7 (700) 123-45-67')
        ->assertJsonPath('data.email', 'office@acme.test');

    expect($company->timeZoneVersions()->orderBy('id')->value('time_zone'))
        ->toBe(WorkingDaySettingDefaults::DEFAULT_TIME_ZONE);
});

it('asks a super admin without a selected company to select one', function () {
    acmeCompany();
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->getJson('/api/v1/company')
        ->assertConflict()
        ->assertJsonPath('error_code', 'company_not_selected')
        ->assertJsonMissingPath('data');
});

it('shows a super admin the selected company profile', function () {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => '123456789012',
        'contact_person' => 'Ada Contact',
        'phone' => '+7 (700) 123-45-67',
        'email' => 'office@acme.test',
    ]);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $company->id)
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.name', 'Acme');
});

it('refuses a guest', function () {
    $this->getJson('/api/v1/company')->assertUnauthorized();
});

it('shows a viewer every profile field', function () {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => '123456789012',
        'contact_person' => 'Ada Contact',
        'phone' => '+7 (700) 123-45-67',
        'email' => 'office@acme.test',
    ]);
    withCompanyContext($company->id, fn () => $company->timeZoneVersions()->create([
        'time_zone' => 'Europe/Moscow',
        'applies_from' => '2026-02-01 00:00:00',
    ]));
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($viewer)
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.time_zone', 'Europe/Moscow')
        ->assertJsonPath('data.bin', '123456789012')
        ->assertJsonPath('data.contact_person', 'Ada Contact')
        ->assertJsonPath('data.phone', '+7 (700) 123-45-67')
        ->assertJsonPath('data.email', 'office@acme.test');
});

it('refuses a viewer profile change and leaves the company unchanged', function () {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => '123456789012',
        'contact_person' => 'Ada Contact',
        'phone' => '+7 (700) 123-45-67',
        'email' => 'office@acme.test',
    ]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $versions = withCompanyContext($company->id, fn () => $company->timeZoneVersions()->count());

    signedInAs($viewer)
        ->patchJson('/api/v1/company', [
            'name' => 'Hacked',
            'time_zone' => 'Europe/Moscow',
            'bin' => '',
            'contact_person' => '',
            'phone' => '',
            'email' => '',
        ])
        ->assertForbidden();

    $fresh = withCompanyContext($company->id, fn () => $company->fresh());

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('Acme')
        ->and($fresh->bin)->toBe('123456789012')
        ->and($fresh->contact_person)->toBe('Ada Contact')
        ->and($fresh->phone)->toBe('+7 (700) 123-45-67')
        ->and($fresh->email)->toBe('office@acme.test')
        ->and($company->timeZoneVersions()->count())->toBe($versions)
        ->and($company->timeZoneVersions()->orderByDesc('id')->value('time_zone'))
        ->toBe(WorkingDaySettingDefaults::DEFAULT_TIME_ZONE);
});

it('refuses a viewer the globex admin routes without revealing that company', function () {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany([
        'name' => 'Globex',
        'bin' => '123456789012',
    ]);
    $viewer = acmeViewer(['company_id' => $acme->id]);

    $list = signedInAs($viewer)->getJson('/api/v1/admin/companies');
    $known = signedInAs($viewer)->getJson('/api/v1/admin/companies/'.$globex->id.'/invitations');
    $unknown = signedInAs($viewer)->getJson('/api/v1/admin/companies/999999/invitations');

    $list->assertForbidden();
    $known->assertForbidden();
    $unknown->assertForbidden();

    expect($known->json('message'))->toBe($unknown->json('message'))
        ->and($list->json('message'))->not->toContain('Globex')
        ->and($known->json('message'))->not->toContain('Globex')
        ->and($known->json('message'))->not->toContain('123456789012');
});

it('refuses a viewer company users and invitations', function () {
    $viewer = acmeViewer();

    signedInAs($viewer)->getJson('/api/v1/company/users')->assertForbidden();
    signedInAs($viewer)->getJson('/api/v1/company/invitations')->assertForbidden();
    signedInAs($viewer)->postJson('/api/v1/company/invitations', [
        'email' => 'new@acme.test',
        'role' => 'viewer',
    ])->assertForbidden();
});

it('does not reveal another company when a company admin probes the admin list path', function () {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany([
        'name' => 'Globex',
        'bin' => '123456789012',
    ]);
    $admin = acmeAdmin(['company_id' => $acme->id]);

    $known = signedInAs($admin)->getJson('/api/v1/admin/companies/'.$globex->id);
    $unknown = signedInAs($admin)->getJson('/api/v1/admin/companies/999999');

    expect($known->status())->toBe($unknown->status())
        ->and($known->status())->toBeIn([403, 404])
        ->and($known->json('message'))->toBe($unknown->json('message'))
        ->and($known->json('message'))->not->toContain('Globex')
        ->and($known->json('message'))->not->toContain('123456789012');
});

it('lets a super admin change a deactivated selected company while company users stay signed out', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);
    persistCompany($company, ['deactivated_at' => '2026-01-15 12:00:00']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $company->id)
        ->patchJson('/api/v1/company', ['name' => 'Acme West'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme West');

    $fresh = withCompanyContext($company->id, fn () => $company->fresh());

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('Acme West')
        ->and($fresh->deactivated_at)->not->toBeNull();

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $admin->email,
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertUnprocessable();
});
