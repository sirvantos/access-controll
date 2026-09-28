<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use Illuminate\Support\Facades\Notification;

it('does not reveal another company user on the company user list', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    User::factory()->viewer($company->id)->deactivated()->create([
        'email' => 'former@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $company->id,
    ]);
    $globex = globexAdmin();

    signedInAs($admin);

    $emails = $this->getJson('/api/v1/company/users')
        ->assertOk()
        ->json('data');

    expect(array_column($emails, 'email'))->toContain('admin@acme.test', 'former@acme.test')
        ->and(array_column($emails, 'email'))->not->toContain($globex->email)
        ->and(array_column($emails, 'id'))->not->toContain($globex->id)
        ->and(json_encode($emails, JSON_THROW_ON_ERROR))->not->toContain('Globex');
});

it('a Globex pending invitation never appears for an Acme admin', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    invitationFor($company, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@acme.test',
    ]);
    $globex = globexCompany();
    invitationFor($globex, SAMPLE_INVITATION_TOKEN_ALT, [
        'email' => 'invitee@globex.test',
    ]);

    signedInAs($admin);

    $rows = $this->getJson('/api/v1/company/invitations')->assertOk()->json('data');

    expect(array_column($rows, 'email'))->toContain('invitee@acme.test')
        ->and(array_column($rows, 'email'))->not->toContain('invitee@globex.test')
        ->and(json_encode($rows, JSON_THROW_ON_ERROR))->not->toContain('Globex');
});

it('does not resend or revoke a Globex invitation for an Acme admin', function () {
    Notification::fake();
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $globex = globexCompany();
    $invitation = invitationFor($globex, SAMPLE_INVITATION_TOKEN, [
        'email' => 'invitee@globex.test',
    ]);
    $tokenHash = $invitation->token_hash;

    signedInAs($admin);

    $resend = $this->postJson('/api/v1/company/invitations/'.$invitation->id.'/resend');
    $revoke = $this->postJson('/api/v1/company/invitations/'.$invitation->id.'/revoke');

    $resend->assertNotFound();
    $revoke->assertNotFound();

    $invitation->refresh();

    expect($invitation->token_hash)->toBe($tokenHash)
        ->and($invitation->revoked_at)->toBeNull()
        ->and($resend->json())->not->toHaveKey('data')
        ->and($revoke->json())->not->toHaveKey('data')
        ->and($resend->json('message'))->not->toContain('invitee@globex.test')
        ->and($revoke->json('message'))->not->toContain('invitee@globex.test')
        ->and($resend->json('message'))->not->toContain('Globex')
        ->and($revoke->json('message'))->not->toContain('Globex');

    Notification::assertNothingSent();
});

it('does not deactivate or reactivate a Globex user for an Acme admin', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $globex = globexCompany();
    $globexAdmin = globexAdmin(['company_id' => $globex->id]);
    $globexViewer = globexViewer(['company_id' => $globex->id]);

    signedInAs($admin);

    $deactivateAdmin = $this->postJson('/api/v1/company/users/'.$globexAdmin->id.'/deactivate');
    $reactivateAdmin = $this->postJson('/api/v1/company/users/'.$globexAdmin->id.'/reactivate');
    $deactivateViewer = $this->postJson('/api/v1/company/users/'.$globexViewer->id.'/deactivate');
    $reactivateViewer = $this->postJson('/api/v1/company/users/'.$globexViewer->id.'/reactivate');

    $deactivateAdmin->assertNotFound();
    $reactivateAdmin->assertNotFound();
    $deactivateViewer->assertNotFound();
    $reactivateViewer->assertNotFound();

    $globexAdmin->refresh();
    $globexViewer->refresh();

    expect($globexAdmin->deactivated_at)->toBeNull()
        ->and($globexViewer->deactivated_at)->toBeNull()
        ->and($deactivateAdmin->json())->not->toHaveKey('data')
        ->and($reactivateAdmin->json())->not->toHaveKey('data')
        ->and($deactivateAdmin->json('message'))->not->toContain('admin@globex.test')
        ->and($reactivateViewer->json('message'))->not->toContain('viewer@globex.test')
        ->and($deactivateAdmin->json('message'))->not->toContain('Globex')
        ->and($reactivateViewer->json('message'))->not->toContain('Globex');
});

it('does not change the role of a Globex user for an Acme admin', function () {
    $company = acmeCompany();
    $admin = acmeAdmin(['company_id' => $company->id]);
    $globex = globexCompany();
    $globexAdmin = globexAdmin(['company_id' => $globex->id]);

    signedInAs($admin);

    $response = $this->patchJson('/api/v1/company/users/'.$globexAdmin->id, [
        'role' => 'viewer',
    ]);

    $response->assertNotFound();

    $globexAdmin->refresh();

    expect($globexAdmin->role)->toBe(Role::CompanyAdmin)
        ->and($response->json())->not->toHaveKey('data')
        ->and($response->json('message'))->not->toContain('admin@globex.test')
        ->and($response->json('message'))->not->toContain('Globex');
});
