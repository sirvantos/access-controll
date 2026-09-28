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
        ->assertJsonPath('data.0.is_active', true)
        ->assertJsonPath('data.0.awaiting_first_admin', true)
        ->assertJsonPath('data.0.created_at', $createdAt)
        ->assertJsonPath('data.1.id', $globex->id)
        ->assertJsonPath('data.1.name', 'Globex')
        ->assertJsonPath('data.1.is_active', true)
        ->assertJsonPath('data.1.awaiting_first_admin', false)
        ->assertJsonPath('data.1.created_at', $createdAt)
        ->assertJsonPath('data.2.id', $initech->id)
        ->assertJsonPath('data.2.name', 'Initech')
        ->assertJsonPath('data.2.is_active', false)
        ->assertJsonPath('data.2.awaiting_first_admin', true)
        ->assertJsonPath('data.2.created_at', $createdAt)
        ->assertJsonPath('meta.per_page', ListCompaniesAction::COMPANY_LIST_PAGE_SIZE)
        ->assertJsonPath('meta.total', 3);

    expect(ListCompaniesAction::COMPANY_LIST_PAGE_SIZE)->toBe(15);
});
