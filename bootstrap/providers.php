<?php

use App\Modules\Companies\CompaniesServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CompaniesServiceProvider::class,
    IdentityServiceProvider::class,
];
