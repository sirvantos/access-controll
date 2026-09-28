<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string DEFAULT_TIME_ZONE = 'Asia/Almaty';

    private const string DEFAULT_START_TIME = '09:00';

    private const string DEFAULT_END_TIME = '18:00';

    private const int DEFAULT_BREAK_DURATION_MINUTES = 60;

    private const int DEFAULT_LATENESS_GRACE_MINUTES = 0;

    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('bin', 12)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('name_normalized', 255)->nullable();
            $table->index('name_normalized');
            $table->index('bin');
        });

        Schema::create('company_time_zone_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('time_zone', 64);
            $table->timestamp('applies_from');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'applies_from', 'id']);
        });

        Schema::create('company_working_day_setting_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->time('start_time');
            $table->time('end_time');
            $table->json('working_days');
            $table->unsignedSmallInteger('break_duration_minutes');
            $table->boolean('break_deducted');
            $table->unsignedSmallInteger('lateness_grace_minutes');
            $table->timestamp('applies_from');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'applies_from', 'id']);
        });

        $this->backfillExistingCompanies();

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('name_normalized', 255)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_working_day_setting_versions');
        Schema::dropIfExists('company_time_zone_versions');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex(['name_normalized']);
            $table->dropIndex(['bin']);
            $table->dropColumn(['bin', 'contact_person', 'phone', 'email', 'name_normalized']);
        });
    }

    public function backfillExistingCompanies(): void
    {
        DB::table('companies')->orderBy('id')->get()->each(
            fn (object $company): bool => $this->backfillCompany($company),
        );
    }

    private function backfillCompany(object $company): bool
    {
        $appliesFrom = CarbonImmutable::parse((string) $company->created_at, 'UTC')
            ->timezone(self::DEFAULT_TIME_ZONE)
            ->startOfDay()
            ->utc()
            ->format('Y-m-d H:i:s');

        DB::table('companies')->where('id', $company->id)->update([
            'name_normalized' => mb_strtolower((string) $company->name),
        ]);

        DB::table('company_time_zone_versions')->insert([
            'company_id' => $company->id,
            'time_zone' => self::DEFAULT_TIME_ZONE,
            'applies_from' => $appliesFrom,
            'created_at' => $appliesFrom,
        ]);

        DB::table('company_working_day_setting_versions')->insert([
            'company_id' => $company->id,
            'start_time' => self::DEFAULT_START_TIME,
            'end_time' => self::DEFAULT_END_TIME,
            'working_days' => json_encode([
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
            ], JSON_THROW_ON_ERROR),
            'break_duration_minutes' => self::DEFAULT_BREAK_DURATION_MINUTES,
            'break_deducted' => true,
            'lateness_grace_minutes' => self::DEFAULT_LATENESS_GRACE_MINUTES,
            'applies_from' => $appliesFrom,
            'created_at' => $appliesFrom,
        ]);

        return true;
    }
};
