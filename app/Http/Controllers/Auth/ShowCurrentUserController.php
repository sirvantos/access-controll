<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use App\Modules\Identity\PublicApi\Actor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class ShowCurrentUserController extends Controller
{
    public function __invoke(Request $request): CurrentUserResource
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        return new CurrentUserResource($actor);
    }
}
