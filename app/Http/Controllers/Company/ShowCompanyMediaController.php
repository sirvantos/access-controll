<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowCompanyMediaRequest;
use App\Http\Resources\CompanyMediaResource;
use App\Modules\Tenancy\Actions\ShowCompanyMediaAction;

final class ShowCompanyMediaController extends Controller
{
    public function __invoke(
        ShowCompanyMediaRequest $request,
        ShowCompanyMediaAction $action,
        string $publicId,
    ): CompanyMediaResource {
        return new CompanyMediaResource($action($publicId));
    }
}
