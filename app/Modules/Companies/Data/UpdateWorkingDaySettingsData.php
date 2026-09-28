<?php

declare(strict_types=1);

namespace App\Modules\Companies\Data;

use App\Modules\Companies\PublicApi\WeekDay;
use Illuminate\Support\Stringable;
use Spatie\LaravelData\Data;

final class UpdateWorkingDaySettingsData extends Data
{
    /**
     * @param  list<WeekDay>  $workingDays
     */
    public function __construct(
        public readonly Stringable $startTime,
        public readonly Stringable $endTime,
        public readonly array $workingDays,
        public readonly int $breakDurationMinutes,
        public readonly int $latenessGraceMinutes,
        public readonly bool $breakDeducted,
    ) {}
}
