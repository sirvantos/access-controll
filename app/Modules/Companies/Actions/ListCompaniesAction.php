<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanySummary;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;

final class ListCompaniesAction
{
    public const int COMPANY_LIST_PAGE_SIZE = 15;

    public function __construct(private FirstAdminInvitations $firstAdminInvitations) {}

    /**
     * @return LengthAwarePaginator<int, CompanySummary>
     */
    public function __invoke(int $page): LengthAwarePaginator
    {
        $companies = Company::query()
            ->orderBy('id')
            ->paginate(perPage: self::COMPANY_LIST_PAGE_SIZE, page: max(1, $page));

        /** @var list<int> $companyIds */
        $companyIds = $companies->getCollection()
            ->map(fn (Company $company): int => $company->id)
            ->values()
            ->all();

        $awaitingIds = $this->firstAdminInvitations->companyIdsAwaitingFirstAdmin($companyIds);

        /** @var LengthAwarePaginator<int, CompanySummary> $summaries */
        $summaries = $companies->through(
            fn (Company $company): CompanySummary => $this->summary($company, $awaitingIds),
        );

        return $summaries;
    }

    /**
     * @param  list<int>  $awaitingIds
     */
    private function summary(Company $company, array $awaitingIds): CompanySummary
    {
        $createdAt = $company->created_at;

        throw_unless($createdAt instanceof CarbonInterface, LogicException::class);

        return new CompanySummary(
            id: $company->id,
            name: $company->name,
            isActive: $company->isActive(),
            awaitingFirstAdmin: in_array($company->id, $awaitingIds, true),
            createdAt: $createdAt,
        );
    }
}
