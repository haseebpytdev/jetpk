<?php

namespace App\Console\Commands;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageAdminContentResolver;
use App\Support\Client\ClientPageKeys;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * One-time JetPakistan import of effective public page content into client_page_settings.
 */
class JetpkPageSettingsBackfillCurrentContentCommand extends Command
{
    protected $signature = 'jetpk:page-settings-backfill-current-content {--dry-run : Report imports without writing}';

    protected $description = 'Import JetPK effective public page content into page settings when no draft/published row exists';

    public function handle(ClientPageAdminContentResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->line('Classification: '.($dryRun ? 'DRY-RUN' : 'WRITE').' JetPK page-settings backfill.');
        $this->line('db_write_attempted='.($dryRun ? 'false' : 'true'));
        $this->newLine();

        if (! Schema::hasTable('client_page_settings')) {
            $this->error('client_page_settings table missing');

            return self::FAILURE;
        }

        $profile = ClientProfile::query()
            ->where('slug', 'jetpk')
            ->where('is_master_profile', false)
            ->first();

        if ($profile === null) {
            $this->error('JetPK client profile not found');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $rows = [];

        foreach (ClientPageKeys::all() as $pageKey) {
            $payload = $resolver->backfillPayloadIfMissing($profile, $pageKey);
            if ($payload === null) {
                $skipped++;
                $rows[] = [$pageKey, 'skip', 'existing draft or published row'];

                continue;
            }

            if (! $dryRun) {
                ClientPageSetting::query()->updateOrCreate(
                    [
                        'client_profile_id' => $profile->id,
                        'page_key' => $pageKey,
                        'status' => ClientPageSettingStatus::Published,
                    ],
                    [
                        'content_json' => $payload,
                        'published_at' => now(),
                    ],
                );
            }

            $imported++;
            $rows[] = [$pageKey, $dryRun ? 'would_import' : 'imported', count($payload).' top-level keys'];
        }

        $this->table(['page_key', 'action', 'detail'], $rows);
        $this->newLine();
        $this->line("imported={$imported} skipped={$skipped} dry_run=".($dryRun ? '1' : '0'));

        return self::SUCCESS;
    }
}
