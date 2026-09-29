<?php

declare(strict_types=1);

use App\Modules\Companies\Models\Company;
use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\Models\Terminal;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

const TENANCY_SHARED_TERMINAL_NAME = 'Lobby terminal';

const ACME_MEDIA_BYTES = 'acme-photo-bytes';

/**
 * @return array{acme: Company, media: CompanyMedia}
 */
function acmeStoredEmployeePhoto(): array
{
    Storage::fake('local');
    $acme = acmeCompany();
    $context = app(CompanyContext::class);

    $media = $context->run($acme->id, function () use ($acme): CompanyMedia {
        $publicId = (string) Str::uuid();
        $path = $acme->id.'/'.$publicId;
        Storage::disk('local')->put($path, ACME_MEDIA_BYTES);

        return CompanyMedia::factory()->create([
            'company_id' => $acme->id,
            'public_id' => $publicId,
            'path' => $path,
            'kind' => CompanyMediaKind::EmployeePhoto,
            'content_type' => 'image/jpeg',
        ]);
    });

    return ['acme' => $acme, 'media' => $media];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function acmeEmployeeNumberSeventeen(array $overrides = []): Employee
{
    return tenancyEmployee(array_replace([
        'company_id' => acmeCompany()->id,
        'employee_number' => '17',
        'name' => 'Acme Seventeen',
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function globexEmployeeNumberSeventeen(array $overrides = []): Employee
{
    return tenancyEmployee(array_replace([
        'company_id' => globexCompany()->id,
        'employee_number' => '17',
        'name' => 'Aigerim Sarsenova',
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function acmeTerminal(array $overrides = []): Terminal
{
    return tenancyTerminal(array_replace([
        'company_id' => acmeCompany()->id,
        'name' => TENANCY_SHARED_TERMINAL_NAME,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function globexTerminal(array $overrides = []): Terminal
{
    return tenancyTerminal(array_replace([
        'company_id' => globexCompany()->id,
        'name' => TENANCY_SHARED_TERMINAL_NAME,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function tenancyEmployee(array $overrides = []): Employee
{
    $companyId = $overrides['company_id'] ?? acmeCompany()->id;
    throw_unless(is_int($companyId), InvalidArgumentException::class, 'company_id override must be an integer.');

    $context = app(CompanyContext::class);

    return $context->run($companyId, fn (): Employee => Employee::factory()->create(array_replace([
        'company_id' => $companyId,
    ], $overrides)));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function tenancyTerminal(array $overrides = []): Terminal
{
    $companyId = $overrides['company_id'] ?? acmeCompany()->id;
    throw_unless(is_int($companyId), InvalidArgumentException::class, 'company_id override must be an integer.');

    $context = app(CompanyContext::class);

    return $context->run($companyId, fn (): Terminal => Terminal::factory()->create(array_replace([
        'company_id' => $companyId,
    ], $overrides)));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function tenancyCompanyMedia(array $overrides = []): CompanyMedia
{
    $companyId = $overrides['company_id'] ?? acmeCompany()->id;
    throw_unless(is_int($companyId), InvalidArgumentException::class, 'company_id override must be an integer.');

    $context = app(CompanyContext::class);

    return $context->run($companyId, fn (): CompanyMedia => CompanyMedia::factory()->create(array_replace([
        'company_id' => $companyId,
        'kind' => CompanyMediaKind::EmployeePhoto,
    ], $overrides)));
}
