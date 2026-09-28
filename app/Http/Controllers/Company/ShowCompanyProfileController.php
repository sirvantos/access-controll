<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyProfileResource;
use App\Modules\Companies\Actions\ShowCompanyProfileAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class ShowCompanyProfileController extends Controller
{
    public function __invoke(Request $request, ShowCompanyProfileAction $action): CompanyProfileResource
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new CompanyProfileResource($action($actor));
    }
}
