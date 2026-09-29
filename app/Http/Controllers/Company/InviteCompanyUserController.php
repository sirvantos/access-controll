<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\InviteCompanyUserAction;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class InviteCompanyUserController extends Controller
{
    public function __invoke(
        InviteUserRequest $request,
        InviteCompanyUserAction $action,
        CompanyContext $companyContext,
    ): JsonResponse {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return (new PendingInvitationResource($action($companyContext->companyId(), $request->toDto())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
