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

    $health = Layer::withName('Health')->collectors(
        BoolConfig::create(
            [DirectoryConfig::create('app/Modules/Health/.*')],
            [DirectoryConfig::create('app/Modules/Health/PublicApi/.*')],
        ),
    );

    $healthPublicApi = Layer::withName('HealthPublicApi')->collectors(
        DirectoryConfig::create('app/Modules/Health/PublicApi/.*'),
    );

    $config
        ->paths('app/Http', 'app/Modules')
        ->layers($http, $health, $healthPublicApi)
        ->rulesets(
            Ruleset::forLayer($http)->accesses($health, $healthPublicApi),
            Ruleset::forLayer($health)->accesses($healthPublicApi),
            Ruleset::forLayer($healthPublicApi),
        );
};
