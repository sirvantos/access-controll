<?php

declare(strict_types=1);

use App\Modules\Identity\Data\SignInSecurityEventType;
use App\Modules\Identity\Models\SignInSecurityEvent;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

it('creates a security event without a user and casts the type', function (SignInSecurityEventType $type) {
    $event = SignInSecurityEvent::query()->create([
        'type' => $type,
        'email' => 'unknown@example.com',
        'user_id' => null,
        'ip_address' => '203.0.113.10',
    ]);

    $event->refresh();

    expect($event->type)->toBe($type)
        ->and($event->email)->toBe('unknown@example.com')
        ->and($event->user_id)->toBeNull()
        ->and($event->ip_address)->toBe('203.0.113.10')
        ->and($event->created_at)->toBeInstanceOf(Carbon::class)
        ->and($event->getAttributes())->not->toHaveKey('password')
        ->and(Schema::hasColumn('sign_in_security_events', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('sign_in_security_events', 'updated_at'))->toBeFalse();
})->with([
    'failed attempt' => SignInSecurityEventType::FailedAttempt,
    'attempt while blocked' => SignInSecurityEventType::AttemptWhileBlocked,
    'account blocked' => SignInSecurityEventType::AccountBlocked,
    'source blocked' => SignInSecurityEventType::SourceBlocked,
]);

it('creates a security event for a known user', function () {
    $user = User::factory()->superAdmin()->create([
        'email' => 'owner@example.com',
    ]);

    $event = SignInSecurityEvent::query()->create([
        'type' => SignInSecurityEventType::AccountBlocked,
        'email' => 'owner@example.com',
        'user_id' => $user->id,
        'ip_address' => '203.0.113.11',
    ]);

    $event->refresh();

    expect($event->type)->toBe(SignInSecurityEventType::AccountBlocked)
        ->and($event->user_id)->toBe($user->id)
        ->and($event->getCasts())->toMatchArray([
            'id' => 'integer',
            'type' => SignInSecurityEventType::class,
            'email' => 'string',
            'user_id' => 'integer',
            'ip_address' => 'string',
            'created_at' => 'datetime',
        ]);
});
