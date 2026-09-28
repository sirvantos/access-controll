<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use App\Modules\Companies\PublicApi\CompanyDirectory;
use App\Modules\Companies\PublicApi\CompanySchedule;
use App\Modules\Companies\Services\CompanyDirectoryService;
use App\Modules\Companies\Services\CompanyScheduleService;
use Illuminate\Support\ServiceProvider;

class CompaniesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanyDirectory::class, CompanyDirectoryService::class);
        $this->app->bind(CompanySchedule::class, CompanyScheduleService::class);
    }
}
