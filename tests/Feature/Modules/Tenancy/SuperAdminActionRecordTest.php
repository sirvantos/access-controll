<?php

declare(strict_types=1);

use App\Modules\Tenancy\Data\SuperAdminActionType;
use App\Modules\Tenancy\Models\SuperAdminActionRecord;
use App\Modules\Tenancy\Services\SuperAdminActionRecorder;
use Illuminate\Support\Facades\Notification;

it('records select then a company profile change', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $acme->id)
        ->patchJson('/api/v1/company', ['name' => 'Acme West'])
        ->assertOk();

    $rows = SuperAdminActionRecord::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->type)->toBe(SuperAdminActionType::SelectedCompany)
        ->and($rows[0]->action)->toBe(SuperAdminActionRecorder::ACTION_SELECTED_COMPANY)
        ->and($rows[0]->company_id)->toBe($acme->id)
        ->and($rows[0]->actor_id)->toBe($owner->id)
        ->and($rows[1]->type)->toBe(SuperAdminActionType::ChangedCompanyData)
        ->and($rows[1]->action)->toBe(SuperAdminActionRecorder::ACTION_UPDATE_PROFILE)
        ->and($rows[1]->company_id)->toBe($acme->id)
        ->and($rows[1]->actor_id)->toBe($owner->id);
});

it('does not record a profile view or a media download', function (): void {
    ['acme' => $acme, 'media' => $media] = acmeStoredEmployeePhoto();
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $acme->id)
        ->getJson('/api/v1/company')
        ->assertOk();

    signedInWithSelectedCompany($owner, $acme->id)
        ->get('/api/v1/company/media/'.$media->public_id)
        ->assertOk();

    expect(SuperAdminActionRecord::query()->pluck('type')->all())
        ->toBe([SuperAdminActionType::SelectedCompany, SuperAdminActionType::SelectedCompany]);
});

it('records an invite as a change of selected company data', function (): void {
    Notification::fake();
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $acme->id)
        ->postJson('/api/v1/company/invitations', [
            'email' => 'new@acme.test',
            'role' => 'viewer',
        ])
        ->assertCreated();

    $change = SuperAdminActionRecord::query()
        ->where('type', SuperAdminActionType::ChangedCompanyData)
        ->sole();

    expect($change->action)->toBe(SuperAdminActionRecorder::ACTION_INVITE_COMPANY_USER)
        ->and($change->company_id)->toBe($acme->id)
        ->and($change->actor_id)->toBe($owner->id);
});

it('does not record a company admin profile change', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $admin = acmeAdmin(['company_id' => $acme->id]);

    signedInAs($admin)
        ->patchJson('/api/v1/company', ['name' => 'Acme West'])
        ->assertOk();

    expect(SuperAdminActionRecord::query()->count())->toBe(0);
});

it('has no http surface to list, change, or delete action records', function (): void {
    $owner = ownerSuperAdmin();

    foreach ([
        ['GET', '/api/v1/admin/super-admin-action-records'],
        ['DELETE', '/api/v1/admin/super-admin-action-records/1'],
        ['PATCH', '/api/v1/admin/super-admin-action-records/1'],
    ] as [$method, $uri]) {
        $response = signedInAs($owner)->json($method, $uri);

        expect($response->status())->toBeIn([404, 405]);
    }
});

it('inserts a new row on a second change and leaves earlier rows unchanged', function (): void {
    $acme = acmeCompany(['name' => 'Acme']);
    $owner = ownerSuperAdmin();

    signedInWithSelectedCompany($owner, $acme->id)
        ->patchJson('/api/v1/company', ['name' => 'Acme West'])
        ->assertOk();

    $firstChange = SuperAdminActionRecord::query()
        ->where('type', SuperAdminActionType::ChangedCompanyData)
        ->sole();
    $firstId = $firstChange->id;
    $firstAction = $firstChange->action;
    $firstOccurredAt = $firstChange->occurred_at?->toIso8601String();

    signedInWithSelectedCompany($owner, $acme->id)
        ->patchJson('/api/v1/company', ['name' => 'Acme East'])
        ->assertOk();

    $firstAgain = SuperAdminActionRecord::query()->find($firstId);

    expect(SuperAdminActionRecord::query()->where('type', SuperAdminActionType::ChangedCompanyData)->count())->toBe(2)
        ->and($firstAgain?->action)->toBe($firstAction)
        ->and($firstAgain?->occurred_at?->toIso8601String())->toBe($firstOccurredAt);
});
