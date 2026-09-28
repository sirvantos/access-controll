<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Companies\PublicApi\CompanySummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class CompanyResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, bin: string|null, is_active: bool, awaiting_first_admin: bool, created_at: string}
     */
    public function toArray(Request $request): array
    {
        $company = $this->resource;

        throw_unless($company instanceof CompanySummary, InvalidArgumentException::class);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'bin' => $company->bin,
            'is_active' => $company->isActive,
            'awaiting_first_admin' => $company->awaitingFirstAdmin,
            'created_at' => $company->createdAt->copy()->utc()->toIso8601String(),
        ];
    }
}
