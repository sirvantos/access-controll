<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class CurrentUserResource extends JsonResource
{
    /**
     * @return array{id: int, email: string, role: string, company_id: int|null}
     */
    public function toArray(Request $request): array
    {
        $actor = $this->resource;

        throw_unless($actor instanceof Actor, InvalidArgumentException::class);

        return [
            'id' => $actor->actorId(),
            'email' => $this->email($actor),
            'role' => $actor->actorRole()->value,
            'company_id' => $actor->actorCompanyId(),
        ];
    }

    private function email(Actor $actor): string
    {
        throw_unless($actor instanceof Model, InvalidArgumentException::class);

        $email = $actor->getAttribute('email');

        throw_unless(is_string($email), InvalidArgumentException::class);

        return $email;
    }
}
