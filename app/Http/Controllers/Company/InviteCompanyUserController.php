<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\InviteCompanyUserAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class InviteCompanyUserController extends Controller
{
    public function __invoke(InviteUserRequest $request, InviteCompanyUserAction $action): JsonResponse
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        return (new PendingInvitationResource($action($companyId, $request->toDto())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
