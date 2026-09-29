<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * @return list<array{0: string, 1: string}>
 */
function materializedCompanyApiRoutes(): array
{
    $calls = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (
            $uri !== 'api/v1/company'
            && ! str_starts_with($uri, 'api/v1/company/')
            && $uri !== '_test/company/isolation-probes'
        ) {
            continue;
        }

        $path = preg_replace('/\{[^}]+\}/', '1', $uri);
        throw_unless(is_string($path), RuntimeException::class);

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $calls[] = [$method, '/'.$path];
        }
    }

    return $calls;
}

/**
 * @return list<array{0: string, 1: string}>
 */
function parameterizedCompanyApiRouteTemplates(): array
{
    $calls = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1/company/') || ! str_contains($uri, '{')) {
            continue;
        }

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $calls[] = [$method, $uri];
        }
    }

    return $calls;
}

function companyDataUriWithIds(string $uri, int $userId, int $invitationId, string $publicId): string
{
    $filled = strtr($uri, [
        '{user}' => (string) $userId,
        '{invitation}' => (string) $invitationId,
        '{public_id}' => $publicId,
    ]);

    throw_if(str_contains($filled, '{'), RuntimeException::class, 'Unfilled company-data parameter: '.$uri);

    return '/'.$filled;
}

/**
 * @return array<string, mixed>
 */
function companyDataIsolationRequestBody(string $method, string $uri): array
{
    if ($method === 'PATCH' && str_contains($uri, 'users/{user}')) {
        return ['role' => 'viewer'];
    }

    return [];
}

/**
 * @param  list<int>  $globexRecordIds
 * @param  list<string>  $globexEmails
 */
function assertJsonHasNoGlobexLeaks(TestResponse $response, array $globexRecordIds, array $globexEmails): void
{
    $payload = $response->json();
    $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

    if (is_array($payload['data'] ?? null)) {
        $rows = $payload['data'];
        $isList = array_is_list($rows);

        if ($isList && $rows !== []) {
            $ids = array_column($rows, 'id');

            foreach ($globexRecordIds as $id) {
                expect($ids)->not->toContain($id);
            }
        } elseif (! $isList && isset($payload['data']['id'])) {
            foreach ($globexRecordIds as $id) {
                expect($payload['data']['id'])->not->toBe($id);
            }
        }
    }

    foreach ($globexEmails as $email) {
        expect($encoded)->not->toContain($email);
    }

    expect($encoded)->not->toContain('Globex')
        ->and($encoded)->not->toContain('Aigerim Sarsenova')
        ->and($encoded)->not->toContain('invitee@globex.test')
        ->and($encoded)->not->toContain('admin@globex.test')
        ->and($encoded)->not->toContain('viewer@globex.test');
}
