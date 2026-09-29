<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeRoleRequest;
use App\Http\Resources\CompanyUserResource;
use App\Modules\Identity\Actions\ChangeCompanyUserRoleAction;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Auth\AuthenticationException;

final class ChangeCompanyUserRoleController extends Controller
{
    public function __invoke(
        ChangeRoleRequest $request,
        int $user,
        ChangeCompanyUserRoleAction $action,
        CompanyContext $companyContext,
    ): CompanyUserResource {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new CompanyUserResource($action($companyContext->companyId(), $user, $request->role()));
    }
}
