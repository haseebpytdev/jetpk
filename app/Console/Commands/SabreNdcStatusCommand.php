<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcStatusService;
use Illuminate\Console\Command;

class SabreNdcStatusCommand extends Command
{
    protected $signature = 'sabre:ndc-status
                            {--connection= : Supplier connection ID}';

    protected $description = 'Sabre NDC module status and credential readiness';

    public function handle(SabreNdcStatusService $statusService): int
    {
        $connection = $this->resolveConnection();
        $status = $statusService->status($connection);
        $this->line(json_encode($status, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && is_numeric($id)) {
            return SupplierConnection::query()->find((int) $id);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->orderByDesc('is_active')->orderBy('id')->first();
    }
}
