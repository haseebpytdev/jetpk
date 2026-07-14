<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64);
            $table->boolean('enabled')->default(false);
            $table->string('scope', 16)->default('global');
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['key', 'scope', 'agency_id'], 'feature_flag_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_feature_flags');
    }
};
