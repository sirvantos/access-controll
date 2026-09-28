<?php

declare(strict_types=1);

use App\Exceptions\LastActiveAdminException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;

it('lets a promoted viewer open company users on the next request', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($admin)
        ->patchJson('/api/v1/company/users/'.$viewer->id, ['role' => Role::CompanyAdmin->value])
        ->assertOk()
        ->assertJsonPath('data.id', $viewer->id)
        ->assertJsonPath('data.email', 'viewer@acme.test')
        ->assertJsonPath('data.role', Role::CompanyAdmin->value)
        ->assertJsonPath('data.is_active', true);

    $this->flushSession();
    auth()->forgetGuards();

    signedInAs($viewer->fresh())
        ->getJson('/api/v1/company/users')
        ->assertOk();
});

it('refuses company user management to a demoted admin on the next request', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $second = User::factory()->companyAdmin($company->id)->create([
        'email' => 'second@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);

    signedInAs($admin)
        ->patchJson('/api/v1/company/users/'.$second->id, ['role' => Role::Viewer->value])
        ->assertOk()
        ->assertJsonPath('data.role', Role::Viewer->value);

    $this->flushSession();
    auth()->forgetGuards();

    signedInAs($second->fresh())
        ->getJson('/api/v1/company/users')
        ->assertForbidden();
});

it('refuses to demote the only active admin, including that admin', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $logs = captureLogEvents();

    signedInAs($admin)
        ->patchJson('/api/v1/company/users/'.$admin->id, ['role' => Role::Viewer->value])
        ->assertConflict()
        ->assertJsonPath('error_code', LastActiveAdminException::ERROR_CODE)
        ->assertJsonPath('message', __('identity.last_active_admin'));

    expectNothingLogged($logs);

    $admin->refresh();

    expect($admin->role)->toBe(Role::CompanyAdmin);
});

it('lets one of two admins demote the other or themselves', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $second = User::factory()->companyAdmin($company->id)->create([
        'email' => 'second@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);

    signedInAs($admin)
        ->patchJson('/api/v1/company/users/'.$second->id, ['role' => Role::Viewer->value])
        ->assertOk()
        ->assertJsonPath('data.role', Role::Viewer->value);

    signedInAs($admin)
        ->patchJson('/api/v1/company/users/'.$second->id, ['role' => Role::CompanyAdmin->value])
        ->assertOk()
        ->assertJsonPath('data.role', Role::CompanyAdmin->value);

    signedInAs($admin)
        ->patchJson('/api/v1/company/users/'.$admin->id, ['role' => Role::Viewer->value])
        ->assertOk()
        ->assertJsonPath('data.role', Role::Viewer->value);

    expect(User::query()
        ->where('company_id', $company->id)
        ->where('role', Role::CompanyAdmin)
        ->whereNull('deactivated_at')
        ->count())->toBe(1);
});

it('refuses to delete a company user', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($admin)
        ->deleteJson('/api/v1/company/users/'.$viewer->id)
        ->assertMethodNotAllowed();

    expect(User::query()->whereKey($viewer->id)->exists())->toBeTrue();
});

it('refuses a super admin role and an unknown role', function (string $role) {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);

    signedInAs($admin)
        ->patchJson('/api/v1/company/users/'.$viewer->id, ['role' => $role])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($viewer->fresh()?->role)->toBe(Role::Viewer);
})->with([
    'super admin' => Role::SuperAdmin->value,
    'unknown' => 'owner',
]);
