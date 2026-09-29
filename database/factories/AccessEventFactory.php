<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessEvent>
 */
class AccessEventFactory extends Factory
{
    protected $model = AccessEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => '1001',
            'employee_id' => null,
            'snapshot_media_id' => null,
            'created_at' => now(),
        ];
    }

    public function forTerminal(Terminal $terminal): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $terminal->company_id,
            'terminal_id' => $terminal->id,
        ]);
    }
}
