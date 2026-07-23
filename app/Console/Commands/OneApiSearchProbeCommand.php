<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Search\OneApiFlightSearchService;
use App\Support\OneApi\OneApiMutationCommandGate;
use Illuminate\Console\Command;

class OneApiSearchProbeCommand extends Command
{
    protected $signature = 'ota:one-api-search-probe
        {--connection= : Supplier connection ID}
        {--from=SHJ : Origin}
        {--to=KHI : Destination}
        {--date= : Departure YYYY-MM-DD}
        {--return= : Return YYYY-MM-DD}
        {--confirm-live-search : Required for live search}
        {--dry-run : Build request only}';

    protected $description = 'Probe One API REST search (fixture transport unless live confirmed).';

    public function handle(OneApiFlightSearchService $searchService): int
    {
        $connection = $this->connection();
        if ($connection === null) {
            return self::FAILURE;
        }

        $gate = OneApiMutationCommandGate::evaluateLive(
            (bool) $this->option('confirm-live-search'),
            ['suppliers.one_api.live_search_enabled'],
        );

        if ($this->option('dry-run') || ! $gate['live_allowed']) {
            $this->warn('Dry-run / fixture mode. blockers='.implode(',', $gate['blockers']));
        }

        $request = new FlightSearchRequestData(
            origin: (string) $this->option('from'),
            destination: (string) $this->option('to'),
            departure_date: (string) ($this->option('date') ?: now()->addDays(14)->toDateString()),
            return_date: $this->option('return') ? (string) $this->option('return') : null,
            trip_type: $this->option('return') ? 'return' : 'oneway',
            adults: 1,
        );

        if ($this->option('dry-run') || ! $gate['live_allowed']) {
            $this->line(json_encode(app(\App\Services\Suppliers\OneApi\Search\OneApiSearchRequestBuilder::class)->build($request, $connection), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $result = $searchService->search($request, $connection);
        $this->line('options='.count($result->offers));

        return self::SUCCESS;
    }

    private function connection(): ?SupplierConnection
    {
        $id = (int) $this->option('connection');
        if ($id <= 0) {
            $this->error('--connection is required.');

            return null;
        }

        $connection = SupplierConnection::query()->find($id);
        if ($connection === null || $connection->provider !== SupplierProvider::OneApi) {
            $this->error('One API connection not found.');

            return null;
        }

        return $connection;
    }
}
