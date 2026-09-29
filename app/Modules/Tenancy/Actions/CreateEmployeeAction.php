<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Exceptions\EmployeeNumberTakenException;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Database\UniqueConstraintViolationException;

final class CreateEmployeeAction
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    public function __invoke(string $name, string $employeeNumber): Employee
    {
        try {
            return Employee::query()->create([
                'company_id' => $this->companyContext->companyId(),
                'name' => $name,
                'employee_number' => $employeeNumber,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new EmployeeNumberTakenException;
        }
    }
}
