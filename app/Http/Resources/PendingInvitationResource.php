<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Identity\PublicApi\PendingInvitationView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class PendingInvitationResource extends JsonResource
{
    /**
     * @return array{id: int, email: string, role: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;

        throw_unless($invitation instanceof PendingInvitationView, InvalidArgumentException::class);

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expiresAt->copy()->utc()->toIso8601String(),
        ];
    }
}
