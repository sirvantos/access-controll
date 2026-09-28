<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const RESET_CONFIRMATION = ['ok' => true];

/**
 * @param  array<string, string>  $payload
 */
function postForgotPassword(array $payload): TestResponse
{
    return test()
        ->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/forgot-password', $payload);
}

it('returns the same confirmation and emails only eligible accounts', function () {
    Notification::fake();
    $admin = acmeAdmin();
    $owner = ownerSuperAdmin();
    $deactivated = acmeViewer([
        'email' => 'former@acme.test',
        'deactivated_at' => '2026-01-15 12:00:00',
    ]);
    $company = Company::factory()->deactivated()->create(['name' => 'Closed Co']);
    $closed = User::factory()->companyAdmin($company)->create([
        'email' => 'closed@example.com',
        'password' => SAMPLE_PASSWORD,
    ]);

    $responses = [
        postForgotPassword(['email' => $admin->email]),
        postForgotPassword(['email' => strtoupper($owner->email)]),
        postForgotPassword(['email' => $deactivated->email]),
        postForgotPassword(['email' => $closed->email]),
        postForgotPassword(['email' => 'missing@example.com']),
    ];

    foreach ($responses as $response) {
        $response->assertOk()->assertExactJson(RESET_CONFIRMATION);
    }

    Notification::assertSentTo($admin, ResetPasswordNotification::class);
    Notification::assertSentTo($owner, ResetPasswordNotification::class);
    Notification::assertNotSentTo($deactivated, ResetPasswordNotification::class);
    Notification::assertNotSentTo($closed, ResetPasswordNotification::class);
    Notification::assertCount(2);
});

it('sends nothing for a second request within 60 seconds and a new link after that', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));
    Notification::fake();
    $admin = acmeAdmin();

    postForgotPassword(['email' => $admin->email])
        ->assertOk()
        ->assertExactJson(RESET_CONFIRMATION);

    postForgotPassword(['email' => $admin->email])
        ->assertOk()
        ->assertExactJson(RESET_CONFIRMATION);

    Notification::assertSentToTimes($admin, ResetPasswordNotification::class, 1);

    Carbon::setTestNow(Carbon::parse('2026-01-15 12:01:00'));

    postForgotPassword(['email' => $admin->email])
        ->assertOk()
        ->assertExactJson(RESET_CONFIRMATION);

    Notification::assertSentToTimes($admin, ResetPasswordNotification::class, 2);
});

it('rejects an invalid email format', function () {
    Notification::fake();

    postForgotPassword(['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    Notification::assertNothingSent();
});
