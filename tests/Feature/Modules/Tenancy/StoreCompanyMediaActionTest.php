<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\StoreCompanyMediaAction;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Facades\Storage;

it('stores media on the private disk under the current company context', function (): void {
    Storage::fake('local');
    $acme = acmeCompany();
    $context = app(CompanyContext::class);

    $media = $context->run(
        $acme->id,
        fn () => (new StoreCompanyMediaAction($context))(
            CompanyMediaKind::EmployeePhoto,
            'stored-bytes',
            'image/jpeg',
        ),
    );

    expect($media->company_id)->toBe($acme->id)
        ->and(Storage::disk('local')->get($media->path))->toBe('stored-bytes');
});
