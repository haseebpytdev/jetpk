<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\Core\SabreCapabilityMatrixService;
use App\Services\Suppliers\Sabre\Diagnostics\SabreProdGapAuditService;
use App\Support\Sabre\SabreLaneRegistry;
use Illuminate\Console\Command;

/**
 * Read-only Sabre architecture and capability posture report.
 *
 * Prints lane registry + capability matrix aligned with sabre:prod-gap-audit code checks.
 * No HTTP, DB, env secrets, or supplier calls.
 */
class SabreArchitectureReportCommand extends Command
{
    public const REPORT_VERSION = 'sabre_architecture_report_v2';

    protected $signature = 'sabre:architecture-report {--json : Emit JSON only (no human tables)}';

    protected $description = 'Read-only Sabre lane registry and capability matrix (no HTTP, no secrets)';

    public function handle(
        SabreCapabilityMatrixService $capabilityMatrix,
        SabreProdGapAuditService $prodGapAudit,
    ): int {
        $payload = $this->buildPayload($capabilityMatrix, $prodGapAudit);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderTextReport($payload);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        SabreCapabilityMatrixService $capabilityMatrix,
        SabreProdGapAuditService $prodGapAudit,
    ): array {
        $prodGap = $prodGapAudit->run();

        return [
            'report_version' => self::REPORT_VERSION,
            'lanes' => SabreLaneRegistry::all(),
            'production_critical_files' => SabreLaneRegistry::productionCriticalFiles(),
            'diagnostics_only_files' => SabreLaneRegistry::diagnosticsOnlyFiles(),
            'obsolete_candidates' => SabreLaneRegistry::obsoleteCandidates(),
            'capabilities' => $capabilityMatrix->all(),
            'evidence_pending_capabilities' => $capabilityMatrix->evidencePending(),
            'provider_unsupported_manual_capabilities' => $capabilityMatrix->providerUnsupportedManual(),
            'not_implemented_capabilities' => $capabilityMatrix->disabled(),
            'prod_gap_audit' => [
                'audit_version' => $prodGap['audit_version'] ?? null,
                'pass' => $prodGap['pass'] ?? 0,
                'fail' => $prodGap['fail'] ?? 0,
                'partial' => $prodGap['partial'] ?? 0,
                'manual' => $prodGap['manual'] ?? 0,
                'matrix_mismatches' => $prodGap['matrix_mismatches'] ?? 0,
            ],
            'unresolved_capabilities' => $capabilityMatrix->evidencePending(),
            'disabled_capabilities' => $capabilityMatrix->disabled(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderTextReport(array $payload): void
    {
        $this->components->info('Sabre architecture report ('.self::REPORT_VERSION.')');
        $this->line('Read-only posture map. No live Sabre HTTP, no env secrets, no DB.');

        $gap = $payload['prod_gap_audit'] ?? [];
        $this->newLine();
        $this->components->twoColumnDetail('Prod gap audit alignment', sprintf(
            'pass=%s fail=%s manual=%s matrix_mismatches=%s',
            $gap['pass'] ?? 0,
            $gap['fail'] ?? 0,
            $gap['manual'] ?? 0,
            $gap['matrix_mismatches'] ?? 0,
        ));

        $this->newLine();
        $this->components->twoColumnDetail('Sabre lanes', (string) count($payload['lanes'] ?? []));

        $laneRows = [];
        foreach ($payload['lanes'] as $lane) {
            $laneRows[] = [
                $lane['key'] ?? '',
                $lane['category'] ?? '',
                $lane['status'] ?? '',
                (string) count($lane['files'] ?? []),
                $lane['risk'] ?? '',
            ];
        }
        $this->table(['Lane', 'Category', 'Status', 'Files', 'Risk'], $laneRows);

        $productionCritical = $payload['production_critical_files'] ?? [];
        $this->newLine();
        $this->components->twoColumnDetail('Production-critical files', (string) count($productionCritical));
        foreach ($productionCritical as $file) {
            $this->line('  - '.$file);
        }

        $diagnosticsOnly = $payload['diagnostics_only_files'] ?? [];
        $this->newLine();
        $this->components->twoColumnDetail('Diagnostics-only files', (string) count($diagnosticsOnly));
        foreach ($diagnosticsOnly as $file) {
            $this->line('  - '.$file);
        }

        $obsolete = $payload['obsolete_candidates'] ?? [];
        $this->newLine();
        $this->components->twoColumnDetail('Obsolete candidates', (string) count($obsolete));
        foreach ($obsolete as $file) {
            $this->line('  - '.$file);
        }

        $this->newLine();
        $this->components->info('Capability matrix');

        $capRows = [];
        foreach ($payload['capabilities'] as $cap) {
            $capRows[] = [
                $cap['key'] ?? '',
                $cap['code_implemented'] ?? 'no',
                $cap['production'] ?? 'no',
                $cap['live_http'] ?? 'no',
                $cap['evidence'] ?? 'unknown',
                $cap['manual'] ?? 'no',
                $cap['command'] ?? '',
            ];
        }
        $this->table(
            ['Capability', 'Code', 'Production', 'Live HTTP', 'Evidence', 'Manual', 'Command'],
            $capRows,
        );

        $this->newLine();
        $this->components->warn('Evidence pending (code implemented; live proof not certified)');
        foreach ($payload['evidence_pending_capabilities'] as $cap) {
            $this->line(sprintf(
                '  - %s: evidence=%s production=%s live_http=%s command=%s',
                $cap['key'] ?? '',
                $cap['evidence'] ?? '',
                $cap['production'] ?? '',
                $cap['live_http'] ?? '',
                $cap['command'] ?? '',
            ));
        }

        $this->newLine();
        $this->components->warn('Provider unsupported / manual (code implemented)');
        foreach ($payload['provider_unsupported_manual_capabilities'] as $cap) {
            $this->line(sprintf(
                '  - %s: evidence=%s manual=%s command=%s',
                $cap['key'] ?? '',
                $cap['evidence'] ?? '',
                $cap['manual'] ?? '',
                $cap['command'] ?? '',
            ));
            if (! empty($cap['notes'])) {
                $this->line('    '.$cap['notes']);
            }
        }

        $this->newLine();
        $this->components->warn('Not implemented (disabled)');
        foreach ($payload['not_implemented_capabilities'] as $cap) {
            $this->line(sprintf('  - %s: code_implemented=%s', $cap['key'] ?? '', $cap['code_implemented'] ?? 'no'));
        }

        $ndcKeys = ['ndc_search', 'ndc_order_create', 'ndc_order_retrieve', 'ndc_reprice', 'ndc_order_change', 'ndc_cancel'];
        $this->newLine();
        $this->components->twoColumnDetail('NDC posture', 'env_gated — evidence pending where implemented');
        foreach ($ndcKeys as $key) {
            $cap = $payload['capabilities'][$key] ?? null;
            if ($cap === null) {
                continue;
            }
            $this->line(sprintf(
                '  - %s: code=%s production=%s evidence=%s command=%s',
                $key,
                $cap['code_implemented'] ?? 'no',
                $cap['production'] ?? 'no',
                $cap['evidence'] ?? '',
                $cap['command'] ?? '',
            ));
        }

        $diagnostics = $payload['capabilities']['diagnostics'] ?? null;
        if (is_array($diagnostics)) {
            $this->newLine();
            $this->components->twoColumnDetail('Diagnostics', 'diagnostic_only (not customer-facing)');
            $this->line('  code_implemented='.$diagnostics['code_implemented']);
            $this->line('  production=no live_http=no command='.($diagnostics['command'] ?? ''));
        }
    }
}
