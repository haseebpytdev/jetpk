<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use Illuminate\Console\Command;

class OtaTestDuffelSearchCommand extends Command
{
    protected $signature = 'ota:test-duffel-search {supplierConnectionId} {origin} {destination} {date}';

    protected $description = 'Run a safe Duffel test-mode search diagnostics check';

    public function handle(DuffelFlightSupplierAdapter $adapter): int
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->components->error('This command is restricted to local/staging/testing environments.');

            return self::FAILURE;
        }

        $connection = SupplierConnection::query()->find((int) $this->argument('supplierConnectionId'));
        if ($connection === null || $connection->provider !== SupplierProvider::Duffel) {
            $this->components->error('Duffel supplier connection not found.');

            return self::FAILURE;
        }
        if (! $connection->isActive()) {
            $this->components->error('Supplier connection is not active.');

            return self::FAILURE;
        }

        $request = FlightSearchRequestData::fromArray([
            'origin' => strtoupper((string) $this->argument('origin')),
            'destination' => strtoupper((string) $this->argument('destination')),
            'depart_date' => (string) $this->argument('date'),
        ]);

        $startedAt = microtime(true);
        $result = $adapter->search($request, $connection);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->line('provider=duffel');
        $this->line('connection_id='.$connection->id);
        $this->line('offers_count='.count($result->offers));
        $this->line('duration_ms='.$durationMs);
        if ($result->warnings !== []) {
            $this->line('warnings='.json_encode(array_values($result->warnings), JSON_UNESCAPED_SLASHES));
        }

        foreach (array_slice($result->offers, 0, 3) as $index => $offer) {
            $arr = $offer->toArray();
            $this->line(sprintf(
                'offer_%d=%s %s-%s %s %.2f',
                $index + 1,
                (string) ($arr['offer_id'] ?? 'n/a'),
                (string) ($arr['origin'] ?? 'n/a'),
                (string) ($arr['destination'] ?? 'n/a'),
                (string) data_get($arr, 'fare_breakdown.currency', 'USD'),
                (float) data_get($arr, 'fare_breakdown.supplier_total', 0)
            ));
        }

        return self::SUCCESS;
    }
}
