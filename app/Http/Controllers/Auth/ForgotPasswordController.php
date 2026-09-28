<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailRequest;
use App\Http\Resources\OkResource;
use App\Modules\Identity\Actions\RequestPasswordResetAction;

final class ForgotPasswordController extends Controller
{
    public function __invoke(EmailRequest $request, RequestPasswordResetAction $action): OkResource
    {
        $action($request->emailAddress());

        return new OkResource(null);
    }
}
