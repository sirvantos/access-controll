<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyUserResource;
use App\Modules\Identity\Actions\ReactivateCompanyUserAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class ReactivateCompanyUserController extends Controller
{
    public function __invoke(Request $request, int $user, ReactivateCompanyUserAction $action): CompanyUserResource
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $companyId = $actor->actorCompanyId();

        throw_unless(is_int($companyId), InvalidArgumentException::class);

        return new CompanyUserResource($action($companyId, $user));
    }
}
