<?php

declare(strict_types=1);

use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('enforces employee number uniqueness within a company only', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    $context = app(CompanyContext::class);

    $context->withoutIsolation(function () use ($acme, $globex): void {
        DB::table('employees')->insert([
            [
                'company_id' => $acme->id,
                'employee_number' => '17',
                'name' => 'Acme Worker',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $globex->id,
                'employee_number' => '17',
                'name' => 'Globex Worker',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    });

    expect(DB::table('employees')->where('employee_number', '17')->count())->toBe(2);

    $context->withoutIsolation(function () use ($acme): void {
        expect(fn (): bool => DB::table('employees')->insert([
            'company_id' => $acme->id,
            'employee_number' => '17',
            'name' => 'Duplicate',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

it('requires company media public ids to be unique', function (): void {
    $company = acmeCompany();
    $publicId = (string) Str::uuid();

    $context = app(CompanyContext::class);

    $context->withoutIsolation(function () use ($company, $publicId): void {
        DB::table('company_media')->insert([
            'company_id' => $company->id,
            'public_id' => $publicId,
            'kind' => 'employee_photo',
            'disk' => 'local',
            'path' => $company->id.'/'.$publicId,
            'content_type' => 'image/jpeg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $context->withoutIsolation(function () use ($company, $publicId): void {
        expect(fn (): bool => DB::table('company_media')->insert([
            'company_id' => $company->id,
            'public_id' => $publicId,
            'kind' => 'employee_photo',
            'disk' => 'local',
            'path' => $company->id.'/'.$publicId.'-dup',
            'content_type' => 'image/jpeg',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});

it('stores super admin action records without an updated_at column', function (): void {
    expect(Schema::hasTable('super_admin_action_records'))->toBeTrue()
        ->and(Schema::hasColumn('super_admin_action_records', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('super_admin_action_records', 'occurred_at'))->toBeTrue();
});
