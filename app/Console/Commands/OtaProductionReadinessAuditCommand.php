<?php

namespace App\Console\Commands;

use App\Support\Audits\ProductionReadinessAuditService;
use App\Support\Sabre\SabreCommandSafetyOutput;
use Illuminate\Console\Command;

class OtaProductionReadinessAuditCommand extends Command
{
    protected $signature = 'ota:production-readiness-audit';

    protected $description = 'Read-only production operations readiness audit (no supplier calls, no secrets).';

    public function handle(ProductionReadinessAuditService $audit): int
    {
        foreach (SabreCommandSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->newLine();

        $result = $audit->run();
        $findings = $result['findings'];
        $counts = $result['counts'];

        $currentSection = null;
        foreach ($findings as $finding) {
            if ($finding['section'] !== $currentSection) {
                $currentSection = $finding['section'];
                $this->newLine();
                $this->info('=== '.$currentSection.' ===');
            }

            $prefix = strtoupper($finding['status']);
            if (! in_array($prefix, ['PASS', 'WARN', 'FAIL'], true)) {
                $prefix = 'WARN';
            }
            $this->line(sprintf('[%s] %s: %s', $prefix, $finding['label'], $finding['detail']));
        }

        $this->newLine();
        $this->info(sprintf(
            'Audit summary: pass=%d warn=%d fail=%d',
            $counts['pass'],
            $counts['warn'],
            $counts['fail'],
        ));

        $this->newLine();
        $this->info('=== Recommendations (guidance only — not executed) ===');
        foreach ($result['recommendations'] as $recommendation) {
            $this->line('- '.$recommendation);
        }

        if ($counts['fail'] > 0) {
            $this->newLine();
            $this->error('Production readiness audit completed with failures.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Production readiness audit passed.');

        return self::SUCCESS;
    }
}
