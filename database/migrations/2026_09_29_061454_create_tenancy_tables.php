<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->uuid('public_id')->unique();
            $table->string('kind', 32);
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->string('content_type', 127);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('employee_number', 32);
            $table->string('name', 255);
            $table->foreignId('photo_media_id')->nullable()->constrained('company_media');
            $table->timestamps();

            $table->unique(['company_id', 'employee_number']);
        });

        Schema::create('terminals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name', 255);
            $table->timestamps();
        });

        Schema::create('access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('terminal_id')->constrained('terminals');
            $table->string('employee_number', 32);
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('snapshot_media_id')->nullable()->constrained('company_media');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('super_admin_action_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('company_id')->constrained('companies');
            $table->string('type', 32);
            $table->string('action', 64);
            $table->timestamp('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_action_records');
        Schema::dropIfExists('access_events');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('company_media');
    }
};
