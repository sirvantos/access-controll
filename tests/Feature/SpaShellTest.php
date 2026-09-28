<?php

declare(strict_types=1);

it('serves the spa shell on client-side routes', function (string $path) {
    $this->withoutVite()
        ->get($path)
        ->assertOk()
        ->assertSee('id="app"', false);
})->with([
    'root' => ['/'],
    'sign-in' => ['/sign-in'],
    'invitation' => ['/invitation/abc'],
    'company users' => ['/company/users'],
]);

it('still reports health through the http stack', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok');
});

it('returns json not found for unknown api routes', function () {
    $response = $this->getJson('/api/v1/unknown');

    $response->assertNotFound();
    $response->assertJsonStructure(['message']);
    expect($response->getContent())->not->toContain('id="app"');
});
