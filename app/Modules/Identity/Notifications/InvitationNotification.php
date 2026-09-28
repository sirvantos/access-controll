<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\PublicApi\Role;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvitationNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly Role $role,
        private readonly CarbonInterface $expiresAt,
    ) {
        $this->afterCommit();
    }

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
        $expiresAt = $this->expiresAt->copy()->utc()->toIso8601String();
        $role = __('identity.roles.'.$this->role->value);

        return (new MailMessage)
            ->subject(__('identity.invitation.subject'))
            ->line(__('identity.invitation.intro', ['role' => $role]))
            ->action(__('identity.invitation.action'), $this->invitationUrl())
            ->line(__('identity.invitation.expires', ['expires_at' => $expiresAt]));
    }

    private function invitationUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/invitation/'.$this->token;
    }
}
