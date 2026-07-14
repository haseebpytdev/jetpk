<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('environment')->default('production');
            $table->string('active_frontend_theme')->default('v1-classic');
            $table->string('active_admin_theme')->nullable();
            $table->string('active_staff_theme')->nullable();
            $table->string('asset_profile');
            $table->string('default_locale')->default('en');
            $table->string('timezone')->default('Asia/Karachi');
            $table->string('currency')->default('PKR');
            $table->boolean('is_master_profile')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('client_profile_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['client_profile_id', 'module_key'], 'client_profile_module_unique');
        });

        Schema::create('client_profile_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('supplier_key', 64);
            $table->boolean('enabled')->default(false);
            $table->string('mode')->nullable();
            $table->text('credentials')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['client_profile_id', 'supplier_key'], 'client_profile_supplier_unique');
        });

        Schema::create('client_profile_branding', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->unique()->constrained('client_profiles')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profile_branding');
        Schema::dropIfExists('client_profile_suppliers');
        Schema::dropIfExists('client_profile_modules');
        Schema::dropIfExists('client_profiles');
    }
};
