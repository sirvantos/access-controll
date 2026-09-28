<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanySummary;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use Carbon\CarbonInterface;
use LogicException;

final class ReactivateCompanyAction
{
    public function __construct(private FirstAdminInvitations $firstAdminInvitations) {}

    public function __invoke(int $companyId): CompanySummary
    {
        $company = Company::query()->whereKey($companyId)->firstOrFail();

        if ($company->deactivated_at !== null) {
            $company->forceFill(['deactivated_at' => null])->save();
        }

        return $this->summary($company);
    }

    private function summary(Company $company): CompanySummary
    {
        $createdAt = $company->created_at;

        throw_unless($createdAt instanceof CarbonInterface, LogicException::class);

        return new CompanySummary(
            id: $company->id,
            name: $company->name,
            bin: $company->bin === '' ? null : $company->bin,
            isActive: $company->isActive(),
            awaitingFirstAdmin: $this->firstAdminInvitations->isAwaitingFirstAdmin($company->id),
            createdAt: $createdAt,
        );
    }
}
