<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_in_security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('email', 255);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('ip_address', 45);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_in_security_events');
    }
};
