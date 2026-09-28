<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('refuses every protected api route for a guest', function () {
    $publicUris = [
        'api/v1/auth/sign-in',
        'api/v1/auth/forgot-password',
        'api/v1/auth/reset-password',
        'api/v1/invitations/{token}',
        'api/v1/invitations/{token}/accept',
    ];
    $checked = 0;

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1/') || in_array($uri, $publicUris, true)) {
            continue;
        }

        if (str_contains($uri, '{fallbackPlaceholder}')) {
            continue;
        }

        $path = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);

        foreach ($route->methods() as $method) {
            $response = $this->withHeaders(statefulHeaders())->json($method, $path);

            $response->assertUnauthorized();

            $body = $response->getContent();

            if (is_string($body) && $body !== '') {
                expect($response->json())->not->toHaveKey('data');
            }

            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(0);
});
