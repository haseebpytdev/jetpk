<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Ancillaries\OneApiAncillaryCatalogService;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;
use App\Support\OneApi\OneApiMutationCommandGate;
use Illuminate\Console\Command;

class OneApiAncillaryProbeCommand extends Command
{
    protected $signature = 'ota:one-api-ancillary-probe
        {--connection= : Supplier connection ID}
        {--fixture=price_base.xml : Price SOAP fixture for ancillary chain}
        {--confirm-live-search : Required for live ancillary calls}
        {--dry-run : Fixture catalog only}';

    protected $description = 'Probe baggage/meal/seat ancillary SOAP (fixture transport by default).';

    public function handle(OneApiAncillaryCatalogService $catalogService): int
    {
        $connection = $this->connection();
        if ($connection === null) {
            return self::FAILURE;
        }

        $gate = OneApiMutationCommandGate::evaluateLive(
            (bool) $this->option('confirm-live-search'),
            ['suppliers.one_api.live_search_enabled'],
        );

        $fixture = base_path('tests/Fixtures/Suppliers/OneApi/'.ltrim((string) $this->option('fixture'), '/'));
        $xml = is_file($fixture) ? (string) file_get_contents($fixture) : '<soapenv:Envelope/>';
        $context = new OneApiWorkflowContext(
            contextId: 'probe-'.uniqid('', true),
            connectionId: $connection->id,
            correlationId: 'probe',
            signedOfferPayload: [],
        );

        $catalog = $catalogService->loadCatalog(
            $connection,
            $context,
            $xml,
            ['fixture_path' => $fixture],
        );

        $this->line('baggage='.count($catalog['baggage']).' meals='.count($catalog['meals']).' seats='.count($catalog['seats']));
        if (! $gate['live_allowed']) {
            $this->line('mode=fixture blockers='.implode(',', $gate['blockers']));
        }

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
