<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileSyncService;
use Illuminate\Console\Command;

class OtaSyncCurrentClientProfileCommand extends Command
{
    protected $signature = 'ota:sync-current-client-profile
                            {--slug= : Override client slug (defaults to config slug or haseeb-master)}
                            {--dry-run : Show what would be synced without writing}';

    protected $description = 'Create or update the DB client profile from config/ota_client.php and branding fallbacks';

    public function handle(ClientProfileSyncService $syncService): int
    {
        $slugOption = $this->option('slug');
        $slug = is_string($slugOption) && trim($slugOption) !== '' ? trim($slugOption) : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $syncService->sync($slug, $dryRun);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run — no database changes made.');
            $this->line('Slug: '.$result['slug']);
            $this->line('Would '.($result['created'] ? 'create' : 'update').' client profile.');

            return self::SUCCESS;
        }

        $this->info('Client profile synced successfully.');
        $this->line('Slug: '.$result['slug']);
        $this->line('Profile ID: '.(string) ($result['profile_id'] ?? ''));
        $this->line('Action: '.($result['created'] ? 'created' : 'updated'));

        if ($result['slug'] === 'haseeb-master') {
            $this->line('Marked as master profile (is_master_profile=true).');
        }

        return self::SUCCESS;
    }
}
