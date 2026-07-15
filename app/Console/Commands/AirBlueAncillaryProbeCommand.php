<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueAncillaryService;
use Illuminate\Console\Command;

class AirBlueAncillaryProbeCommand extends Command
{
    protected $signature = 'airblue:ancillary-probe {--connection= : Supplier connection ID}';

    protected $description = 'Probe AirBlue NDC ancillary capability (seat/baggage samples)';

    public function handle(AirBlueAncillaryService $ancillaryService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No AirBlue SupplierConnection found.');

            return self::FAILURE;
        }

        $this->line('connection_id='.$connection->id);
        $this->line('supported='.($ancillaryService->isSupported($connection) ? 'true' : 'false'));
        $ancillaryService->logUnavailable($connection, 'ancillary_probe');

        return self::SUCCESS;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && $id !== '') {
            return SupplierConnection::query()
                ->where('id', (int) $id)
                ->where('provider', SupplierProvider::Airblue)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Airblue)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}
