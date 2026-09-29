<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\PublicApi\Role;
use Database\Factories\Concerns\CreatesWhenIsolationUnbound;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    use CreatesWhenIsolationUnbound;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email' => 'invitee@example.com',
            'role' => Role::Viewer,
            'token_hash' => hash('sha256', Str::uuid()->toString()),
            'expires_at' => '2030-01-15 12:00:00',
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => '2026-01-15 11:00:00',
            'accepted_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => null,
            'revoked_at' => '2026-01-15 12:00:00',
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => '2026-01-15 12:00:00',
        ]);
    }
}
