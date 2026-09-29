<?php

declare(strict_types=1);

/**
 * @return list<string>
 */
function localeLangPhpFiles(string $locale): array
{
    $directory = base_path('lang/'.$locale);

    if (! is_dir($directory)) {
        return [];
    }

    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($directory) + 1);
        $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    sort($files);

    return $files;
}

/**
 * @return list<string>
 */
function dottedKeysFromLangFile(string $path): array
{
    /** @var array<string, mixed> $lines */
    $lines = require $path;

    return dottedKeysFromArray($lines);
}

/**
 * @param  array<string, mixed>  $lines
 * @return list<string>
 */
function dottedKeysFromArray(array $lines): array
{
    $keys = [];

    $walk = function (array $array, string $prefix) use (&$keys, &$walk): void {
        foreach ($array as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $walk($value, $dotted);
            } else {
                $keys[] = $dotted;
            }
        }
    };

    $walk($lines, '');
    sort($keys);

    return $keys;
}

it('has matching lang file paths between en and ru', function () {
    $enFiles = localeLangPhpFiles('en');
    $ruFiles = localeLangPhpFiles('ru');

    expect($enFiles)->not->toBeEmpty();
    expect($ruFiles)->toBe($enFiles);
});

it('has exactly the same dotted translation keys in en and ru for each lang file', function () {
    foreach (localeLangPhpFiles('en') as $relativePath) {
        $enPath = base_path('lang/en/'.$relativePath);
        $ruPath = base_path('lang/ru/'.$relativePath);

        expect(file_exists($ruPath))->toBeTrue("Missing Russian lang file for {$relativePath}");

        $enKeys = dottedKeysFromLangFile($enPath);
        $ruKeys = dottedKeysFromLangFile($ruPath);

        expect($ruKeys)->toBe($enKeys);
    }
});

it('has no extra Russian lang files without an English counterpart', function () {
    $enFiles = localeLangPhpFiles('en');

    foreach (localeLangPhpFiles('ru') as $relativePath) {
        expect($enFiles)->toContain($relativePath);
    }
});

it('has exactly the same dotted keys in frontend locale files', function () {
    /** @var array<string, mixed> $en */
    $en = json_decode((string) file_get_contents(resource_path('js/locales/en.json')), true, 512, JSON_THROW_ON_ERROR);
    /** @var array<string, mixed> $ru */
    $ru = json_decode((string) file_get_contents(resource_path('js/locales/ru.json')), true, 512, JSON_THROW_ON_ERROR);

    expect(dottedKeysFromArray($en))->toBe(dottedKeysFromArray($ru));
});
