<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class TimeZoneIdentifiersResource extends JsonResource
{
    /**
     * @return array{identifiers: list<string>}
     */
    public function toArray(Request $request): array
    {
        $identifiers = $this->resource;

        throw_unless(is_array($identifiers), InvalidArgumentException::class);

        $identifiers = array_values($identifiers);

        throw_unless(
            array_all($identifiers, fn (mixed $identifier): bool => is_string($identifier)),
            InvalidArgumentException::class,
        );

        /** @var list<string> $identifiers */
        return [
            'identifiers' => $identifiers,
        ];
    }
}
