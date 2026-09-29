<?php

declare(strict_types=1);

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanyDirectory;
use App\Modules\Tenancy\PublicApi\CompanyContext;

class CompanyDirectoryService implements CompanyDirectory
{
    public function __construct(private CompanyContext $companyContext) {}

    public function exists(int $companyId): bool
    {
        return $this->companyContext->withoutIsolation(
            fn (): bool => Company::query()->whereKey($companyId)->exists(),
        );
    }

    public function isActive(int $companyId): bool
    {
        return $this->companyContext->withoutIsolation(
            fn (): bool => Company::query()->find($companyId)?->isActive() ?? false,
        );
    }

    public function name(int $companyId): ?string
    {
        return Company::query()->find($companyId)?->name;
    }
}
