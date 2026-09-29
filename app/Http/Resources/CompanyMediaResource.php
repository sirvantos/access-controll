<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Tenancy\PublicApi\CompanyMediaFileView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CompanyMediaResource extends JsonResource
{
    /**
     * @return array<string, never>
     */
    public function toArray(Request $request): array
    {
        return [];
    }

    /**
     * @phpstan-ignore method.childReturnType (JsonResource::toResponse() is typed JsonResponse; media 200 must stream BinaryFileResponse per specs/003 contracts/http-api.md. Remove this ignore when JsonResource::toResponse() is typed to allow BinaryFileResponse.)
     */
    public function toResponse($request): BinaryFileResponse
    {
        $file = $this->resource;

        throw_unless($file instanceof CompanyMediaFileView, InvalidArgumentException::class);

        $absolutePath = Storage::disk($file->disk)->path($file->path);

        return response()->file($absolutePath, [
            'Content-Type' => $file->contentType,
        ]);
    }
}
