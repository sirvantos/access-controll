<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanyDirectory;
use App\Modules\Companies\Services\CompanyDirectoryService;

it('reports that an unknown company does not exist and is not active', function () {
    $directory = app(CompanyDirectory::class);
    $unknownCompanyId = 9_999_999;

    expect($directory)->toBeInstanceOf(CompanyDirectoryService::class)
        ->and($directory->exists($unknownCompanyId))->toBeFalse()
        ->and($directory->isActive($unknownCompanyId))->toBeFalse();
});

it('reports that an active company exists and is active', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $directory = app(CompanyDirectory::class);

    expect($directory->exists($company->id))->toBeTrue()
        ->and($directory->isActive($company->id))->toBeTrue();
});

it('reports that a deactivated company exists and is not active', function () {
    $company = Company::factory()->deactivated()->create([
        'name' => 'Acme',
    ]);

    $directory = app(CompanyDirectory::class);

    expect($directory->exists($company->id))->toBeTrue()
        ->and($directory->isActive($company->id))->toBeFalse();
});
