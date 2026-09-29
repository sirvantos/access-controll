<?php

declare(strict_types=1);

use App\Modules\Identity\Console\CreateSuperAdminCommand;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Support\Facades\Route;

const SUPER_ADMIN_EMAIL = 'owner@example.com';

it('creates a super admin who can sign in', function () {
    $password = 'super-secret';

    $this->artisan('identity:create-super-admin', ['email' => 'Owner@Example.com'])
        ->expectsQuestion(__('identity.password'), $password)
        ->expectsQuestion(__('identity.password_confirmation'), $password)
        ->expectsOutputToContain(__('identity.super_admin_created', ['email' => SUPER_ADMIN_EMAIL]))
        ->doesntExpectOutputToContain($password)
        ->assertExitCode(0);

    $user = withoutCompanyIsolation(
        fn () => User::query()->where('email', SUPER_ADMIN_EMAIL)->first(),
    );

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(Role::SuperAdmin)
        ->and($user->company_id)->toBeNull();

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => SUPER_ADMIN_EMAIL,
            'password' => $password,
        ])
        ->assertOk()
        ->assertJsonPath('data.role', 'super_admin')
        ->assertJsonPath('data.email', SUPER_ADMIN_EMAIL);
});

it('refuses an email that is already registered in another case', function () {
    acmeAdmin(['email' => SUPER_ADMIN_EMAIL]);

    $this->artisan('identity:create-super-admin', ['email' => 'OWNER@example.com'])
        ->expectsQuestion(__('identity.password'), SAMPLE_PASSWORD)
        ->expectsQuestion(__('identity.password_confirmation'), SAMPLE_PASSWORD)
        ->expectsOutputToContain(__('identity.email_already_registered'))
        ->assertExitCode(1);

    expect(withoutCompanyIsolation(fn () => User::query()->where('role', Role::SuperAdmin)->count()))->toBe(0)
        ->and(withoutCompanyIsolation(fn () => User::query()->count()))->toBe(1);
});

it('refuses a password shorter than 8 characters', function () {
    $password = '1234567';

    $this->artisan('identity:create-super-admin', ['email' => SUPER_ADMIN_EMAIL])
        ->expectsQuestion(__('identity.password'), $password)
        ->expectsQuestion(__('identity.password_confirmation'), $password)
        ->expectsOutputToContain(__('validation.min.string', ['attribute' => 'password', 'min' => 8]))
        ->doesntExpectOutputToContain($password)
        ->assertExitCode(1);

    expect(withoutCompanyIsolation(fn () => User::query()->count()))->toBe(0);
});

it('refuses a password confirmation that does not match', function () {
    $this->artisan('identity:create-super-admin', ['email' => SUPER_ADMIN_EMAIL])
        ->expectsQuestion(__('identity.password'), SAMPLE_PASSWORD)
        ->expectsQuestion(__('identity.password_confirmation'), 'different-password')
        ->expectsOutputToContain(__('validation.confirmed', ['attribute' => 'password']))
        ->doesntExpectOutputToContain(SAMPLE_PASSWORD)
        ->assertExitCode(1);

    expect(withoutCompanyIsolation(fn () => User::query()->count()))->toBe(0);
});

it('refuses an invalid email', function () {
    $this->artisan('identity:create-super-admin', ['email' => 'not-an-email'])
        ->expectsOutputToContain(__('validation.email', ['attribute' => 'email']))
        ->assertExitCode(1);

    expect(withoutCompanyIsolation(fn () => User::query()->count()))->toBe(0);
});

it('has no password option and no http route that creates a super admin', function () {
    $command = $this->app->make(CreateSuperAdminCommand::class);

    expect($command->getDefinition()->hasOption('password'))->toBeFalse()
        ->and($command->getDefinition()->hasArgument('password'))->toBeFalse();

    $createsSuperAdmin = collect(Route::getRoutes())->contains(
        fn (Illuminate\Routing\Route $route): bool => str_contains($route->uri(), 'super'),
    );

    expect($createsSuperAdmin)->toBeFalse();

    withoutCompanyIsolation(function (): void {
        signedInAs(ownerSuperAdmin());
        $this->flushSession();
        auth('web')->logout();

        $this->withHeaders(statefulHeaders())
            ->postJson('/api/v1/admin/super-admins', [
                'email' => 'second-owner@example.com',
                'password' => SAMPLE_PASSWORD,
            ])
            ->assertMethodNotAllowed();

        expect(User::query()->where('email', 'second-owner@example.com')->exists())->toBeFalse();
    });
});
