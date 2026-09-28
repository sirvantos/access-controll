<?php

declare(strict_types=1);

use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\Role;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

it('renders the invitation link, role label, and expiry in both locales', function () {
    $token = SAMPLE_INVITATION_TOKEN;
    $expiresAt = Carbon::parse('2026-06-20 10:00:00');
    $notification = new InvitationNotification($token, Role::CompanyAdmin, $expiresAt);
    $link = rtrim((string) config('app.url'), '/').'/invitation/'.$token;
    $expiry = $expiresAt->copy()->utc()->toIso8601String();

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue();

    foreach (['en' => 'Company admin', 'ru' => 'Администратор компании'] as $locale => $roleLabel) {
        app()->setLocale($locale);

        $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

        expect($html)->toContain($link)
            ->and($html)->toContain($roleLabel)
            ->and($html)->toContain($expiry);
    }
});
