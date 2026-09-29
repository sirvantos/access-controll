<?php

declare(strict_types=1);

use App\Exceptions\CompanyContextRequiredException;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Support\BelongsToCompany;
use App\Support\CompanyContextStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('isolation_probe_records');
    Schema::dropIfExists('isolation_id_scoped_records');

    Schema::create('isolation_probe_records', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('company_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('isolation_id_scoped_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function (): void {
    app(CompanyContextStore::class)->flush();
});

it('hides another company row while context is bound', function (): void {
    app(CompanyContext::class)->withoutIsolation(fn () => IsolationProbeRecord::query()->insert([
        ['company_id' => 1, 'name' => 'Acme', 'created_at' => now(), 'updated_at' => now()],
        ['company_id' => 2, 'name' => 'Globex', 'created_at' => now(), 'updated_at' => now()],
    ]));

    app(CompanyContext::class)->run(1, function (): void {
        expect(IsolationProbeRecord::query()->pluck('name')->all())->toBe(['Acme']);
    });
});

it('overwrites a planted company id on create', function (): void {
    app(CompanyContext::class)->run(1, function (): void {
        $record = IsolationProbeRecord::query()->create([
            'company_id' => 99,
            'name' => 'Planted',
        ]);

        expect($record->company_id)->toBe(1);
    });
});

it('scopes by primary key column without writing company id', function (): void {
    app(CompanyContext::class)->withoutIsolation(fn () => IsolationIdScopedRecord::query()->insert([
        ['id' => 5, 'name' => 'Five', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'name' => 'Six', 'created_at' => now(), 'updated_at' => now()],
    ]));

    app(CompanyContext::class)->run(5, function (): void {
        expect(IsolationIdScopedRecord::query()->pluck('name')->all())->toBe(['Five']);

        $created = IsolationIdScopedRecord::query()->create(['name' => 'New']);

        expect($created->id)->not->toBeNull()
            ->and(array_key_exists('company_id', $created->getAttributes()))->toBeFalse();
    });
});

it('refuses unbound queries and saves', function (): void {
    app(CompanyContext::class)->withoutIsolation(fn () => IsolationProbeRecord::query()->insert([
        ['company_id' => 1, 'name' => 'Acme', 'created_at' => now(), 'updated_at' => now()],
    ]));

    expect(fn () => IsolationProbeRecord::query()->count())->toThrow(CompanyContextRequiredException::class);

    expect(fn () => IsolationProbeRecord::query()->create(['name' => 'Blocked']))
        ->toThrow(CompanyContextRequiredException::class);
});

it('refuses unbound updates to an existing company-scoped row', function (): void {
    $record = app(CompanyContext::class)->withoutIsolation(
        fn () => IsolationProbeRecord::query()->create([
            'company_id' => 1,
            'name' => 'Acme',
        ]),
    );

    expect(fn () => tap($record)->update(['name' => 'Changed']))
        ->toThrow(CompanyContextRequiredException::class);
});

it('reverts a planted company id change on save', function (): void {
    $record = app(CompanyContext::class)->withoutIsolation(
        fn () => IsolationProbeRecord::query()->create([
            'company_id' => 1,
            'name' => 'Scoped',
        ]),
    );

    app(CompanyContext::class)->run(1, function () use ($record): void {
        $record->company_id = 99;
        $record->save();

        expect($record->company_id)->toBe(1);
    });
});

it('refuses unbound saves for id-scoped models', function (): void {
    $record = app(CompanyContext::class)->withoutIsolation(
        fn () => IsolationIdScopedRecord::query()->create(['name' => 'Five']),
    );

    expect(fn () => tap($record)->update(['name' => 'Changed']))
        ->toThrow(CompanyContextRequiredException::class);
});

it('keeps explicit company id and null owner rules inside withoutIsolation', function (): void {
    app(CompanyContext::class)->withoutIsolation(function (): void {
        $record = IsolationProbeRecord::query()->create([
            'company_id' => 77,
            'name' => 'Explicit',
        ]);

        expect($record->company_id)->toBe(77);

        $superAdminLike = IsolationSuperAdminLikeRecord::query()->create([
            'company_id' => null,
            'name' => 'Super',
        ]);

        expect($superAdminLike->company_id)->toBeNull();

    });

    expect(fn () => IsolationStrictRecord::query()->create([
        'company_id' => null,
        'name' => 'Denied',
    ]))->toThrow(CompanyContextRequiredException::class);
});

final class IsolationProbeRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'isolation_probe_records';

    protected $fillable = ['company_id', 'name'];
}

final class IsolationIdScopedRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'isolation_id_scoped_records';

    protected $fillable = ['name'];

    public function companyIsolationColumn(): string
    {
        return 'id';
    }
}

final class IsolationSuperAdminLikeRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'isolation_probe_records';

    protected $fillable = ['company_id', 'name'];

    public function allowsNullCompanyOwner(): bool
    {
        return true;
    }
}

final class IsolationStrictRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'isolation_probe_records';

    protected $fillable = ['company_id', 'name'];
}
