<?php

declare(strict_types=1);

use App\Exceptions\LastActiveAdminException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;

it('refuses to deactivate the only active admin, including that admin', function (string $locale) {
    app()->setLocale($locale);
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $logs = captureLogEvents();

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$admin->id.'/deactivate')
        ->assertConflict()
        ->assertJsonPath('error_code', LastActiveAdminException::ERROR_CODE)
        ->assertJsonPath('message', __('identity.last_active_admin'));

    expectNothingLogged($logs);

    $admin->refresh();

    expect($admin->deactivated_at)->toBeNull()
        ->and($admin->role)->toBe(Role::CompanyAdmin);
})->with(['ru', 'en']);

it('lets one of two admins deactivate the other or themselves', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $second = User::factory()->companyAdmin($company->id)->create([
        'email' => 'second@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$second->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$second->id.'/reactivate')
        ->assertOk();

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$admin->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect(User::query()
        ->where('company_id', $company->id)
        ->where('role', Role::CompanyAdmin)
        ->whereNull('deactivated_at')
        ->count())->toBe(1);
});

it('leaves exactly one active admin after two sequential deactivations', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $second = User::factory()->companyAdmin($company->id)->create([
        'email' => 'second@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$second->id.'/deactivate')
        ->assertOk();

    $logs = captureLogEvents();

    signedInAs($admin)
        ->postJson('/api/v1/company/users/'.$admin->id.'/deactivate')
        ->assertConflict()
        ->assertJsonPath('error_code', LastActiveAdminException::ERROR_CODE);

    expectNothingLogged($logs);

    expect(User::query()
        ->where('company_id', $company->id)
        ->where('role', Role::CompanyAdmin)
        ->whereNull('deactivated_at')
        ->count())->toBe(1)
        ->and($admin->fresh()?->deactivated_at)->toBeNull()
        ->and($second->fresh()?->deactivated_at)->not->toBeNull();
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
