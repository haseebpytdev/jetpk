<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Support\OneApiReadinessService;
use Illuminate\Console\Command;

class OneApiConnectionAuditCommand extends Command
{
    protected $signature = 'ota:one-api-connection-audit {--connection= : Supplier connection ID} {--live : Perform live auth/search probes}';

    protected $description = 'Audit One API supplier connection configuration and readiness (no supplier calls unless --live).';

    public function handle(OneApiReadinessService $readinessService): int
    {
        $connectionId = (int) $this->option('connection');
        if ($connectionId <= 0) {
            $this->error('--connection is required.');

            return self::FAILURE;
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            $this->error('Connection not found.');

            return self::FAILURE;
        }

        $this->info('One API connection audit: #'.$connection->id.' '.$connection->name);
        foreach ($readinessService->dimensions($connection) as $key => $row) {
            $flag = ($row['ready'] ?? false) ? 'OK' : 'BLOCKED';
            $this->line(sprintf('[%s] %s — %s', $flag, $row['label'], $row['detail']));
        }

        if ($this->option('live')) {
            $this->warn('Live probe not enabled in this audit pass without explicit search confirm flags.');
        }

        return self::SUCCESS;
    }
}
