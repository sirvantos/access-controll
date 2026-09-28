<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Data\InvitationState;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Identity\Services\InvitationTokenService;
use Database\Seeders\ManualTestDataSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the manual sign-in accounts', function () {
    $this->seed(ManualTestDataSeeder::class);

    $owner = User::query()->where('email', 'owner@example.com')->first();
    $acmeAdmin = User::query()->where('email', 'admin@acme.test')->first();
    $acmeViewer = User::query()->where('email', 'viewer@acme.test')->first();
    $globexAdmin = User::query()->where('email', 'admin@globex.test')->first();
    $globexViewer = User::query()->where('email', 'viewer@globex.test')->first();
    $acme = Company::query()->where('name', 'Acme')->first();
    $globex = Company::query()->where('name', 'Globex')->first();

    expect($owner)->not->toBeNull()
        ->and($owner->role)->toBe(Role::SuperAdmin)
        ->and($owner->company_id)->toBeNull()
        ->and(Hash::check(ManualTestDataSeeder::PASSWORD, $owner->password))->toBeTrue()
        ->and($acmeAdmin?->role)->toBe(Role::CompanyAdmin)
        ->and($acmeAdmin?->company_id)->toBe($acme?->id)
        ->and($acmeViewer?->role)->toBe(Role::Viewer)
        ->and($acmeViewer?->company_id)->toBe($acme?->id)
        ->and($globexAdmin?->role)->toBe(Role::CompanyAdmin)
        ->and($globexAdmin?->company_id)->toBe($globex?->id)
        ->and($globexViewer?->role)->toBe(Role::Viewer)
        ->and($globexViewer?->company_id)->toBe($globex?->id)
        ->and(Hash::check(ManualTestDataSeeder::PASSWORD, (string) $acmeAdmin?->password))->toBeTrue();
});

it('seeds a pending acme invitation with a known token', function () {
    $this->seed(ManualTestDataSeeder::class);

    $invitation = app(InvitationTokenService::class)->findByToken(ManualTestDataSeeder::INVITATION_TOKEN);

    expect($invitation)->toBeInstanceOf(Invitation::class)
        ->and($invitation->email)->toBe('invitee@acme.test')
        ->and($invitation->role)->toBe(Role::Viewer)
        ->and($invitation->state())->toBe(InvitationState::Pending)
        ->and(ManualTestDataSeeder::INVITATION_TOKEN)->toBe(SAMPLE_INVITATION_TOKEN);
});

it('keeps the same rows when seeded again', function () {
    $this->seed(ManualTestDataSeeder::class);
    $this->seed(ManualTestDataSeeder::class);

    expect(User::query()->count())->toBe(5)
        ->and(Company::query()->count())->toBe(2)
        ->and(Invitation::query()->count())->toBe(1);
});
