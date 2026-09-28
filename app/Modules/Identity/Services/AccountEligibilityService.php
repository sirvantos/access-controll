<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Companies\PublicApi\CompanyDirectory;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Identity\PublicApi\SessionValidity;

class AccountEligibilityService implements SessionValidity
{
    public function __construct(private CompanyDirectory $companies) {}

    public function isEligible(Actor $actor): bool
    {
        $user = $this->asUser($actor);

        if (! $user instanceof User || $user->deactivated_at !== null) {
            return false;
        }

        if ($user->actorRole() === Role::SuperAdmin) {
            return true;
        }

        $companyId = $user->actorCompanyId();

        return $companyId !== null && $this->companies->isActive($companyId);
    }

    public function isCurrent(Actor $actor, ?int $storedSessionVersion): bool
    {
        $user = $this->asUser($actor);

        return $user instanceof User
            && $storedSessionVersion === $user->sessionVersion()
            && $this->isEligible($user);
    }

    private function asUser(Actor $actor): ?User
    {
        if ($actor instanceof User) {
            return $actor;
        }

        return User::query()->find($actor->actorId());
    }
}
