<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSessionIsCurrent;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

function registerSessionProbeRoute(): void
{
    Route::middleware([
        EnsureFrontendRequestsAreStateful::class,
        'auth:sanctum',
        'current-session',
        'role:company_admin,viewer',
    ])->get('/_test/session', fn () => response()->json(['ok' => true]));
}

beforeEach(fn () => registerSessionProbeRoute());

it('accepts a current session for a listed role', function () {
    signedInAs(acmeAdmin());

    $this->getJson('/_test/session')
        ->assertSuccessful()
        ->assertExactJson(['ok' => true]);
});

it('accepts a current session for a viewer', function () {
    signedInAs(acmeViewer());

    $this->getJson('/_test/session')
        ->assertSuccessful()
        ->assertExactJson(['ok' => true]);
});

it('returns 401 when the stored session version is stale', function () {
    $admin = acmeAdmin();
    $storedVersion = $admin->sessionVersion();
    persistUser($admin, ['session_version' => $storedVersion + 1]);

    test()
        ->actingAs(freshUser($admin), 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $storedVersion])
        ->withHeaders(statefulHeaders())
        ->getJson('/_test/session')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('returns 401 for a deactivated user', function () {
    $viewer = acmeViewer();
    persistUser($viewer, ['deactivated_at' => '2026-01-15 12:00:00']);

    signedInAs(freshUser($viewer));

    $this->getJson('/_test/session')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('returns 401 for a user of a deactivated company', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    persistCompany($company, ['deactivated_at' => '2026-01-15 12:00:00']);

    signedInAs(freshUser($admin));

    $this->getJson('/_test/session')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('returns 403 when the role is not listed', function () {
    signedInAs(ownerSuperAdmin());

    $response = $this->getJson('/_test/session')->assertForbidden();

    expect($response->json('message'))->toBeString()->not->toBeEmpty();
});

it('keeps the same session refused after a 401', function () {
    $admin = acmeAdmin();
    $storedVersion = $admin->sessionVersion();
    persistUser($admin, ['session_version' => $storedVersion + 1]);

    test()
        ->actingAs(freshUser($admin), 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $storedVersion])
        ->withHeaders(statefulHeaders());

    $this->getJson('/_test/session')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');

    $this->getJson('/_test/session')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});
