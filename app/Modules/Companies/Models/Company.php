<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'deactivated_at'])]
#[UseFactory(CompanyFactory::class)]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * @return array{
     *     id: 'integer',
     *     name: 'string',
     *     deactivated_at: 'datetime',
     *     created_at: 'datetime',
     *     updated_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'deactivated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
