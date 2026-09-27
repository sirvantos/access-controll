<?php

declare(strict_types=1);

it('reports health through the http stack', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok');
});

it('renders the vue mount point', function () {
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertSee('id="app"', false);
});
