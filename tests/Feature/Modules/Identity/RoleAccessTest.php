<?php

declare(strict_types=1);

use App\Http\Resources\OkResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * @return list<string>
 */
function readCategoryNames(): array
{
    return ['events', 'timesheets', 'reports'];
}

/**
 * @return list<string>
 */
function managementCategoryNames(): array
{
    return ['employees', 'photos', 'terminals', 'settings', 'users'];
}

function registerRoleCategoryRoutes(): void
{
    Route::middleware([
        EnsureFrontendRequestsAreStateful::class,
        'auth:sanctum',
        'current-session',
        'role:company_admin,viewer',
    ])->group(registerReadCategoryRoutes(...));

    Route::middleware([
        EnsureFrontendRequestsAreStateful::class,
        'auth:sanctum',
        'current-session',
        'role:company_admin',
    ])->group(registerManagementCategoryRoutes(...));
}

function registerReadCategoryRoutes(): void
{
    foreach (readCategoryNames() as $category) {
        Route::get('/_test/read/'.$category, fn () => new OkResource(null));
    }
}

function registerManagementCategoryRoutes(): void
{
    foreach (managementCategoryNames() as $category) {
        Route::get('/_test/manage/'.$category, fn () => new OkResource(null));
        Route::post('/_test/manage/'.$category, fn () => new OkResource(null));
    }
}

/**
 * @return list<array{0: string, 1: string}>
 */
function materializedApiRoutes(string $prefix): array
{
    $calls = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, $prefix)) {
            continue;
        }

        $path = preg_replace('/\{[^}]+\}/', '1', $uri);
        throw_unless(is_string($path), RuntimeException::class);

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $calls[] = [$method, '/'.$path];
        }
    }

    return $calls;
}

/**
 * @return list<array{0: string, 1: string}>
 */
function materializedCompanyRoutes(): array
{
    $calls = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if ($uri !== 'api/v1/company' && ! str_starts_with($uri, 'api/v1/company/')) {
            continue;
        }

        $path = preg_replace('/\{[^}]+\}/', '1', $uri);
        throw_unless(is_string($path), RuntimeException::class);

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $calls[] = [$method, '/'.$path];
        }
    }

    return $calls;
}

function callMaterializedRoute(string $method, string $uri): TestResponse
{
    return test()->json($method, $uri);
}

beforeEach(fn () => registerRoleCategoryRoutes());

it('lets a viewer read events, timesheets, and reports and refuses management', function () {
    signedInAs(acmeViewer());

    foreach (readCategoryNames() as $category) {
        $this->getJson('/_test/read/'.$category)
            ->assertSuccessful()
            ->assertExactJson(['ok' => true]);
    }

    foreach (managementCategoryNames() as $category) {
        $this->getJson('/_test/manage/'.$category)->assertForbidden();
        $this->postJson('/_test/manage/'.$category)->assertForbidden();
    }

    $companyRoutes = materializedCompanyRoutes();
    $adminRoutes = materializedApiRoutes('api/v1/admin/');

    expect($companyRoutes)->not->toBeEmpty();

    foreach ($companyRoutes as [$method, $uri]) {
        if ($method === 'GET' && ($uri === '/api/v1/company' || $uri === '/api/v1/company/working-day-settings')) {
            callMaterializedRoute($method, $uri)->assertOk();

            continue;
        }

        callMaterializedRoute($method, $uri)->assertForbidden();
    }

    foreach ($adminRoutes as [$method, $uri]) {
        callMaterializedRoute($method, $uri)->assertForbidden();
    }
});

it('lets a company admin manage company categories and refuses super-admin routes', function () {
    signedInAs(acmeAdmin());

    foreach (readCategoryNames() as $category) {
        $this->getJson('/_test/read/'.$category)->assertSuccessful();
    }

    foreach (managementCategoryNames() as $category) {
        $this->getJson('/_test/manage/'.$category)
            ->assertSuccessful()
            ->assertExactJson(['ok' => true]);
        $this->postJson('/_test/manage/'.$category)
            ->assertSuccessful()
            ->assertExactJson(['ok' => true]);
    }

    foreach (materializedApiRoutes('api/v1/admin/') as [$method, $uri]) {
        callMaterializedRoute($method, $uri)->assertForbidden();
    }
});

it('refuses a super admin every company route and both category probes', function () {
    signedInAs(ownerSuperAdmin());

    $companyRoutes = materializedCompanyRoutes();

    expect($companyRoutes)->not->toBeEmpty();

    foreach ($companyRoutes as [$method, $uri]) {
        callMaterializedRoute($method, $uri)->assertForbidden();
    }

    foreach (readCategoryNames() as $category) {
        $this->getJson('/_test/read/'.$category)->assertForbidden();
    }

    foreach (managementCategoryNames() as $category) {
        $this->getJson('/_test/manage/'.$category)->assertForbidden();
        $this->postJson('/_test/manage/'.$category)->assertForbidden();
    }
});
