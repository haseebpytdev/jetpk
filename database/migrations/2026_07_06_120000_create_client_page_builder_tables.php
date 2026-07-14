<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_page_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('page_key', 64);
            $table->string('status', 16)->default('draft');
            $table->string('title')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('content_json')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_profile_id', 'page_key', 'status'], 'client_page_settings_profile_key_status');
        });

        Schema::create('client_page_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('page_key', 64);
            $table->string('asset_key', 64);
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('public_url')->nullable();
            $table->string('alt_text')->nullable();
            $table->json('meta_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_profile_id', 'page_key', 'asset_key'], 'client_page_assets_unique');
        });

        Schema::create('client_theme_palettes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('source_logo_path')->nullable();
            $table->string('primary', 16)->nullable();
            $table->string('secondary', 16)->nullable();
            $table->string('accent', 16)->nullable();
            $table->string('background', 16)->nullable();
            $table->string('surface', 16)->nullable();
            $table->string('text', 16)->nullable();
            $table->string('muted', 16)->nullable();
            $table->string('success', 16)->nullable();
            $table->string('warning', 16)->nullable();
            $table->string('danger', 16)->nullable();
            $table->json('palette_json')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_theme_palettes');
        Schema::dropIfExists('client_page_assets');
        Schema::dropIfExists('client_page_settings');
    }
};
