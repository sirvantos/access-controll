<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyMedia>
 */
class CompanyMediaFactory extends Factory
{
    protected $model = CompanyMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publicId = (string) Str::uuid();

        return [
            'public_id' => $publicId,
            'kind' => CompanyMediaKind::EmployeePhoto,
            'disk' => 'local',
            'path' => '0/'.$publicId,
            'content_type' => 'image/jpeg',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CompanyMedia $media): void {
            if ($media->path === '0/'.$media->public_id) {
                $media->path = $media->company_id.'/'.$media->public_id;
            }
        });
    }
}
