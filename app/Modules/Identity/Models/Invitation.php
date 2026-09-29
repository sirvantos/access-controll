<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\PublicApi\PendingInvitationView;
use App\Modules\Identity\PublicApi\Role;
use App\Support\BelongsToCompany;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'email', 'role', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at'])]
#[UseFactory(InvitationFactory::class)]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToCompany, HasFactory;

    public function state(): InvitationState
    {
        if ($this->accepted_at !== null) {
            return InvitationState::Accepted;
        }

        if ($this->revoked_at !== null) {
            return InvitationState::Revoked;
        }

        if ($this->expires_at->lessThanOrEqualTo(now())) {
            return InvitationState::Expired;
        }

        return InvitationState::Pending;
    }

    public function toPendingInvitationView(): PendingInvitationView
    {
        return new PendingInvitationView(
            id: $this->id,
            email: $this->email,
            role: $this->role,
            expiresAt: $this->expires_at,
        );
    }

    /**
     * @return array{
     *     id: 'integer',
     *     company_id: 'integer',
     *     email: 'string',
     *     role: 'App\Modules\Identity\PublicApi\Role',
     *     token_hash: 'string',
     *     expires_at: 'datetime',
     *     accepted_at: 'datetime',
     *     revoked_at: 'datetime',
     *     created_at: 'datetime',
     *     updated_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'email' => 'string',
            'role' => Role::class,
            'token_hash' => 'string',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
