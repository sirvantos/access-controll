<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\ResendInvitationAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class ResendCompanyInvitationController extends Controller
{
    public function __invoke(Request $request, int $invitation, ResendInvitationAction $action): PendingInvitationResource
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        return new PendingInvitationResource($action($companyId, $invitation));
    }
}
