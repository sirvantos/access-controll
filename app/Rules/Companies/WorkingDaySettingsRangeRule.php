<?php

declare(strict_types=1);

namespace App\Rules\Companies;

use App\Modules\Companies\PublicApi\WeekDay;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;
use LogicException;

final class WorkingDaySettingsRangeRule implements DataAwareRule, ValidationRule, ValidatorAwareRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private ?Validator $validator = null;

    /**
     * @return array<string, list<self>>
     */
    public static function rules(): array
    {
        return [
            'end_time' => [new self],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $length = $this->workingDayLengthMinutes();

        if ($length === null) {
            $fail('companies.end_time_after_start')->translate();
        }

        if (! $this->hasWorkingDay()) {
            $this->errors()->add('working_days', __('companies.working_days_required'));
        }

        if ($length === null) {
            return;
        }

        $this->rejectOutOfRange('break_duration_minutes', 'companies.break_duration_range', $length);
        $this->rejectOutOfRange('lateness_grace_minutes', 'companies.lateness_grace_range', $length);
    }

    private function workingDayLengthMinutes(): ?int
    {
        $start = $this->timeOfDay($this->data['start_time'] ?? null);
        $end = $this->timeOfDay($this->data['end_time'] ?? null);

        if (! $start instanceof Carbon || ! $end instanceof Carbon || $end->lessThanOrEqualTo($start)) {
            return null;
        }

        return (int) $start->diffInMinutes($end);
    }

    private function timeOfDay(mixed $time): ?Carbon
    {
        if (! is_string($time) || ! Carbon::hasFormat($time, 'H:i')) {
            return null;
        }

        $parsed = Carbon::createFromFormat('!H:i', $time);

        return $parsed instanceof Carbon ? $parsed : null;
    }

    private function hasWorkingDay(): bool
    {
        $days = $this->data['working_days'] ?? null;

        if (! is_array($days)) {
            return false;
        }

        foreach ($days as $day) {
            if (is_string($day) && in_array($day, self::weekDayValues(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function weekDayValues(): array
    {
        return array_map(fn (WeekDay $day): string => $day->value, WeekDay::cases());
    }

    private function rejectOutOfRange(string $field, string $messageKey, int $length): void
    {
        $value = $this->data[$field] ?? null;

        if (is_int($value) && $value >= 0 && $value < $length) {
            return;
        }

        $this->errors()->add($field, __($messageKey));
    }

    private function errors(): MessageBag
    {
        throw_unless($this->validator instanceof Validator, LogicException::class);

        return $this->validator->errors();
    }
}
