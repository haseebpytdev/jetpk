<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkDashboardRouteAuditService;
use Illuminate\Console\Command;

/**
 * Read-only JetPK authenticated dashboard route inventory and issue matrix.
 */
class JetpkDashboardRouteAuditCommand extends Command
{
    protected $signature = 'jetpk:dashboard-route-audit
                            {--client=jetpk : Client slug}
                            {--json : Print JSON path only}';

    protected $description = 'Enumerate authenticated JetPK dashboard routes — layout, view, CSS, QA safety (read-only)';

    public function handle(JetpkDashboardRouteAuditService $service): int
    {
        $slug = trim((string) $this->option('client'));
        $this->line('Classification: READ-ONLY JetPK dashboard route audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $service->run($slug);
        $summary = $result['summary'];

        if ($this->option('json')) {
            $this->line($result['json_path']);

            return ((int) ($summary['fail'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Route inventory');
        $this->table(
            ['metric', 'value'],
            [
                ['routes', (string) ($summary['pass'] + $summary['warn'] + $summary['fail'])],
                ['pass', (string) $summary['pass']],
                ['warn', (string) $summary['warn']],
                ['fail', (string) $summary['fail']],
                ['blocked_visual_qa', (string) ($summary['blocked_visual_qa'] ?? 0)],
                ['json', $result['json_path']],
                ['markdown', $result['md_path']],
            ],
        );

        $this->newLine();
        $this->info('By role');
        foreach ($summary['by_role'] ?? [] as $role => $count) {
            $this->line("  {$role}: {$count}");
        }

        $failRows = collect($result['rows'])->where('severity', 'fail')->take(15);
        if ($failRows->isNotEmpty()) {
            $this->newLine();
            $this->error('Failed routes (first 15)');
            $this->table(
                ['uri', 'role', 'view', 'issues'],
                $failRows->map(fn (array $r) => [
                    $r['uri'],
                    $r['role'],
                    $r['view'],
                    collect($r['issues'])->pluck('issue')->implode('; '),
                ])->all(),
            );
        }

        return ((int) ($summary['fail'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
