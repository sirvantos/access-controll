<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\CompanyUserView;
use App\Modules\Tenancy\PublicApi\CompanyContext;

final class ReactivateCompanyUserAction
{
    public function __construct(private CompanyContext $companyContext) {}

    public function __invoke(int $companyId, int $userId): CompanyUserView
    {
        return $this->companyContext->run($companyId, fn (): CompanyUserView => $this->reactivate($companyId, $userId));
    }

    private function reactivate(int $companyId, int $userId): CompanyUserView
    {
        $user = User::query()
            ->where('company_id', $companyId)
            ->whereKey($userId)
            ->firstOrFail();

        if ($user->deactivated_at === null) {
            return $this->view($user);
        }

        $user->forceFill(['deactivated_at' => null])->save();

        return $this->view($user);
    }

    private function view(User $user): CompanyUserView
    {
        return new CompanyUserView(
            id: $user->id,
            email: $user->email,
            role: $user->role,
            isActive: $user->deactivated_at === null,
        );
    }
}
