<?php

declare(strict_types=1);

use App\Http\Resources\OkResource;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;

function registerOkProbeRoute(): void
{
    Route::middleware('throttle:api')->get('/_test/ok', fn () => new OkResource(null));
}

beforeEach(fn () => registerOkProbeRoute());

it('allows the configured number of requests and rejects the next one', function () {
    expect(AppServiceProvider::API_REQUESTS_PER_MINUTE)->toBe(60);

    foreach (range(1, AppServiceProvider::API_REQUESTS_PER_MINUTE) as $attempt) {
        $this->getJson('/_test/ok')
            ->assertSuccessful()
            ->assertExactJson(['ok' => true]);
    }

    $this->getJson('/_test/ok')->assertTooManyRequests();
});

it('keeps separate counters for two signed-out ips', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

    foreach (range(1, AppServiceProvider::API_REQUESTS_PER_MINUTE) as $attempt) {
        $this->getJson('/_test/ok')->assertSuccessful();
    }

    $this->getJson('/_test/ok')->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->getJson('/_test/ok')
        ->assertSuccessful()
        ->assertExactJson(['ok' => true]);
});
