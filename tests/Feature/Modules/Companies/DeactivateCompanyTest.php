<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyTimeZoneVersion;
use App\Modules\Companies\Models\CompanyWorkingDaySettingVersion;
use App\Modules\Companies\PublicApi\WeekDay;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Password;

it('ends company sessions, refuses sign-in, and invalidates reset links', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewer = acmeViewer(['company_id' => $company->id]);
    $owner = ownerSuperAdmin();
    $adminVersion = $admin->sessionVersion();
    $viewerVersion = $viewer->sessionVersion();
    $token = Password::broker()->createToken($admin);

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.is_active', false);

    $admin->refresh();
    $viewer->refresh();

    expect($admin->session_version)->toBe($adminVersion + 1)
        ->and($viewer->session_version)->toBe($viewerVersion + 1)
        ->and(Password::broker()->tokenExists($admin, $token))->toBeFalse();

    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    test()
        ->actingAs($admin->fresh(), 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $adminVersion])
        ->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    test()
        ->actingAs($viewer->fresh(), 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $viewerVersion])
        ->withHeaders(statefulHeaders())
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    $unknown = test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/sign-in', [
            'email' => 'missing@example.com',
            'password' => 'Wrong-Sign-In-Secret',
        ]);

    foreach ([$admin, $viewer] as $user) {
        $rejected = test()->withHeaders(statefulHeaders())
            ->postJson('/api/v1/auth/sign-in', [
                'email' => $user->email,
                'password' => SAMPLE_PASSWORD,
            ]);

        $rejected->assertUnprocessable();
        expect($rejected->json())->toBe($unknown->json());
    }

    test()->withHeaders(statefulHeaders())
        ->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'new-password',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.token.0', __('passwords.token'));
});

it('does nothing when the company is already deactivated', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk();

    $version = $admin->fresh()?->session_version;

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($admin->fresh()?->session_version)->toBe($version);
});

it('keeps details and version rows when a company is deactivated', function () {
    $company = acmeCompany([
        'name' => 'Alma Stroy',
        'bin' => '123456789012',
        'contact_person' => 'Ada Contact',
        'phone' => '+7 (700) 123-45-67',
        'email' => 'office@acme.test',
    ]);
    $admin = acmeAdmin(['company_id' => $company->id, 'email' => 'admin@acme.test']);
    $viewer = acmeViewer(['company_id' => $company->id, 'email' => 'viewer@acme.test']);
    $owner = ownerSuperAdmin();
    $before = $company->fresh();
    $storedUsers = storedCompanyUsers($company->id);
    $timeZones = CompanyTimeZoneVersion::query()->where('company_id', $company->id)->orderBy('id')->get();
    $settings = CompanyWorkingDaySettingVersion::query()->where('company_id', $company->id)->orderBy('id')->get();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.bin', '123456789012');

    $after = $company->fresh();

    expect($after)->not->toBeNull()
        ->and($after->bin)->toBe('123456789012')
        ->and($after->contact_person)->toBe('Ada Contact')
        ->and($after->phone)->toBe('+7 (700) 123-45-67')
        ->and($after->email)->toBe('office@acme.test')
        ->and($after->name)->toBe('Alma Stroy')
        ->and($after->name_normalized)->toBe('alma stroy')
        ->and($after->deactivated_at)->not->toBeNull()
        ->and($before?->name_normalized)->toBe($after->name_normalized)
        ->and(storedCompanyUsers($company->id))->toBe($storedUsers)
        ->and(User::query()->whereKey([$admin->id, $viewer->id])->count())->toBe(2);

    expect(companyVersionSnapshots(CompanyTimeZoneVersion::query()->where('company_id', $company->id)->orderBy('id')->get()))
        ->toBe(companyVersionSnapshots($timeZones))
        ->and(companyVersionSnapshots(CompanyWorkingDaySettingVersion::query()->where('company_id', $company->id)->orderBy('id')->get()))
        ->toBe(companyVersionSnapshots($settings));

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect(CompanyTimeZoneVersion::query()->where('company_id', $company->id)->count())->toBe($timeZones->count())
        ->and(CompanyWorkingDaySettingVersion::query()->where('company_id', $company->id)->count())->toBe($settings->count());
});

