<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteFirstAdminRequest;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\InviteCompanyUserAction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class InviteFirstAdminController extends Controller
{
    public function __invoke(InviteFirstAdminRequest $request, int $company, InviteCompanyUserAction $action): JsonResponse
    {
        return (new PendingInvitationResource($action($company, $request->toDto())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
