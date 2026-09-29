<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use App\Modules\Tenancy\PublicApi\CompanyContext;

final class TenancyLimits
{
    public const string COMPANY_CONTEXT_HEADER = CompanyContext::COMPANY_CONTEXT_HEADER;

    public const int EMPLOYEE_NUMBER_MIN_LENGTH = 1;

    public const int EMPLOYEE_NUMBER_MAX_LENGTH = 32;

    public const int EMPLOYEE_NAME_MIN_LENGTH = 1;

    public const int EMPLOYEE_NAME_MAX_LENGTH = 255;

    public const int TERMINAL_NAME_MIN_LENGTH = 1;

    public const int TERMINAL_NAME_MAX_LENGTH = 255;
}
