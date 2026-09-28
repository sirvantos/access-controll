<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OkResource;
use App\Modules\Identity\Actions\RevokeInvitationAction;

final class RevokeFirstAdminInvitationController extends Controller
{
    public function __invoke(int $company, int $invitation, RevokeInvitationAction $action): OkResource
    {
        $action($company, $invitation);

        return new OkResource(null);
    }
}
