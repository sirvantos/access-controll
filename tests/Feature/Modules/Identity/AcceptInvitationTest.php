<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use Carbon\Carbon;

it('previews a pending invitation and accepts it without signing in', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $company = acmeCompany();
    $invitation = invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@acme.test',
        'role' => Role::CompanyAdmin,
        'expires_at' => '2030-01-15 12:00:00',
    ]);

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN)
        ->assertOk()
        ->assertJsonPath('data.email', 'invitee@acme.test')
        ->assertJsonPath('data.role', 'company_admin')
        ->assertJsonPath('data.expires_at', $invitation->expires_at->copy()->utc()->toIso8601String());

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    expect(auth('web')->check())->toBeFalse();

    $user = withoutCompanyIsolation(
        fn () => User::query()->where('email', 'invitee@acme.test')->first(),
    );

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(Role::CompanyAdmin)
        ->and($user->company_id)->toBe($company->id)
        ->and($user->deactivated_at)->toBeNull()
        ->and(withoutCompanyIsolation(fn () => $invitation->refresh()->accepted_at))->not->toBeNull();

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => 'invitee@acme.test',
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk()
        ->assertJsonPath('data.role', 'company_admin');

    $logs = captureLogEvents();

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN)
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid')
        ->assertJsonPath('message', __('identity.invitation_invalid'));

    expectNothingLogged($logs);
});

it('rejects an invitation that is no longer open', function (string $state) {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $logs = captureLogEvents();
    $company = acmeCompany();
    $overrides = match ($state) {
        'expired' => ['expires_at' => '2026-01-15 11:00:00'],
        'revoked' => ['revoked_at' => '2026-01-15 11:00:00'],
        'accepted' => ['accepted_at' => '2026-01-15 11:00:00'],
        default => throw new InvalidArgumentException($state),
    };
    invitationFor($company, SAMPLE_INVITATION_TOKEN, $overrides);

    foreach (['en', 'ru'] as $locale) {
        app()->setLocale($locale);

        $preview = $this->withHeaders(statefulHeaders())
            ->getJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN);
        $accept = $this->withHeaders(statefulHeaders())
            ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
                'password' => SAMPLE_PASSWORD,
            ]);

        $preview->assertGone()
            ->assertJsonPath('error_code', 'invitation_invalid')
            ->assertJsonPath('message', __('identity.invitation_invalid'));
        $accept->assertGone()
            ->assertJsonPath('error_code', 'invitation_invalid')
            ->assertJsonPath('message', __('identity.invitation_invalid'));
    }

    expectNothingLogged($logs);
    expect(withoutCompanyIsolation(
        fn () => User::query()->where('email', 'invitee@acme.test')->exists(),
    ))->toBeFalse();
})->with(['expired', 'revoked', 'accepted']);

it('rejects an unknown invitation token', function () {
    $logs = captureLogEvents();

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN)
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    expectNothingLogged($logs);
});

it('rejects a pending invitation when the email is already registered', function () {
    $logs = captureLogEvents();
    $company = acmeCompany();
    acmeAdmin(['company_id' => $company->id, 'email' => 'invitee@acme.test']);
    invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@acme.test',
    ]);

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN)
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    expectNothingLogged($logs);
    expect(withoutCompanyIsolation(fn () => Invitation::query()->first()?->accepted_at))->toBeNull()
        ->and(withoutCompanyIsolation(
            fn () => User::query()->where('email', 'invitee@acme.test')->count(),
        ))->toBe(1);
});

it('rejects a second pending invitation after the first is accepted', function () {
    $company = acmeCompany();
    invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@acme.test',
    ]);
    invitationFor($company, SAMPLE_INVITATION_TOKEN_ALT, [
        'email' => 'invitee@acme.test',
    ]);

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk();

    $logs = captureLogEvents();

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN_ALT.'/accept', [
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertGone()
        ->assertJsonPath('error_code', 'invitation_invalid');

    expectNothingLogged($logs);
    expect(withoutCompanyIsolation(
        fn () => User::query()->where('email', 'invitee@acme.test')->count(),
    ))->toBe(1);
});

it('accepts an invitation for a deactivated company', function () {
    $company = Company::factory()->deactivated()->create(['name' => 'Closed Co']);
    invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@closed.test',
        'role' => Role::Viewer,
    ]);

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $user = withoutCompanyIsolation(
        fn () => User::query()->where('email', 'invitee@closed.test')->first(),
    );

    expect($user)->not->toBeNull()
        ->and($user->company_id)->toBe($company->id)
        ->and($user->deactivated_at)->toBeNull()
        ->and($user->role)->toBe(Role::Viewer)
        ->and(withoutCompanyIsolation(
            fn () => Invitation::query()->where('email', 'invitee@closed.test')->first()?->accepted_at,
        ))->not->toBeNull();
});

it('rejects a password outside the length rules', function (int $length) {
    $company = acmeCompany();
    $invitation = invitationFor($company, SAMPLE_INVITATION_TOKEN);

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/invitations/'.SAMPLE_INVITATION_TOKEN.'/accept', [
            'password' => str_repeat('a', $length),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(withoutCompanyIsolation(fn () => User::query()->count()))->toBe(0)
        ->and(withoutCompanyIsolation(fn () => $invitation->refresh()->accepted_at))->toBeNull();
})->with([
    'too short' => 7,
    'too long' => 129,
]);
