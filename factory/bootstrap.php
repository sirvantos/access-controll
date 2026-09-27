<?php

declare(strict_types=1);

$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (! is_file($autoload)) {
    throw new RuntimeException('Run composer install before the factory.');
}

require $autoload;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Access\\Factory\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__.'/src/'.substr($class, strlen($prefix)).'.php';
    if (is_file($path)) {
        require $path;
    }
});
