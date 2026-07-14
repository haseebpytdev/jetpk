<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcCapabilityReportService;
use App\Support\Sabre\SabreCommandSafetyOutput;
use Illuminate\Console\Command;

class SabreNdcCapabilityReportCommand extends Command
{
    protected $signature = 'sabre:ndc-capability-report
                            {--connection= : Supplier connection ID}
                            {--json : Emit compact JSON only}';

    protected $description = 'Read-only Sabre NDC capability and readiness report (no HTTP, no secrets)';

    public function handle(SabreNdcCapabilityReportService $reportService): int
    {
        $report = $reportService->report($this->resolveConnection());

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach (SabreCommandSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }

        $this->line('lane='.($report['lane'] ?? 'sabre_ndc'));
        $this->line('gds_lane_separated='.(($report['gds_lane_separated'] ?? false) ? 'true' : 'false'));

        $lane = is_array($report['lane_gate'] ?? null) ? $report['lane_gate'] : [];
        $this->line('effective_ndc_enabled='.(($lane['effective_ndc_enabled'] ?? false) ? 'true' : 'false'));
        $this->line('effective_gds_enabled='.(($lane['effective_gds_enabled'] ?? false) ? 'true' : 'false'));
        $this->line('selected_sabre_lanes='.json_encode($lane['selected_sabre_lanes'] ?? []));
        $this->line('gds_suppressed='.(($lane['gds_suppressed'] ?? false) ? 'true' : 'false'));
        $this->line('ndc_allowed='.(($lane['ndc_allowed'] ?? false) ? 'true' : 'false'));
        $this->line('credentials_shared=true');

        $capabilities = is_array($report['capabilities'] ?? null) ? $report['capabilities'] : [];
        foreach ($capabilities as $key => $cap) {
            if (! is_array($cap)) {
                continue;
            }
            $enabled = ($cap['enabled'] ?? false) ? 'yes' : 'no';
            $this->line('capability.'.$key.'='.$enabled.' ('.($cap['env_flag'] ?? '').')');
        }

        $credentials = is_array($report['credentials'] ?? null) ? $report['credentials'] : [];
        $this->line('credentials.present='.(($credentials['present'] ?? false) ? 'yes' : 'no'));
        $this->line('credentials.pcc_len='.(int) ($credentials['pcc_len'] ?? 0));

        $status = is_array($report['status'] ?? null) ? $report['status'] : [];
        $blockers = is_array($status['blockers'] ?? null) ? $status['blockers'] : [];
        $this->line('blockers='.implode(',', $blockers));

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
