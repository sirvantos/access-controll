<?php

declare(strict_types=1);

use App\Modules\Identity\Data\SignInSecurityEventType;
use App\Modules\Identity\Models\SignInSecurityEvent;
use App\Modules\Identity\Services\SignInThrottleService;
use Illuminate\Support\Carbon;

const THROTTLE_IP = '203.0.113.10';

const THROTTLE_EMAIL = 'admin@acme.test';

const THROTTLE_SECRET = 'submitted-password';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('starts a fifteen minute account block on the fifth consecutive failure', function () {
    $user = acmeAdmin(['email' => THROTTLE_EMAIL]);
    $logs = captureLogEvents();
    $service = app(SignInThrottleService::class);

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT - 1) as $attempt) {
        $service->recordFailure(THROTTLE_EMAIL, $user->id, THROTTLE_IP);

        expect($service->isBlocked(THROTTLE_EMAIL, THROTTLE_IP))->toBeFalse();
    }

    $service->recordFailure(strtoupper(THROTTLE_EMAIL), $user->id, THROTTLE_IP);

    expect($service->isBlocked(THROTTLE_EMAIL, THROTTLE_IP))->toBeTrue()
        ->and(SignInSecurityEvent::query()->where('type', SignInSecurityEventType::AccountBlocked)->count())->toBe(1)
        ->and(SignInSecurityEvent::query()->where('type', SignInSecurityEventType::FailedAttempt)->count())->toBe(5);

    expectAuthenticationFailureLogs($logs, [THROTTLE_SECRET, THROTTLE_EMAIL]);
});

it('resets the consecutive failure count after a success', function () {
    $service = app(SignInThrottleService::class);

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT - 1) as $attempt) {
        $service->recordFailure(THROTTLE_EMAIL, null, THROTTLE_IP);
    }

    $service->recordSuccess(THROTTLE_EMAIL);

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT - 1) as $attempt) {
        $service->recordFailure(THROTTLE_EMAIL, null, THROTTLE_IP);
    }

    expect($service->isBlocked(THROTTLE_EMAIL, THROTTLE_IP))->toBeFalse();
});

it('ends the account block after exactly fifteen minutes', function () {
    $service = app(SignInThrottleService::class);
    $start = Carbon::parse('2026-01-15 12:00:00');

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT) as $attempt) {
        $service->recordFailure(THROTTLE_EMAIL, null, THROTTLE_IP);
    }

    Carbon::setTestNow($start->copy()->addMinutes(SignInThrottleService::ACCOUNT_BLOCK_MINUTES)->subSecond());

    expect($service->isBlocked(THROTTLE_EMAIL, THROTTLE_IP))->toBeTrue();

    Carbon::setTestNow($start->copy()->addMinutes(SignInThrottleService::ACCOUNT_BLOCK_MINUTES));

    expect($service->isBlocked(THROTTLE_EMAIL, THROTTLE_IP))->toBeFalse();
});

it('starts a source block after twenty failures across emails within fifteen minutes', function () {
    $logs = captureLogEvents();
    $service = app(SignInThrottleService::class);

    foreach (range(1, SignInThrottleService::SOURCE_FAILURE_LIMIT) as $attempt) {
        $service->recordFailure('person-'.$attempt.'@example.com', null, THROTTLE_IP);
    }

    expect($service->isBlocked('other@example.com', THROTTLE_IP))->toBeTrue()
        ->and(SignInSecurityEvent::query()->where('type', SignInSecurityEventType::SourceBlocked)->count())->toBe(1);

    expectAuthenticationFailureLogs($logs, [THROTTLE_SECRET, 'person-1@example.com']);
});

it('expires the source window after fifteen minutes', function () {
    $service = app(SignInThrottleService::class);
    $start = Carbon::parse('2026-01-15 12:00:00');

    foreach (range(1, SignInThrottleService::SOURCE_FAILURE_LIMIT - 1) as $attempt) {
        $service->recordFailure('window-'.$attempt.'@example.com', null, THROTTLE_IP);
    }

    Carbon::setTestNow($start->copy()->addMinutes(SignInThrottleService::SOURCE_WINDOW_MINUTES));

    foreach (range(1, SignInThrottleService::SOURCE_FAILURE_LIMIT - 1) as $attempt) {
        $service->recordFailure('later-'.$attempt.'@example.com', null, THROTTLE_IP);
    }

    expect($service->isBlocked('later@example.com', THROTTLE_IP))->toBeFalse();
});

it('writes every security event type without a password', function () {
    $user = acmeAdmin(['email' => THROTTLE_EMAIL]);
    $logs = captureLogEvents();
    $service = app(SignInThrottleService::class);

    $service->recordFailure(THROTTLE_EMAIL, $user->id, THROTTLE_IP);
    $service->recordBlockedAttempt(THROTTLE_EMAIL, $user->id, THROTTLE_IP);

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT - 1) as $attempt) {
        $service->recordFailure(THROTTLE_EMAIL, $user->id, THROTTLE_IP);
    }

    foreach (range(1, SignInThrottleService::SOURCE_FAILURE_LIMIT) as $attempt) {
        $service->recordFailure('source-'.$attempt.'@example.com', null, '203.0.113.11');
    }

    $types = SignInSecurityEvent::query()->pluck('type')->map(fn (SignInSecurityEventType $type): string => $type->value)->all();

    expect($types)->toContain(SignInSecurityEventType::FailedAttempt->value)
        ->and($types)->toContain(SignInSecurityEventType::AttemptWhileBlocked->value)
        ->and($types)->toContain(SignInSecurityEventType::AccountBlocked->value)
        ->and($types)->toContain(SignInSecurityEventType::SourceBlocked->value);

    $matched = SignInSecurityEvent::query()->where('email', THROTTLE_EMAIL)->first();

    expect($matched)->not->toBeNull()
        ->and($matched->user_id)->toBe($user->id)
        ->and($matched->ip_address)->toBe(THROTTLE_IP);

    $unknown = SignInSecurityEvent::query()->where('email', 'source-1@example.com')->first();

    expect($unknown)->not->toBeNull()
        ->and($unknown->user_id)->toBeNull();

    foreach (SignInSecurityEvent::query()->get() as $event) {
        expect($event->getAttributes())->not->toHaveKey('password')
            ->and((string) json_encode($event->getAttributes(), JSON_THROW_ON_ERROR))->not->toContain(THROTTLE_SECRET);
    }

    expectAuthenticationFailureLogs($logs, [THROTTLE_SECRET, THROTTLE_EMAIL]);
});

it('does not count an attempt that is already blocked', function () {
    $service = app(SignInThrottleService::class);

    foreach (range(1, SignInThrottleService::ACCOUNT_FAILURE_LIMIT - 1) as $attempt) {
        $service->recordFailure(THROTTLE_EMAIL, null, THROTTLE_IP);
    }

    $service->recordBlockedAttempt(THROTTLE_EMAIL, null, THROTTLE_IP);
    $service->recordFailure(THROTTLE_EMAIL, null, THROTTLE_IP);

    expect($service->isBlocked(THROTTLE_EMAIL, THROTTLE_IP))->toBeTrue()
        ->and(SignInSecurityEvent::query()->where('type', SignInSecurityEventType::FailedAttempt)->count())->toBe(SignInThrottleService::ACCOUNT_FAILURE_LIMIT)
        ->and(SignInSecurityEvent::query()->where('type', SignInSecurityEventType::AttemptWhileBlocked)->count())->toBe(1);
});
