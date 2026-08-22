<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64);
            $table->string('test_type', 40)->default('connection');
            $table->string('status', 40);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('environment', 16)->nullable();
            $table->timestamp('tested_at');
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sanitized_error_code', 80)->nullable();
            $table->string('sanitized_message', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['provider', 'tested_at']);
            $table->index(['provider', 'test_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_health_checks');
    }
};
