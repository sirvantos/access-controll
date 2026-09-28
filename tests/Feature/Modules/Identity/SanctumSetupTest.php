<?php

declare(strict_types=1);

use App\Modules\Identity\Data\SanctumSetupProbeData;

it('returns csrf cookie from sanctum endpoint', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

it('autoloads a spatie data class in the identity module', function () {
    expect(new SanctumSetupProbeData)->toBeInstanceOf(SanctumSetupProbeData::class);
});
