<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenancy\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => '1001',
            'name' => 'Sample Employee',
        ];
    }
}
