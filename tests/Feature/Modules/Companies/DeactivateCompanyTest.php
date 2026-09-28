<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSessionIsCurrent;
use Illuminate\Support\Facades\Password;

it('ends company sessions, refuses sign-in, and invalidates reset links', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $owner = ownerSuperAdmin();
    $adminVersion = $admin->sessionVersion();
    $viewerVersion = $viewer->sessionVersion();
    $token = Password::broker()->createToken($admin);

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.is_active', false);

    $admin->refresh();
    $viewer->refresh();

    expect($admin->session_version)->toBe($adminVersion + 1)
        ->and($viewer->session_version)->toBe($viewerVersion + 1)
        ->and(Password::broker()->tokenExists($admin, $token))->toBeFalse();

    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    test()
        ->actingAs($admin->fresh(), 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $adminVersion])
        ->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    test()
        ->actingAs($viewer->fresh(), 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $viewerVersion])
        ->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    $unknown = test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => 'missing@example.com',
            'password' => 'Wrong-Sign-In-Secret',
        ]);

    foreach ([$admin, $viewer] as $user) {
        $rejected = test()->withHeaders(statefulHeaders())
            ->postJson('/api/v1/auth/sign-in', [
                'email' => $user->email,
                'password' => SAMPLE_PASSWORD,
            ]);

        $rejected->assertUnprocessable();
        expect($rejected->json())->toBe($unknown->json());
    }

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'new-password',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.token.0', __('passwords.token'));
});

it('does nothing when the company is already deactivated', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk();

    $version = $admin->fresh()?->session_version;

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($admin->fresh()?->session_version)->toBe($version);
});
