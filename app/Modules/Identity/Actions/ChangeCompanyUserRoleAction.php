<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\CompanyUserView;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Identity\Services\AdminSeatGuardService;
use App\Modules\Tenancy\PublicApi\CompanyContext;

final class ChangeCompanyUserRoleAction
{
    public function __construct(
        private AdminSeatGuardService $adminSeatGuard,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(int $companyId, int $userId, Role $role): CompanyUserView
    {
        return $this->companyContext->run($companyId, fn (): CompanyUserView => $this->changeRole($companyId, $userId, $role));
    }

    private function changeRole(int $companyId, int $userId, Role $role): CompanyUserView
    {
        $user = $this->userInCompany($companyId, $userId);

        if ($this->removesAdminSeat($user, $role)) {
            $this->adminSeatGuard->execute($companyId, $user->id, fn () => $this->assignRole($user, $role));

            return $this->view($user->refresh());
        }

        $this->assignRole($user, $role);

        return $this->view($user->refresh());
    }

    private function removesAdminSeat(User $user, Role $role): bool
    {
        return $user->role === Role::CompanyAdmin && $role === Role::Viewer;
    }

    private function assignRole(User $user, Role $role): void
    {
        $user->refresh();
        $user->forceFill(['role' => $role])->save();
    }

    private function userInCompany(int $companyId, int $userId): User
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereKey($userId)
            ->firstOrFail();
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
