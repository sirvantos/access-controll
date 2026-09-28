<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Data\SignInSecurityEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'email', 'user_id', 'ip_address', 'created_at'])]
class SignInSecurityEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array{
     *     id: 'integer',
     *     type: 'App\Modules\Identity\Data\SignInSecurityEventType',
     *     email: 'string',
     *     user_id: 'integer',
     *     ip_address: 'string',
     *     created_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'type' => SignInSecurityEventType::class,
            'email' => 'string',
            'user_id' => 'integer',
            'ip_address' => 'string',
            'created_at' => 'datetime',
        ];
    }
}
