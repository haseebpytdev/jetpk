<?php

namespace App\Console\Commands;

use App\Services\GroupTicketing\GroupInventorySyncService;
use Illuminate\Console\Command;

class GroupTicketingSyncInventoryCommand extends Command
{
    protected $signature = 'group-ticketing:sync-inventory {--dry-run : Count packages without writing}';

    protected $description = 'Sync Al-Haider group packages into local group_inventories';

    public function handle(GroupInventorySyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $syncService->sync($dryRun);

        if ($result['skipped']) {
            $this->warn($result['message'] ?? 'Sync skipped.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run: would sync '.$result['synced'].' package(s).');

            return self::SUCCESS;
        }

        $this->info('Synced '.$result['synced'].' package(s); deactivated '.$result['deactivated'].'.');

        return self::SUCCESS;
    }
}
