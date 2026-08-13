<?php

namespace App\Console\Commands;

use App\Services\Rbac\RbacInstallService;
use Illuminate\Console\Command;

class RbacInstallCommand extends Command
{
    protected $signature = 'rbac:install';

    protected $description = 'Seed protected system roles and backfill account-type assignments without rewriting staff_permissions meta.';

    public function handle(RbacInstallService $install): int
    {
        $result = $install->seedAndBackfill();
        $this->info('RBAC_SEED roles='.$result['roles'].' assignments='.$result['assignments'].' drift='.$result['drift']);

        return $result['drift'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
