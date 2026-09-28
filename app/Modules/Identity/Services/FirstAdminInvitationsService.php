<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Modules\Identity\Actions\InviteCompanyUserAction;
use App\Modules\Identity\Data\InviteUserData;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use App\Modules\Identity\PublicApi\Role;

final class FirstAdminInvitationsService implements FirstAdminInvitations
{
    public function __construct(private InviteCompanyUserAction $inviteCompanyUser) {}

    public function invite(int $companyId, string $email): void
    {
        try {
            $this->inviteCompanyUser->__invoke($companyId, new InviteUserData(
                email: mb_strtolower($email),
                role: Role::CompanyAdmin,
            ));
        } catch (EmailAlreadyRegisteredException) {
            throw new EmailAlreadyRegisteredException('first_admin_email');
        }
    }

    public function isAwaitingFirstAdmin(int $companyId): bool
    {
        return $this->companyIdsAwaitingFirstAdmin([$companyId]) === [$companyId];
    }

    public function companyIdsAwaitingFirstAdmin(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $companiesWithUsers = User::query()
            ->whereIn('company_id', $companyIds)
            ->distinct()
            ->pluck('company_id')
            ->map(fn (mixed $companyId): int => (int) $companyId)
            ->all();

        return array_values(array_filter(
            $companyIds,
            fn (int $companyId): bool => ! in_array($companyId, $companiesWithUsers, true),
        ));
    }
}
