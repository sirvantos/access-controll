<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanySummary;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Stringable;
use LogicException;
use Throwable;

final class ListCompaniesAction
{
    public const int COMPANY_LIST_PAGE_SIZE = 15;

    public function __construct(
        private readonly FirstAdminInvitations $firstAdminInvitations,
        private readonly CompanyContext $companyContext,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CompanySummary>
     */
    public function __invoke(?Stringable $search, int $page): LengthAwarePaginator
    {
        return $this->companyContext->withoutIsolation(fn (): LengthAwarePaginator => $this->paginate($search, $page));
    }

    /**
     * @return LengthAwarePaginator<int, CompanySummary>
     */
    private function paginate(?Stringable $search, int $page): LengthAwarePaginator
    {
        $query = Company::query();

        if ($search !== null) {
            $this->matchingSearch($query, $search);
        }

        $companies = $query
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
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    private function matchingSearch(Builder $query, Stringable $search): Builder
    {
        $pattern = '%'.$search.'%';

        return $query->where(
            fn (Builder $inner): Builder => $inner
                ->where('name_normalized', 'like', $pattern)
                ->orWhere('bin', 'like', $pattern),
        );
    }

    /**
     * @param  list<int>  $awaitingIds
     *
     * @throws Throwable
     */
    private function summary(Company $company, array $awaitingIds): CompanySummary
    {
        $createdAt = $company->created_at;

        throw_unless($createdAt instanceof CarbonInterface, LogicException::class);

        return new CompanySummary(
            id: $company->id,
            name: $company->name,
            bin: $company->bin === '' ? null : $company->bin,
            isActive: $company->isActive(),
            awaitingFirstAdmin: in_array($company->id, $awaitingIds, true),
            createdAt: $createdAt,
        );
    }
}
