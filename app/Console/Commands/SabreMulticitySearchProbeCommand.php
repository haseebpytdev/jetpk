<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchRequestBuilder;
use Illuminate\Console\Command;

class SabreMulticitySearchProbeCommand extends Command
{
    protected $signature = 'sabre:multicity-search-probe
                            {--connection= : Supplier connection ID}
                            {--json : Emit JSON only}';

    protected $description = 'Probe Sabre multi-city BFM shop payload shape (no live HTTP)';

    public function handle(SabreFlightSearchRequestBuilder $builder): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('Sabre connection not found.');

            return self::FAILURE;
        }

        $request = new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'IST',
            departure_date: '2026-08-01',
            return_date: null,
            trip_type: 'multi_city',
            adults: 1,
            children: 0,
            infants: 0,
            cabin: 'economy',
            currency: 'USD',
            segments: [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-08-01'],
                ['origin' => 'DXB', 'destination' => 'IST', 'departure_date' => '2026-08-10'],
            ],
        );

        $payload = $builder->build($request, $connection);
        $odis = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation', []);

        $report = [
            'trip_type' => 'multi_city',
            'odi_count' => is_array($odis) ? count($odis) : 0,
            'rph_values' => collect(is_array($odis) ? $odis : [])->pluck('RPH')->filter()->values()->all(),
            'has_seats_requested' => data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.SeatsRequested') !== null,
            'data_sources' => data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources'),
            'intellisell' => data_get($payload, 'OTA_AirLowFareSearchRQ.TPA_Extensions.IntelliSellTransaction.RequestType.Name'),
            'live_supplier_call_attempted' => false,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($report as $key => $value) {
                $this->line($key.'='.(is_array($value) ? json_encode($value) : (string) $value));
            }
        }

        return ($report['odi_count'] ?? 0) >= 2 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveConnection(): ?SupplierConnection
    {
        $id = (int) $this->option('connection');
        if ($id > 0) {
            return SupplierConnection::query()->find($id);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->where('is_active', true)->orderBy('id')->first();
    }
}
