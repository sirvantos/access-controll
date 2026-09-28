<?php

declare(strict_types=1);

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanyDirectory;

class CompanyDirectoryService implements CompanyDirectory
{
    public function exists(int $companyId): bool
    {
        return Company::query()->whereKey($companyId)->exists();
    }

    public function isActive(int $companyId): bool
    {
        return Company::query()->find($companyId)?->isActive() ?? false;
    }
}
