<?php

declare(strict_types=1);

namespace App\Modules\Companies\PublicApi;

use Carbon\CarbonInterface;

interface CompanySchedule
{
    public function timeZoneIdentifierAt(int $companyId, CarbonInterface $instant): string;

    public function workingDaySettingsAt(int $companyId, CarbonInterface $instant): WorkingDaySettingsView;
}
