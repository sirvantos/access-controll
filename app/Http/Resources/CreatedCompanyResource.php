<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Companies\PublicApi\CreatedCompanyView;
use App\Modules\Companies\PublicApi\WeekDay;
use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class CreatedCompanyResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     time_zone: string,
     *     bin: string|null,
     *     contact_person: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     is_active: bool,
     *     awaiting_first_admin: bool,
     *     created_at: string,
     *     working_day_settings: array{
     *         start_time: string,
     *         end_time: string,
     *         working_days: list<string>,
     *         break_duration_minutes: int,
     *         break_deducted: bool,
     *         lateness_grace_minutes: int
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        $company = $this->resource;

        throw_unless($company instanceof CreatedCompanyView, InvalidArgumentException::class);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'time_zone' => $company->timeZone,
            'bin' => $company->bin,
            'contact_person' => $company->contactPerson,
            'phone' => $company->phone,
            'email' => $company->email,
            'is_active' => $company->isActive,
            'awaiting_first_admin' => $company->awaitingFirstAdmin,
            'created_at' => $company->createdAt->copy()->utc()->toIso8601String(),
            'working_day_settings' => $this->workingDaySettings($company->workingDaySettings),
        ];
    }

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
    private function workingDaySettings(WorkingDaySettingsView $settings): array
    {
        return [
            'start_time' => $settings->startTime->format('H:i'),
            'end_time' => $settings->endTime->format('H:i'),
            'working_days' => array_map(fn (WeekDay $day): string => $day->value, $settings->workingDays),
            'break_duration_minutes' => $settings->breakDurationMinutes,
            'break_deducted' => $settings->breakDeducted,
            'lateness_grace_minutes' => $settings->latenessGraceMinutes,
        ];
    }
}
