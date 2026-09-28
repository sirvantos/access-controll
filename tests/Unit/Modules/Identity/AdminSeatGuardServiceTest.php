<?php

declare(strict_types=1);

use App\Exceptions\LastActiveAdminException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AdminSeatGuardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

it('refuses a change that would remove the only active admin', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $ran = false;

    expect(fn () => app(AdminSeatGuardService::class)->execute(
        $company->id,
        $admin->id,
        function () use (&$ran): void {
            $ran = true;
        },
    ))->toThrow(LastActiveAdminException::class);

    expect($ran)->toBeFalse();
});

it('allows a change when another active admin would remain', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $second = User::factory()->companyAdmin($company->id)->create([
        'email' => 'second@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);
    $ran = false;

    app(AdminSeatGuardService::class)->execute($company->id, $second->id, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue()
        ->and($admin->exists)->toBeTrue();
});

it('allows a change for a company with zero active admins', function () {
    $company = acmeCompany();
    $viewer = acmeViewer(['company_id' => $company->id]);
    $ran = false;

    app(AdminSeatGuardService::class)->execute($company->id, $viewer->id, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('does not count a deactivated admin as an active seat', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    User::factory()->companyAdmin($company->id)->deactivated()->create([
        'email' => 'former-admin@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);

    expect(fn () => app(AdminSeatGuardService::class)->execute(
        $company->id,
        $admin->id,
        fn () => null,
    ))->toThrow(LastActiveAdminException::class);
});

it('waits for a held company admin lock instead of failing', function () {
    $company = acmeCompany();
    acmeAdmin(['company_id' => $company->id]);
    $second = User::factory()->companyAdmin($company->id)->create([
        'email' => 'second@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);
    $guard = app(AdminSeatGuardService::class);

    expect($guard->lockName($company->id))->toBe('identity:company-admins:'.$company->id);

    $lock = Cache::lock($guard->lockName($company->id), AdminSeatGuardService::LOCK_TTL_SECONDS);
    expect($lock->get())->toBeTrue();

    Sleep::fake();

    try {
        Sleep::whenFakingSleep(function () use ($lock): void {
            $lock->forceRelease();
        });

        $ran = false;

        $guard->execute($company->id, $second->id, function () use (&$ran): void {
            $ran = true;
        });

        expect($ran)->toBeTrue();
        Sleep::assertSleptTimes(1);
    } finally {
        Sleep::fake(false);
    }
});
