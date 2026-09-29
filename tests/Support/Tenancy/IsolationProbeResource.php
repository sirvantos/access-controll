<?php

declare(strict_types=1);

namespace Tests\Support\Tenancy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class IsolationProbeResource extends JsonResource
{
    /**
     * @return array{id: int, name: string}
     */
    public function toArray(Request $request): array
    {
        $probe = $this->resource;

        throw_unless($probe instanceof IsolationProbe, InvalidArgumentException::class);

        return [
            'id' => $probe->id,
            'name' => $probe->name,
        ];
    }
}
