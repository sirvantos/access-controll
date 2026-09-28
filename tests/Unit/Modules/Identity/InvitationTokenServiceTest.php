<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Services\InvitationTokenService;

it('generates a 64 character token and its sha-256 hash', function () {
    $service = app(InvitationTokenService::class);

    $first = $service->generate();
    $second = $service->generate();

    expect(strlen($first['token']))->toBe(InvitationTokenService::INVITATION_TOKEN_LENGTH)
        ->and($first['hash'])->toBe(hash('sha256', $first['token']))
        ->and($first['hash'])->toBe($service->hash($first['token']))
        ->and($first['token'])->not->toBe($second['token'])
        ->and($first['hash'])->not->toBe($second['hash']);
});

it('finds an invitation by the raw token and misses an unknown token', function () {
    $service = app(InvitationTokenService::class);
    $token = str_repeat('a', InvitationTokenService::INVITATION_TOKEN_LENGTH);
    $unknown = str_repeat('b', InvitationTokenService::INVITATION_TOKEN_LENGTH);
    $invitation = Invitation::factory()->create([
        'email' => 'invitee@example.com',
        'token_hash' => hash('sha256', $token),
    ]);
    Invitation::factory()->create([
        'email' => 'other@example.com',
        'token_hash' => hash('sha256', $unknown),
    ]);

    expect($service->findByToken($token)?->is($invitation))->toBeTrue()
        ->and($service->findByToken(str_repeat('c', InvitationTokenService::INVITATION_TOKEN_LENGTH)))->toBeNull();
});
