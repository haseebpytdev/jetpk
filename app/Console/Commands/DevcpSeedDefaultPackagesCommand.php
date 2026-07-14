<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformPackageService;
use Illuminate\Console\Command;

/**
 * Idempotent seed of default platform packages from registry presets.
 */
class DevcpSeedDefaultPackagesCommand extends Command
{
    protected $signature = 'devcp:seed-default-packages';

    protected $description = 'Seed default platform packages (full_ota, b2b_only, b2c_only, maintenance_lite)';

    public function handle(PlatformPackageService $packages): int
    {
        $result = $packages->seedDefaults();

        $this->info(sprintf(
            'Default packages seeded. Created: %d, updated: %d.',
            $result['created'],
            $result['updated'],
        ));

        return self::SUCCESS;
    }
}
