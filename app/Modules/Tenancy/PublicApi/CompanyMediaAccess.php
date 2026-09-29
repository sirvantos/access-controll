<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\PublicApi;

interface CompanyMediaAccess
{
    public function kindForCurrentCompanyPublicId(string $publicId): ?CompanyMediaKind;
}
