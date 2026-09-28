<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Modules\Identity\Actions\InviteCompanyUserAction;
use App\Modules\Identity\Data\InviteUserData;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

final class FirstAdminInvitationsService implements FirstAdminInvitations
{
    public function __construct(private InviteCompanyUserAction $inviteCompanyUser) {}

    public function invite(int $companyId, Stringable|string $email): void
    {
        try {
            $this->inviteCompanyUser->__invoke($companyId, new InviteUserData(
                email: $email instanceof Stringable ? $email->lower() : Str::of($email)->lower(),
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
