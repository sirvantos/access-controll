<?php

declare(strict_types=1);

namespace App\Modules\Identity\PublicApi;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case Viewer = 'viewer';

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::CompanyAdmin, self::Viewer];
    }
}
