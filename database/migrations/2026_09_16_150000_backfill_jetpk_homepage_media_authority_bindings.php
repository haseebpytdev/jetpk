<?php

use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageMediaAuthorityBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Binds authoritative homepage route/deal media for JetPK without generating new images.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_profiles')) {
            return;
        }

        $backfill = app(JetpkHomepageMediaAuthorityBackfill::class);

        ClientProfile::query()
            ->where('is_active', true)
            ->whereIn('slug', ['jetpk', 'jetpakistan'])
            ->each(function (ClientProfile $profile) use ($backfill): void {
                $backfill->runForProfile($profile);
            });
    }

    public function down(): void
    {
        // Non-destructive data binding; no rollback of CMS media associations.
    }
};
