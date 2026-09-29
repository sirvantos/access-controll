<?php

declare(strict_types=1);

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('stores a company admin invitation and notifies the invitee', function () {
    Notification::fake();
    $company = acmeCompany();
    $invitations = app(FirstAdminInvitations::class);

    $invitations->invite($company->id, 'Admin@Acme.Test');

    $invitation = withoutCompanyIsolation(
        fn () => Invitation::query()->where('email', 'admin@acme.test')->first(),
    );

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(Role::CompanyAdmin)
        ->and($invitation->company_id)->toBe($company->id);

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

it('refuses an email that already belongs to a user', function () {
    Notification::fake();
    $logs = captureLogEvents();
    $company = acmeCompany();
    acmeAdmin(['company_id' => $company->id]);

    $caught = null;

    try {
        app(FirstAdminInvitations::class)->invite($company->id, 'Admin@Acme.Test');
    } catch (EmailAlreadyRegisteredException $exception) {
        $caught = $exception;
        report($exception);
    }

    expect($caught)->toBeInstanceOf(EmailAlreadyRegisteredException::class)
        ->and($caught?->field)->toBe('first_admin_email')
        ->and(withoutCompanyIsolation(fn () => Invitation::query()->count()))->toBe(0);

    Notification::assertNothingSent();
    expectNothingLogged($logs);
});

it('reports awaiting first admin only while the company has no users', function () {
    $empty = acmeCompany();
    $staffed = globexCompany();
    globexAdmin(['company_id' => $staffed->id]);
    $invitations = app(FirstAdminInvitations::class);

    expect($invitations->isAwaitingFirstAdmin($empty->id))->toBeTrue()
        ->and($invitations->isAwaitingFirstAdmin($staffed->id))->toBeFalse();
});

it('returns only company ids that still have no users', function () {
    $empty = acmeCompany();
    $staffed = globexCompany();
    globexAdmin(['company_id' => $staffed->id]);
    $alsoEmpty = Company::factory()->create(['name' => 'Initech']);

    $awaiting = app(FirstAdminInvitations::class)->companyIdsAwaitingFirstAdmin([
        $staffed->id,
        $empty->id,
        $alsoEmpty->id,
    ]);

    expect($awaiting)->toBe([$empty->id, $alsoEmpty->id]);
});
