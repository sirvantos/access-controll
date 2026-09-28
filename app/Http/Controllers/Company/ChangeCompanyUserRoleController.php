<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeRoleRequest;
use App\Http\Resources\CompanyUserResource;
use App\Modules\Identity\Actions\ChangeCompanyUserRoleAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use InvalidArgumentException;

final class ChangeCompanyUserRoleController extends Controller
{
    public function __invoke(ChangeRoleRequest $request, int $user, ChangeCompanyUserRoleAction $action): CompanyUserResource
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        return new CompanyUserResource($action($companyId, $user, $request->role()));
    }
}
