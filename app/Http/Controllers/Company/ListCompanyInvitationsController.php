<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\ListPendingInvitationsAction;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCompanyInvitationsController extends Controller
{
    public function __invoke(
        Request $request,
        ListPendingInvitationsAction $action,
        CompanyContext $companyContext,
    ): AnonymousResourceCollection {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return PendingInvitationResource::collection($action($companyContext->companyId(), $request->integer('page', 1)));
    }
}
