<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;

class OtaNormalizeAirportsForSearchCommand extends Command
{
    protected $signature = 'ota:normalize-airports-search';

    protected $description = 'Trim/uppercase IATA/ICAO and null invalid sentinel codes (no deletes, no activation changes)';

    public function handle(): int
    {
        $stats = Airport::normalizeCatalogForSearch();
        $this->info('Airport catalog normalized for search: '.json_encode($stats));

        return self::SUCCESS;
    }
}
