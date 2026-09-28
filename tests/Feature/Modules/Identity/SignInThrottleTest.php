<?php

declare(strict_types=1);

use App\Modules\Identity\Models\SignInSecurityEvent;
use App\Modules\Identity\Services\SignInThrottleService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

const SIGN_IN_THROTTLE_IP = '203.0.113.20';

const SIGN_IN_THROTTLE_SECRET = 'Wrong-Sign-In-Secret';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function postThrottledSignIn(string $email, string $password): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => SIGN_IN_THROTTLE_IP])
        ->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $email,
            'password' => $password,
        ]);
}

it('blocks the account after five failures until fifteen minutes pass', function () {
    $logs = captureLogEvents();
    $admin = acmeAdmin();

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT) as $attempt) {
        postThrottledSignIn($admin->email, SIGN_IN_THROTTLE_SECRET)->assertUnprocessable();
    }

    $blocked = postThrottledSignIn($admin->email, SAMPLE_PASSWORD)->assertTooManyRequests();

    expect($blocked->json('errors.email.0'))->toBe(__('auth.blocked'));

    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00')->addMinutes(SignInThrottleService::ACCOUNT_BLOCK_MINUTES));

    postThrottledSignIn($admin->email, SAMPLE_PASSWORD)
        ->assertOk()
        ->assertJsonPath('data.email', $admin->email);

    expectAuthenticationFailureLogs($logs, [SIGN_IN_THROTTLE_SECRET, SAMPLE_PASSWORD, $admin->email]);
});

it('blocks an unknown email the same way as a known account', function () {
    $logs = captureLogEvents();
    $email = 'missing@example.com';

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT) as $attempt) {
        postThrottledSignIn($email, SIGN_IN_THROTTLE_SECRET)->assertUnprocessable();
    }

    $blocked = postThrottledSignIn($email, SAMPLE_PASSWORD)->assertTooManyRequests();

    expect($blocked->json('errors.email.0'))->toBe(__('auth.blocked'));

    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00')->addMinutes(SignInThrottleService::ACCOUNT_BLOCK_MINUTES));

    postThrottledSignIn($email, SAMPLE_PASSWORD)
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', __('auth.failed'));

    expectAuthenticationFailureLogs($logs, [SIGN_IN_THROTTLE_SECRET, SAMPLE_PASSWORD, $email]);
});

it('blocks a source after twenty failures across emails', function () {
    $logs = captureLogEvents();

    foreach (range(1, SignInThrottleService::SOURCE_FAILURE_LIMIT) as $attempt) {
        postThrottledSignIn('source-'.$attempt.'@example.com', SIGN_IN_THROTTLE_SECRET)->assertUnprocessable();
    }

    postThrottledSignIn('another@example.com', SAMPLE_PASSWORD)->assertTooManyRequests();

    $events = SignInSecurityEvent::query()->get();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        $encoded = (string) json_encode($event->getAttributes(), JSON_THROW_ON_ERROR);

        expect($event->getAttributes())->not->toHaveKey('password')
            ->and($encoded)->not->toContain(SIGN_IN_THROTTLE_SECRET)
            ->and($encoded)->not->toContain(SAMPLE_PASSWORD);
    }

    expectAuthenticationFailureLogs($logs, [SIGN_IN_THROTTLE_SECRET, SAMPLE_PASSWORD, 'source-1@example.com']);
});
