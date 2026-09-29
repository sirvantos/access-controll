<?php

declare(strict_types=1);

use App\Modules\Companies\Models\CompanyTimeZoneVersion;
use App\Modules\Companies\Models\CompanyWorkingDaySettingVersion;
use App\Modules\Identity\Models\User;

it('lets active users sign in again after the company is reactivated', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.is_active', true);

    foreach ([$admin, $viewer] as $user) {
        withoutCompanyIsolation(function (): void {
            auth('web')->logout();
            $this->flushSession();
        });

        test()->withHeaders(statefulHeaders())
            ->postJson('/api/v1/auth/sign-in', [
                'email' => $user->email,
                'password' => SAMPLE_PASSWORD,
            ])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
});

it('still refuses a user who was deactivated inside the company', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = User::factory()->viewer($company->id)->deactivated()->create([
        'email' => 'former@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/reactivate')
        ->assertOk();

    withoutCompanyIsolation(function (): void {
        auth('web')->logout();
        $this->flushSession();
    });

    $unknown = test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => 'missing@example.com',
            'password' => 'Wrong-Sign-In-Secret',
        ]);
    $rejected = test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $viewer->email,
            'password' => SAMPLE_PASSWORD,
        ]);

    $unknown->assertUnprocessable();
    $rejected->assertUnprocessable();

    expect($rejected->json())->toBe($unknown->json());

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $admin->email,
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk();
});

it('returns 404 for an unknown company on deactivate and reactivate', function () {
    $owner = ownerSuperAdmin();
    $missingId = 999999;

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$missingId.'/deactivate')
        ->assertNotFound();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$missingId.'/reactivate')
        ->assertNotFound();
});

it('leaves an already active company unchanged', function () {
    $company = acmeCompany();
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($company->fresh()?->deactivated_at)->toBeNull();
});

it('restores the active state and leaves details and version history unchanged', function () {
    $company = acmeCompany([
        'name' => 'Alma Stroy',
        'bin' => '123456789012',
        'contact_person' => 'Ada Contact',
        'phone' => '+7 (700) 123-45-67',
        'email' => 'office@acme.test',
    ]);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk();

    $timeZones = withCompanyContext(
        $company->id,
        fn () => CompanyTimeZoneVersion::query()->where('company_id', $company->id)->orderBy('id')->get(),
    );
    $settings = withCompanyContext(
        $company->id,
        fn () => CompanyWorkingDaySettingVersion::query()->where('company_id', $company->id)->orderBy('id')->get(),
    );

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.bin', '123456789012')
        ->assertJsonPath('data.name', 'Alma Stroy');

    $restored = withCompanyContext($company->id, fn () => $company->fresh());

    expect($restored)->not->toBeNull()
        ->and($restored->isActive())->toBeTrue()
        ->and($restored->bin)->toBe('123456789012')
        ->and($restored->contact_person)->toBe('Ada Contact')
        ->and($restored->phone)->toBe('+7 (700) 123-45-67')
        ->and($restored->email)->toBe('office@acme.test')
        ->and($restored->name_normalized)->toBe('alma stroy');

    expect(companyVersionSnapshots(withCompanyContext(
        $company->id,
        fn () => CompanyTimeZoneVersion::query()->where('company_id', $company->id)->orderBy('id')->get(),
    )))
        ->toBe(companyVersionSnapshots($timeZones))
        ->and(companyVersionSnapshots(withCompanyContext(
            $company->id,
            fn () => CompanyWorkingDaySettingVersion::query()->where('company_id', $company->id)->orderBy('id')->get(),
        )))
        ->toBe(companyVersionSnapshots($settings));

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect(withCompanyContext(
        $company->id,
        fn () => CompanyTimeZoneVersion::query()->where('company_id', $company->id)->count(),
    ))->toBe($timeZones->count())
        ->and(withCompanyContext(
            $company->id,
            fn () => CompanyWorkingDaySettingVersion::query()->where('company_id', $company->id)->count(),
        ))->toBe($settings->count());
});
