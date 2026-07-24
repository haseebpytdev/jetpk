<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JETPK-HOMEPAGE-CMS Task 11: page revisions.
 *
 * Immutable snapshots of client_page_settings content, taken before any
 * destructive write (publish, reset) so a prior state can be inspected or
 * restored. This table is generic to all Page Settings pages, not
 * homepage-specific, since ClientPageSetting itself is generic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_page_setting_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->string('page_key', 64);
            // What status the source row had at the moment of snapshot (draft/published) —
            // NOT the status of this revision itself, which has no status of its own.
            $table->string('source_status', 16);
            $table->unsignedInteger('schema_version')->nullable();
            $table->json('content_json')->nullable();
            $table->json('settings_json')->nullable();
            // sha256 of content_json at snapshot time — tamper-evidence / integrity check,
            // not a deduplication key (key-order differences produce different checksums
            // even for logically identical content — see ClientPageSettingRevisionService).
            $table->string('checksum', 64);
            // before_publish | before_reset | manual_snapshot | migration | restore
            $table->string('revision_reason', 32);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // created_at only — revisions are immutable, there is deliberately no
            // updated_at column and no code path that ever updates an existing row
            // (see ClientPageSettingRevision::UPDATED_AT = null and its boot() guard).
            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_profile_id', 'page_key', 'created_at'], 'client_page_setting_revisions_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_page_setting_revisions');
    }
};
