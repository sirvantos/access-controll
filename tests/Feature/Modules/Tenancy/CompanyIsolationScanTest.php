<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Data\TenancyLimits;
use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\IsolationProbe;

require_once __DIR__.'/../../../Support/Tenancy/isolation_scan.php';

function seedIsolationScanFixture(): array
{
    $acme = acmeCompany();
    $globex = globexCompany();
    $acmeAdmin = acmeAdmin(['company_id' => $acme->id]);
    $globexAdmin = globexAdmin(['company_id' => $globex->id]);
    $globexViewer = globexViewer(['company_id' => $globex->id]);

    User::factory()->viewer($acme->id)->create([
        'email' => 'extra@acme.test',
        'password' => SAMPLE_PASSWORD,
        'company_id' => $acme->id,
    ]);

    invitationFor($acme, SAMPLE_INVITATION_TOKEN, ['email' => 'invitee@acme.test']);
    invitationFor($globex, SAMPLE_INVITATION_TOKEN_ALT, ['email' => 'invitee@globex.test']);

    acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    globexEmployeeNumberSeventeen(['company_id' => $globex->id]);
    acmeTerminal(['company_id' => $acme->id]);
    globexTerminal(['company_id' => $globex->id]);

    $context = app(CompanyContext::class);
    $acmeProbe = $context->run($acme->id, fn (): IsolationProbe => IsolationProbe::query()->create([
        'company_id' => $acme->id,
        'name' => 'Acme probe',
    ]));
    $globexProbe = $context->run($globex->id, fn (): IsolationProbe => IsolationProbe::query()->create([
        'company_id' => $globex->id,
        'name' => 'Aigerim Sarsenova',
    ]));

    return [
        'acme' => $acme,
        'globex' => $globex,
        'acmeAdmin' => $acmeAdmin,
        'globexAdmin' => $globexAdmin,
        'globexViewer' => $globexViewer,
        'globexRecordIds' => [$globexAdmin->id, $globexViewer->id, $globexProbe->id],
        'globexEmails' => [$globexAdmin->email, $globexViewer->email, 'invitee@globex.test'],
        'acmeProbeId' => $acmeProbe->id,
        'globexProbeId' => $globexProbe->id,
    ];
}

beforeEach(function (): void {
    IsolationProbe::ensureSchema();
    registerIsolationProbeRoutes();
    $this->fixture = seedIsolationScanFixture();
    signedInAs($this->fixture['acmeAdmin']);
});

it('keeps Globex data out of every successful company list response', function (): void {
    $globexIds = $this->fixture['globexRecordIds'];
    $globexEmails = $this->fixture['globexEmails'];

    foreach (materializedCompanyApiRoutes() as [$method, $uri]) {
        if ($method !== 'GET') {
            continue;
        }

        $response = $this->json($method, $uri);

        if ($response->status() !== 200) {
            continue;
        }

        assertJsonHasNoGlobexLeaks($response, $globexIds, $globexEmails);
    }

    $users = $this->getJson('/api/v1/company/users')->assertOk()->json('data');

    expect(count($users))->toBe(2);
});

it('keeps forged company context headers from widening list results', function (): void {
    $globex = $this->fixture['globex'];
    $globexAdmin = $this->fixture['globexAdmin'];

    $users = $this->getJson('/api/v1/company/users', [
        TenancyLimits::COMPANY_CONTEXT_HEADER => (string) $globex->id,
    ])
        ->assertOk()
        ->json('data');

    expect(array_column($users, 'id'))->not->toContain($globexAdmin->id);
});

