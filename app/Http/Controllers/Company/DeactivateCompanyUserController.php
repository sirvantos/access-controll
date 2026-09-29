<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyUserResource;
use App\Modules\Identity\Actions\DeactivateCompanyUserAction;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class DeactivateCompanyUserController extends Controller
{
    public function __invoke(
        Request $request,
        int $user,
        DeactivateCompanyUserAction $action,
        CompanyContext $companyContext,
    ): CompanyUserResource {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new CompanyUserResource($action($companyContext->companyId(), $user));
    }
}
