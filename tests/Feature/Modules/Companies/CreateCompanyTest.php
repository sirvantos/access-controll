<?php

declare(strict_types=1);

use App\Http\Requests\CreateCompanyRequest;
use App\Modules\Companies\Models\Company;
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
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.awaiting_first_admin', true)
        ->assertJsonPath('data.created_at', $createdAt);

    $invitation = Invitation::query()->where('email', 'admin@acme.test')->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(Role::CompanyAdmin)
        ->and($invitation->company_id)->toBe(Company::query()->where('name', 'Acme')->value('id'));

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
    $companies = Company::query()->count();

    signedInAs($owner)
        ->postJson('/api/v1/admin/companies', [
            'name' => 'New Co',
            'first_admin_email' => 'Admin@Acme.Test',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.first_admin_email.0', __('identity.email_already_registered'));

    expect(Company::query()->count())->toBe($companies)
        ->and(Company::query()->where('name', 'New Co')->exists())->toBeFalse()
        ->and(Invitation::query()->where('email', $existing->email)->exists())->toBeFalse();

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
        ->and(Company::query()->count())->toBe(0)
        ->and(Invitation::query()->count())->toBe(0);

    Notification::assertNothingSent();
});
