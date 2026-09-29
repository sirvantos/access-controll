<?php

declare(strict_types=1);

use App\Modules\Tenancy\PublicApi\CompanyMediaKind;

it('uses the data-model backed values for company media kinds', function (): void {
    expect(CompanyMediaKind::EmployeePhoto->value)->toBe('employee_photo')
        ->and(CompanyMediaKind::EventSnapshot->value)->toBe('event_snapshot')
        ->and(CompanyMediaKind::Report->value)->toBe('report')
        ->and(CompanyMediaKind::Export->value)->toBe('export');
});
