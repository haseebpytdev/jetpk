<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcDiagnosticService;
use Illuminate\Console\Command;

class PiaNdcHealthCommand extends Command
{
    protected $signature = 'pia-ndc:health {--connection= : Supplier connection ID}';

    protected $description = 'Check PIA NDC credentials, endpoint, and configuration';

    public function handle(PiaNdcDiagnosticService $diagnosticService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No active PIA NDC SupplierConnection found.');

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
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}
