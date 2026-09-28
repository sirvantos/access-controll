<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCompanyRequest;
use App\Http\Resources\CreatedCompanyResource;
use App\Modules\Companies\Actions\CreateCompanyAction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateCompanyController extends Controller
{
    public function __invoke(CreateCompanyRequest $request, CreateCompanyAction $action): JsonResponse
    {
        return (new CreatedCompanyResource($action($request->toDto())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
