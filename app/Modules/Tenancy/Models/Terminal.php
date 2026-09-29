<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\BelongsToCompany;
use Database\Factories\TerminalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name'])]
#[UseFactory(TerminalFactory::class)]
class Terminal extends Model
{
    /** @use HasFactory<TerminalFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return HasMany<AccessEvent, $this>
     */
    public function accessEvents(): HasMany
    {
        return $this->hasMany(AccessEvent::class);
    }

    /**
     * @return array{
     *     id: 'integer',
     *     company_id: 'integer',
     *     name: 'string',
     *     created_at: 'datetime',
     *     updated_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'name' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
