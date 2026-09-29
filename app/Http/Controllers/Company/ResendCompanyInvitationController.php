<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\ResendInvitationAction;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class ResendCompanyInvitationController extends Controller
{
    public function __invoke(
        Request $request,
        int $invitation,
        ResendInvitationAction $action,
        CompanyContext $companyContext,
    ): PendingInvitationResource {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new PendingInvitationResource($action($companyContext->companyId(), $invitation));
    }
}
