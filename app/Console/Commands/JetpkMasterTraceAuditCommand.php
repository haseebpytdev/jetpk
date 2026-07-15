<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkMasterTraceAuditService;
use Illuminate\Console\Command;

class JetpkMasterTraceAuditCommand extends Command
{
    protected $signature = 'jetpk:master-trace-audit {--show-warnings : Include non-fail bucket rows in output}';

    protected $description = 'Scan JetPK fork for Master/Parwaaz/YD branding traces and classify leak risk';

    public function handle(JetpkMasterTraceAuditService $audit): int
    {
        $this->line('Classification: READ-ONLY JetPK master trace audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $audit->run();
        $showWarnings = (bool) $this->option('show-warnings');

        $failBuckets = [
            'visible_public_ui_leak', 'visible_dashboard_leak', 'visible_checkout_leak',
            'visible_email_leak', 'visible_error_page_leak', 'visible_devcp_leak',
            'visible_asset_leak', 'database_branding_leak', 'route_generation_risk', 'root_mode_risk',
        ];

        $rows = [];
        foreach ($result['findings'] as $finding) {
            if (! $showWarnings && ! in_array($finding['bucket'], $failBuckets, true)) {
                continue;
            }
            $rows[] = [
                $finding['bucket'],
                $finding['term'],
                $finding['path'].':'.$finding['line'],
            ];
        }

        if ($rows !== []) {
            $this->table(['bucket', 'term', 'location'], array_slice($rows, 0, 80));
            if (count($rows) > 80) {
                $this->line('... '.(count($rows) - 80).' more rows');
            }
        }

        $this->newLine();
        $this->info(sprintf('fail_count=%d warn_count=%d total=%d', $result['fail_count'], $result['warn_count'], count($result['findings'])));
        foreach ($result['by_bucket'] as $bucket => $count) {
            $this->line("  {$bucket}: {$count}");
        }

        return $result['fail_count'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
