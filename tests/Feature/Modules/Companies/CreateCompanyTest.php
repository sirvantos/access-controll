<?php

declare(strict_types=1);

use App\Http\Requests\CreateCompanyRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyTimeZoneVersion;
use App\Modules\Companies\Models\CompanyWorkingDaySettingVersion;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\Role;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('creates a company and sends the first company admin invitation', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    Notification::fake();
    $owner = ownerSuperAdmin();
    $createdAt = Carbon::parse('2026-01-15 12:00:00')->utc()->toIso8601String();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies', [
            'name' => 'Acme',
            'first_admin_email' => 'admin@acme.test',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.time_zone', 'Asia/Almaty')
        ->assertJsonPath('data.bin', null)
        ->assertJsonPath('data.contact_person', null)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.awaiting_first_admin', true)
        ->assertJsonPath('data.created_at', $createdAt)
        ->assertJsonPath('data.working_day_settings.start_time', '09:00')
        ->assertJsonPath('data.working_day_settings.end_time', '18:00')
        ->assertJsonPath('data.working_day_settings.working_days', [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
        ])
        ->assertJsonPath('data.working_day_settings.break_duration_minutes', 60)
        ->assertJsonPath('data.working_day_settings.break_deducted', true)
        ->assertJsonPath('data.working_day_settings.lateness_grace_minutes', 0);

    $appliesFrom = Carbon::parse('2026-01-15 00:00:00', 'Asia/Almaty');

    withoutCompanyIsolation(function () use ($appliesFrom): void {
        $company = Company::query()->where('name', 'Acme')->first();

        expect($company)->not->toBeNull()
            ->and($company->name_normalized)->toBe('acme')
            ->and($company->timeZoneVersions)->toHaveCount(1)
            ->and($company->workingDaySettingVersions)->toHaveCount(1)
            ->and($company->timeZoneVersions->first()?->time_zone)->toBe('Asia/Almaty')
            ->and($company->timeZoneVersions->first()?->applies_from?->equalTo($appliesFrom))->toBeTrue()
            ->and($company->workingDaySettingVersions->first()?->applies_from?->equalTo($appliesFrom))->toBeTrue();

        $invitation = Invitation::query()->where('email', 'admin@acme.test')->first();

        expect($invitation)->not->toBeNull()
            ->and($invitation->role)->toBe(Role::CompanyAdmin)
            ->and($invitation->company_id)->toBe(Company::query()->where('name', 'Acme')->value('id'));
    });

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        function (InvitationNotification $notification, array $channels, object $notifiable): bool {
            return $notification instanceof ShouldQueue
                && $channels === ['mail']
                && $notifiable instanceof AnonymousNotifiable
                && ($notifiable->routes['mail'] ?? null) === 'admin@acme.test';
        },
    );
});

it('refuses a registered first admin email without creating a company', function (string $locale) {
    app()->setLocale($locale);
    Notification::fake();
    $logs = captureLogEvents();
    $owner = ownerSuperAdmin();
    $existing = acmeAdmin();
    $companies = withoutCompanyIsolation(fn () => Company::query()->count());
    $timeZones = withoutCompanyIsolation(fn () => CompanyTimeZoneVersion::query()->count());
    $settings = withoutCompanyIsolation(fn () => CompanyWorkingDaySettingVersion::query()->count());

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies', [
            'name' => 'New Co',
            'first_admin_email' => 'Admin@Acme.Test',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.first_admin_email.0', __('identity.email_already_registered'));

    expect(withoutCompanyIsolation(fn () => Company::query()->count()))->toBe($companies)
        ->and(withoutCompanyIsolation(fn () => CompanyTimeZoneVersion::query()->count()))->toBe($timeZones)
        ->and(withoutCompanyIsolation(fn () => CompanyWorkingDaySettingVersion::query()->count()))->toBe($settings)
        ->and(withoutCompanyIsolation(fn () => Company::query()->where('name', 'New Co')->exists()))->toBeFalse()
        ->and(withoutCompanyIsolation(
            fn () => Invitation::query()->where('email', $existing->email)->exists(),
        ))->toBeFalse();

    Notification::assertNothingSent();
    expectNothingLogged($logs);
})->with(['en', 'ru']);

