<?php

declare(strict_types=1);

it('ends the session so the same client can no longer load the current user', function () {
    $admin = acmeAdmin();

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => $admin->email,
            'password' => SAMPLE_PASSWORD,
        ])
        ->assertOk();

    $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $admin->email);

    $this->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-out')
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    $response = $this->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    expect($response->json())->not->toHaveKey('data');
});
