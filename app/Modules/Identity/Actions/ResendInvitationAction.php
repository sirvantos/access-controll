<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Exceptions\InvitationNotPendingException;
use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\PendingInvitationView;
use App\Modules\Identity\Services\InvitationTokenService;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class ResendInvitationAction
{
    public function __construct(
        private InvitationTokenService $tokens,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(int $companyId, int $invitationId): PendingInvitationView
    {
        return $this->companyContext->run($companyId, fn (): PendingInvitationView => $this->resend($companyId, $invitationId));
    }

    private function resend(int $companyId, int $invitationId): PendingInvitationView
    {
        $invitation = $this->findInCompany($companyId, $invitationId);

        throw_unless($invitation->state() === InvitationState::Pending, InvitationNotPendingException::class);
        throw_if(
            User::emailIsRegistered($invitation->email),
            fn (): EmailAlreadyRegisteredException => new EmailAlreadyRegisteredException,
        );

        $generated = $this->tokens->generate();

        DB::transaction(function () use ($invitation, $generated): void {
            $invitation->forceFill([
                'token_hash' => $generated['hash'],
                'expires_at' => now()->addDays(InviteCompanyUserAction::INVITATION_LIFETIME_DAYS),
            ])->save();

            Notification::route('mail', $invitation->email)->notify(new InvitationNotification(
                token: $generated['token'],
                role: $invitation->role,
                expiresAt: $invitation->expires_at,
            ));
        });

        return $invitation->toPendingInvitationView();
    }

    private function findInCompany(int $companyId, int $invitationId): Invitation
    {
        return Invitation::query()
            ->where('company_id', $companyId)
            ->whereKey($invitationId)
            ->firstOrFail();
    }
}