it('keeps mutated details, settings, and version rows across deactivation and reactivation', function () {
    $company = acmeCompany([
        'name' => 'Acme',
        'bin' => '123456789012',
        'contact_person' => 'Ada Contact',
        'phone' => '+7 (700) 123-45-67',
        'email' => 'office@acme.test',
    ]);
    $admin = acmeAdmin(['company_id' => $company->id]);
    $owner = ownerSuperAdmin();

    signedInAs($admin)
        ->patchJson('/api/v1/company', [
            'contact_person' => 'New Contact',
        ])
        ->assertOk()
        ->assertJsonPath('data.contact_person', 'New Contact');

    signedInAs($admin)
        ->patchJson('/api/v1/company/working-day-settings', defaultWorkingDaySettingsPayload([
            'start_time' => '08:00',
        ]))
        ->assertOk()
        ->assertJsonPath('data.start_time', '08:00');

    $fresh = $company->fresh();
    $details = companyDetailSnapshot($fresh);
    $timeZones = companyVersionSnapshots(
        CompanyTimeZoneVersion::query()->where('company_id', $company->id)->orderBy('id')->get(),
    );
    $settings = storedWorkingDaySettingVersions($company->id);

    expect($settings)->toHaveCount(2);

    auth('web')->logout();
    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies/'.$company->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.bin', '123456789012');

    expect(companyDetailSnapshot($company->fresh()))->toBe($details)
        ->and(companyVersionSnapshots(CompanyTimeZoneVersion::query()->where('company_id', $company->id)->orderBy('id')->get()))
        ->toBe($timeZones)
        ->and(storedWorkingDaySettingVersions($company->id))->toBe($settings);

    auth('web')->logout();
    $this->flushSession();
    session()->invalidate();
    auth()->forgetGuards();
    $admin->refresh();

    signedInAs($admin)
        ->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.contact_person', 'New Contact')
        ->assertJsonPath('data.bin', '123456789012');

    signedInAs($admin)
        ->getJson('/api/v1/company/working-day-settings')
        ->assertOk()
        ->assertJsonPath('data.start_time', '08:00');
});

it('refuses to delete a company', function () {
    $company = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->deleteJson('/api/v1/admin/companies/'.$company->id)
        ->assertMethodNotAllowed();

    expect($company->fresh())->not->toBeNull()
        ->and($company->fresh()?->name)->toBe('Acme');
});

it('refuses company admin and viewer deactivate and reactivate without revealing the company', function () {
    $acme = acmeCompany(['name' => 'Acme']);
    $globex = globexCompany([
        'name' => 'Globex',
        'bin' => '123456789012',
    ]);
    $admin = acmeAdmin(['company_id' => $acme->id]);
    $viewer = acmeViewer(['company_id' => $acme->id]);

    foreach ([$admin, $viewer] as $user) {
        foreach (['deactivate', 'reactivate'] as $action) {
            auth('web')->logout();
            $this->flushSession();

            $known = signedInAs($user)->postJson('/api/v1/admin/companies/'.$globex->id.'/'.$action);

            auth('web')->logout();
            $this->flushSession();

            $unknown = signedInAs($user)->postJson('/api/v1/admin/companies/999999/'.$action);

            $known->assertForbidden();
            $unknown->assertForbidden();

            expect($known->json('message'))->toBe($unknown->json('message'))
                ->and($known->json('message'))->not->toContain('Globex')
                ->and($known->json('message'))->not->toContain('123456789012');
        }
    }

    expect($globex->fresh()?->isActive())->toBeTrue();
});

/**
 * @return array{
 *     name: string,
 *     name_normalized: string,
 *     bin: string|null,
 *     contact_person: string|null,
 *     phone: string|null,
 *     email: string|null
 * }
 */
function companyDetailSnapshot(?Company $company): array
{
    throw_unless($company instanceof Company, RuntimeException::class);

    return [
        'name' => $company->name,
        'name_normalized' => $company->name_normalized,
        'bin' => $company->bin,
        'contact_person' => $company->contact_person,
        'phone' => $company->phone,
        'email' => $company->email,
    ];
}

/**
 * @return list<array{
 *     id: int,
 *     start_time: string,
 *     end_time: string,
 *     working_days: list<string>,
 *     break_duration_minutes: int,
 *     break_deducted: bool,
 *     lateness_grace_minutes: int,
 *     applies_from: string|null
 * }>
 */
function storedWorkingDaySettingVersions(int $companyId): array
{
    return CompanyWorkingDaySettingVersion::query()
        ->where('company_id', $companyId)
        ->orderBy('id')
        ->get()
        ->map(fn (CompanyWorkingDaySettingVersion $row): array => [
            'id' => $row->id,
            'start_time' => $row->start_time,
            'end_time' => $row->end_time,
            'working_days' => $row->working_days
                ->map(fn (WeekDay $day): string => $day->value)
                ->values()
                ->all(),
            'break_duration_minutes' => $row->break_duration_minutes,
            'break_deducted' => $row->break_deducted,
            'lateness_grace_minutes' => $row->lateness_grace_minutes,
            'applies_from' => $row->applies_from?->utc()->toIso8601String(),
        ])
        ->all();
}

/**
 * @return list<array{id: int, email: string, company_id: int|null, role: string}>
 */
function storedCompanyUsers(int $companyId): array
{
    return User::query()
        ->where('company_id', $companyId)
        ->orderBy('id')
        ->get()
        ->map(fn (User $user): array => [
            'id' => $user->id,
            'email' => $user->email,
            'company_id' => $user->company_id,
            'role' => $user->role->value,
        ])
        ->all();
}
