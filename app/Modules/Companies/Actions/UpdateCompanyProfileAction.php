<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Data\UpdateCompanyProfileData;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanyProfileView;
use App\Modules\Companies\Services\CompanyScheduleService;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Stringable;
use Spatie\LaravelData\Optional;

final class UpdateCompanyProfileAction
{
    public function __construct(
        private CompanyScheduleService $schedule,
        private ShowCompanyProfileAction $showCompanyProfile,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(Actor $actor, UpdateCompanyProfileData $data): CompanyProfileView
    {
        $companyId = $this->companyContext->companyId();

        DB::transaction(function () use ($companyId, $data): void {
            $attributes = $this->attributes($data);

            if ($attributes !== []) {
                $company = Company::query()->findOrFail($companyId);
                $company->fill($attributes)->save();
            }

            if (! $data->timeZone instanceof Optional) {
                $this->schedule->saveTimeZone($companyId, $data->timeZone->toString(), now());
            }
        });

        return ($this->showCompanyProfile)($actor);
    }

    /**
     * @return array<string, Stringable|null>
     */
    private function attributes(UpdateCompanyProfileData $data): array
    {
        $attributes = [];

        if (! $data->name instanceof Optional) {
            $attributes['name'] = $data->name;
        }

        foreach ([
            'bin' => $data->bin,
            'contact_person' => $data->contactPerson,
            'phone' => $data->phone,
            'email' => $data->email,
        ] as $column => $value) {
            if ($value instanceof Optional) {
                continue;
            }

            $attributes[$column] = $value;
        }

        return $attributes;
    }
}
