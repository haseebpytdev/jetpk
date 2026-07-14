<?php

namespace App\Console\Commands;

use App\Services\TravelData\TravelDataAirportAirlineBackupService;
use Illuminate\Console\Command;

class TravelDataRestoreAirportAirlineBackupCommand extends Command
{
    protected $signature = 'travel-data:restore-airport-airline-backup
                            {--path= : Backup JSON path}
                            {--dry-run : Preview restore counts only}
                            {--authoritative : Delete rows not present in the backup snapshot}';

    protected $description = 'Restore airports and airlines tables from a travel-data export backup.';

    public function handle(TravelDataAirportAirlineBackupService $service): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/audits/jetpk-airport-parity/pre-import-backup.json'));
        $dryRun = (bool) $this->option('dry-run');
        $authoritative = (bool) $this->option('authoritative');

        if ($authoritative && ! $dryRun && ! $this->confirm('Authoritative restore will delete rows not in the backup. Continue?')) {
            $this->warn('Restore cancelled.');

            return self::FAILURE;
        }

        try {
            $stats = $service->restore($path, $dryRun, $authoritative);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('dry_run='.(($stats['dry_run'] ?? false) ? 'true' : 'false'));
        $this->line('authoritative='.(($stats['authoritative'] ?? false) ? 'true' : 'false'));
        $this->line('db_write_attempted='.(($stats['db_write_attempted'] ?? false) ? 'true' : 'false'));
        $this->line('backup_sha256='.($stats['backup_sha256'] ?? ''));
        foreach (['airports', 'airlines'] as $table) {
            $bucket = $stats[$table] ?? [];
            $this->line(sprintf(
                '%s insert=%d update=%d delete=%d skip=%d conflict=%d',
                $table,
                $bucket['insert'] ?? 0,
                $bucket['update'] ?? 0,
                $bucket['delete'] ?? 0,
                $bucket['skip'] ?? 0,
                $bucket['conflict'] ?? 0,
            ));
        }

        return self::SUCCESS;
    }
}
