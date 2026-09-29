<?php

declare(strict_types=1);

use App\Modules\Identity\Models\SignInSecurityEvent;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Services\SignInThrottleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const DISTINCTIVE_PASSWORD = 'Distinctive-Vault-9f3a';

const PASSWORD_SECRECY_IP = '203.0.113.77';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));
});

it('keeps the password out of responses, security events, logs, mail, and command output', function () {
    Notification::fake();
    $logs = captureLogEvents();
    $company = acmeCompany();
    $admin = acmeAdmin([
        'company_id' => $company->id,
        'password' => DISTINCTIVE_PASSWORD,
    ]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $bodies = [];

    $success = postSecrecySignIn($admin->email, DISTINCTIVE_PASSWORD);
    $bodies[] = (string) $success->getContent();
    $success->assertOk();

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT) as $attempt) {
        $failed = postSecrecySignIn($viewer->email, DISTINCTIVE_PASSWORD);
        $bodies[] = (string) $failed->getContent();
        $failed->assertUnprocessable();
    }

    $blocked = postSecrecySignIn($viewer->email, DISTINCTIVE_PASSWORD);
    $bodies[] = (string) $blocked->getContent();
    $blocked->assertTooManyRequests();

    invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@acme.test',
    ]);

    $accepted = test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
            'password' => DISTINCTIVE_PASSWORD,
        ]);
    $bodies[] = (string) $accepted->getContent();
    $accepted->assertOk();

    $resetRequested = test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/forgot-password', ['email' => $viewer->email]);
    $bodies[] = (string) $resetRequested->getContent();
    $resetRequested->assertOk();

    $token = null;

    Notification::assertSentTo(
        $viewer,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use (&$token, $viewer): bool {
            $token = $notification->token();
            $html = (string) $notification->toMail($viewer)->render();

            expect($html)->not->toContain(DISTINCTIVE_PASSWORD);

            return true;
        },
    );

    throw_unless(is_string($token), RuntimeException::class);

    $reset = test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $viewer->email,
            'password' => DISTINCTIVE_PASSWORD,
        ]);
    $bodies[] = (string) $reset->getContent();
    $reset->assertOk();

    test()->artisan('identity:create-super-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion(__('identity.password'), DISTINCTIVE_PASSWORD)
        ->expectsQuestion(__('identity.password_confirmation'), DISTINCTIVE_PASSWORD)
        ->doesntExpectOutputToContain(DISTINCTIVE_PASSWORD)
        ->assertSuccessful();

    foreach ($bodies as $body) {
        expect($body)->not->toContain(DISTINCTIVE_PASSWORD);
    }

    foreach (SignInSecurityEvent::query()->get() as $event) {
        $encoded = (string) json_encode($event->getAttributes(), JSON_THROW_ON_ERROR);

        expect($encoded)->not->toContain(DISTINCTIVE_PASSWORD);
    }

    foreach ($logs->events as $event) {
        $encoded = (string) json_encode($event->context, JSON_THROW_ON_ERROR);

        expect($event->message)->not->toContain(DISTINCTIVE_PASSWORD)
            ->and($encoded)->not->toContain(DISTINCTIVE_PASSWORD);
    }

    expectAuthenticationFailureLogs($logs, [
        DISTINCTIVE_PASSWORD,
        $admin->email,
        $viewer->email,
        'invitee@acme.test',
        'owner@example.com',
    ]);

    $invitee = withoutCompanyIsolation(
        fn () => User::query()->where('email', 'invitee@acme.test')->first(),
    );
    $owner = withoutCompanyIsolation(
        fn () => User::query()->where('email', 'owner@example.com')->first(),
    );

    expect($invitee)->not->toBeNull()
        ->and($owner)->not->toBeNull();

    foreach ([$admin->fresh(), $viewer->fresh(), $invitee, $owner] as $user) {
        expect($user)->toBeInstanceOf(User::class)
            ->and($user->password)->not->toBe(DISTINCTIVE_PASSWORD)
            ->and($user->password)->not->toContain(DISTINCTIVE_PASSWORD)
            ->and(Hash::check(DISTINCTIVE_PASSWORD, $user->password))->toBeTrue();
    }
});

function postSecrecySignIn(string $email, string $password): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => PASSWORD_SECRECY_IP])
        ->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $email,
            'password' => $password,
        ]);
}
