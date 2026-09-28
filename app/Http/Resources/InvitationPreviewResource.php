<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Identity\PublicApi\InvitationPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class InvitationPreviewResource extends JsonResource
{
    /**
     * @return array{email: string, role: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;

        throw_unless($invitation instanceof InvitationPreview, InvalidArgumentException::class);

        return [
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expiresAt->copy()->utc()->toIso8601String(),
        ];
    }
}
