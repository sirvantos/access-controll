<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Resources\OkResource;
use App\Modules\Identity\Actions\AcceptInvitationAction;

final class AcceptInvitationController extends Controller
{
    public function __invoke(AcceptInvitationRequest $request, string $token, AcceptInvitationAction $action): OkResource
    {
        $action($token, $request->string('password'));

        return new OkResource(null);
    }
}
