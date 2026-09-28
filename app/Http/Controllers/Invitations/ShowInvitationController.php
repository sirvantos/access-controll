<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationPreviewResource;
use App\Modules\Identity\Actions\ShowInvitationAction;

final class ShowInvitationController extends Controller
{
    public function __invoke(string $token, ShowInvitationAction $action): InvitationPreviewResource
    {
        return new InvitationPreviewResource($action($token));
    }
}
