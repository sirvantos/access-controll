<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StoreCompanyMediaAction
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    public function __invoke(
        CompanyMediaKind $kind,
        string $contents,
        string $contentType,
    ): CompanyMedia {
        $companyId = $this->companyContext->companyId();
        $publicId = (string) Str::uuid();
        $path = $companyId.'/'.$publicId;

        $media = CompanyMedia::query()->create([
            'company_id' => $companyId,
            'public_id' => $publicId,
            'kind' => $kind,
            'disk' => 'local',
            'path' => $path,
            'content_type' => $contentType,
        ]);

        Storage::disk('local')->put($path, $contents);

        return $media;
    }
}
