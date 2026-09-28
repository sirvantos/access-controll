<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Companies\Data\UpdateWorkingDaySettingsData;
use App\Modules\Companies\PublicApi\WeekDay;
use App\Rules\Companies\WorkingDaySettingsRangeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class UpdateWorkingDaySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', new WorkingDaySettingsRangeRule],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', 'distinct', Rule::enum(WeekDay::class)],
            'break_duration_minutes' => ['required', 'integer'],
            'lateness_grace_minutes' => ['required', 'integer'],
            'break_deducted' => ['required', 'boolean'],
        ];
    }

    public function toDto(): UpdateWorkingDaySettingsData
    {
        return new UpdateWorkingDaySettingsData(
            startTime: $this->string('start_time'),
            endTime: $this->string('end_time'),
            workingDays: $this->workingDays(),
            breakDurationMinutes: $this->integer('break_duration_minutes'),
            latenessGraceMinutes: $this->integer('lateness_grace_minutes'),
            breakDeducted: $this->boolean('break_deducted'),
        );
    }

    /**
     * @return list<WeekDay>
     */
    private function workingDays(): array
    {
        $days = $this->input('working_days');

        throw_unless(is_array($days), InvalidArgumentException::class);

        return array_map($this->weekDay(...), array_values($days));
    }

    private function weekDay(mixed $day): WeekDay
    {
        throw_unless(is_string($day), InvalidArgumentException::class);

        return WeekDay::from($day);
    }
}
