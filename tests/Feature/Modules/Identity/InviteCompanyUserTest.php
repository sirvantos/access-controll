<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\InviteCompanyUserAction;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

it('refuses a signed-in viewer', function () {
    Notification::fake();
    $viewer = acmeViewer();

    signedInAs($viewer)
        ->postJson('/api/v1/company/invitations', [
            'email' => 'new@acme.test',
            'role' => 'viewer',
        ])
        ->assertForbidden();

    Notification::assertNothingSent();
    expect(Invitation::query()->count())->toBe(0);
});

it('invites a viewer and a company admin', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    Notification::fake();
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $expiresAt = Carbon::parse('2026-01-15 12:00:00')
        ->addDays(InviteCompanyUserAction::INVITATION_LIFETIME_DAYS)
        ->utc()
        ->toIso8601String();
    $payloads = [
        ['email' => 'Viewer.Invite@Acme.Test', 'role' => 'viewer', 'stored' => 'viewer.invite@acme.test'],
        ['email' => 'admin.invite@acme.test', 'role' => 'company_admin', 'stored' => 'admin.invite@acme.test'],
    ];
    $responses = [];

    foreach ($payloads as $payload) {
        $responses[] = signedInAs($admin)
            ->postJson('/api/v1/company/invitations', [
                'email' => $payload['email'],
                'role' => $payload['role'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', $payload['stored'])
            ->assertJsonPath('data.role', $payload['role'])
            ->assertJsonPath('data.expires_at', $expiresAt);
    }

    $tokens = array_map(
        fn (InvitationNotification $notification): string => $notification->token(),
        sentInvitationNotifications(),
    );

    expect(InviteCompanyUserAction::INVITATION_LIFETIME_DAYS)->toBe(7)
        ->and(Invitation::query()->where('company_id', $company->id)->orderBy('id')->pluck('email')->all())
        ->toBe(['viewer.invite@acme.test', 'admin.invite@acme.test'])
        ->and($tokens)->toHaveCount(2);

    foreach ($responses as $response) {
        foreach ($tokens as $token) {
            expect($response->getContent())->not->toContain($token);
        }
    }

    Notification::assertSentOnDemandTimes(InvitationNotification::class, 2);
});

it('refuses an email that belongs to an existing user', function (string $locale, bool $deactivated) {
    app()->setLocale($locale);
    Notification::fake();
    $logs = captureLogEvents();
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $email = $deactivated ? 'Former@Acme.Test' : $admin->email;

    if ($deactivated) {
        User::factory()->viewer($company)->deactivated()->create([
            'email' => $email,
            'password' => SAMPLE_PASSWORD,
            'company_id' => $company->id,
        ]);
    }

    signedInAs($admin)
        ->postJson('/api/v1/company/invitations', [
            'email' => $deactivated ? 'former@acme.test' : 'Admin@Acme.Test',
            'role' => 'viewer',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', __('identity.email_already_registered'));

    Notification::assertNothingSent();
    expectNothingLogged($logs);
    expect(Invitation::query()->count())->toBe(0);
})->with([
    'active en' => ['en', false],
    'active ru' => ['ru', false],
    'deactivated en' => ['en', true],
    'deactivated ru' => ['ru', true],
]);

it('rejects the super admin role and unknown roles', function (string $role) {
    Notification::fake();
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);

    signedInAs($admin)
        ->postJson('/api/v1/company/invitations', [
            'email' => 'new@acme.test',
            'role' => $role,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    Notification::assertNothingSent();
    expect(Invitation::query()->count())->toBe(0);
})->with(['super_admin', 'owner']);
