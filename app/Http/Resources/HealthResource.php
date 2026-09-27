<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Health\PublicApi\HealthStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HealthResource extends JsonResource
{
    /**
     * @return array{status: string}
     */
    public function toArray(Request $request): array
    {
        /** @var HealthStatus $status */
        $status = $this->resource;

        return [
            'status' => $status->status,
        ];
    }
}
