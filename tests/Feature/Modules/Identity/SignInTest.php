<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\User;
use Illuminate\Testing\TestResponse;

const SIGN_IN_SECRET = 'Wrong-Sign-In-Secret';

/**
 * @param  array<string, string>  $payload
 */
function postSignIn(array $payload): TestResponse
{
    return test()
        ->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', $payload);
}

it('signs in an active company admin', function () {
    $admin = acmeAdmin();

    postSignIn([
        'email' => $admin->email,
        'password' => SAMPLE_PASSWORD,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.email', $admin->email)
        ->assertJsonPath('data.role', 'company_admin')
        ->assertJsonPath('data.company_id', $admin->company_id);

    expect(auth('web')->id())->toBe($admin->id);
});

it('signs in an active super admin', function () {
    $owner = ownerSuperAdmin();

    postSignIn([
        'email' => $owner->email,
        'password' => SAMPLE_PASSWORD,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $owner->id)
        ->assertJsonPath('data.email', $owner->email)
        ->assertJsonPath('data.role', 'super_admin')
        ->assertJsonPath('data.company_id', null);
});

it('signs in with a mixed-case email', function () {
    $admin = acmeAdmin();

    postSignIn([
        'email' => strtoupper($admin->email),
        'password' => SAMPLE_PASSWORD,
    ])
        ->assertOk()
        ->assertJsonPath('data.email', $admin->email);
});

it('returns the same body for every rejected sign-in', function () {
    $logs = captureLogEvents();
    $admin = acmeAdmin();
    $deactivated = acmeViewer();
    $deactivated->forceFill(['deactivated_at' => '2026-01-15 12:00:00'])->save();
    $company = Company::factory()->deactivated()->create(['name' => 'Closed Co']);
    $closed = User::factory()->companyAdmin($company)->create([
        'email' => 'closed@example.com',
        'password' => SAMPLE_PASSWORD,
    ]);

    $responses = [
        postSignIn(['email' => $admin->email, 'password' => SIGN_IN_SECRET]),
        postSignIn(['email' => 'missing@example.com', 'password' => SIGN_IN_SECRET]),
        postSignIn(['email' => $deactivated->email, 'password' => SAMPLE_PASSWORD]),
        postSignIn(['email' => $closed->email, 'password' => SAMPLE_PASSWORD]),
    ];

    $expected = $responses[0]->assertUnprocessable()->json();

    expect($expected['errors']['email'][0])->toBe(__('auth.failed'));

    foreach ($responses as $response) {
        $response->assertUnprocessable();
        expect($response->json())->toBe($expected);
        expect(auth('web')->check())->toBeFalse();
    }

    expectAuthenticationFailureLogs($logs, [SIGN_IN_SECRET, $admin->email, 'missing@example.com', SAMPLE_PASSWORD]);
});

it('rejects an invalid sign-in payload', function () {
    postSignIn([
        'email' => 'not-an-email',
        'password' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
});
