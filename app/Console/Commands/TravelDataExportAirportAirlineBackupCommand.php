<?php

namespace App\Console\Commands;

use App\Services\TravelData\TravelDataAirportAirlineBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TravelDataExportAirportAirlineBackupCommand extends Command
{
    protected $signature = 'travel-data:export-airport-airline-backup
                            {--path= : Output JSON path (default: storage/app/audits/jetpk-airport-parity/pre-import-backup.json)}';

    protected $description = 'Export airports and airlines tables to JSON for rollback (full row fidelity).';

    public function handle(TravelDataAirportAirlineBackupService $service): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/audits/jetpk-airport-parity/pre-import-backup.json'));
        File::ensureDirectoryExists(dirname($path));

        $result = $service->export($path);
        $this->info('Backup written: '.$result['path']);
        $this->line('sha256='.$result['sha256']);
        $this->line('exported_at='.$result['exported_at']);
        $this->line('airport_rows='.$result['airport_rows']);
        $this->line('airline_rows='.$result['airline_rows']);
        $this->line('airport_columns='.implode(',', $result['schema']['airports'] ?? []));
        $this->line('airline_columns='.implode(',', $result['schema']['airlines'] ?? []));

        return self::SUCCESS;
    }
}
