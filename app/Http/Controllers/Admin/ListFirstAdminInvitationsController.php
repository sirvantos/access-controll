<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingInvitationResource;
use App\Modules\Identity\Actions\ListPendingInvitationsAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListFirstAdminInvitationsController extends Controller
{
    public function __invoke(Request $request, int $company, ListPendingInvitationsAction $action): AnonymousResourceCollection
    {
        return PendingInvitationResource::collection($action($company, $request->integer('page', 1)));
    }
}
