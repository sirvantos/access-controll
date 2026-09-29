<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Tenancy\PublicApi\SelectedCompanyView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class SelectedCompanyResource extends JsonResource
{
    /**
     * @return array{id: int, name: string}
     */
    public function toArray(Request $request): array
    {
        $selected = $this->resource;

        throw_unless($selected instanceof SelectedCompanyView, InvalidArgumentException::class);

        return [
            'id' => $selected->id,
            'name' => $selected->name,
        ];
    }
}
