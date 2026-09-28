<?php

declare(strict_types=1);

use App\Modules\Identity\PublicApi\Role;

it('exposes the three roles', function () {
    expect(Role::SuperAdmin->value)->toBe('super_admin')
        ->and(Role::CompanyAdmin->value)->toBe('company_admin')
        ->and(Role::Viewer->value)->toBe('viewer')
        ->and(Role::cases())->toHaveCount(3);
});

it('never includes super admin among assignable roles', function () {
    expect(Role::assignable())->toBe([Role::CompanyAdmin, Role::Viewer])
        ->and(Role::assignable())->not->toContain(Role::SuperAdmin);
});
