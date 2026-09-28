<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use Illuminate\Support\Carbon;

it('casts every companies column', function () {
    $company = Company::factory()->create([
        'name' => 'Acme',
        'deactivated_at' => '2026-01-15 12:00:00',
    ]);

    expect($company->getCasts())->toMatchArray([
        'id' => 'integer',
        'name' => 'string',
        'deactivated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ])
        ->and($company->id)->toBeInt()
        ->and($company->name)->toBe('Acme')
        ->and($company->deactivated_at)->toEqual(Carbon::parse('2026-01-15 12:00:00'))
        ->and($company->created_at)->toBeInstanceOf(Carbon::class)
        ->and($company->updated_at)->toBeInstanceOf(Carbon::class);
});

it('is active only while deactivated_at is null', function () {
    $active = Company::factory()->create([
        'name' => 'Acme',
    ]);

    $deactivated = Company::factory()->create([
        'name' => 'Acme',
        'deactivated_at' => '2026-01-15 12:00:00',
    ]);

    expect($active->isActive())->toBeTrue()
        ->and($active->deactivated_at)->toBeNull()
        ->and($deactivated->isActive())->toBeFalse();
});

it('builds an active Acme company by default and a deactivated one from the factory state', function () {
    $active = Company::factory()->create();
    $deactivated = Company::factory()->deactivated()->create();

    expect($active->name)->toBe('Acme')
        ->and($active->deactivated_at)->toBeNull()
        ->and($active->isActive())->toBeTrue()
        ->and($deactivated->name)->toBe('Acme')
        ->and($deactivated->deactivated_at)->not->toBeNull()
        ->and($deactivated->isActive())->toBeFalse();
});
