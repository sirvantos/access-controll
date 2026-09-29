<?php

declare(strict_types=1);

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Models\CompanyTimeZoneVersion;
use App\Modules\Companies\Models\CompanyWorkingDaySettingVersion;
use App\Modules\Companies\PublicApi\CompanySchedule;
use App\Modules\Companies\PublicApi\WeekDay;
use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Enumerable;
use LogicException;

final class CompanyScheduleService implements CompanySchedule
{
    public function __construct(private CompanyContext $companyContext) {}

    public function timeZoneIdentifierAt(int $companyId, CarbonInterface $instant): string
    {
        return $this->companyContext->run(
            $companyId,
            fn (): string => $this->timeZoneVersionAt($companyId, $instant)->time_zone,
        );
    }

    public function workingDaySettingsAt(int $companyId, CarbonInterface $instant): WorkingDaySettingsView
    {
        return $this->companyContext->run(
            $companyId,
            fn (): WorkingDaySettingsView => $this->toSettingsView($this->settingsVersionAt($companyId, $instant)),
        );
    }

    public function latestWorkingDaySettings(int $companyId): WorkingDaySettingsView
    {
        return $this->companyContext->run(
            $companyId,
            fn (): WorkingDaySettingsView => $this->toSettingsView($this->latestSettings($companyId)),
        );
    }

    public function appliesFromNextLocalMidnight(string $timeZoneIdentifier, CarbonInterface $now): CarbonInterface
    {
        return $now->copy()->timezone($timeZoneIdentifier)->addDay()->startOfDay()->utc();
    }

    public function appliesFromStartOfLocalDay(string $timeZoneIdentifier, CarbonInterface $now): CarbonInterface
    {
        return $now->copy()->timezone($timeZoneIdentifier)->startOfDay()->utc();
    }

    public function saveTimeZone(int $companyId, string $timeZoneIdentifier, CarbonInterface $now): void
    {
        $this->companyContext->run($companyId, function () use ($companyId, $timeZoneIdentifier, $now): void {
            $latest = $this->latestTimeZone($companyId);

            if ($latest->time_zone === $timeZoneIdentifier) {
                return;
            }

            $this->insertTimeZoneVersion(
                $companyId,
                $timeZoneIdentifier,
                $this->appliesFromNextLocalMidnight($this->timeZoneVersionAt($companyId, $now)->time_zone, $now),
            );
        });
    }

    public function saveWorkingDaySettings(int $companyId, WorkingDaySettingsView $settings, CarbonInterface $now): void
    {
        $this->companyContext->run($companyId, function () use ($companyId, $settings, $now): void {
            $latest = $this->latestSettings($companyId);

            if ($this->sameSettings($latest, $settings)) {
                return;
            }

            $this->insertWorkingDaySettingsVersion(
                $companyId,
                $settings,
                $this->appliesFromNextLocalMidnight($this->timeZoneVersionAt($companyId, $now)->time_zone, $now),
            );
        });
    }

    public function insertTimeZoneVersion(int $companyId, string $timeZoneIdentifier, CarbonInterface $appliesFrom): void
    {
        $this->companyContext->run($companyId, function () use ($companyId, $timeZoneIdentifier, $appliesFrom): void {
            $this->persistTimeZoneVersion($companyId, $timeZoneIdentifier, $appliesFrom);
        });
    }

    public function insertWorkingDaySettingsVersion(int $companyId, WorkingDaySettingsView $settings, CarbonInterface $appliesFrom): void
    {
        $this->companyContext->run($companyId, function () use ($companyId, $settings, $appliesFrom): void {
            $this->persistWorkingDaySettingsVersion($companyId, $settings, $appliesFrom);
        });
    }

    private function persistTimeZoneVersion(int $companyId, string $timeZoneIdentifier, CarbonInterface $appliesFrom): void
    {
        CompanyTimeZoneVersion::query()->create([
            'company_id' => $companyId,
            'time_zone' => $timeZoneIdentifier,
            'applies_from' => $appliesFrom->copy()->utc(),
        ]);
    }

