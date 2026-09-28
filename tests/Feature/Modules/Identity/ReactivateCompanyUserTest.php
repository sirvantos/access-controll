<?php

declare(strict_types=1);

use App\Modules\Identity\PublicApi\Role;
use Illuminate\Support\Facades\Hash;

it('lets a reactivated user sign in with the same password and role', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $passwordHash = $viewer->password;
    $sessionVersion = $viewer->session_version;

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/deactivate')
        ->assertOk();

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.email', 'viewer@acme.test')
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.is_active', true);

    $viewer->refresh();

    expect($viewer->deactivated_at)->toBeNull()
        ->and($viewer->role)->toBe(Role::Viewer)
        ->and($viewer->password)->toBe($passwordHash)
        ->and(Hash::check(SAMPLE_PASSWORD, $viewer->password))->toBeTrue()
        ->and($viewer->session_version)->toBe($sessionVersion + 1);

    auth('web')->logout();

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $viewer->email,
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk()
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.email', 'viewer@acme.test');
});

it('leaves an already active user unchanged', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $sessionVersion = $viewer->session_version;

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    $viewer->refresh();

    expect($viewer->deactivated_at)->toBeNull()
        ->and($viewer->role)->toBe(Role::Viewer)
        ->and($viewer->session_version)->toBe($sessionVersion);
});
