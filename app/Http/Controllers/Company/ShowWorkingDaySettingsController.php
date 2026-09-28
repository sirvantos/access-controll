<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkingDaySettingsResource;
use App\Modules\Companies\Actions\ShowWorkingDaySettingsAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class ShowWorkingDaySettingsController extends Controller
{
    public function __invoke(Request $request, ShowWorkingDaySettingsAction $action): WorkingDaySettingsResource
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new WorkingDaySettingsResource($action($actor));
    }
}
