<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Reservation\OneApiRetrieveService;
use App\Support\OneApi\OneApiMutationCommandGate;
use Illuminate\Console\Command;

class OneApiReadReservationCommand extends Command
{
    protected $signature = 'ota:one-api-read-reservation
        {--connection= : Supplier connection ID}
        {--pnr= : Record locator}
        {--fixture=book_paid.xml : Fixture for dry-run}
        {--confirm-live-search : Required for live read}
        {--dry-run : Fixture only}';

    protected $description = 'Read One API reservation (OTA_ReadRQ).';

    public function handle(OneApiRetrieveService $retrieveService): int
    {
        $connection = $this->connection();
        if ($connection === null) {
            return self::FAILURE;
        }

        $pnr = trim((string) $this->option('pnr'));
        if ($pnr === '') {
            $this->error('--pnr is required.');

            return self::FAILURE;
        }

        $gate = OneApiMutationCommandGate::evaluateLive(
            (bool) $this->option('confirm-live-search'),
            ['suppliers.one_api.live_search_enabled'],
        );

        $fixture = base_path('tests/Fixtures/Suppliers/OneApi/'.ltrim((string) $this->option('fixture'), '/'));
        if ($this->option('dry-run') || ! $gate['live_allowed']) {
            $this->line('pnr='.$pnr.' fixture='.basename($fixture));

            return is_file($fixture) ? self::SUCCESS : self::FAILURE;
        }

        $result = $retrieveService->getReservationByPnr(
            $connection,
            $pnr,
            'read-'.uniqid('', true),
            ['fixture_path' => $fixture],
        );
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

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
