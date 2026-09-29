<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\Support\Tenancy\IsolationProbe;
use Tests\Support\Tenancy\IsolationProbeResource;

function isolationProbeRoutesAreRegistered(): bool
{
    foreach (Route::getRoutes() as $route) {
        if ($route->uri() === '_test/company/isolation-probes') {
            return true;
        }
    }

    return false;
}

function registerIsolationProbeRoutes(): void
{
    if (isolationProbeRoutesAreRegistered()) {
        return;
    }

    Route::middleware([
        EnsureFrontendRequestsAreStateful::class,
        'auth:sanctum',
        'current-session',
        'role:company_admin,super_admin',
        'company-context',
    ])->group(function (): void {
        Route::get('/_test/company/isolation-probes', function (Request $request) {
            if ($request->query('id') !== null && $request->query('id') !== '') {
                return IsolationProbeResource::make(
                    IsolationProbe::query()->whereKey($request->integer('id'))->firstOrFail(),
                );
            }

            $query = IsolationProbe::query()->orderBy('id');

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search').'%');
            }

            return IsolationProbeResource::collection($query->get());
        });

        Route::post('/_test/company/isolation-probes', function (Request $request) {
            $probe = IsolationProbe::query()->create([
                'company_id' => $request->integer('company_id'),
                'name' => $request->input('name'),
            ]);

            return IsolationProbeResource::make($probe)->response()->setStatusCode(201);
        });
    });
}
