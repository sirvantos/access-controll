<?php

declare(strict_types=1);

use App\Modules\Companies\Actions\ListCompaniesAction;
use App\Modules\Identity\Actions\AcceptInvitationAction;
use App\Modules\Identity\Actions\CreateSuperAdminAction;
use App\Modules\Identity\Actions\RequestPasswordResetAction;
use App\Modules\Identity\Actions\ResetPasswordAction;
use App\Modules\Identity\Actions\SignInAction;
use App\Modules\Identity\Data\PasswordResetData;
use App\Modules\Identity\Data\SignInAttempt;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

it('finds users on sign-in without a bound company context', function (): void {
    $company = acmeCompany();
    $user = acmeAdmin(['company_id' => $company->id, 'email' => 'sign-in@acme.test']);

    $actor = app(SignInAction::class)(new SignInAttempt(
        email: Str::of('sign-in@acme.test'),
        password: Str::of(SAMPLE_PASSWORD),
        ip: '127.0.0.1',
    ));

    expect($actor->actorId())->toBe($user->id);
});

it('lists every company without a bound company context', function (): void {
    acmeCompany();
    globexCompany();

    $paginator = app(ListCompaniesAction::class)(null, 1);

    expect($paginator->total())->toBe(2);
});

it('creates a super admin with a null company id', function (): void {
    $user = app(CreateSuperAdminAction::class)('owner@example.com', 'password');

    expect($user->role)->toBe(Role::SuperAdmin)
        ->and($user->company_id)->toBeNull();
});

it('requests a password reset without a bound company context', function (): void {
    Notification::fake();
    $admin = acmeAdmin(['email' => 'reset-broker@acme.test']);

    app(RequestPasswordResetAction::class)(Str::of('reset-broker@acme.test'));

    Notification::assertSentTo($admin, ResetPasswordNotification::class);
});

it('resets a password without a bound company context', function (): void {
    $admin = acmeAdmin(['email' => 'reset-complete@acme.test']);
    $token = Password::broker()->createToken($admin);
    $newPassword = 'new-secure-password';

    app(ResetPasswordAction::class)(new PasswordResetData(
        email: Str::of('reset-complete@acme.test'),
        token: Str::of($token),
        password: Str::of($newPassword),
    ));

    expect(Hash::check($newPassword, $admin->fresh()->password))->toBeTrue();
});

it('keeps the invitation company id when accepting an invitation', function (): void {
    $company = acmeCompany();
    $token = SAMPLE_INVITATION_TOKEN;
    invitationFor($company, $token, [
        'email' => 'accepted@acme.test',
    ]);

    app(AcceptInvitationAction::class)($token, Str::of('new-password-1'));

    $created = app(CompanyContext::class)->withoutIsolation(
        fn (): ?User => User::query()->where('email', 'accepted@acme.test')->first(),
    );

    expect($created)->not->toBeNull()
        ->and($created->company_id)->toBe($company->id);
});
