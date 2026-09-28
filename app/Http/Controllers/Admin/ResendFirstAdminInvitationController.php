<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\ResendInvitationAction;

final class ResendFirstAdminInvitationController extends Controller
{
    public function __invoke(int $company, int $invitation, ResendInvitationAction $action): PendingInvitationResource
    {
        return new PendingInvitationResource($action($company, $invitation));
    }
}
