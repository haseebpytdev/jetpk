<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineLogoCacheService;
use Illuminate\Console\Command;

class OtaCacheAirlineLogosCommand extends Command
{
    protected $signature = 'ota:cache-airline-logos
                            {--iata= : Cache a single IATA/ICAO code (e.g. PK)}
                            {--all-used : Cache logos for all active airlines in the database}';

    protected $description = 'Download and cache airline logos under storage/app/public/airline-logos';

    public function handle(AirlineLogoCacheService $cache): int
    {
        $iata = trim((string) $this->option('iata'));
        $allUsed = (bool) $this->option('all-used');

        if ($iata === '' && ! $allUsed) {
            $this->error('Specify --iata=PK or --all-used');

            return self::FAILURE;
        }

        if ($iata !== '' && $allUsed) {
            $this->error('Use either --iata or --all-used, not both');

            return self::FAILURE;
        }

        if ($iata !== '') {
            $safe = $cache->normalizeSafeCode($iata);
            if ($safe === null) {
                $this->error('Invalid airline code. Use 2–3 alphanumeric IATA/ICAO characters only.');

                return self::FAILURE;
            }

            if ($cache->hasCachedLogo($safe)) {
                $this->info("Logo already cached for {$safe}: ".$cache->publicUrlForCachedLogo($safe));

                return self::SUCCESS;
            }

            if ($cache->cacheLogoFromFallback($safe)) {
                $this->info("Cached logo for {$safe}: ".$cache->publicUrlForCachedLogo($safe));

                return self::SUCCESS;
            }

            $this->warn("Could not download logo for {$safe}. Generic fallback will be used in UI.");

            return self::FAILURE;
        }

        $stats = $cache->cacheAllUsedFromDatabase();
        $this->info(sprintf(
            'Airline logo cache complete — attempted: %d, stored: %d, skipped: %d, failed: %d',
            $stats['attempted'],
            $stats['stored'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return $stats['failed'] > 0 && $stats['stored'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
