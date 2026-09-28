<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Support\Carbon;

it('casts every users column', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $user = User::factory()->companyAdmin($company)->create([
        'email' => 'admin@example.com',
        'deactivated_at' => '2026-01-15 12:00:00',
        'session_version' => 2,
        'email_verified_at' => '2026-01-15 11:00:00',
    ]);

    expect($user->getCasts())->toMatchArray([
        'id' => 'integer',
        'email' => 'string',
        'password' => 'hashed',
        'role' => Role::class,
        'company_id' => 'integer',
        'deactivated_at' => 'datetime',
        'session_version' => 'integer',
        'email_verified_at' => 'datetime',
        'remember_token' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ])
        ->and($user->id)->toBeInt()
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->role)->toBe(Role::CompanyAdmin)
        ->and($user->company_id)->toBe($company->id)
        ->and($user->deactivated_at)->toEqual(Carbon::parse('2026-01-15 12:00:00'))
        ->and($user->session_version)->toBe(2)
        ->and($user->email_verified_at)->toEqual(Carbon::parse('2026-01-15 11:00:00'))
        ->and($user->created_at)->toBeInstanceOf(Carbon::class)
        ->and($user->updated_at)->toBeInstanceOf(Carbon::class);
});

it('builds each factory state', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $superAdmin = User::factory()->superAdmin()->create([
        'email' => 'owner@example.com',
    ]);

    $admin = User::factory()->companyAdmin($company)->create([
        'email' => 'admin@example.com',
    ]);

    $adminFromId = User::factory()->companyAdmin($company->id)->create([
        'email' => 'admin-id@example.com',
    ]);

    $viewer = User::factory()->viewer($company)->create([
        'email' => 'viewer@example.com',
    ]);

    $viewerFromId = User::factory()->viewer($company->id)->create([
        'email' => 'viewer-id@example.com',
    ]);

    $deactivated = User::factory()->viewer($company)->deactivated()->create([
        'email' => 'former@example.com',
    ]);

    expect($superAdmin->role)->toBe(Role::SuperAdmin)
        ->and($superAdmin->company_id)->toBeNull()
        ->and($superAdmin->deactivated_at)->toBeNull()
        ->and($superAdmin->session_version)->toBe(1)
        ->and($admin->role)->toBe(Role::CompanyAdmin)
        ->and($admin->company_id)->toBe($company->id)
        ->and($adminFromId->role)->toBe(Role::CompanyAdmin)
        ->and($adminFromId->company_id)->toBe($company->id)
        ->and($viewer->role)->toBe(Role::Viewer)
        ->and($viewer->company_id)->toBe($company->id)
        ->and($viewerFromId->role)->toBe(Role::Viewer)
        ->and($viewerFromId->company_id)->toBe($company->id)
        ->and($deactivated->role)->toBe(Role::Viewer)
        ->and($deactivated->company_id)->toBe($company->id)
        ->and($deactivated->deactivated_at)->toEqual(Carbon::parse('2026-01-15 12:00:00'));
});

it('exposes the actor contract and hides the password', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $user = User::factory()->viewer($company)->create([
        'email' => 'viewer@example.com',
        'session_version' => 4,
    ]);

    expect($user)->toBeInstanceOf(Actor::class)
        ->and($user->actorId())->toBe($user->id)
        ->and($user->actorRole())->toBe(Role::Viewer)
        ->and($user->actorCompanyId())->toBe($company->id)
        ->and($user->sessionVersion())->toBe(4)
        ->and($user->toArray())->not->toHaveKey('password')
        ->and($user->toArray())->not->toHaveKey('remember_token');
});
