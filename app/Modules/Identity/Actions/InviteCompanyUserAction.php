<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Modules\Identity\Data\InviteUserData;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\PendingInvitationView;
use App\Modules\Identity\Services\InvitationTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class InviteCompanyUserAction
{
    public const int INVITATION_LIFETIME_DAYS = 7;

    public function __construct(private InvitationTokenService $tokens) {}

    public function __invoke(int $companyId, InviteUserData $data): PendingInvitationView
    {
        throw_if(
            User::emailIsRegistered($data->email),
            fn (): EmailAlreadyRegisteredException => new EmailAlreadyRegisteredException,
        );

        $generated = $this->tokens->generate();

        $invitation = DB::transaction(function () use ($companyId, $data, $generated): Invitation {
            $invitation = Invitation::query()->create([
                'company_id' => $companyId,
                'email' => $data->email,
                'role' => $data->role,
                'token_hash' => $generated['hash'],
                'expires_at' => now()->addDays(self::INVITATION_LIFETIME_DAYS),
            ]);

            Notification::route('mail', $data->email->toString())->notify(new InvitationNotification(
                token: $generated['token'],
                role: $data->role,
                expiresAt: $invitation->expires_at,
            ));

            return $invitation;
        });

        return $invitation->toPendingInvitationView();
    }
}
