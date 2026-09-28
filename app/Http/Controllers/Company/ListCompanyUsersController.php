<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyUserResource;
use App\Modules\Identity\Actions\ListCompanyUsersAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCompanyUsersController extends Controller
{
    public function __invoke(Request $request, ListCompanyUsersAction $action): AnonymousResourceCollection
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return CompanyUserResource::collection($action($actor, $request->integer('page', 1)));
    }
}
