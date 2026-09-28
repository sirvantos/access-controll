<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Password;

it('deactivates a viewer, keeps them in the list, and ends their session even after reactivation', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $sessionVersion = $viewer->sessionVersion();

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.id', $viewer->id)
        ->assertJsonPath('data.email', 'viewer@acme.test')
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.is_active', false);

    $viewer->refresh();

    expect($viewer->deactivated_at)->not->toBeNull()
        ->and($viewer->session_version)->toBe($sessionVersion + 1);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    test()
        ->actingAs($viewer->fresh(), 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $sessionVersion])
        ->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('refuses sign-in for a deactivated user with the generic failure', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/deactivate')
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

    expect($rejected->json())->toBe($unknown->json())
        ->and($rejected->json('errors.email.0'))->toBe(__('auth.failed'))
        ->and(auth('web')->check())->toBeFalse();
});

it('keeps a deactivated user in the company user list', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/deactivate')
        ->assertOk();

    $rows = signedInAs($admin)
        ->getJson('/api/v1/company/users')
        ->assertOk()
        ->json('data');

    $viewerRow = collect($rows)->firstWhere('email', 'viewer@acme.test');

    expect($viewerRow)->not->toBeNull()
        ->and($viewerRow['is_active'])->toBeFalse()
        ->and(User::query()->whereKey($viewer->id)->exists())->toBeTrue();
});

it('stops a pending reset token when the user is deactivated', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $token = Password::broker()->createToken($viewer);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/deactivate')
        ->assertOk();

    expect(Password::broker()->tokenExists($viewer, $token))->toBeFalse();

    $logs = captureLogEvents();

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $viewer->email,
            'password' => 'new-password',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.token.0', __('passwords.token'));

    expectNothingLogged($logs);
});

it('leaves an already deactivated user unchanged', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/deactivate')
        ->assertOk();

    $viewer->refresh();
    $deactivatedAt = $viewer->deactivated_at;
    $sessionVersion = $viewer->session_version;

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$viewer->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $viewer->refresh();

    expect($viewer->deactivated_at?->equalTo($deactivatedAt))->toBeTrue()
        ->and($viewer->session_version)->toBe($sessionVersion);
});
