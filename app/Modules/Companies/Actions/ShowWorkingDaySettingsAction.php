<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use App\Modules\Companies\Services\CompanyScheduleService;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;

final class ShowWorkingDaySettingsAction
{
    public function __construct(
        private CompanyScheduleService $schedule,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(Actor $actor): WorkingDaySettingsView
    {
        return $this->schedule->latestWorkingDaySettings($this->companyContext->companyId());
    }
}
