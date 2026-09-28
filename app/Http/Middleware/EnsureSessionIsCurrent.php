<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\SessionValidity;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsCurrent
{
    public const string SESSION_KEY = 'session_version';

    public function __construct(private SessionValidity $sessionValidity) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $isCurrent = $actor instanceof Actor
            && $this->sessionValidity->isCurrent($actor, $this->storedSessionVersion($request));

        if (! $isCurrent) {
            $this->endSession($request);
        }

        throw_unless($isCurrent, AuthenticationException::class);

        return $next($request);
    }

    private function storedSessionVersion(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $stored = $request->session()->get(self::SESSION_KEY);

        return is_int($stored) ? $stored : null;
    }

    private function endSession(Request $request): void
    {
        auth('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
        }
    }
}
