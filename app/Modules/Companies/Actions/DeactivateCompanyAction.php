<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanyDeactivated;
use App\Modules\Companies\PublicApi\CompanySummary;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final readonly class DeactivateCompanyAction
{
    public function __construct(private FirstAdminInvitations $firstAdminInvitations) {}

    /**
     * @throws Throwable
     */
    public function __invoke(int $companyId): CompanySummary
    {
        $company = DB::transaction(fn (): Company => $this->deactivate($companyId));

        return $this->summary($company);
    }

    private function deactivate(int $companyId): Company
    {
        $company = Company::query()->whereKey($companyId)->firstOrFail();

        if ($company->deactivated_at !== null) {
            return $company;
        }

        $company->forceFill(['deactivated_at' => now()])->save();

        event(new CompanyDeactivated($company->id));

        return $company;
    }

    /**
     * @throws Throwable
     */
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
