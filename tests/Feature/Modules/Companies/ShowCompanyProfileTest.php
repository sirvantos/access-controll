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
    $company->timeZoneVersions()->create([
        'time_zone' => 'Europe/Moscow',
        'applies_from' => '2026-02-01 00:00:00',
    ]);
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

it('refuses a super admin', function () {
    acmeCompany();
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->getJson('/api/v1/company')
        ->assertForbidden();
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
    $company->timeZoneVersions()->create([
        'time_zone' => 'Europe/Moscow',
        'applies_from' => '2026-02-01 00:00:00',
    ]);
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
    $versions = $company->timeZoneVersions()->count();

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

    $fresh = $company->fresh();

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
