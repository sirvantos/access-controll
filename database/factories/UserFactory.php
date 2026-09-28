<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    private const string DEACTIVATED_AT = '2026-01-15 12:00:00';

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Viewer,
            'company_id' => Company::factory(),
            'deactivated_at' => null,
            'session_version' => 1,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => Role::SuperAdmin,
            'company_id' => null,
        ]);
    }

    public function companyAdmin(Company|int $company): static
    {
        return $this->forCompany(Role::CompanyAdmin, $company);
    }

    public function viewer(Company|int $company): static
    {
        return $this->forCompany(Role::Viewer, $company);
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deactivated_at' => self::DEACTIVATED_AT,
        ]);
    }

    private function forCompany(Role $role, Company|int $company): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role,
            'company_id' => $company instanceof Company ? $company->id : $company,
        ]);
    }
}
