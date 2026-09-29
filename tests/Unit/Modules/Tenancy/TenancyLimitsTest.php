<?php

declare(strict_types=1);

use App\Modules\Tenancy\Data\TenancyLimits;

it('defines company context header and field length limits from constraints', function () {
    expect(TenancyLimits::COMPANY_CONTEXT_HEADER)->toBe('X-Company-Context')
        ->and(TenancyLimits::EMPLOYEE_NUMBER_MIN_LENGTH)->toBe(1)
        ->and(TenancyLimits::EMPLOYEE_NUMBER_MAX_LENGTH)->toBe(32)
        ->and(TenancyLimits::EMPLOYEE_NAME_MIN_LENGTH)->toBe(1)
        ->and(TenancyLimits::EMPLOYEE_NAME_MAX_LENGTH)->toBe(255)
        ->and(TenancyLimits::TERMINAL_NAME_MIN_LENGTH)->toBe(1)
        ->and(TenancyLimits::TERMINAL_NAME_MAX_LENGTH)->toBe(255);
});
