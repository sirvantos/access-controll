<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Identity\PublicApi\Actor;
use App\Support\CompanyContextStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        $this->rebindAuthenticatedCompanyContext();

        return $response;
    }

    protected function rebindAuthenticatedCompanyContext(): void
    {
        $user = auth('web')->user();

        if (! $user instanceof Actor) {
            return;
        }

        $store = app(CompanyContextStore::class);
        $companyId = $user->actorCompanyId();

        if ($companyId !== null) {
            $store->bindCompany($companyId);
        }

        $store->setActorId($user->actorId());
    }
}
