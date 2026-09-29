<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\PublicApi;

enum CompanyMediaKind: string
{
    case EmployeePhoto = 'employee_photo';
    case EventSnapshot = 'event_snapshot';
    case Report = 'report';
    case Export = 'export';
}
