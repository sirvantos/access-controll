<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\PublicApi\CompanyMediaFileView;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

final class ShowCompanyMediaAction
{
    public function __invoke(string $publicId): CompanyMediaFileView
    {
        $media = CompanyMedia::query()
            ->where('public_id', $publicId)
            ->firstOrFail();

        if (! Storage::disk($media->disk)->exists($media->path)) {
            throw (new ModelNotFoundException)->setModel(CompanyMedia::class, [$publicId]);
        }

        return new CompanyMediaFileView(
            disk: $media->disk,
            path: $media->path,
            contentType: $media->content_type,
        );
    }
}
