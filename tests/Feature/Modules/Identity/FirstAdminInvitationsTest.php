<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\InviteCompanyUserAction;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

it('lets a super admin manage first-admin invitations until one is accepted', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    Notification::fake();
    $owner = ownerSuperAdmin();
    $company = acmeCompany();
    $other = globexCompany();
    $invitation = invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'first@acme.test',
        'role' => Role::CompanyAdmin,
    ]);
    $foreign = invitationFor($other, SAMPLE_INVITATION_TOKEN_ALT, [
        'email' => 'first@globex.test',
        'role' => Role::CompanyAdmin,
    ]);

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies/'.$company->id.'/invitations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $invitation->id)
        ->assertJsonPath('data.0.email', 'first@acme.test')
        ->assertJsonPath('data.0.role', 'company_admin');

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations', [
            'email' => 'replacement@acme.test',
            'role' => 'super_admin',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'replacement@acme.test')
        ->assertJsonPath('data.role', 'company_admin');

    $replacement = Invitation::query()->where('email', 'replacement@acme.test')->firstOrFail();

    expect($replacement->role)->toBe(Role::CompanyAdmin)
        ->and($replacement->company_id)->toBe($company->id);

    Carbon::setTestNow('2026-01-16 12:00:00');
    $expiresAt = Carbon::parse('2026-01-16 12:00:00')
        ->addDays(InviteCompanyUserAction::INVITATION_LIFETIME_DAYS)
        ->utc()
        ->toIso8601String();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations/'.$invitation->id.'/resend')
        ->assertOk()
        ->assertJsonPath('data.email', 'first@acme.test')
        ->assertJsonPath('data.role', 'company_admin')
        ->assertJsonPath('data.expires_at', $expiresAt);

    $tokens = array_map(
        fn (InvitationNotification $notification): string => $notification->token(),
        sentInvitationNotifications(),
    );
    $replacementToken = $tokens[0];
    $resentToken = $tokens[1];

    expect($tokens)->toHaveCount(2)
        ->and($resentToken)->not->toBe(SAMPLE_INVITATION_TOKEN);

    $this->getJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN)
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    $this->getJson('/api/v1/invitations/'.$resentToken)
        ->assertOk()
        ->assertJsonPath('data.email', 'first@acme.test');

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations/'.$replacement->id.'/revoke')
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $this->getJson('/api/v1/invitations/'.$replacementToken)
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations/'.$foreign->id.'/resend')
        ->assertNotFound();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations/'.$foreign->id.'/revoke')
        ->assertNotFound();

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies/999999/invitations')
        ->assertNotFound();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/999999/invitations', [
            'email' => 'another@acme.test',
        ])
        ->assertNotFound();

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.$resentToken.'/accept', [
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk();

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies/'.$company->id.'/invitations')
        ->assertForbidden();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations', [
            'email' => 'later@acme.test',
        ])
        ->assertForbidden();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations/'.$invitation->id.'/resend')
        ->assertForbidden();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/invitations/'.$invitation->id.'/revoke')
        ->assertForbidden();
});
