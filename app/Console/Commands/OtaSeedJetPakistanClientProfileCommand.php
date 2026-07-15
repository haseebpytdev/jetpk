<?php

namespace App\Console\Commands;

use App\Services\Client\JetPakistanClientProfileProvisioner;
use Illuminate\Console\Command;

class OtaSeedJetPakistanClientProfileCommand extends Command
{
    protected $signature = 'ota:seed-jetpakistan-client-profile
                            {--dry-run : Show what would be provisioned without writing}';

    protected $description = 'Create or update the JetPakistan client preview profile (slug: jetpk)';

    public function handle(JetPakistanClientProfileProvisioner $provisioner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $provisioner->provision($dryRun);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run — no database changes made.');
            $this->line('Slug: '.$result['slug']);
            $this->line('Would '.($result['created'] ? 'create' : 'update').' JetPakistan client profile.');

            return self::SUCCESS;
        }

        $this->info('JetPakistan client profile provisioned successfully.');
        $this->line('Slug: '.$result['slug']);
        $this->line('Profile ID: '.(string) ($result['profile_id'] ?? ''));
        $this->line('Action: '.($result['created'] ? 'created' : 'updated'));

        return self::SUCCESS;
    }
}
