<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function token(): string
    {
        return $this->token;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.password_reset.subject'))
            ->line(__('identity.password_reset.intro'))
            ->action(__('identity.password_reset.action'), $this->resetUrl($notifiable));
    }

    private function resetUrl(object $notifiable): string
    {
        $email = $notifiable instanceof CanResetPassword
            ? $notifiable->getEmailForPasswordReset()
            : '';

        return rtrim((string) config('app.url'), '/')
            .'/reset-password/'.$this->token
            .'?email='.rawurlencode($email);
    }
}
