<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Companies\Data\WorkingDaySettingDefaults;
use App\Modules\Companies\Models\Company;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    private const string DEACTIVATED_AT = '2026-01-15 12:00:00';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Acme',
            'deactivated_at' => null,
        ];
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deactivated_at' => self::DEACTIVATED_AT,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Company $company): Company => $this->insertDefaultVersions($company));
    }

    private function insertDefaultVersions(Company $company): Company
    {
        $createdAt = $company->created_at;

        throw_unless($createdAt instanceof CarbonInterface, LogicException::class);

        $appliesFrom = $createdAt->copy()
            ->timezone(WorkingDaySettingDefaults::DEFAULT_TIME_ZONE)
            ->startOfDay()
            ->utc();

        $company->timeZoneVersions()->create([
            'time_zone' => WorkingDaySettingDefaults::DEFAULT_TIME_ZONE,
            'applies_from' => $appliesFrom,
        ]);

        $company->workingDaySettingVersions()->create([
            'start_time' => WorkingDaySettingDefaults::DEFAULT_START_TIME,
            'end_time' => WorkingDaySettingDefaults::DEFAULT_END_TIME,
            'working_days' => WorkingDaySettingDefaults::DEFAULT_WORKING_DAYS,
            'break_duration_minutes' => WorkingDaySettingDefaults::DEFAULT_BREAK_DURATION_MINUTES,
            'break_deducted' => WorkingDaySettingDefaults::DEFAULT_BREAK_DEDUCTED,
            'lateness_grace_minutes' => WorkingDaySettingDefaults::DEFAULT_LATENESS_GRACE_MINUTES,
            'applies_from' => $appliesFrom,
        ]);

        return $company;
    }
}
