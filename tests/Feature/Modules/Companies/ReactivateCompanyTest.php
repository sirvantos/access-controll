<?php

declare(strict_types=1);

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
        auth('web')->logout();
        $this->flushSession();

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

    auth('web')->logout();
    $this->flushSession();

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
