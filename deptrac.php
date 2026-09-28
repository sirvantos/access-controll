<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\BoolConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $http = Layer::withName('Http')->collectors(
        DirectoryConfig::create('app/Http/.*'),
    );
    $support = Layer::withName('Support')->collectors(
        DirectoryConfig::create('app/Support/.*'),
    );
    $exceptions = Layer::withName('Exceptions')->collectors(
        DirectoryConfig::create('app/Exceptions/.*'),
    );
    $rules = Layer::withName('Rules')->collectors(
        DirectoryConfig::create('app/Rules/.*'),
    );
    $providers = Layer::withName('Providers')->collectors(
        DirectoryConfig::create('app/Providers/.*'),
    );

    $moduleDirectoryNames = static function (): array {
        $modulesPath = __DIR__.'/app/Modules';
        $entries = scandir($modulesPath);
        throw_unless(is_array($entries), RuntimeException::class, 'Unable to read app/Modules.');

        $names = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($modulesPath.'/'.$entry),
        ));
        sort($names);

        return $names;
    };

    $modules = [];
    $publicApis = [];

    foreach ($moduleDirectoryNames() as $module) {
        $modulePattern = 'app/Modules/'.preg_quote($module, '#').'/.*';
        $publicApiPattern = 'app/Modules/'.preg_quote($module, '#').'/PublicApi/.*';
        $publicApiPath = __DIR__.'/app/Modules/'.$module.'/PublicApi';

        if (is_dir($publicApiPath)) {
            $modules[$module] = Layer::withName($module)->collectors(
                BoolConfig::create(
                    [DirectoryConfig::create($modulePattern)],
                    [DirectoryConfig::create($publicApiPattern)],
                ),
            );
            $publicApis[$module] = Layer::withName($module.'PublicApi')->collectors(
                DirectoryConfig::create($publicApiPattern),
            );

            continue;
        }

        $modules[$module] = Layer::withName($module)->collectors(
            DirectoryConfig::create($modulePattern),
        );
    }

    $moduleLayers = array_values($modules);
    $publicApiLayers = array_values($publicApis);
    $sharedLayers = [$support, $exceptions];
    $rulesets = [
        Ruleset::forLayer($http)->accesses(...array_merge($moduleLayers, $publicApiLayers, [$support, $rules, $exceptions])),
        Ruleset::forLayer($rules)->accesses(...array_merge($publicApiLayers, [$support])),
        Ruleset::forLayer($support),
        Ruleset::forLayer($exceptions),
        Ruleset::forLayer($providers),
    ];

    foreach ($modules as $moduleLayer) {
        $rulesets[] = Ruleset::forLayer($moduleLayer)->accesses(...array_merge($publicApiLayers, $sharedLayers));
    }

    foreach ($publicApis as $publicApiLayer) {
        $rulesets[] = Ruleset::forLayer($publicApiLayer);
    }

    $config
        ->paths('app')
        ->layers($http, $support, $exceptions, $rules, $providers, ...$moduleLayers, ...$publicApiLayers)
        ->rulesets(...$rulesets);
};
