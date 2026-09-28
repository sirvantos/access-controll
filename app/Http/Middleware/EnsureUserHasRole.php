<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\PublicApi\Actor;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = $request->user();
        $role = $actor instanceof Actor ? $actor->actorRole()->value : null;

        throw_unless(in_array($role, $roles, true), AuthorizationException::class);

        return $next($request);
    }
}
