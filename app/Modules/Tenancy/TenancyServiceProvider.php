<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

use App\Exceptions\CompanyContextRequiredException;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaAccess;
use App\Modules\Tenancy\Services\CompanyContextService;
use App\Modules\Tenancy\Services\CompanyMediaAccessService;
use App\Modules\Tenancy\Services\SuperAdminActionRecorder;
use App\Support\CompanyContextStore;
use App\Support\CompanyMutationRecorder;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CompanyContextStore::class);
        $this->app->scoped(CompanyContext::class, CompanyContextService::class);
        $this->app->bind(CompanyMediaAccess::class, CompanyMediaAccessService::class);
        $this->app->scoped(CompanyMutationRecorder::class, SuperAdminActionRecorder::class);

        $this->app->afterResolving(CompanyContextStore::class, function (CompanyContextStore $store): void {
            $store->registerUnboundHandler(fn (): never => throw new CompanyContextRequiredException);
        });
    }

    public function boot(): void
    {
        $this->app->terminating(function (): void {
            if ($this->app->bound(CompanyContextStore::class)) {
                $this->app->make(CompanyContextStore::class)->flushRequestBinding();
            }
        });
    }
}
