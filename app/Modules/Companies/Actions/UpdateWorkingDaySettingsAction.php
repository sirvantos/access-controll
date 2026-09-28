<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Data\UpdateWorkingDaySettingsData;
use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use App\Modules\Companies\Services\CompanyScheduleService;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class UpdateWorkingDaySettingsAction
{
    public function __construct(private CompanyScheduleService $schedule) {}

    public function __invoke(Actor $actor, UpdateWorkingDaySettingsData $data): WorkingDaySettingsView
    {
        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        $startTime = Carbon::createFromFormat('!H:i', $data->startTime->toString());
        $endTime = Carbon::createFromFormat('!H:i', $data->endTime->toString());

        throw_unless($startTime instanceof Carbon && $endTime instanceof Carbon, InvalidArgumentException::class);

        $this->schedule->saveWorkingDaySettings($companyId, new WorkingDaySettingsView(
            startTime: $startTime,
            endTime: $endTime,
            workingDays: $data->workingDays,
            breakDurationMinutes: $data->breakDurationMinutes,
            breakDeducted: $data->breakDeducted,
            latenessGraceMinutes: $data->latenessGraceMinutes,
        ), now());

        return $this->schedule->latestWorkingDaySettings($companyId);
    }
}
