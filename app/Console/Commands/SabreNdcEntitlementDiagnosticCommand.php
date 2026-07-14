<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcStatusService;
use Illuminate\Console\Command;

class SabreNdcEntitlementDiagnosticCommand extends Command
{
    protected $signature = 'sabre:ndc-entitlement-diagnostic
                            {--connection= : Supplier connection ID}';

    protected $description = 'Sabre NDC entitlement diagnostic summary (no secrets, payloads, or PCC values)';

    public function handle(SabreNdcStatusService $statusService): int
    {
        $connection = $this->resolveConnection();
        $status = $statusService->status($connection);
        $entitlement = is_array($status['entitlement_diagnostic'] ?? null)
            ? $status['entitlement_diagnostic']
            : [];

        $this->line(json_encode($entitlement, JSON_UNESCAPED_SLASHES));

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
