<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenancy\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Terminal>
 */
class TerminalFactory extends Factory
{
    protected $model = Terminal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Main terminal',
        ];
    }
}
