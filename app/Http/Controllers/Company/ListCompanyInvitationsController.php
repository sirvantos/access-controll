<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\ListPendingInvitationsAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

final class ListCompanyInvitationsController extends Controller
{
    public function __invoke(Request $request, ListPendingInvitationsAction $action): AnonymousResourceCollection
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        return PendingInvitationResource::collection($action($companyId, $request->integer('page', 1)));
    }
}
