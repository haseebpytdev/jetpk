<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirportImportService;
use Illuminate\Console\Command;

class AirportsImportCommand extends Command
{
    protected $signature = 'airports:import
                            {--source= : Path to OurAirports-style CSV (default: storage/app/imports/airports.csv)}
                            {--dry-run : Preview counts without writing to the database}
                            {--truncate : Delete all airports before import (use with care)}
                            {--prune-not-in-source : Delete airports whose IATA is absent from the source CSV (explicit; use with --dry-run first)}';

    protected $description = 'Import or update passenger airports from a local IATA-code CSV dataset (OurAirports-style; upsert only by default)';

    public function handle(AirportImportService $importer): int
    {
        $source = (string) ($this->option('source') ?: config('airports_import.default_source'));
        $dryRun = (bool) $this->option('dry-run');
        $truncate = (bool) $this->option('truncate');
        $prune = (bool) $this->option('prune-not-in-source');

        if ($prune && ! $dryRun && ! $this->confirm('Prune airports not present in the source CSV?')) {
            $this->warn('Import cancelled.');

            return self::FAILURE;
        }

        if ($truncate && ! $dryRun && ! $this->confirm('Truncate the airports table before import?')) {
            $this->warn('Import cancelled.');

            return self::FAILURE;
        }

        $this->info('Source: '.$source);
        $this->line('Dataset: '.(string) config('airports_import.dataset_label'));
        if ($dryRun) {
            $this->warn('Dry run — no database writes.');
        }
        if ($truncate) {
            $this->warn($dryRun ? 'Would truncate airports table first.' : 'Truncating airports table.');
        }

        if ($prune) {
            $this->warn($dryRun ? 'Prune preview — no deletions.' : 'Prune enabled — airports absent from source will be deleted.');
        }

        try {
            $stats = $importer->import($source, $dryRun, $truncate, $prune);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Import summary');
        $this->line('Imported: '.$stats['imported']);
        $this->line('Updated: '.$stats['updated']);
        $this->line('Skipped (total): '.$stats['skipped']);
        $this->line('Skipped (closed): '.$stats['skipped_closed']);
        $this->line('Skipped (no IATA): '.$stats['skipped_no_iata']);
        $this->line('Skipped (type/filter): '.$stats['skipped_type']);
        $this->line('Overrides applied: '.$stats['overrides_applied']);
        $this->line('db_write_attempted='.(($stats['db_write_attempted'] ?? false) ? 'true' : 'false'));
        if ($prune) {
            $this->line('Prune candidates: '.($stats['pruned_not_in_source'] ?? 0));
            foreach ($stats['prune_candidates'] ?? [] as $candidate) {
                $this->line(sprintf(
                    '  id=%s iata=%s name=%s',
                    $candidate['id'] ?? '',
                    $candidate['iata_code'] ?? '',
                    $candidate['name'] ?? '',
                ));
            }
        }

        if (! $dryRun) {
            $this->newLine();
            $this->comment('After deploy, clear airport search cache: php artisan cache:clear');
        }

        return self::SUCCESS;
    }
}
