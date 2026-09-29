<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\CompanyUserView;
use App\Modules\Identity\Services\AdminSeatGuardService;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Password;

final class DeactivateCompanyUserAction
{
    public function __construct(
        private AdminSeatGuardService $adminSeatGuard,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(int $companyId, int $userId): CompanyUserView
    {
        return $this->companyContext->run($companyId, fn (): CompanyUserView => $this->deactivateUser($companyId, $userId));
    }

    private function deactivateUser(int $companyId, int $userId): CompanyUserView
    {
        $user = $this->userInCompany($companyId, $userId);

        if ($user->deactivated_at !== null) {
            return $this->view($user);
        }

        $this->adminSeatGuard->execute($companyId, $user->id, fn () => $this->deactivate($user));

        return $this->view($user->refresh());
    }

    private function deactivate(User $user): void
    {
        $user->refresh();

        if ($user->deactivated_at !== null) {
            return;
        }

        $user->forceFill([
            'deactivated_at' => now(),
            'session_version' => $user->session_version + 1,
        ])->save();

        Password::broker()->getRepository()->delete($user);
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
