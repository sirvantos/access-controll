<?php

declare(strict_types=1);

use App\Modules\Tenancy\Data\TenancyLimits;

it('lets an Acme admin read the company profile', function () {
    $company = acmeCompany();
    signedInAs(acmeAdmin(['company_id' => $company->id]));

    $this->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme');
});

it('ignores a forged company context header for a company admin', function () {
    $acme = acmeCompany();
    $globex = globexCompany();
    $globexAdmin = globexAdmin(['company_id' => $globex->id]);
    signedInAs(acmeAdmin(['company_id' => $acme->id]));

    $this->getJson('/api/v1/company', [
        TenancyLimits::COMPANY_CONTEXT_HEADER => (string) $globex->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.id', $acme->id);

    $users = $this->getJson('/api/v1/company/users', [
        TenancyLimits::COMPANY_CONTEXT_HEADER => (string) $globex->id,
    ])
        ->assertOk()
        ->json('data');

    expect(array_column($users, 'email'))->not->toContain($globexAdmin->email)
        ->and(array_column($users, 'id'))->not->toContain($globexAdmin->id);
});

it('still refuses guests on company routes', function () {
    $this->getJson('/api/v1/company')->assertUnauthorized();
});
