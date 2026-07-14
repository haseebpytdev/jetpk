<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineCanonicalSyncService;
use Illuminate\Console\Command;

class JetpkAirlinesCanonicalSyncCommand extends Command
{
    protected $signature = 'jetpk:airlines:canonical-sync {--dry-run : Report planned changes without writing}';

    protected $description = 'Sync configured JetPK canonical airline overrides into the database (targeted; no general import).';

    public function handle(AirlineCanonicalSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $dryRun ? $service->plan() : $service->apply();

        $this->line('JetPK canonical airline sync');
        $this->line('db_write_attempted='.(($result['db_write_attempted'] ?? false) ? 'true' : 'false'));

        foreach ($result['entries'] ?? [] as $entry) {
            $this->line(sprintf('[%s]', $entry['iata'] ?? ''));
            $this->line('  iata='.($entry['iata'] ?? ''));
            $this->line('  current_db_id='.($entry['current_db_id'] ?? 'null'));
            $this->line('  current_name='.($entry['current_name'] ?? 'null'));
            $this->line('  desired_name='.($entry['desired_name'] ?? 'null'));
            $this->line('  current_icao='.($entry['current_icao'] ?? 'null'));
            $this->line('  desired_icao='.($entry['desired_icao'] ?? 'null'));
            $this->line('  current_country='.($entry['current_country'] ?? 'null'));
            $this->line('  desired_country='.($entry['desired_country'] ?? 'null'));
            $this->line('  current_is_active='.($this->formatBool($entry['current_is_active'] ?? null)));
            $this->line('  desired_is_active='.($this->formatBool($entry['desired_is_active'] ?? null)));
            $this->line('  action='.($entry['action'] ?? ''));
            if (($entry['conflict_db_ids'] ?? []) !== []) {
                $this->line('  conflict_db_ids='.json_encode($entry['conflict_db_ids']));
            }
        }

        $this->line('update_count='.($result['update_count'] ?? 0));
        $this->line('insert_count='.($result['insert_count'] ?? 0));
        $this->line('unchanged_count='.($result['unchanged_count'] ?? 0));
        $this->line('conflict_count='.($result['conflict_count'] ?? 0));

        if (($result['has_conflicts'] ?? false) === true) {
            $this->error('Duplicate IATA rows detected for configured canonical codes. No writes performed.');

            return self::FAILURE;
        }

        if (! $dryRun && ($result['applied'] ?? false) !== true) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function formatBool(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        return $value ? 'true' : 'false';
    }
}
