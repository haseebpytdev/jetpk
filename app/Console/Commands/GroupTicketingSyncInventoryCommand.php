<?php

namespace App\Console\Commands;

use App\Services\GroupTicketing\GroupInventorySyncService;
use Illuminate\Console\Command;

class GroupTicketingSyncInventoryCommand extends Command
{
    protected $signature = 'group-ticketing:sync-inventory
                            {--dry-run : Count packages without writing}
                            {--provider= : Sync a single provider (alhaider or ameer_e_millat)}
                            {--all : Sync all enabled providers (default)}';

    protected $description = 'Sync group ticket packages from configured suppliers into local group_inventories';

    public function handle(GroupInventorySyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $provider = $this->option('provider');
        $provider = is_string($provider) && trim($provider) !== '' ? trim($provider) : null;

        $result = $syncService->sync($provider, $dryRun);

        foreach ($result['providers'] ?? [] as $providerKey => $providerResult) {
            $status = ($providerResult['skipped'] ?? false) ? 'skipped' : 'ok';
            $message = $providerResult['message'] ?? '';
            $this->line(sprintf(
                '[%s] %s — synced %d, deactivated %d%s',
                $providerKey,
                $status,
                (int) ($providerResult['synced'] ?? 0),
                (int) ($providerResult['deactivated'] ?? 0),
                $message !== '' ? " ({$message})" : '',
            ));
        }

        if (($result['providers'] ?? []) === [] && ($result['message'] ?? null)) {
            $this->warn($result['message']);
        }

        if ($result['skipped'] && ($result['successful_providers'] ?? []) === []) {
            $this->warn($result['message'] ?? 'Sync skipped.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run: would sync '.$result['synced'].' package(s) across '.count($result['successful_providers'] ?? []).' provider(s).');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Synced %d package(s); deactivated %d. Successful providers: %s',
            $result['synced'],
            $result['deactivated'],
            implode(', ', $result['successful_providers'] ?? []) ?: 'none',
        ));

        if (($result['failed_providers'] ?? []) !== []) {
            $this->warn('Failed providers: '.implode(', ', $result['failed_providers']));
        }

        return self::SUCCESS;
    }
}
