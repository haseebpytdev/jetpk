<?php

namespace App\Console\Commands;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientPageBootstrapTemplate;
use App\Support\Client\ClientPageKeys;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * One-time explicit bootstrap import of JetPK managed page CMS content.
 */
class JetpkPublicPageCmsBootstrapCommand extends Command
{
    protected $signature = 'jetpk:public-page-cms-bootstrap
                            {--profile=jetpk : Client profile slug}
                            {--dry-run : Report imports without writing}
                            {--execute : Required to perform writes}';

    protected $description = 'Import missing JetPK managed page CMS content from bootstrap templates (never overwrites existing published content)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');
        if (! $dryRun && ! $execute) {
            $this->error('Refusing to write without --execute. Use --dry-run to preview.');

            return self::FAILURE;
        }

        $this->line('Classification: '.($dryRun ? 'DRY-RUN' : 'WRITE').' JetPK public page CMS bootstrap.');
        $this->line('db_write_attempted='.($dryRun ? 'false' : 'true'));
        $this->newLine();

        if (! Schema::hasTable('client_page_settings')) {
            $this->error('client_page_settings table missing');

            return self::FAILURE;
        }

        $profile = ClientProfile::query()
            ->where('slug', (string) $this->option('profile'))
            ->where('is_master_profile', false)
            ->first();
        if ($profile === null) {
            $this->error('Client profile not found');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $rows = [];
        foreach (ClientPageKeys::all() as $pageKey) {
            $exists = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->whereIn('status', [ClientPageSettingStatus::Draft, ClientPageSettingStatus::Published])
                ->exists();
            if ($exists) {
                $skipped++;
                $rows[] = [$pageKey, 'skip', 'existing draft or published row'];

                continue;
            }

            $payload = ClientPageBootstrapTemplate::contentFor($pageKey);
            if ($payload === []) {
                $skipped++;
                $rows[] = [$pageKey, 'skip', 'no bootstrap template'];

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
        $this->line("imported={$imported} skipped={$skipped} dry_run=".($dryRun ? '1' : '0'));

        return self::SUCCESS;
    }
}