it('rejects a company name longer than 255 characters', function () {
    Notification::fake();
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies', [
            'name' => str_repeat('a', CreateCompanyRequest::COMPANY_NAME_MAX_LENGTH + 1),
            'first_admin_email' => 'admin@acme.test',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect(CreateCompanyRequest::COMPANY_NAME_MAX_LENGTH)->toBe(255)
        ->and(withoutCompanyIsolation(fn () => Company::query()->count()))->toBe(0)
        ->and(withoutCompanyIsolation(fn () => Invitation::query()->count()))->toBe(0);

    Notification::assertNothingSent();
});

it('saves a chosen time zone and every optional detail', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    Notification::fake();
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies', [
            'name' => 'Acme',
            'first_admin_email' => 'admin@acme.test',
            'time_zone' => 'Europe/Moscow',
            'bin' => sampleCompanyBin(),
            'contact_person' => 'Acme Contact',
            'phone' => sampleCompanyPhone(),
            'email' => 'Office@Acme.Test',
        ])
        ->assertCreated()
        ->assertJsonPath('data.time_zone', 'Europe/Moscow')
        ->assertJsonPath('data.bin', sampleCompanyBin())
        ->assertJsonPath('data.contact_person', 'Acme Contact')
        ->assertJsonPath('data.phone', sampleCompanyPhone())
        ->assertJsonPath('data.email', 'office@acme.test')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.working_day_settings.start_time', '09:00');

    $appliesFrom = Carbon::parse('2026-01-15 00:00:00', 'Europe/Moscow');

    withoutCompanyIsolation(function () use ($appliesFrom): void {
        $company = Company::query()->where('name', 'Acme')->first();

        expect($company)->not->toBeNull()
            ->and($company->bin)->toBe(sampleCompanyBin())
            ->and($company->contact_person)->toBe('Acme Contact')
            ->and($company->phone)->toBe(sampleCompanyPhone())
            ->and($company->email)->toBe('office@acme.test')
            ->and($company->timeZoneVersions->first()?->time_zone)->toBe('Europe/Moscow')
            ->and($company->timeZoneVersions->first()?->applies_from?->equalTo($appliesFrom))->toBeTrue();
    });
});

it('explains each invalid company field and creates nothing', function () {
    Notification::fake();
    $owner = ownerSuperAdmin();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies', [
            'name' => '',
            'first_admin_email' => 'admin@acme.test',
            'time_zone' => 'Not/AZone',
            'bin' => '12345678901',
            'phone' => '123456789',
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'time_zone', 'bin', 'phone', 'email']);

    expect(withoutCompanyIsolation(fn () => Company::query()->count()))->toBe(0)
        ->and(withoutCompanyIsolation(fn () => CompanyTimeZoneVersion::query()->count()))->toBe(0)
        ->and(withoutCompanyIsolation(fn () => CompanyWorkingDaySettingVersion::query()->count()))->toBe(0);

    Notification::assertNothingSent();
});

it('refuses a company admin or viewer', function (callable $actor) {
    Notification::fake();
    $user = $actor();
    $companies = withoutCompanyIsolation(fn () => Company::query()->count());

    signedInAs($user)
        ->postJson('/api/v1/admin/companies', [
            'name' => 'New Co',
            'first_admin_email' => 'admin@newco.test',
        ])
        ->assertForbidden();

    expect(withoutCompanyIsolation(fn () => Company::query()->count()))->toBe($companies)
        ->and(withoutCompanyIsolation(fn () => Company::query()->where('name', 'New Co')->exists()))->toBeFalse();

    Notification::assertNothingSent();
})->with([
    'company admin' => [fn () => acmeAdmin()],
    'viewer' => [fn () => acmeViewer()],
]);
