<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const int API_REQUESTS_PER_MINUTE = 60;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'api',
            fn (Request $request): Limit => Limit::perMinute(self::API_REQUESTS_PER_MINUTE)->by(
                $this->apiRateLimitKey($request),
            ),
        );
    }

    private function apiRateLimitKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            return (string) $userId;
        }

        return (string) $request->ip();
    }
}
