<?php

declare(strict_types=1);

use App\Exceptions\CompanyNotSelectedException;
use App\Modules\Tenancy\Data\SuperAdminActionType;
use App\Modules\Tenancy\Models\SuperAdminActionRecord;
use App\Modules\Tenancy\Services\SuperAdminActionRecorder;
use Illuminate\Support\Carbon;

it('returns the selected acme company', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => $acme->id])
        ->assertOk()
        ->assertJsonPath('data.id', $acme->id)
        ->assertJsonPath('data.name', 'Acme');
});

it('returns not found for an unknown company id', function (): void {
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => 9_999_999])
        ->assertNotFound();
});

it('explains an invalid company id', function (): void {
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => 'acme'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_id');
});

it('lets a super admin select a deactivated company', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    persistCompany($acme, ['deactivated_at' => '2026-01-15 12:00:00']);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => $acme->id])
        ->assertOk()
        ->assertJsonPath('data.id', $acme->id)
        ->assertJsonPath('data.name', 'Acme');
});

it('clears the tab selection without writing an action row', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => $acme->id])
        ->assertOk();

    signedInAs($owner)
        ->deleteJson('/api/v1/admin/selected-company')
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    expect(SuperAdminActionRecord::query()->count())->toBe(1)
        ->and(SuperAdminActionRecord::query()->value('type'))->toBe(SuperAdminActionType::SelectedCompany);
});

it('refuses a guest', function (): void {
    $this->postJson('/api/v1/admin/selected-company', ['company_id' => 1])
        ->assertUnauthorized();
});

it('refuses a company admin', function (): void {
    $admin = acmeAdmin();

    signedInAs($admin)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => $admin->company_id])
        ->assertForbidden();
});

it('stores a selection row with actor, time, company, and action', function (): void {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $logs = captureLogEvents();
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => $acme->id])
        ->assertOk();

    $record = SuperAdminActionRecord::query()->sole();

    expect($record->actor_id)->toBe($owner->id)
        ->and($record->company_id)->toBe($acme->id)
        ->and($record->type)->toBe(SuperAdminActionType::SelectedCompany)
        ->and($record->action)->toBe(SuperAdminActionRecorder::ACTION_SELECTED_COMPANY)
        ->and($record->occurred_at?->equalTo(Carbon::parse('2026-01-15 12:00:00')))->toBeTrue();

    expectNothingLogged($logs);
});

it('refuses company data without a selection and asks to select a company', function (): void {
    $logs = captureLogEvents();
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    $response = signedInAs($owner)->getJson('/api/v1/company');

    $response->assertConflict()
        ->assertJsonPath('error_code', CompanyNotSelectedException::ERROR_CODE)
        ->assertJsonMissingPath('data')
        ->assertJsonPath('message', __('tenancy.company_not_selected'));

    expect($response->json())->not->toHaveKey('data');

    expectNothingLogged($logs);
    expect($acme->name)->toBe('Acme');
});

it('lists only acme users for a super admin with acme selected', function (): void {
    $acme = acmeCompany();
    $acmeAdmin = acmeAdmin(['company_id' => $acme->id]);
    $globexAdmin = globexAdmin();
    $owner = ownerSuperAdmin();

    $emails = array_column(
        signedInWithSelectedCompany($owner, $acme->id)
            ->getJson('/api/v1/company/users')
            ->assertOk()
            ->json('data'),
        'email',
    );

    expect($emails)->toContain($acmeAdmin->email)
        ->and($emails)->not->toContain($globexAdmin->email);
});

it('shows acme after it is selected', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $acme->id)
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.id', $acme->id)
        ->assertJsonPath('data.name', 'Acme');
});

it('answers not found for a globex user identically to an unused id', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany(['name' => 'Globex']);
    $globexUser = globexAdmin(['company_id' => $globex->id]);
    $owner = ownerSuperAdmin();
    $payload = ['role' => 'viewer'];

    $known = signedInWithSelectedCompany($owner, $acme->id)
        ->patchJson('/api/v1/company/users/'.$globexUser->id, $payload);
    $unknown = signedInWithSelectedCompany($owner, $acme->id)
        ->patchJson('/api/v1/company/users/999999', $payload);

    expect($known->status())->toBe(404)
        ->and($unknown->status())->toBe(404)
        ->and($known->json())->toBe($unknown->json());
});

it('shows only globex after switching the selected company', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany(['name' => 'Globex']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $acme->id)
        ->postJson('/api/v1/admin/selected-company', ['company_id' => $globex->id])
        ->assertOk()
        ->assertJsonPath('data.name', 'Globex');

    signedInAs($owner)
        ->withHeaders(companyContextHeaders($globex->id))
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.id', $globex->id)
        ->assertJsonPath('data.name', 'Globex');
});

it('refuses a forged company context header for a never-selected id', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany(['name' => 'Globex']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $acme->id)
        ->withHeaders(companyContextHeaders($globex->id))
        ->getJson('/api/v1/company')
        ->assertConflict()
        ->assertJsonPath('error_code', CompanyNotSelectedException::ERROR_CODE);
});
