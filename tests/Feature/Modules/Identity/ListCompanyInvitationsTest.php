<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\ListPendingInvitationsAction;
use App\Modules\Identity\PublicApi\Role;
use Carbon\Carbon;

function paddedInvitationToken(int $seed): string
{
    return str_pad((string) $seed, 64, '0', STR_PAD_LEFT);
}

it('lists only pending invitations for the signed-in company', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $viewerInvite = invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'viewer.invite@acme.test',
        'role' => Role::Viewer,
        'expires_at' => '2026-02-01 12:00:00',
    ]);
    $adminInvite = invitationFor($company, SAMPLE_INVITATION_TOKEN_ALT, [
        'email' => 'admin.invite@acme.test',
        'role' => Role::CompanyAdmin,
        'expires_at' => '2026-02-02 12:00:00',
    ]);

    foreach (range(1, 14) as $index) {
        invitationFor($company, paddedInvitationToken($index), [
            'email' => sprintf('pending-%02d@acme.test', $index),
            'role' => Role::Viewer,
        ]);
    }

    invitationFor($company, paddedInvitationToken(90), [
        'email' => 'expired@acme.test',
        'expires_at' => '2026-01-15 11:00:00',
    ]);
    invitationFor($company, paddedInvitationToken(91), [
        'email' => 'boundary@acme.test',
        'expires_at' => '2026-01-15 12:00:00',
    ]);
    invitationFor($company, paddedInvitationToken(92), [
        'email' => 'revoked@acme.test',
        'revoked_at' => '2026-01-15 11:00:00',
    ]);
    invitationFor($company, paddedInvitationToken(93), [
        'email' => 'accepted@acme.test',
        'accepted_at' => '2026-01-15 11:00:00',
    ]);
    invitationFor(globexCompany(), paddedInvitationToken(94), [
        'email' => 'invitee@globex.test',
    ]);

    signedInAs($admin);

    $firstPage = $this->getJson('/api/v1/company/invitations')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(15, 'data');

    $secondPage = $this->getJson('/api/v1/company/invitations?page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(1, 'data');

    $rows = [
        ...$firstPage->json('data'),
        ...$secondPage->json('data'),
    ];
    $emails = array_column($rows, 'email');
    $firstPageIds = array_column($firstPage->json('data'), 'id');
    $sortedIds = $firstPageIds;
    sort($sortedIds);

    expect(ListPendingInvitationsAction::PENDING_INVITATION_LIST_PAGE_SIZE)->toBe(15)
        ->and($emails)->toContain('viewer.invite@acme.test', 'admin.invite@acme.test')
        ->and($emails)->not->toContain('expired@acme.test', 'boundary@acme.test', 'revoked@acme.test', 'accepted@acme.test', 'invitee@globex.test')
        ->and($firstPageIds)->toBe($sortedIds)
        ->and(collect($rows)->firstWhere('email', 'viewer.invite@acme.test'))->toMatchArray([
            'id' => $viewerInvite->id,
            'email' => 'viewer.invite@acme.test',
            'role' => 'viewer',
            'expires_at' => $viewerInvite->expires_at->copy()->utc()->toIso8601String(),
        ])
        ->and(collect($rows)->firstWhere('email', 'admin.invite@acme.test'))->toMatchArray([
            'id' => $adminInvite->id,
            'role' => 'company_admin',
            'expires_at' => $adminInvite->expires_at->copy()->utc()->toIso8601String(),
        ]);
});
