<?php

declare(strict_types=1);

namespace App\Modules\Companies\PublicApi;

use Carbon\CarbonInterface;

final readonly class WorkingDaySettingsView
{
    /**
     * @param  list<WeekDay>  $workingDays
     */
    public function __construct(
        public CarbonInterface $startTime,
        public CarbonInterface $endTime,
        public array $workingDays,
        public int $breakDurationMinutes,
        public bool $breakDeducted,
        public int $latenessGraceMinutes,
    ) {}
}
