<?php

declare(strict_types=1);

use App\Modules\Companies\Actions\ListCompaniesAction;
use App\Modules\Companies\Models\Company;
use Carbon\Carbon;

it('lists every company with its state and whether it still awaits a first admin', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $createdAt = Carbon::parse('2026-01-15 12:00:00')->utc()->toIso8601String();
    $acme = acmeCompany();
    $globex = globexCompany();
    globexAdmin(['company_id' => $globex->id]);
    $initech = Company::factory()->deactivated()->create(['name' => 'Initech']);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $acme->id)
        ->assertJsonPath('data.0.name', 'Acme')
        ->assertJsonPath('data.0.bin', null)
        ->assertJsonPath('data.0.is_active', true)
        ->assertJsonPath('data.0.awaiting_first_admin', true)
        ->assertJsonPath('data.0.created_at', $createdAt)
        ->assertJsonPath('data.1.id', $globex->id)
        ->assertJsonPath('data.1.name', 'Globex')
        ->assertJsonPath('data.1.bin', null)
        ->assertJsonPath('data.1.is_active', true)
        ->assertJsonPath('data.1.awaiting_first_admin', false)
        ->assertJsonPath('data.1.created_at', $createdAt)
        ->assertJsonPath('data.2.id', $initech->id)
        ->assertJsonPath('data.2.name', 'Initech')
        ->assertJsonPath('data.2.bin', null)
        ->assertJsonPath('data.2.is_active', false)
        ->assertJsonPath('data.2.awaiting_first_admin', true)
        ->assertJsonPath('data.2.created_at', $createdAt)
        ->assertJsonPath('meta.per_page', ListCompaniesAction::COMPANY_LIST_PAGE_SIZE)
        ->assertJsonPath('meta.total', 3);

    expect(ListCompaniesAction::COMPANY_LIST_PAGE_SIZE)->toBe(15);
});

it('searches companies by part of the name or BIN and ignores case and surrounding spaces', function () {
    $alma = acmeCompany([
        'name' => 'Alma Stroy',
        'bin' => '123456789012',
    ]);
    Company::factory()->create([
        'name' => 'Алма Строй',
        'bin' => null,
    ]);
    Company::factory()->deactivated()->create([
        'name' => 'Other Works',
        'bin' => '999999999999',
    ]);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies?search=STROY')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $alma->id)
        ->assertJsonPath('data.0.name', 'Alma Stroy')
        ->assertJsonPath('data.0.bin', '123456789012');

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies?search='.rawurlencode('  stroy  '))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $alma->id);

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies?search='.rawurlencode('строй'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Алма Строй');

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies?search=4567')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.bin', '123456789012');
});

it('returns an empty list when the search matches no company', function () {
    acmeCompany();
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies?search=zzzz-none')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

it('paginates fifteen companies and applies search before pagination', function () {
    foreach (range(1, 15) as $index) {
        Company::factory()->create([
            'name' => 'Paged '.$index,
            'bin' => null,
        ]);
    }

    $alma = Company::factory()->create([
        'name' => 'Alma Stroy',
        'bin' => '123456789012',
    ]);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies')
        ->assertOk()
        ->assertJsonCount(ListCompaniesAction::COMPANY_LIST_PAGE_SIZE, 'data')
        ->assertJsonPath('meta.per_page', ListCompaniesAction::COMPANY_LIST_PAGE_SIZE)
        ->assertJsonPath('meta.total', 16)
        ->assertJsonMissing(['id' => $alma->id]);

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies?search=stroy')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $alma->id)
        ->assertJsonPath('meta.per_page', ListCompaniesAction::COMPANY_LIST_PAGE_SIZE)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.current_page', 1);
});

it('rejects a search longer than 255 characters', function () {
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->getJson('/api/v1/admin/companies?search='.str_repeat('a', 256))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search']);
});

it('refuses the company list to a company admin and a viewer', function (callable $actor) {
    $user = $actor();

    signedInAs($user)
        ->getJson('/api/v1/admin/companies')
        ->assertForbidden();
})->with([
    'company admin' => [fn () => acmeAdmin()],
    'viewer' => [fn () => acmeViewer()],
]);
