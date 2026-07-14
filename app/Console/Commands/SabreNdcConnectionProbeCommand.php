<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcConnectionProbeService;
use Illuminate\Console\Command;

class SabreNdcConnectionProbeCommand extends Command
{
    protected $signature = 'sabre:ndc-connection-probe
                            {--connection= : Supplier connection ID}
                            {--send : Live OAuth token probe only (no NDC shop/order calls)}
                            {--confirm= : Required for --send: READONLY-SABRE-NDC-CONNECTION-PROBE}
                            {--json : Emit compact JSON only}';

    protected $description = 'Read-only Sabre NDC connection probe (OAuth only; no order/search/ticketing/cancel)';

    public function handle(SabreNdcConnectionProbeService $probeService): int
    {
        $send = (bool) $this->option('send');
        if ($send && trim((string) $this->option('confirm')) !== SabreNdcConnectionProbeService::CONFIRM_PHRASE) {
            $this->components->error('--send requires --confirm='.SabreNdcConnectionProbeService::CONFIRM_PHRASE);

            return self::FAILURE;
        }

        $result = $probeService->probe($this->resolveConnection(), $send);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

            return $this->exitCode($result, $send);
        }

        $this->line('classification='.($result['classification'] ?? 'READ-ONLY'));
        $this->line('lane='.($result['lane'] ?? 'sabre_ndc'));
        $this->line('live_supplier_call_attempted='.(($result['live_supplier_call_attempted'] ?? false) ? 'true' : 'false'));
        $this->line('safe_error_family='.($result['safe_error_family'] ?? 'unknown'));

        $auth = is_array($result['auth'] ?? null) ? $result['auth'] : [];
        foreach ($auth as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $this->line('auth.'.$key.'='.$this->scalar($value));
            }
        }

        $blockers = is_array($result['blockers'] ?? null) ? $result['blockers'] : [];
        $this->line('blockers='.implode(',', $blockers));

        return $this->exitCode($result, $send);
    }

    private function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && is_numeric($id)) {
            return SupplierConnection::query()->find((int) $id);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->orderByDesc('is_active')->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function exitCode(array $result, bool $send): int
    {
        if (! $send) {
            return self::SUCCESS;
        }

        $auth = is_array($result['auth'] ?? null) ? $result['auth'] : [];

        return ($auth['token_obtained'] ?? null) === true ? self::SUCCESS : self::FAILURE;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
