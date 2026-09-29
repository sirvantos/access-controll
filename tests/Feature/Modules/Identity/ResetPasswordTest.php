<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const NEW_PASSWORD = 'new-password';

function issueResetToken(User $user): string
{
    Notification::fake();

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    $token = null;

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token();

            return true;
        },
    );

    throw_unless(is_string($token), RuntimeException::class);

    return $token;
}

/**
 * @param  array<string, string>  $payload
 */
function postResetPassword(array $payload): TestResponse
{
    return test()
        ->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/reset-password', $payload);
}

it('changes the password so the new one signs in and the old one fails', function () {
    $admin = acmeAdmin();
    $token = issueResetToken($admin);

    postResetPassword([
        'token' => $token,
        'email' => strtoupper($admin->email),
        'password' => NEW_PASSWORD,
    ])->assertOk()->assertExactJson(['ok' => true]);

    expect(auth('web')->check())->toBeFalse();

    postResetSignIn($admin->email, NEW_PASSWORD)->assertOk();

    auth('web')->logout();

    postResetSignIn($admin->email, SAMPLE_PASSWORD)->assertUnprocessable();
});

it('ends the session that was current before the reset', function () {
    $admin = acmeAdmin();

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $admin->email,
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk();

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertOk();

    $token = issueResetToken($admin);

    postResetPassword([
        'token' => $token,
        'email' => $admin->email,
        'password' => NEW_PASSWORD,
    ])->assertOk();

    auth()->forgetGuards();

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('refuses a reused reset link', function () {
    $admin = acmeAdmin();
    $token = issueResetToken($admin);
    $payload = [
        'token' => $token,
        'email' => $admin->email,
        'password' => NEW_PASSWORD,
    ];

    postResetPassword($payload)->assertOk();

    $logs = captureLogEvents();

    postResetPassword($payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.token.0', __('passwords.token'));

    expectNothingLogged($logs);
});

it('refuses a reset link older than 60 minutes', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));
    $admin = acmeAdmin();
    $token = issueResetToken($admin);

    Carbon::setTestNow(Carbon::parse('2026-01-15 13:01:00'));
    $logs = captureLogEvents();

    postResetPassword([
        'token' => $token,
        'email' => $admin->email,
        'password' => NEW_PASSWORD,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.token.0', __('passwords.token'));

    expectNothingLogged($logs);
});

it('refuses a reset link for a deactivated user', function () {
    $user = acmeAdmin();
    $token = issueResetToken($user);
    persistUser($user, ['deactivated_at' => '2026-01-15 12:00:00']);
    $logs = captureLogEvents();

    postResetPassword([
        'token' => $token,
        'email' => $user->email,
        'password' => NEW_PASSWORD,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.token.0', __('passwords.token'));

    expectNothingLogged($logs);
});

it('refuses a reset link for a user of a deactivated company', function () {
    $company = Company::factory()->create(['name' => 'Acme']);
    $user = acmeAdmin(['company_id' => $company->id]);
    $token = issueResetToken($user);
    persistCompany($company, ['deactivated_at' => '2026-01-15 12:00:00']);
    $logs = captureLogEvents();

    postResetPassword([
        'token' => $token,
        'email' => $user->email,
        'password' => NEW_PASSWORD,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.token.0', __('passwords.token'));

    expectNothingLogged($logs);
});

function postResetSignIn(string $email, string $password): TestResponse
{
    return test()
        ->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $email,
            'password' => $password,
        ]);
}

it('rejects a password of 7 characters', function () {
    postResetPassword([
        'token' => 'not-a-real-token',
        'email' => 'admin@acme.test',
        'password' => '1234567',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
