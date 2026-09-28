<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\SessionValidity;
use App\Modules\Identity\Services\AccountEligibilityService;

it('treats an active user of an active company as eligible and current', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $user = User::factory()->viewer($company)->create([
        'email' => 'viewer@example.com',
        'session_version' => 3,
    ]);

    $service = app(AccountEligibilityService::class);

    expect(app(SessionValidity::class))->toBeInstanceOf(AccountEligibilityService::class)
        ->and($service->isEligible($user))->toBeTrue()
        ->and($service->isCurrent($user, 3))->toBeTrue();
});

it('rejects a deactivated user', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $user = User::factory()->companyAdmin($company)->deactivated()->create([
        'email' => 'admin@example.com',
        'session_version' => 1,
    ]);

    $service = app(AccountEligibilityService::class);

    expect($service->isEligible($user))->toBeFalse()
        ->and($service->isCurrent($user, 1))->toBeFalse();
});

it('rejects an active user of a deactivated company', function () {
    $company = Company::factory()->deactivated()->create([
        'name' => 'Acme',
    ]);

    $user = User::factory()->companyAdmin($company)->create([
        'email' => 'admin@example.com',
        'session_version' => 1,
    ]);

    $service = app(AccountEligibilityService::class);

    expect($user->deactivated_at)->toBeNull()
        ->and($service->isEligible($user))->toBeFalse()
        ->and($service->isCurrent($user, 1))->toBeFalse();
});

it('treats an active super admin as eligible and a deactivated super admin as ineligible', function () {
    $active = User::factory()->superAdmin()->create([
        'email' => 'owner@example.com',
        'session_version' => 1,
    ]);

    $deactivated = User::factory()->superAdmin()->deactivated()->create([
        'email' => 'former-owner@example.com',
        'session_version' => 2,
    ]);

    $service = app(AccountEligibilityService::class);

    expect($service->isEligible($active))->toBeTrue()
        ->and($service->isCurrent($active, 1))->toBeTrue()
        ->and($service->isEligible($deactivated))->toBeFalse()
        ->and($service->isCurrent($deactivated, 2))->toBeFalse();
});

it('rejects a null stored session version and a stale stored session version', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $user = User::factory()->viewer($company)->create([
        'email' => 'viewer@example.com',
        'session_version' => 2,
    ]);

    $service = app(AccountEligibilityService::class);

    expect($service->isEligible($user))->toBeTrue()
        ->and($service->isCurrent($user, null))->toBeFalse()
        ->and($service->isCurrent($user, 1))->toBeFalse()
        ->and($service->isCurrent($user, 2))->toBeTrue();
});
