<?php

declare(strict_types=1);

use App\Support\CompanyContextStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../factory/bootstrap.php';
require_once __DIR__.'/Support/Identity/helpers.php';
require_once __DIR__.'/Support/Companies/helpers.php';
require_once __DIR__.'/Support/Tenancy/helpers.php';
require_once __DIR__.'/Support/Tenancy/isolation_probe.php';

$flushCompanyContext = function (): void {
    app(CompanyContextStore::class)->flush();
};

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->afterEach($flushCompanyContext)
    ->in(
        'Feature/Modules/Tenancy',
        'Feature/Modules/Identity',
        'Feature/Modules/Companies',
        'Unit/Modules/Tenancy',
        'Unit/Support',
    );

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        app(CompanyContextStore::class)->enterWithoutIsolation();
    })
    ->afterEach(function () use ($flushCompanyContext): void {
        $store = app(CompanyContextStore::class);

        while ($store->isWithoutIsolation()) {
            $store->leaveWithoutIsolation();
        }

        $flushCompanyContext();
    })
    ->in(
        'Feature/Modules/Health',
        'Feature/Support',
        'Feature/Http',
        'Unit/Modules/Identity',
        'Unit/Modules/Companies',
    );

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->afterEach($flushCompanyContext)
    ->in('Feature/LocalizationParityTest.php', 'Feature/OpenApiDocumentTest.php', 'Feature/SpaShellTest.php');
