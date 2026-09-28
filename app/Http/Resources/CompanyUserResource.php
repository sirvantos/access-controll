<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Identity\PublicApi\CompanyUserView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class CompanyUserResource extends JsonResource
{
    /**
     * @return array{id: int, email: string, role: string, is_active: bool}
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        throw_unless($user instanceof CompanyUserView, InvalidArgumentException::class);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->isActive,
        ];
    }
}
