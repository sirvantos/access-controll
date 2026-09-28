<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class WorkingDaySettingsResource extends JsonResource
{
    /**
     * @return array{
     *     start_time: string,
     *     end_time: string,
     *     working_days: list<string>,
     *     break_duration_minutes: int,
     *     break_deducted: bool,
     *     lateness_grace_minutes: int
     * }
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;

        throw_unless($settings instanceof WorkingDaySettingsView, InvalidArgumentException::class);

        return [
            'start_time' => $settings->startTime->format('H:i'),
            'end_time' => $settings->endTime->format('H:i'),
            'working_days' => $this->workingDays($settings),
            'break_duration_minutes' => $settings->breakDurationMinutes,
            'break_deducted' => $settings->breakDeducted,
            'lateness_grace_minutes' => $settings->latenessGraceMinutes,
        ];
    }

    /**
     * @return list<string>
     */
    private function workingDays(WorkingDaySettingsView $settings): array
    {
        $days = [];

        foreach ($settings->workingDays as $day) {
            $days[$day->value] = $day->value;
        }

        return array_values($days);
    }
}
