<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Companies\PublicApi\CompanyDeactivated;
use App\Modules\Identity\Actions\EndCompanySessionsAction;
use App\Modules\Identity\Console\CreateSuperAdminCommand;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use App\Modules\Identity\PublicApi\SessionValidity;
use App\Modules\Identity\Services\AccountEligibilityService;
use App\Modules\Identity\Services\FirstAdminInvitationsService;
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
        Event::listen(CompanyDeactivated::class, EndCompanySessionsAction::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateSuperAdminCommand::class,
            ]);
        }
    }
}
