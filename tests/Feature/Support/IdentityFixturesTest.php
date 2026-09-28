<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\PublicApi\Role;
use App\Support\Validation\PasswordRules;
use Illuminate\Support\Facades\Validator;

it('accepts the sample password', function () {
    $validator = Validator::make(
        ['password' => SAMPLE_PASSWORD],
        ['password' => PasswordRules::rules()],
    );

    expect($validator->passes())->toBeTrue();
});

it('builds the acme and globex companies', function () {
    $acme = acmeCompany();
    $globex = globexCompany();

    expect($acme->name)->toBe('Acme')
        ->and($acme->isActive())->toBeTrue()
        ->and($globex->name)->toBe('Globex')
        ->and($globex->isActive())->toBeTrue()
        ->and($acme->id)->not->toBe($globex->id);
});

it('builds the owner super admin without a company', function () {
    $owner = ownerSuperAdmin();

    expect($owner->email)->toBe('owner@example.com')
        ->and($owner->actorRole())->toBe(Role::SuperAdmin)
        ->and($owner->actorCompanyId())->toBeNull();
});

it('builds company users with the documented email, role, and company', function (string $helper, string $email, Role $role, string $companyName) {
    $user = $helper();
    $company = Company::query()->find($user->actorCompanyId());

    expect($user->email)->toBe($email)
        ->and($user->actorRole())->toBe($role)
        ->and($company)->toBeInstanceOf(Company::class)
        ->and($company->name)->toBe($companyName);
})->with([
    'acme admin' => ['acmeAdmin', 'admin@acme.test', Role::CompanyAdmin, 'Acme'],
    'acme viewer' => ['acmeViewer', 'viewer@acme.test', Role::Viewer, 'Acme'],
    'globex admin' => ['globexAdmin', 'admin@globex.test', Role::CompanyAdmin, 'Globex'],
    'globex viewer' => ['globexViewer', 'viewer@globex.test', Role::Viewer, 'Globex'],
]);
