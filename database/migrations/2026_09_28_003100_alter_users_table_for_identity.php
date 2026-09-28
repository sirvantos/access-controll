<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
            $table->string('role', 32);
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedInteger('session_version')->default(1);
            $table->index(['company_id', 'role', 'deactivated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'role', 'deactivated_at']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['role', 'deactivated_at', 'session_version']);
            $table->string('name');
        });
    }
};
