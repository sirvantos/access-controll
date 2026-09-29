<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Data\SuperAdminActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['actor_id', 'company_id', 'type', 'action', 'occurred_at'])]
class SuperAdminActionRecord extends Model
{
    public const ?string UPDATED_AT = null;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Super admin action records are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Super admin action records are append-only.'));
    }

    /**
     * @return array{
     *     id: 'integer',
     *     actor_id: 'integer',
     *     company_id: 'integer',
     *     type: 'App\Modules\Tenancy\Data\SuperAdminActionType',
     *     action: 'string',
     *     occurred_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'actor_id' => 'integer',
            'company_id' => 'integer',
            'type' => SuperAdminActionType::class,
            'action' => 'string',
            'occurred_at' => 'datetime',
        ];
    }
}
