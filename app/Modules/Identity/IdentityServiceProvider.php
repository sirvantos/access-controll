<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Auth\IsolationAwareUserProvider;
use App\Modules\Companies\PublicApi\CompanyDeactivated;
use App\Modules\Identity\Actions\EndCompanySessionsAction;
use App\Modules\Identity\Console\CreateSuperAdminCommand;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use App\Modules\Identity\PublicApi\SessionValidity;
use App\Modules\Identity\Services\AccountEligibilityService;
use App\Modules\Identity\Services\FirstAdminInvitationsService;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SessionValidity::class, AccountEligibilityService::class);
        $this->app->bind(FirstAdminInvitations::class, FirstAdminInvitationsService::class);
    }

    public function boot(): void
    {
        Auth::provider('isolation-aware-eloquent', fn ($app, array $config): IsolationAwareUserProvider => new IsolationAwareUserProvider(
            $app['hash'],
            $config['model'],
            $app->make(CompanyContext::class),
        ));

        Event::listen(CompanyDeactivated::class, EndCompanySessionsAction::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateSuperAdminCommand::class,
            ]);
        }
    }
}
