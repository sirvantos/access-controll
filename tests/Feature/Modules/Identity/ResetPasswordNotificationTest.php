<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

it('renders the reset link in both locales and is queued', function () {
    $token = 'reset-token-0123456789abcdef';
    $email = 'admin@acme.test';
    $notification = new ResetPasswordNotification($token);
    $user = new User(['email' => $email]);
    $link = rtrim((string) config('app.url'), '/')
        .'/reset-password/'.$token
        .'?email='.rawurlencode($email);

    expect($notification)->toBeInstanceOf(ShouldQueue::class);

    foreach (['en', 'ru'] as $locale) {
        app()->setLocale($locale);

        $html = (string) $notification->toMail($user)->render();

        expect($html)->toContain($link)
            ->and($html)->toContain(__('identity.password_reset.intro'))
            ->and($html)->toContain(__('identity.password_reset.action'));
    }
});
