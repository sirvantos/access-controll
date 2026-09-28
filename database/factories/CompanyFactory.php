<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    private const string DEACTIVATED_AT = '2026-01-15 12:00:00';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Acme',
            'deactivated_at' => null,
        ];
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deactivated_at' => self::DEACTIVATED_AT,
        ]);
    }
}
