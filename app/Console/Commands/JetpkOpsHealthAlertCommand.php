<?php

namespace App\Console\Commands;

use App\Support\Ops\OpsExternalAlertRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Offline-testable ops alert probe. Does not replay historical notifications.
 */
class JetpkOpsHealthAlertCommand extends Command
{
    protected $signature = 'jetpk:ops-health-alert
        {--category=disk_pressure : Alert category}
        {--level=HIGH : Severity label}
        {--dry-run : Force dry-run (no send)}
        {--probe-failed-jobs : Include failed_jobs count in body}';

    protected $description = 'Route a non-customer operational health alert via configured OPS_ALERT_EMAIL (opt-in)';

    public function handle(OpsExternalAlertRouter $router): int
    {
        $category = (string) $this->option('category');
        $level = strtoupper((string) $this->option('level'));
        $dryRun = (bool) $this->option('dry-run');

        $failedJobs = null;
        if ($this->option('probe-failed-jobs')) {
            try {
                $failedJobs = (int) DB::table('failed_jobs')->count();
            } catch (Throwable) {
                $failedJobs = -1;
            }
        }

        $subject = "Health {$level} ({$category})";
        $body = "JetPakistan operational health alert\n"
            ."category={$category}\n"
            ."level={$level}\n"
            .'failed_jobs='.($failedJobs === null ? 'n/a' : (string) $failedJobs)."\n"
            ."generated_at=".now()->utc()->toIso8601String()."\n";

        $result = $router->route(
            category: $category,
            subject: $subject,
            body: $body,
            context: [
                'level' => $level,
                'failed_jobs' => $failedJobs,
            ],
            dryRun: $dryRun,
        );

        $this->line('OPS_ALERT_CATEGORY='.$category);
        $this->line('OPS_ALERT_LEVEL='.$level);
        $this->line('OPS_ALERT_CHANNEL='.$result['channel']);
        $this->line('OPS_ALERT_DELIVERED='.($result['delivered'] ? 'YES' : 'NO'));
        $this->line('OPS_ALERT_DRY_RUN='.($result['dry_run'] ? 'YES' : 'NO'));
        if (isset($result['reason'])) {
            $this->line('OPS_ALERT_REASON='.$result['reason']);
        }

        return self::SUCCESS;
    }
}
