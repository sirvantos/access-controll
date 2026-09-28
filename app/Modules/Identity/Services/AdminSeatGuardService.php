<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Exceptions\LastActiveAdminException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class AdminSeatGuardService
{
    public const int LOCK_TTL_SECONDS = 10;

    public const int LOCK_WAIT_SECONDS = 5;

    public const string LOCK_NAME_PREFIX = 'identity:company-admins:';

    /**
     * @param  Closure(): void  $callback
     */
    public function execute(int $companyId, int $targetUserId, Closure $callback): void
    {
        Cache::lock($this->lockName($companyId), self::LOCK_TTL_SECONDS)
            ->block(
                self::LOCK_WAIT_SECONDS,
                fn () => $this->runGuarded($companyId, $targetUserId, $callback),
            );
    }

    public function lockName(int $companyId): string
    {
        return self::LOCK_NAME_PREFIX.$companyId;
    }

    /**
     * @param  Closure(): void  $callback
     */
    private function runGuarded(int $companyId, int $targetUserId, Closure $callback): void
    {
        DB::transaction(fn () => $this->apply($companyId, $targetUserId, $callback));
    }

    /**
     * @param  Closure(): void  $callback
     */
    private function apply(int $companyId, int $targetUserId, Closure $callback): void
    {
        $this->assertAnotherAdminRemains($companyId, $targetUserId);
        $callback();
    }

    private function assertAnotherAdminRemains(int $companyId, int $targetUserId): void
    {
        /** @var list<int> $activeAdminIds */
        $activeAdminIds = User::query()
            ->where('company_id', $companyId)
            ->where('role', Role::CompanyAdmin)
            ->whereNull('deactivated_at')
            ->pluck('id')
            ->all();

        $remaining = count(array_filter(
            $activeAdminIds,
            fn (int $id): bool => $id !== $targetUserId,
        ));

        throw_if($remaining === 0 && $activeAdminIds !== [], LastActiveAdminException::class);
    }
}
