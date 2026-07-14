<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('background_removal_settings')) {
            Schema::create('background_removal_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 32)->default('disabled');
                $table->string('api_endpoint', 255)->nullable();
                $table->text('api_key')->nullable();
                $table->unsignedSmallInteger('timeout_seconds')->default(30);
                $table->unsignedInteger('max_source_bytes')->default(5_242_880);
                $table->unsignedInteger('max_source_pixels')->default(16_777_216);
                $table->boolean('is_enabled')->default(false);
                $table->boolean('default_for_logos')->default(false);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique('agency_id');
            });
        }

        if (! Schema::hasTable('branding_asset_processes')) {
            Schema::create('branding_asset_processes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('asset_type', 32);
                $table->string('provider', 32)->nullable();
                $table->string('source_path', 512);
                $table->string('result_path', 512)->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('source_checksum', 64)->nullable();
                $table->string('result_checksum', 64)->nullable();
                $table->string('source_mime', 64)->nullable();
                $table->string('result_mime', 64)->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->unsignedBigInteger('source_size')->nullable();
                $table->unsignedBigInteger('result_size')->nullable();
                $table->decimal('transparent_ratio', 8, 5)->nullable();
                $table->decimal('opaque_ratio', 8, 5)->nullable();
                $table->string('provider_request_id', 128)->nullable();
                $table->string('error_code', 64)->nullable();
                $table->string('error_message_safe', 500)->nullable();
                $table->unsignedInteger('processing_ms')->nullable();
                $table->json('warnings')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('discarded_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['agency_id', 'status']);
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_asset_processes');
        Schema::dropIfExists('background_removal_settings');
    }
};
