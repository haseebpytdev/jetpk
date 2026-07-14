<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class JetpkTravelDataDiscoveryCommand extends Command
{
    protected $signature = 'jetpk:travel-data-discovery';

    protected $description = 'Read-only production-safe airport/airline discovery metrics (no imports).';

    public function handle(): int
    {
        $this->line('JetPK travel-data discovery');
        $this->line('airport_columns='.implode(',', Schema::getColumnListing('airports')));
        $this->line('airline_columns='.implode(',', Schema::getColumnListing('airlines')));
        $this->line('airports='.Airport::query()->count());
        $this->line('airlines='.Airline::query()->count());
        $this->line('airports_active='.Airport::query()->where('is_active', true)->count());
        $this->line('airports_inactive='.Airport::query()->where('is_active', false)->count());
        $this->line('airlines_active='.Airline::query()->where('is_active', true)->count());
        $this->line('airlines_inactive='.Airline::query()->where('is_active', false)->count());
        $this->line('airport_unique_iata='.Airport::query()->whereNotNull('iata_code')->distinct('iata_code')->count('iata_code'));
        $this->line('airport_unique_icao='.Airport::query()->whereNotNull('icao_code')->distinct('icao_code')->count('icao_code'));
        $this->line('airline_unique_iata='.Airline::query()->whereNotNull('iata_code')->distinct('iata_code')->count('iata_code'));
        $this->line('airline_unique_icao='.Airline::query()->whereNotNull('icao_code')->distinct('icao_code')->count('icao_code'));
        $this->line('airport_blank_name='.Airport::query()->whereRaw('TRIM(COALESCE(name, "")) = ""')->count());
        $this->line('airport_blank_city='.Airport::query()->whereRaw('TRIM(COALESCE(city, "")) = ""')->count());
        $this->line('airline_blank_name='.Airline::query()->whereRaw('TRIM(COALESCE(name, "")) = ""')->count());

        $airportsCsv = storage_path('app/imports/airports.csv');
        if (File::isFile($airportsCsv)) {
            $this->line('airports_csv_size='.filesize($airportsCsv));
            $this->line('airports_csv_sha256='.hash_file('sha256', $airportsCsv));
        } else {
            $this->line('airports_csv_missing=1');
        }

        $generic = public_path('images/airline-generic.svg');
        if (is_file($generic)) {
            $this->line('generic_fallback_size='.filesize($generic));
            $this->line('generic_fallback_sha256='.hash_file('sha256', $generic));
        }

        $appStorage = public_path('storage');
        $this->line('app_public_storage_is_link='.(is_link($appStorage) ? '1' : '0'));
        $this->line('app_public_storage_target='.(is_link($appStorage) ? (string) readlink($appStorage) : ''));

        return self::SUCCESS;
    }
}
