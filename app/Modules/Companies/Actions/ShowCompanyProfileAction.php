<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyTimeZoneVersion;
use App\Modules\Companies\PublicApi\CompanyProfileView;
use App\Modules\Identity\PublicApi\Actor;
use InvalidArgumentException;
use LogicException;

final class ShowCompanyProfileAction
{
    public function __invoke(Actor $actor): CompanyProfileView
    {
        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        $company = Company::query()->findOrFail($companyId);
        $timeZone = $company->timeZoneVersions()->orderByDesc('id')->first();

        throw_unless($timeZone instanceof CompanyTimeZoneVersion, LogicException::class);

        return new CompanyProfileView(
            id: $company->id,
            name: $company->name,
            timeZone: $timeZone->time_zone,
            bin: $this->emptyAsNull($company->bin),
            contactPerson: $this->emptyAsNull($company->contact_person),
            phone: $this->emptyAsNull($company->phone),
            email: $this->emptyAsNull($company->email),
        );
    }

    private function emptyAsNull(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
