<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\CompanyUserView;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListCompanyUsersAction
{
    public const int COMPANY_USER_LIST_PAGE_SIZE = 15;

    public function __construct(private CompanyContext $companyContext) {}

    /**
     * @return LengthAwarePaginator<int, CompanyUserView>
     */
    public function __invoke(Actor $actor, int $page): LengthAwarePaginator
    {
        return $this->paginate($this->companyContext->companyId(), $page);
    }

    /**
     * @return LengthAwarePaginator<int, CompanyUserView>
     */
    private function paginate(int $companyId, int $page): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, CompanyUserView> $users */
        $users = User::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->paginate(perPage: self::COMPANY_USER_LIST_PAGE_SIZE, page: max(1, $page))
            ->through(fn (User $user): CompanyUserView => new CompanyUserView(
                id: $user->id,
                email: $user->email,
                role: $user->role,
                isActive: $user->deactivated_at === null,
            ));

        return $users;
    }
}
