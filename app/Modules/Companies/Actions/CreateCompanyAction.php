<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Data\CreateCompanyData;
use App\Modules\Companies\Data\WorkingDaySettingDefaults;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CreatedCompanyView;
use App\Modules\Companies\PublicApi\WeekDay;
use App\Modules\Companies\PublicApi\WorkingDaySettingsView;
use App\Modules\Companies\Services\CompanyScheduleService;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CreateCompanyAction
{
    public function __construct(
        private FirstAdminInvitations $firstAdminInvitations,
        private CompanyScheduleService $schedule,
    ) {}

    public function __invoke(CreateCompanyData $data): CreatedCompanyView
    {
        $company = DB::transaction(function () use ($data): Company {
            $company = Company::query()->create([
                'name' => $data->name,
                'bin' => $data->bin,
                'contact_person' => $data->contactPerson,
                'phone' => $data->phone,
                'email' => $data->email,
            ]);

            $createdAt = $company->created_at;

            throw_unless($createdAt instanceof CarbonInterface, LogicException::class);

            $appliesFrom = $this->schedule->appliesFromStartOfLocalDay($data->timeZone->toString(), $createdAt);

            $this->schedule->insertTimeZoneVersion($company->id, $data->timeZone->toString(), $appliesFrom);
            $this->schedule->insertWorkingDaySettingsVersion($company->id, $this->defaultSettings(), $appliesFrom);
            $this->firstAdminInvitations->invite($company->id, $data->firstAdminEmail);

            return $company;
        });

        $createdAt = $company->created_at;

        throw_unless($createdAt instanceof CarbonInterface, LogicException::class);

        return new CreatedCompanyView(
            id: $company->id,
            name: $company->name,
            timeZone: $this->schedule->timeZoneIdentifierAt($company->id, $createdAt),
            bin: $this->blankToNull($company->bin),
            contactPerson: $this->blankToNull($company->contact_person),
            phone: $this->blankToNull($company->phone),
            email: $this->blankToNull($company->email),
            isActive: $company->isActive(),
            awaitingFirstAdmin: $this->firstAdminInvitations->isAwaitingFirstAdmin($company->id),
            createdAt: $createdAt,
            workingDaySettings: $this->schedule->workingDaySettingsAt($company->id, $createdAt),
        );
    }

    private function defaultSettings(): WorkingDaySettingsView
    {
        return new WorkingDaySettingsView(
            startTime: Carbon::parse(WorkingDaySettingDefaults::DEFAULT_START_TIME),
            endTime: Carbon::parse(WorkingDaySettingDefaults::DEFAULT_END_TIME),
            workingDays: array_map(
                fn (string $day): WeekDay => WeekDay::from($day),
                WorkingDaySettingDefaults::DEFAULT_WORKING_DAYS,
            ),
            breakDurationMinutes: WorkingDaySettingDefaults::DEFAULT_BREAK_DURATION_MINUTES,
            breakDeducted: WorkingDaySettingDefaults::DEFAULT_BREAK_DEDUCTED,
            latenessGraceMinutes: WorkingDaySettingDefaults::DEFAULT_LATENESS_GRACE_MINUTES,
        );
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
