<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use App\Modules\Companies\Services\CompanyScheduleService;
use App\Modules\Identity\PublicApi\Actor;
use InvalidArgumentException;

final class ShowWorkingDaySettingsAction
{
    public function __construct(private CompanyScheduleService $schedule) {}

    public function __invoke(Actor $actor): WorkingDaySettingsView
    {
        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        return $this->schedule->latestWorkingDaySettings($companyId);
    }
}
