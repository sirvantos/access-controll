<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanySchedule;
use Illuminate\Support\Carbon;

it('saves valid details, clears an optional field, and shows them on the next view', function () {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => sampleCompanyBin(),
        'contact_person' => 'Ada Contact',
        'phone' => sampleCompanyPhone(),
        'email' => 'office@acme.test',
    ]);
    $admin = acmeAdmin(['company_id' => $company->id]);

    signedInAs($admin)
        ->patchJson('/api/v1/company', [
            'name' => 'Alma Stroy',
            ...companyCreateOptionalDetails([
                'bin' => '',
                'contact_person' => 'New Contact',
                'email' => 'Office@Acme.Test',
            ]),
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Alma Stroy')
        ->assertJsonPath('data.bin', null)
        ->assertJsonPath('data.contact_person', 'New Contact')
        ->assertJsonPath('data.phone', sampleCompanyPhone())
        ->assertJsonPath('data.email', 'office@acme.test');

    signedInAs($admin)
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.name', 'Alma Stroy')
        ->assertJsonPath('data.bin', null)
        ->assertJsonPath('data.contact_person', 'New Contact');

    expect($company->refresh()->name_normalized)->toBe('alma stroy')
        ->and($company->bin)->toBeNull();
});

it('leaves omitted name and time zone unchanged', function () {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => null,
    ]);
    $admin = acmeAdmin(['company_id' => $company->id]);

    signedInAs($admin)
        ->patchJson('/api/v1/company', [
            'contact_person' => 'Only Contact',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.time_zone', 'Asia/Almaty')
        ->assertJsonPath('data.contact_person', 'Only Contact');

    expect($company->timeZoneVersions()->count())->toBe(1);
});

it('explains an empty name, an unknown time zone, and a bad optional format without saving', function (string $field, mixed $value) {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => sampleCompanyBin(),
        'contact_person' => 'Ada Contact',
        'phone' => sampleCompanyPhone(),
        'email' => 'office@acme.test',
    ]);
    $admin = acmeAdmin(['company_id' => $company->id]);
    $versions = $company->timeZoneVersions()->count();

    signedInAs($admin)
        ->patchJson('/api/v1/company', [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $company->refresh();

    expect($company->name)->toBe('Acme')
        ->and($company->bin)->toBe(sampleCompanyBin())
        ->and($company->timeZoneVersions()->count())->toBe($versions);
})->with([
    'empty name' => ['name', ''],
    'unknown time zone' => ['time_zone', 'Not/AZone'],
    'bad bin' => ['bin', '12'],
    'bad phone' => ['phone', '123'],
    'bad email' => ['email', 'not-an-email'],
]);

it('applies a time zone change from the next local midnight and keeps today on the previous zone', function () {
    $now = Carbon::parse('2026-06-15 10:00:00');
    Carbon::setTestNow($now);
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);
    $schedule = app(CompanySchedule::class);

    signedInAs($admin)
        ->patchJson('/api/v1/company', ['time_zone' => 'Europe/Moscow'])
        ->assertOk()
        ->assertJsonPath('data.time_zone', 'Europe/Moscow');

    $latest = $company->timeZoneVersions()->orderByDesc('id')->first();
    $appliesFrom = $latest?->applies_from;

    expect($latest?->time_zone)->toBe('Europe/Moscow')
        ->and($appliesFrom)->not->toBeNull()
        ->and($schedule->timeZoneIdentifierAt($company->id, $now))->toBe('Asia/Almaty')
        ->and($schedule->timeZoneIdentifierAt($company->id, Carbon::parse('2026-06-15 00:00:00')))->toBe('Asia/Almaty')
        ->and($schedule->timeZoneIdentifierAt($company->id, $appliesFrom))->toBe('Europe/Moscow');
});

it('keeps only the last time zone saved on the same day from that midnight', function () {
    $now = Carbon::parse('2026-06-15 10:00:00');
    Carbon::setTestNow($now);
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);
    $schedule = app(CompanySchedule::class);

    signedInAs($admin)->patchJson('/api/v1/company', ['time_zone' => 'Europe/Moscow'])->assertOk();
    signedInAs($admin)->patchJson('/api/v1/company', ['time_zone' => 'Asia/Tokyo'])->assertOk();

    $latest = $company->timeZoneVersions()->orderByDesc('id')->first();

    expect($company->timeZoneVersions()->count())->toBe(3)
        ->and($latest?->time_zone)->toBe('Asia/Tokyo')
        ->and($schedule->timeZoneIdentifierAt($company->id, $now))->toBe('Asia/Almaty')
        ->and($schedule->timeZoneIdentifierAt($company->id, $latest?->applies_from ?? $now))->toBe('Asia/Tokyo');
});

it('inserts nothing when the time zone matches the latest row', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $company->id]);

    signedInAs($admin)
        ->patchJson('/api/v1/company', ['time_zone' => 'Asia/Almaty'])
        ->assertOk()
        ->assertJsonPath('data.time_zone', 'Asia/Almaty');

    expect($company->timeZoneVersions()->count())->toBe(1);
});

it('refuses a company admin probing another company through an admin path', function () {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany([
        'name' => 'Globex',
        'bin' => sampleCompanyBin(),
    ]);
    $admin = acmeAdmin(['company_id' => $acme->id]);

    foreach (['GET', 'PATCH'] as $method) {
        $known = signedInAs($admin)->json($method, '/api/v1/admin/companies/'.$globex->id, ['name' => 'Hacked']);
        $unknown = signedInAs($admin)->json($method, '/api/v1/admin/companies/999999', ['name' => 'Hacked']);

        expect($known->status())->toBe($unknown->status())
            ->and($known->status())->toBeIn([403, 404, 405])
            ->and($known->json('message'))->not->toContain('Globex')
            ->and($known->json('message'))->not->toContain(sampleCompanyBin())
            ->and($unknown->json('message'))->not->toContain('Globex');
    }

    expect(Company::query()->find($globex->id)?->name)->toBe('Globex');
});

it('refuses a viewer and a super admin', function (string $role) {
    $company = acmeCompany(['name' => 'Acme']);
    $user = $role === 'viewer'
        ? acmeViewer(['company_id' => $company->id])
        : ownerSuperAdmin();

    signedInAs($user)
        ->patchJson('/api/v1/company', ['name' => 'Hacked'])
        ->assertForbidden();

    expect($company->refresh()->name)->toBe('Acme');
})->with([
    'viewer' => 'viewer',
    'super admin' => 'super_admin',
]);

it('does not define company deletion', function () {
    $admin = acmeAdmin();

    signedInAs($admin)
        ->deleteJson('/api/v1/company')
        ->assertMethodNotAllowed();
});
