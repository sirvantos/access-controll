<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('returns stored bytes for an acme admin', function (): void {
    ['acme' => $acme, 'media' => $media] = acmeStoredEmployeePhoto();
    $admin = acmeAdmin(['company_id' => $acme->id]);

    signedInAs($admin)
        ->get('/api/v1/company/media/'.$media->public_id)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    $response = signedInAs($admin)->get('/api/v1/company/media/'.$media->public_id);

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toBe(ACME_MEDIA_BYTES);
});

it('refuses guests with the auth envelope', function (): void {
    ['media' => $media] = acmeStoredEmployeePhoto();

    $this->getJson('/api/v1/company/media/'.$media->public_id)
        ->assertUnauthorized();
});

it('returns identical not found json for globex media and an unused uuid', function (): void {
    ['media' => $media] = acmeStoredEmployeePhoto();
    $globexAdmin = globexAdmin(['company_id' => globexCompany()->id]);
    $unusedUuid = (string) Str::uuid();

    $globexResponse = signedInAs($globexAdmin)->getJson('/api/v1/company/media/'.$media->public_id);
    $missingResponse = signedInAs($globexAdmin)->getJson('/api/v1/company/media/'.$unusedUuid);

    expect($globexResponse->status())->toBe(404)
        ->and($missingResponse->status())->toBe(404)
        ->and($globexResponse->json())->toBe($missingResponse->json());
});

it('does not register a public storage route for the private local disk', function (): void {
    acmeStoredEmployeePhoto();

    expect(config('filesystems.disks.local.serve'))->toBeFalse();

    $hasStorageRoute = collect(Route::getRoutes())->contains(
        fn ($route): bool => str_starts_with($route->uri(), 'storage/')
    );

    expect($hasStorageRoute)->toBeFalse();
});

it('does not return media after sign-out', function (): void {
    ['acme' => $acme, 'media' => $media] = acmeStoredEmployeePhoto();
    $admin = acmeAdmin(['company_id' => $acme->id]);

    signedInAs($admin)
        ->postJson('/api/v1/auth/sign-out')
        ->assertOk();

    $this->get('/api/v1/company/media/'.$media->public_id)
        ->assertUnauthorized();
});

it('forbids viewers from employee photos', function (): void {
    Storage::fake('local');
    $acme = acmeCompany();
    $context = app(CompanyContext::class);
    $media = $context->run($acme->id, function () use ($acme): CompanyMedia {
        $media = CompanyMedia::factory()->create([
            'company_id' => $acme->id,
            'kind' => CompanyMediaKind::EmployeePhoto,
            'content_type' => 'image/jpeg',
        ]);
        Storage::disk('local')->put($media->path, ACME_MEDIA_BYTES);

        return $media;
    });
    $viewer = acmeViewer(['company_id' => $acme->id]);

    signedInAs($viewer)
        ->getJson('/api/v1/company/media/'.$media->public_id)
        ->assertForbidden();
});

it('allows viewers to read event snapshots in their company', function (): void {
    Storage::fake('local');
    $acme = acmeCompany();
    $context = app(CompanyContext::class);
    $bytes = 'snapshot-bytes';
    $media = $context->run($acme->id, function () use ($acme, $bytes): CompanyMedia {
        $media = CompanyMedia::factory()->create([
            'company_id' => $acme->id,
            'kind' => CompanyMediaKind::EventSnapshot,
            'content_type' => 'image/png',
        ]);
        Storage::disk('local')->put($media->path, $bytes);

        return $media;
    });
    $viewer = acmeViewer(['company_id' => $acme->id]);

    signedInAs($viewer)
        ->get('/api/v1/company/media/'.$media->public_id)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $response = signedInAs($viewer)->get('/api/v1/company/media/'.$media->public_id);

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toBe($bytes);
});
