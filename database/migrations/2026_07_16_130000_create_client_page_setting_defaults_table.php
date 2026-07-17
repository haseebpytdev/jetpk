<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JETPK-HOMEPAGE-CMS Task 12: page-specific, tenant-specific saved defaults.
 *
 * Deliberately NOT auto-seeded from ClientPagePublicFallbackCatalog / the
 * code-level defaultHomeContent() fallback — per the programme spec, no
 * default exists here until an Admin explicitly saves one. The code-level
 * fallback catalog remains a separate, always-present safety net for when
 * NEITHER a Published row NOR an active saved default exists; this table is
 * for the deliberate, explicit "reset to this known-good state" mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_page_setting_defaults', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('page_key', 64);
            $table->unsignedInteger('schema_version')->nullable();
            $table->json('content_json')->nullable();
            $table->json('settings_json')->nullable();
            $table->string('checksum', 64);
            $table->string('label')->nullable();
            $table->text('note')->nullable();
            // Only one row per (client_profile_id, page_key) may have is_active=true at a time —
            // enforced in ClientPageSettingDefaultService, not at the DB constraint level, since
            // a partial unique index predicated on is_active=true isn't portably expressible across
            // the DB drivers this app supports without raw per-driver SQL.
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_profile_id', 'page_key', 'is_active'], 'client_page_setting_defaults_active_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_page_setting_defaults');
    }
};
