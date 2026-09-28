<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkingDaySettingsRequest;
use App\Http\Resources\WorkingDaySettingsResource;
use App\Modules\Companies\Actions\UpdateWorkingDaySettingsAction;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;

final class UpdateWorkingDaySettingsController extends Controller
{
    public function __invoke(
        UpdateWorkingDaySettingsRequest $request,
        UpdateWorkingDaySettingsAction $action,
    ): WorkingDaySettingsResource {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new WorkingDaySettingsResource($action($actor, $request->toDto()));
    }
}
