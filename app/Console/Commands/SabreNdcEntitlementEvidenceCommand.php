<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcEntitlementEvidenceService;
use Illuminate\Console\Command;

class SabreNdcEntitlementEvidenceCommand extends Command
{
    protected $signature = 'sabre:ndc-entitlement-evidence
                            {--connection= : Supplier connection ID}
                            {--verbose-safe : Include safe transaction-id observation count}
                            {--json : Emit compact JSON only}';

    protected $description = 'Export safe Sabre NDC entitlement evidence for client/Sabre review (no secrets, PCC, tokens, or payloads)';

    public function handle(SabreNdcEntitlementEvidenceService $evidenceService): int
    {
        $connection = $this->resolveConnection();
        $payload = $evidenceService->export($connection, (bool) $this->option('verbose-safe'));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $this->line($key.'='.$this->scalar($value));
            } else {
                $this->line($key.'='.$this->scalar($value));
            }
        }

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

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
