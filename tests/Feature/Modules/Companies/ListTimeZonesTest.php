<?php

declare(strict_types=1);

it('lists iana time zones for a super admin and a company admin', function (callable $actor) {
    $user = $actor();

    signedInAs($user)
        ->getJson('/api/v1/time-zones')
        ->assertSuccessful()
        ->assertJsonPath('data.identifiers', DateTimeZone::listIdentifiers());
})->with([
    'super admin' => [fn () => ownerSuperAdmin()],
    'company admin' => [fn () => acmeAdmin()],
]);

it('refuses a viewer', function () {
    signedInAs(acmeViewer())
        ->getJson('/api/v1/time-zones')
        ->assertForbidden();
});

it('refuses a guest', function () {
    $this->getJson('/api/v1/time-zones')->assertUnauthorized();
});
