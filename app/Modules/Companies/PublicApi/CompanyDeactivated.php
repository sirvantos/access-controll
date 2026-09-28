<?php

declare(strict_types=1);

namespace App\Modules\Companies\PublicApi;

final class CompanyDeactivated
{
    public function __construct(public int $companyId) {}
}
