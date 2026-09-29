<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\OkResource;
use App\Modules\Identity\Actions\RevokeInvitationAction;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class RevokeCompanyInvitationController extends Controller
{
    public function __invoke(
        Request $request,
        int $invitation,
        RevokeInvitationAction $action,
        CompanyContext $companyContext,
    ): OkResource {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $action($companyContext->companyId(), $invitation);

        return new OkResource(null);
    }
}
