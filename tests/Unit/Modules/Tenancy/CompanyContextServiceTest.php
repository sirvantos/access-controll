<?php

declare(strict_types=1);

use App\Exceptions\CompanyContextRequiredException;
use App\Modules\Companies\Actions\ListCompaniesAction;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CrossCompanyFunctions;
use App\Support\BelongsToCompany;
use App\Support\CompanyContextStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('context_service_probe_records');

    Schema::create('context_service_probe_records', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('company_id');
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function (): void {
    app(CompanyContextStore::class)->flush();
});

it('binds company id for the duration of run and restores afterward', function () {
    $context = app(CompanyContext::class);

    $seen = $context->run(42, fn (): int => $context->companyId());

    expect($seen)->toBe(42);

    expect(fn () => $context->companyId())->toThrow(CompanyContextRequiredException::class);
});

it('restores the outer company id after nested run', function () {
    $context = app(CompanyContext::class);

    $context->run(10, function () use ($context): void {
        $context->run(20, function () use ($context): void {
            expect($context->companyId())->toBe(20);
        });

        expect($context->companyId())->toBe(10);
    });
});

it('throws when company id is requested without a bound context', function () {
    $context = app(CompanyContext::class);

    expect(fn () => $context->companyId())->toThrow(CompanyContextRequiredException::class);
});

it('allows unbound access only inside withoutIsolation', function () {
    $context = app(CompanyContext::class);

    $context->withoutIsolation(fn () => ContextServiceProbeRecord::query()->insert([
        ['company_id' => 1, 'name' => 'Row', 'created_at' => now(), 'updated_at' => now()],
    ]));

    $queried = $context->withoutIsolation(fn (): int => ContextServiceProbeRecord::query()->count());

    expect($queried)->toBe(1);

    expect(fn () => ContextServiceProbeRecord::query()->count())->toThrow(CompanyContextRequiredException::class);
});

it('lists every company through a named withoutIsolation exception', function (): void {
    acmeCompany();
    globexCompany();

    $paginator = app(ListCompaniesAction::class)(null, 1);

    expect($paginator->total())->toBe(2);
});

it('does not grant withoutIsolation to an ordinary company query', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    globexEmployeeNumberSeventeen(['company_id' => $globex->id]);

    $context = app(CompanyContext::class);

    $acmeCount = $context->run($acme->id, fn (): int => Employee::query()->count());
    $allCount = $context->withoutIsolation(fn (): int => Employee::query()->count());

    expect($acmeCount)->toBe(1)
        ->and($allCount)->toBe(2);
});

it('matches the isolation contract named-exception identifiers', function (): void {
    $markdown = (string) file_get_contents(base_path('specs/003-data-isolation-between-companies/contracts/isolation.md'));
    preg_match_all('/^\| `([a-z0-9_.]+)` \|/m', $markdown, $matches);

    expect($matches[1])->toBe(CrossCompanyFunctions::identifiers());
});

it('flushes the bound company id the way an octane worker must', function (): void {
    $context = app(CompanyContext::class);
    $store = app(CompanyContextStore::class);

    $store->bindCompany(7);
    expect($context->companyId())->toBe(7);

    $store->flushRequestBinding();

    expect(fn () => $context->companyId())->toThrow(CompanyContextRequiredException::class);
});

final class ContextServiceProbeRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'context_service_probe_records';

    protected $fillable = ['company_id', 'name'];
}
