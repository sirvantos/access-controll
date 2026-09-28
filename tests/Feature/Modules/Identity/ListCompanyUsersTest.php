<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\ListCompanyUsersAction;
use App\Modules\Identity\Models\User;

it('lists the company users, including deactivated ones, and paginates them', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $former = User::factory()->viewer($company->id)->deactivated()->create([
        'email' => 'former@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);

    foreach (range(1, 14) as $index) {
        User::factory()->viewer($company->id)->create([
            'email' => sprintf('member-%02d@acme.test', $index),
            'password' => SAMPLE_PASSWORD,
            'company_id' => $company->id,
        ]);
    }

    $globex = globexAdmin();

    signedInAs($admin);

    $firstPage = $this->getJson('/api/v1/company/users')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(15, 'data');

    $secondPage = $this->getJson('/api/v1/company/users?page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(1, 'data');

    $rows = [
        ...$firstPage->json('data'),
        ...$secondPage->json('data'),
    ];
    $emails = array_column($rows, 'email');
    $firstPageIds = array_column($firstPage->json('data'), 'id');
    $sortedIds = $firstPageIds;
    sort($sortedIds);

    expect(ListCompanyUsersAction::COMPANY_USER_LIST_PAGE_SIZE)->toBe(15)
        ->and($emails)->toContain($admin->email, $former->email)
        ->and($emails)->not->toContain($globex->email)
        ->and($firstPageIds)->toBe($sortedIds)
        ->and(collect($rows)->firstWhere('email', $former->email))->toMatchArray([
            'id' => $former->id,
            'email' => 'former@acme.test',
            'role' => 'viewer',
            'is_active' => false,
        ])
        ->and(collect($rows)->firstWhere('email', $admin->email))->toMatchArray([
            'role' => 'company_admin',
            'is_active' => true,
        ]);
});
