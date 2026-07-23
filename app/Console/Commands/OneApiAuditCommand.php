<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Support\OneApiReadinessService;
use Illuminate\Console\Command;

class OneApiAuditCommand extends Command
{
    protected $signature = 'ota:one-api-audit {--connection= : Supplier connection ID}';

    protected $description = 'Summarize One API module readiness for a connection (configuration only).';

    public function handle(OneApiReadinessService $readiness): int
    {
        $connection = $this->connection();
        if ($connection === null) {
            return self::FAILURE;
        }

        foreach ($readiness->dimensions($connection) as $row) {
            $this->line(sprintf('%s: %s', ($row['ready'] ?? false) ? 'OK' : 'BLOCKED', $row['label']));
            $this->line('  '.$row['detail']);
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

        return SupplierConnection::query()->find($id);
    }
}
