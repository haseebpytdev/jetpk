<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Pricing\OneApiPricingService;
use App\Support\OneApi\OneApiMutationCommandGate;
use Illuminate\Console\Command;

class OneApiPriceProbeCommand extends Command
{
    protected $signature = 'ota:one-api-price-probe
        {--connection= : Supplier connection ID}
        {--fixture=price_base.xml : SOAP fixture under tests/Fixtures/Suppliers/OneApi}
        {--confirm-live-search : Required for live price}
        {--dry-run : No SOAP send}';

    protected $description = 'Probe One API initial/final price (fixture by default).';

    public function handle(OneApiPricingService $pricingService): int
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
        if ($this->option('dry-run') || ! $gate['live_allowed']) {
            $this->line('fixture='.$fixture);
            $this->line('blockers='.implode(',', $gate['blockers']));

            return is_file($fixture) ? self::SUCCESS : self::FAILURE;
        }

        $this->warn('Live price probe requires workflow context; use booking flow or dedicated scenario runner.');

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
