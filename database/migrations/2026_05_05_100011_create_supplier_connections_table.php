<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('display_name')->nullable();
            $table->text('credentials')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_connections');
    }
};
