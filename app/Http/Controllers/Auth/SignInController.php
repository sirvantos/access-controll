<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionIsCurrent;
use App\Http\Requests\SignInRequest;
use App\Http\Resources\CurrentUserResource;
use App\Modules\Identity\Actions\SignInAction;

final class SignInController extends Controller
{
    public function __invoke(SignInRequest $request, SignInAction $signIn): CurrentUserResource
    {
        $actor = $signIn($request->toDto());

        session()->regenerate();
        session()->put(EnsureSessionIsCurrent::SESSION_KEY, $actor->sessionVersion());
        auth('web')->login($actor);

        return new CurrentUserResource($actor);
    }
}
