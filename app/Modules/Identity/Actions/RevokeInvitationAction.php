<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\InvitationNotPendingException;
use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\Models\Invitation;

final class RevokeInvitationAction
{
    public function __invoke(int $companyId, int $invitationId): void
    {
        $invitation = Invitation::query()
            ->where('company_id', $companyId)
            ->whereKey($invitationId)
            ->firstOrFail();

        throw_unless($invitation->state() === InvitationState::Pending, InvitationNotPendingException::class);

        $invitation->forceFill(['revoked_at' => now()])->save();
    }
}
