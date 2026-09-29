<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\PublicApi\CompanyMediaAccess;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;

final class CompanyMediaAccessService implements CompanyMediaAccess
{
    public function kindForCurrentCompanyPublicId(string $publicId): ?CompanyMediaKind
    {
        $media = CompanyMedia::query()
            ->where('public_id', $publicId)
            ->first();

        return $media?->kind;
    }
}
