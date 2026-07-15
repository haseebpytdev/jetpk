<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkDashboardRouteAuditService;
use Illuminate\Console\Command;

/**
 * Classifies authenticated routes for operational QA closure.
 */
class JetpkAuthenticatedRouteAuditCommand extends Command
{
    protected $signature = 'jetpk:authenticated-route-audit {--client=jetpk} {--json}';

    protected $description = 'Authenticated GET route matrix with explicit classifications (read-only)';

    public function handle(JetpkDashboardRouteAuditService $audit): int
    {
        $this->line('Classification: READ-ONLY authenticated route audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $audit->run((string) $this->option('client'));
        $rows = $result['rows'];
        $warn = 0;
        $fail = 0;
        $blocked = 0;
        $compat = 0;

        foreach ($rows as $row) {
            if (($row['qa_blocked'] ?? false) === true) {
                $blocked++;
            }
            if (($row['severity'] ?? '') === 'fail') {
                $fail++;
            } elseif (($row['severity'] ?? '') === 'warn') {
                $warn++;
            }
            foreach ($row['issues'] ?? [] as $issue) {
                if (($issue['classification'] ?? '') === 'compat-shell') {
                    $compat++;
                }
            }
        }

        $this->line('routes='.count($rows)." fail={$fail} warn={$warn} blocked_visual_qa={$blocked} compat_shell={$compat}");
        $this->line('json='.$result['json_path']);

        if ($this->option('json')) {
            $this->line(file_get_contents($result['json_path']) ?: '{}');
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
