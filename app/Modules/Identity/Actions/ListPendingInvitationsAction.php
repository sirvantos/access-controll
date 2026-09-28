<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\PublicApi\PendingInvitationView;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPendingInvitationsAction
{
    public const int PENDING_INVITATION_LIST_PAGE_SIZE = 15;

    /**
     * @return LengthAwarePaginator<int, PendingInvitationView>
     */
    public function __invoke(int $companyId, int $page): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, PendingInvitationView> $invitations */
        $invitations = Invitation::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->paginate(perPage: self::PENDING_INVITATION_LIST_PAGE_SIZE, page: max(1, $page))
            ->through(fn (Invitation $invitation): PendingInvitationView => $invitation->toPendingInvitationView());

        return $invitations;
    }
}
