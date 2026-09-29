<?php

declare(strict_types=1);

namespace App\Modules\Companies\PublicApi;

interface CompanyDirectory
{
    public function exists(int $companyId): bool;

    public function isActive(int $companyId): bool;

    public function name(int $companyId): ?string;
}
