<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirportOrphanService;
use Illuminate\Console\Command;

class JetpkAirportsOrphanCleanupCommand extends Command
{
    protected $signature = 'jetpk:airports:orphan-cleanup {--dry-run : List orphan rows without deleting}';

    protected $description = 'Delete airport rows without a valid stored IATA code (explicit cleanup; not part of default import).';

    public function handle(AirportOrphanService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->confirm('Delete orphan airport rows without valid IATA?')) {
            $this->warn('Cleanup cancelled.');

            return self::FAILURE;
        }

        $result = $service->cleanup($dryRun);
        $this->line('db_write_attempted='.(($result['db_write_attempted'] ?? false) ? 'true' : 'false'));
        $this->line('candidate_count='.($result['candidate_count'] ?? 0));
        $this->line('deleted='.($result['deleted'] ?? 0));
        foreach ($result['candidates'] ?? [] as $candidate) {
            $this->line(sprintf(
                'id=%s iata=%s name=%s',
                $candidate['id'] ?? '',
                $candidate['iata_code'] ?? '',
                $candidate['name'] ?? '',
            ));
        }

        return self::SUCCESS;
    }
}
