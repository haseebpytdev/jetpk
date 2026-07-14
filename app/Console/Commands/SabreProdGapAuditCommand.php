<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\Diagnostics\SabreProdGapAuditService;
use Illuminate\Console\Command;

class SabreProdGapAuditCommand extends Command
{
    protected $signature = 'sabre:prod-gap-audit {--json : Emit JSON only}';

    protected $description = 'Code-level Sabre prod gap audit vs Binham reference scope';

    public function handle(SabreProdGapAuditService $auditService): int
    {
        $report = $auditService->run();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($report['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->components->info('Sabre prod gap audit ('.($report['audit_version'] ?? '').')');
        $this->line('pass='.($report['pass'] ?? 0));
        $this->line('fail='.($report['fail'] ?? 0));
        $this->line('partial='.($report['partial'] ?? 0));
        $this->line('manual='.($report['manual'] ?? 0));
        $this->line('matrix_mismatches='.($report['matrix_mismatches'] ?? 0));

        $rows = [];
        foreach ($report['capabilities'] ?? [] as $cap) {
            $rows[] = [
                $cap['key'] ?? '',
                $cap['code_implemented'] ?? '',
                $cap['production'] ?? '',
                $cap['live_http'] ?? '',
                $cap['evidence'] ?? '',
                $cap['manual'] ?? '',
                $cap['command'] ?? ($cap['evidence_command'] ?? ''),
                $cap['status'] ?? '',
            ];
        }
        $this->table(
            ['Capability', 'Code', 'Production', 'Live HTTP', 'Evidence', 'Manual', 'Command', 'Audit'],
            $rows,
        );

        return ($report['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
