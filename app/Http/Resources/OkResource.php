<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OkResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{ok: true}
     */
    public function toArray(Request $request): array
    {
        return [
            'ok' => true,
        ];
    }
}
