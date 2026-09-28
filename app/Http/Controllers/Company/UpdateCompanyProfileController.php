<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Http\Resources\CompanyProfileResource;
use App\Modules\Companies\Actions\UpdateCompanyProfileAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;

final class UpdateCompanyProfileController extends Controller
{
    public function __invoke(
        UpdateCompanyProfileRequest $request,
        UpdateCompanyProfileAction $action,
    ): CompanyProfileResource {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new CompanyProfileResource($action($actor, $request->toDto()));
    }
}
