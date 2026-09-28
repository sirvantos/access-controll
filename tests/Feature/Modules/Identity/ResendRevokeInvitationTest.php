<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\InviteCompanyUserAction;
use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

it('rotates the token when a pending invitation is resent', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    Notification::fake();
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);

    signedInAs($admin)
        ->postJson('/api/v1/company/invitations', [
            'email' => 'invitee@acme.test',
            'role' => 'viewer',
        ])
        ->assertCreated();

    $invitation = Invitation::query()->where('email', 'invitee@acme.test')->firstOrFail();
    $originalHash = $invitation->token_hash;
    $originalToken = sentInvitationNotifications()[0]->token();

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/invitations/'.$originalToken)
        ->assertOk();

    Carbon::setTestNow('2026-01-16 12:00:00');
    $expiresAt = Carbon::parse('2026-01-16 12:00:00')
        ->addDays(InviteCompanyUserAction::INVITATION_LIFETIME_DAYS)
        ->utc()
        ->toIso8601String();

    signedInAs($admin)
        ->postJson('/api/v1/company/invitations/'.$invitation->id.'/resend')
        ->assertOk()
        ->assertJsonPath('data.email', 'invitee@acme.test')
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.expires_at', $expiresAt);

    $tokens = array_map(
        fn (InvitationNotification $notification): string => $notification->token(),
        sentInvitationNotifications(),
    );

    expect($tokens)->toHaveCount(2)
        ->and($tokens[1])->not->toBe($originalToken)
        ->and($invitation->refresh()->token_hash)->not->toBe($originalHash)
        ->and($invitation->expires_at?->equalTo(Carbon::parse('2026-01-23 12:00:00')))->toBeTrue();

    $this->getJson('/api/v1/invitations/'.$originalToken)
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    $this->getJson('/api/v1/invitations/'.$tokens[1])
        ->assertOk()
        ->assertJsonPath('data.email', 'invitee@acme.test');
});

it('revokes a pending invitation', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $invitation = invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@acme.test',
        'role' => Role::Viewer,
    ]);

    signedInAs($admin)
        ->postJson('/api/v1/company/invitations/'.$invitation->id.'/revoke')
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    expect($invitation->refresh()->revoked_at)->not->toBeNull()
        ->and($invitation->state())->toBe(InvitationState::Revoked);

    $this->getJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN)
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');
});

it('refuses to resend or revoke an invitation that is not pending', function (string $state) {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $logs = captureLogEvents();
    Notification::fake();
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $overrides = match ($state) {
        'expired' => ['expires_at' => '2026-01-15 11:00:00'],
        'revoked' => ['revoked_at' => '2026-01-15 11:00:00'],
        'accepted' => ['accepted_at' => '2026-01-15 11:00:00'],
        default => throw new InvalidArgumentException($state),
    };
    $invitation = invitationFor($company, SAMPLE_INVITATION_TOKEN, $overrides);

    signedInAs($admin);

    foreach (['en', 'ru'] as $locale) {
        app()->setLocale($locale);

        $resend = $this->postJson('/api/v1/company/invitations/'.$invitation->id.'/resend');
        $revoke = $this->postJson('/api/v1/company/invitations/'.$invitation->id.'/revoke');

        $resend->assertConflict()
            ->assertJsonPath('error_code', 'invitation_not_pending')
            ->assertJsonPath('message', __('identity.invitation_not_pending'));
        $revoke->assertConflict()
            ->assertJsonPath('error_code', 'invitation_not_pending')
            ->assertJsonPath('message', __('identity.invitation_not_pending'));
    }

    expectNothingLogged($logs);
    Notification::assertNothingSent();
})->with(['accepted', 'revoked', 'expired']);

it('refuses to resend an invitation after the email is registered', function (string $locale) {
    app()->setLocale($locale);
    Carbon::setTestNow('2026-01-15 12:00:00');
    $logs = captureLogEvents();
    Notification::fake();
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $invitation = invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@acme.test',
        'role' => Role::Viewer,
    ]);
    $tokenHash = $invitation->token_hash;
    User::factory()->viewer($company)->create([
        'email' => 'invitee@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);

    signedInAs($admin)
        ->postJson('/api/v1/company/invitations/'.$invitation->id.'/resend')
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', __('identity.email_already_registered'));

    expect($invitation->refresh()->token_hash)->toBe($tokenHash);
    Notification::assertNothingSent();
    expectNothingLogged($logs);
})->with(['en', 'ru']);

it('refuses a viewer from resending or revoking an invitation', function () {
    Notification::fake();
    $company = acmeCompany();
    $viewer = acmeViewer(['company_id' => $company->id]);
    $invitation = invitationFor($company, SAMPLE_INVITATION_TOKEN);

    signedInAs($viewer)
        ->postJson('/api/v1/company/invitations/'.$invitation->id.'/resend')
        ->assertForbidden();

    signedInAs($viewer)
        ->postJson('/api/v1/company/invitations/'.$invitation->id.'/revoke')
        ->assertForbidden();

    expect($invitation->refresh()->revoked_at)->toBeNull();
    Notification::assertNothingSent();
});