    private function persistWorkingDaySettingsVersion(int $companyId, WorkingDaySettingsView $settings, CarbonInterface $appliesFrom): void
    {
        CompanyWorkingDaySettingVersion::query()->create([
            'company_id' => $companyId,
            'start_time' => $settings->startTime->format('H:i'),
            'end_time' => $settings->endTime->format('H:i'),
            'working_days' => $settings->workingDays,
            'break_duration_minutes' => $settings->breakDurationMinutes,
            'break_deducted' => $settings->breakDeducted,
            'lateness_grace_minutes' => $settings->latenessGraceMinutes,
            'applies_from' => $appliesFrom->copy()->utc(),
        ]);
    }

    private function timeZoneVersionAt(int $companyId, CarbonInterface $instant): CompanyTimeZoneVersion
    {
        $version = CompanyTimeZoneVersion::query()
            ->where('company_id', $companyId)
            ->where('applies_from', '<=', $instant->copy()->utc())
            ->orderByDesc('id')
            ->first();

        throw_unless($version instanceof CompanyTimeZoneVersion, LogicException::class);

        return $version;
    }

    private function settingsVersionAt(int $companyId, CarbonInterface $instant): CompanyWorkingDaySettingVersion
    {
        $version = CompanyWorkingDaySettingVersion::query()
            ->where('company_id', $companyId)
            ->where('applies_from', '<=', $instant->copy()->utc())
            ->orderByDesc('id')
            ->first();

        throw_unless($version instanceof CompanyWorkingDaySettingVersion, LogicException::class);

        return $version;
    }

    private function latestTimeZone(int $companyId): CompanyTimeZoneVersion
    {
        $version = CompanyTimeZoneVersion::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        throw_unless($version instanceof CompanyTimeZoneVersion, LogicException::class);

        return $version;
    }

    private function latestSettings(int $companyId): CompanyWorkingDaySettingVersion
    {
        $version = CompanyWorkingDaySettingVersion::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        throw_unless($version instanceof CompanyWorkingDaySettingVersion, LogicException::class);

        return $version;
    }

    private function sameSettings(CompanyWorkingDaySettingVersion $latest, WorkingDaySettingsView $settings): bool
    {
        return Carbon::parse($latest->start_time)->format('H:i') === $settings->startTime->format('H:i')
            && Carbon::parse($latest->end_time)->format('H:i') === $settings->endTime->format('H:i')
            && $latest->break_duration_minutes === $settings->breakDurationMinutes
            && $latest->break_deducted === $settings->breakDeducted
            && $latest->lateness_grace_minutes === $settings->latenessGraceMinutes
            && $this->sortedDayValues($latest->working_days) === $this->sortedDayValues($settings->workingDays);
    }

    private function toSettingsView(CompanyWorkingDaySettingVersion $version): WorkingDaySettingsView
    {
        return new WorkingDaySettingsView(
            startTime: Carbon::parse($version->start_time),
            endTime: Carbon::parse($version->end_time),
            workingDays: $this->weekDays($version->working_days),
            breakDurationMinutes: $version->break_duration_minutes,
            breakDeducted: $version->break_deducted,
            latenessGraceMinutes: $version->lateness_grace_minutes,
        );
    }

    /**
     * @return list<WeekDay>
     */
    private function weekDays(mixed $days): array
    {
        $weekDays = [];

        if (is_array($days) || $days instanceof Enumerable) {
            foreach ($days as $day) {
                if ($day instanceof WeekDay) {
                    $weekDays[] = $day;
                }
            }
        }

        return $weekDays;
    }

    /**
     * @return list<string>
     */
    private function sortedDayValues(mixed $days): array
    {
        $values = [];

        if (is_array($days) || $days instanceof Enumerable) {
            foreach ($days as $day) {
                if ($day instanceof WeekDay) {
                    $values[] = $day->value;
                } elseif (is_string($day)) {
                    $values[] = $day;
                }
            }
        }

        sort($values);

        return $values;
    }
}
