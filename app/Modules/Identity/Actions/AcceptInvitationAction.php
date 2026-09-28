<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\InvitationNoLongerValidException;
use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\InvitationTokenService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class AcceptInvitationAction
{
    public function __construct(private InvitationTokenService $tokens) {}

    public function __invoke(string $token, string $password): void
    {
        $invitation = $this->tokens->findByToken($token);

        throw_unless($invitation instanceof Invitation, InvitationNoLongerValidException::class);
        throw_unless($this->canAccept($invitation), InvitationNoLongerValidException::class);

        try {
            DB::transaction(function () use ($invitation, $password): void {
                $locked = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->first();

                throw_unless($locked instanceof Invitation, InvitationNoLongerValidException::class);
                throw_unless($this->canAccept($locked), InvitationNoLongerValidException::class);

                User::query()->create([
                    'email' => mb_strtolower($locked->email),
                    'password' => $password,
                    'role' => $locked->role,
                    'company_id' => $locked->company_id,
                ]);

                $locked->forceFill(['accepted_at' => now()])->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvitationNoLongerValidException;
        }
    }

    private function canAccept(?Invitation $invitation): bool
    {
        return $invitation instanceof Invitation
            && $invitation->state() === InvitationState::Pending
            && ! User::emailIsRegistered($invitation->email);
    }
}
