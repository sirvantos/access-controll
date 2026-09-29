<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('documents a nullable bin on the company schema', function () {
    $yaml = (string) file_get_contents(public_path('swagger.yaml'));

    expect($yaml)->toContain('required: [id, name, bin, is_active, awaiting_first_admin, created_at]')
        ->and($yaml)->toContain("bin:\n          type: [\"string\", \"null\"]");
});

it('documents every api v1 route', function () {
    $operations = openApiOperations((string) file_get_contents(public_path('swagger.yaml')));

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1/')) {
            continue;
        }

        $path = '/'.ltrim(substr($uri, strlen('api/v1')), '/');

        expect($operations)->toHaveKey($path);

        foreach ($route->methods() as $method) {
            expect($operations[$path])->toContain(strtolower($method));
        }
    }
});

it('documents selected company, media bytes, and company_not_selected without test probes', function () {
    $yaml = (string) file_get_contents(public_path('swagger.yaml'));

    expect($yaml)->toContain('/admin/selected-company')
        ->and($yaml)->toContain('/company/media/{public_id}')
        ->and($yaml)->toContain('X-Company-Context')
        ->and($yaml)->toContain('company_not_selected')
        ->and($yaml)->toContain('format: binary')
        ->and($yaml)->not->toContain('/_test/');
});

/**
 * @return array<string, list<string>>
 */
function openApiOperations(string $yaml): array
{
    $operations = [];
    $currentPath = null;
    $inPaths = false;

    foreach (preg_split("/\r\n|\n|\r/", $yaml) as $line) {
        if (preg_match('/^paths:\s*$/', (string) $line) === 1) {
            $inPaths = true;

            continue;
        }

        if (! $inPaths) {
            continue;
        }

        if (is_string($line) && preg_match('/^[A-Za-z]/', $line) === 1) {
            break;
        }

        if (is_string($line) && preg_match('/^  (\/\S*):$/', $line, $matches) === 1) {
            $currentPath = $matches[1];
            $operations[$currentPath] = [];

            continue;
        }

        if ($currentPath !== null && is_string($line) && preg_match('/^    ([a-z]+):$/', $line, $matches) === 1) {
            $operations[$currentPath][] = $matches[1];
        }
    }

    return $operations;
}
