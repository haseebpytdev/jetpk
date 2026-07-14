<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcFlightSearchService;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PiaNdcTestSearchCommand extends Command
{
    protected $signature = 'pia-ndc:test-search
        {--connection= : Supplier connection ID}
        {--from=ISB : Origin}
        {--to=DXB : Destination}
        {--date= : Departure YYYY-MM-DD}
        {--return= : Return YYYY-MM-DD}
        {--adults=1}
        {--children=0}
        {--infants=0}
        {--currency=PKR}';

    protected $description = 'Run PIA NDC test search and save sanitized fixture';

    public function handle(PiaNdcFlightSearchService $searchService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        $date = (string) ($this->option('date') ?: now()->addMonth()->format('Y-m-d'));
        $return = $this->option('return');
        $criteria = [
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => $date,
            'adults' => (int) $this->option('adults'),
            'children' => (int) $this->option('children'),
            'infants' => (int) $this->option('infants'),
            'currency' => strtoupper((string) $this->option('currency')),
            'trip_type' => $return ? 'return' : 'one_way',
        ];
        if ($return) {
            $criteria['return_date'] = (string) $return;
        }

        $request = FlightSearchRequestData::fromArray($criteria);
        $bundle = $searchService->runAirShoppingDiagnostic($request, $connection);
        $result = $bundle['result'];
        $diagnosticPath = (string) ($bundle['diagnostic_path'] ?? '');

        $this->line('offers_count='.count($result->offers));
        $this->line('diagnostic_path='.$diagnosticPath);

        if (isset($result->meta['error_code'])) {
            $this->error('error_code='.$result->meta['error_code']);
            foreach (['correlation_id', 'http_status', 'fault_code', 'fault_message', 'operation'] as $key) {
                if (isset($result->meta[$key]) && is_scalar($result->meta[$key])) {
                    $this->line($key.'='.$result->meta[$key]);
                }
            }
            if (isset($result->meta['provider_errors']) && is_array($result->meta['provider_errors'])) {
                $this->line('provider_errors='.json_encode(
                    SensitiveDataRedactor::redact($result->meta['provider_errors']),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ));
            }

            return self::FAILURE;
        }

        if ($result->offers !== []) {
            $first = $result->offers[0]->toArray();
            $this->line('first_offer_id='.($first['offer_id'] ?? ''));
            $this->line('first_route='.($first['origin'] ?? '').'-'.($first['destination'] ?? ''));
            $this->line('first_total='.($first['fare_breakdown']['supplier_total'] ?? ''));
        }

        $dir = base_path('tests/Fixtures/pia-ndc');
        File::ensureDirectoryExists($dir);
        $fixture = [
            'criteria' => $criteria,
            'offers_count' => count($result->offers),
            'first_offer' => isset($result->offers[0]) ? SensitiveDataRedactor::redact($result->offers[0]->toArray()) : null,
            'warnings' => $result->warnings,
            'diagnostic_path' => $diagnosticPath,
        ];
        $path = $dir.'/search_'.strtolower($criteria['origin']).'_'.strtolower($criteria['destination']).'.json';
        file_put_contents($path, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('fixture_saved='.$path);

        return self::SUCCESS;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::PiaNdc)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::PiaNdc)->orderByDesc('is_active')->first();
    }
}
