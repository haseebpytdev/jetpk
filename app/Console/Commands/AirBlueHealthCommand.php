<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueDiagnosticService;
use Illuminate\Console\Command;

class AirBlueHealthCommand extends Command
{
    protected $signature = 'airblue:health {--connection= : Supplier connection ID}';

    protected $description = 'Check AirBlue credentials, endpoint, and configuration';

    public function handle(AirBlueDiagnosticService $diagnosticService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No active AirBlue SupplierConnection found.');

            return self::FAILURE;
        }

        $this->line('connection_id='.$connection->id);
        $this->line('is_active='.($connection->is_active ? 'true' : 'false'));

        $result = $diagnosticService->healthCheck($connection);
        foreach ($result as $key => $value) {
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_scalar($value)) {
                $this->line($key.'='.$value);
            }
        }

        return ($result['healthy'] ?? false) ? self::SUCCESS : self::FAILURE;
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
