<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('derives pending, accepted, revoked, and expired in that rule order', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));

    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $pending = Invitation::factory()->create([
        'company_id' => $company->id,
        'email' => 'pending@example.com',
        'role' => Role::Viewer,
        'token_hash' => hash('sha256', 'pending-token'),
        'expires_at' => '2026-01-15 12:00:01',
        'accepted_at' => null,
        'revoked_at' => null,
    ]);

    $accepted = Invitation::factory()->accepted()->create([
        'company_id' => $company->id,
        'email' => 'accepted@example.com',
        'role' => Role::CompanyAdmin,
        'token_hash' => hash('sha256', 'accepted-token'),
        'expires_at' => '2026-01-15 11:00:00',
        'revoked_at' => '2026-01-15 11:30:00',
    ]);

    $revoked = Invitation::factory()->revoked()->create([
        'company_id' => $company->id,
        'email' => 'revoked@example.com',
        'role' => Role::Viewer,
        'token_hash' => hash('sha256', 'revoked-token'),
        'expires_at' => '2026-01-15 11:00:00',
        'accepted_at' => null,
    ]);

    $expired = Invitation::factory()->expired()->create([
        'company_id' => $company->id,
        'email' => 'expired@example.com',
        'role' => Role::CompanyAdmin,
        'token_hash' => hash('sha256', 'expired-token'),
        'accepted_at' => null,
        'revoked_at' => null,
    ]);

    expect($pending->state())->toBe(InvitationState::Pending)
        ->and($accepted->state())->toBe(InvitationState::Accepted)
        ->and($revoked->state())->toBe(InvitationState::Revoked)
        ->and($expired->state())->toBe(InvitationState::Expired);
});

it('treats an invitation that expires at the current instant as expired', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));

    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $invitation = Invitation::factory()->create([
        'company_id' => $company->id,
        'email' => 'boundary@example.com',
        'role' => Role::Viewer,
        'token_hash' => hash('sha256', 'boundary-token'),
        'expires_at' => '2026-01-15 12:00:00',
        'accepted_at' => null,
        'revoked_at' => null,
    ]);

    expect($invitation->state())->toBe(InvitationState::Expired)
        ->and($invitation->expires_at)->toEqual(Carbon::parse('2026-01-15 12:00:00'));
});
