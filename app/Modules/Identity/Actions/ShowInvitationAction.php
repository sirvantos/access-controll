<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\InvitationNoLongerValidException;
use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\InvitationPreview;
use App\Modules\Identity\Services\InvitationTokenService;

final class ShowInvitationAction
{
    public function __construct(private InvitationTokenService $tokens) {}

    public function __invoke(string $token): InvitationPreview
    {
        $invitation = $this->openInvitation($token);

        return new InvitationPreview(
            email: $invitation->email,
            role: $invitation->role,
            expiresAt: $invitation->expires_at,
        );
    }

    private function openInvitation(string $token): Invitation
    {
        $invitation = $this->tokens->findByToken($token);

        throw_unless($invitation instanceof Invitation, InvitationNoLongerValidException::class);
        throw_unless($invitation->state() === InvitationState::Pending, InvitationNoLongerValidException::class);
        throw_if(User::emailIsRegistered($invitation->email), InvitationNoLongerValidException::class);

        return $invitation;
    }
}
