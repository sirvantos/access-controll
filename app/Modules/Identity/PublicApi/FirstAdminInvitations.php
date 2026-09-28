<?php

declare(strict_types=1);

namespace App\Modules\Identity\PublicApi;

use Illuminate\Support\Stringable;

interface FirstAdminInvitations
{
    public function invite(int $companyId, Stringable|string $email): void;

    public function isAwaitingFirstAdmin(int $companyId): bool;

    /**
     * @param  list<int>  $companyIds
     * @return list<int>
     */
    public function companyIdsAwaitingFirstAdmin(array $companyIds): array;
}
