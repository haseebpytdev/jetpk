<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_diagnostic_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_connection_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('action');
            $table->string('status');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('safe_message')->nullable();
            $table->string('correlation_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['supplier_connection_id', 'action', 'status']);
            $table->index(['provider', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_diagnostic_logs');
    }
};