it('returns identical not found json for Globex ids and unused ids on parameterized routes', function (): void {
    Notification::fake();
    Storage::fake('local');

    $globex = $this->fixture['globex'];
    $globexAdmin = $this->fixture['globexAdmin'];
    $globexInvitation = app(CompanyContext::class)->withoutIsolation(fn () => invitationFor($globex, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', [
        'email' => 'scan-invite@globex.test',
    ]));
    $globexMedia = app(CompanyContext::class)->run(
        $globex->id,
        function () use ($globex): CompanyMedia {
            $media = CompanyMedia::factory()->create([
                'company_id' => $globex->id,
                'kind' => CompanyMediaKind::EmployeePhoto,
                'content_type' => 'image/jpeg',
            ]);
            Storage::disk('local')->put($media->path, 'globex-secret');

            return $media;
        },
    );

    $missingUserId = 9_999_991;
    $missingInvitationId = 9_999_992;
    $missingProbeId = 9_999_993;
    $unusedUuid = (string) Str::uuid();

    $templates = parameterizedCompanyApiRouteTemplates();
    expect($templates)->not->toBeEmpty()
        ->and(array_column($templates, 1))->toContain('api/v1/company/users/{user}/reactivate');

    foreach ($templates as [$method, $uri]) {
        $body = companyDataIsolationRequestBody($method, $uri);
        $globexUri = companyDataUriWithIds($uri, $globexAdmin->id, $globexInvitation->id, $globexMedia->public_id);
        $missingUri = companyDataUriWithIds($uri, $missingUserId, $missingInvitationId, $unusedUuid);

        $globexResponse = $this->json($method, $globexUri, $body);
        $missingResponse = $this->json($method, $missingUri, $body);

        expect($globexResponse->status())->toBe($missingResponse->status())
            ->and($globexResponse->status())->toBe(404);
        $globexResponse->assertExactJson($missingResponse->json());
    }

    $probeBodies = [];

    foreach ([$this->fixture['globexProbeId'], $missingProbeId] as $probeId) {
        $probeBodies[] = $this->getJson('/_test/company/isolation-probes?id='.$probeId);
    }

    expect($probeBodies[0]->status())->toBe($probeBodies[1]->status())
        ->and($probeBodies[0]->status())->toBe(404);
    $probeBodies[0]->assertExactJson($probeBodies[1]->json());

    $globexAdmin->refresh();
    $globexInvitation->refresh();

    expect($globexAdmin->role->value)->toBe('company_admin')
        ->and($globexAdmin->deactivated_at)->toBeNull()
        ->and($globexInvitation->revoked_at)->toBeNull();

    Notification::assertNothingSent();
});

it('returns identical not found json for globex media and unused media uuids', function (): void {
    Storage::fake('local');
    $acme = $this->fixture['acme'];
    $context = app(CompanyContext::class);
    $globexMedia = $context->run(
        $this->fixture['globex']->id,
        function (): CompanyMedia {
            $media = CompanyMedia::factory()->create([
                'company_id' => $this->fixture['globex']->id,
                'kind' => CompanyMediaKind::EmployeePhoto,
                'content_type' => 'image/jpeg',
            ]);
            Storage::disk('local')->put($media->path, 'globex-secret');

            return $media;
        },
    );
    $unusedUuid = (string) Str::uuid();

    $globexResponse = $this->getJson('/api/v1/company/media/'.$globexMedia->public_id);
    $missingResponse = $this->getJson('/api/v1/company/media/'.$unusedUuid);

    expect($globexResponse->status())->toBe(404)
        ->and($missingResponse->status())->toBe(404)
        ->and($globexResponse->json())->toBe($missingResponse->json());

    $acmeMedia = $context->run(
        $acme->id,
        function () use ($acme): CompanyMedia {
            $media = CompanyMedia::factory()->create([
                'company_id' => $acme->id,
                'kind' => CompanyMediaKind::EmployeePhoto,
                'content_type' => 'image/jpeg',
            ]);
            Storage::disk('local')->put($media->path, 'acme-secret');

            return $media;
        },
    );

    $ok = $this->get('/api/v1/company/media/'.$acmeMedia->public_id);

    $filePath = $ok->baseResponse->getFile()->getPathname();

    expect($ok->getStatusCode())->toBe(200)
        ->and(file_get_contents($filePath))->toBe('acme-secret')
        ->and(file_get_contents($filePath))->not->toContain('globex-secret');
});

it('keeps globex out of company lists for a super admin with acme selected', function (): void {
    $owner = ownerSuperAdmin();
    $globexIds = $this->fixture['globexRecordIds'];
    $globexEmails = $this->fixture['globexEmails'];
    $asSuperAdmin = signedInWithSelectedCompany($owner, $this->fixture['acme']->id);

    $users = $asSuperAdmin->getJson('/api/v1/company/users')->assertOk();
    assertJsonHasNoGlobexLeaks($users, $globexIds, $globexEmails);

    $probes = $asSuperAdmin->getJson('/_test/company/isolation-probes')->assertOk();
    assertJsonHasNoGlobexLeaks($probes, $globexIds, $globexEmails);

    expect(array_column($probes->json('data'), 'id'))->toContain($this->fixture['acmeProbeId']);
});
