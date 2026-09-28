<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Companies\PublicApi\CompanyProfileView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class CompanyProfileResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     time_zone: string,
     *     bin: string|null,
     *     contact_person: string|null,
     *     phone: string|null,
     *     email: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $profile = $this->resource;

        throw_unless($profile instanceof CompanyProfileView, InvalidArgumentException::class);

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'time_zone' => $profile->timeZone,
            'bin' => $profile->bin,
            'contact_person' => $profile->contactPerson,
            'phone' => $profile->phone,
            'email' => $profile->email,
        ];
    }
}
