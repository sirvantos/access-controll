<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\OkResource;
use App\Modules\Identity\Actions\ResetPasswordAction;

final class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, ResetPasswordAction $action): OkResource
    {
        $action($request->toDto());

        return new OkResource(null);
    }
}
