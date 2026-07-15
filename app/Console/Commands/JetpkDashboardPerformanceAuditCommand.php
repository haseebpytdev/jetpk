<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkDashboardPerformanceAuditService;
use Illuminate\Console\Command;

/**
 * Read-only JetPK dashboard route performance audit (no writes, no supplier calls, no email).
 */
class JetpkDashboardPerformanceAuditCommand extends Command
{
    protected $signature = 'jetpk:dashboard-performance-audit';

    protected $description = 'Audit JetPK dashboard GET performance — pagination, query counts, customer index (read-only)';

    public function handle(JetpkDashboardPerformanceAuditService $service): int
    {
        $this->line('Classification: READ-ONLY JetPK dashboard performance audit.');
        $this->line('db_write_attempted=false');
        $this->line('supplier_calls=false');
        $this->line('email_sent=false');
        $this->newLine();

        $result = $service->run();
        $summary = $result['summary'];

        $this->table(
            ['route', 'controller', 'check', 'status', 'detail'],
            collect($result['rows'])->map(fn (array $row) => [
                $row['route'] ?? '—',
                $row['controller'] ?? '—',
                $row['check'] ?? '—',
                $row['status'] ?? '—',
                $row['detail'] ?? '',
            ])->all(),
        );

        $this->newLine();
        $this->line("pass={$summary['pass']} warn={$summary['warn']} fail={$summary['fail']}");

        return ($summary['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
