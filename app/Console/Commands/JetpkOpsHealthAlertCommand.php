<?php

namespace App\Console\Commands;

use App\Support\Ops\OpsExternalAlertRouter;
use App\Support\Ops\OpsHealthAlertTriggers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Offline-testable ops alert probe. Does not replay historical notifications.
 */
class JetpkOpsHealthAlertCommand extends Command
{
    protected $signature = 'jetpk:ops-health-alert
        {--category= : Single alert category (disk_pressure|queue_failure|...)}
        {--level=HIGH : Severity label for single-category mode}
        {--disk-level= : When set with --failed-jobs, dispatch all triggered categories}
        {--failed-jobs= : Failed job count for dispatch mode (omit to probe DB)}
        {--dry-run : Force dry-run (no send)}
        {--probe-failed-jobs : Include failed_jobs count in body (single-category mode)}';

    protected $description = 'Route non-customer operational health alerts via configured OPS_ALERT_EMAIL (opt-in)';

    public function handle(OpsExternalAlertRouter $router): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $diskLevel = $this->option('disk-level');

        if ($diskLevel !== null && $diskLevel !== '') {
            return $this->dispatchFromHealth($router, (string) $diskLevel, $dryRun);
        }

        $category = (string) ($this->option('category') ?: OpsExternalAlertRouter::LEVEL_DISK);

        return $this->routeOne(
            $router,
            $category,
            strtoupper((string) $this->option('level')),
            $this->resolveFailedJobsCount((bool) $this->option('probe-failed-jobs')),
            $dryRun,
        );
    }

    private function dispatchFromHealth(OpsExternalAlertRouter $router, string $diskLevel, bool $dryRun): int
    {
        $failedJobs = $this->option('failed-jobs');
        if ($failedJobs === null || $failedJobs === '') {
            $count = $this->resolveFailedJobsCount(true) ?? 0;
        } else {
            $count = max(0, (int) $failedJobs);
        }

        $triggers = OpsHealthAlertTriggers::forDiskAndFailedJobs($diskLevel, $count);
        $this->line('OPS_ALERT_TRIGGER_COUNT='.count($triggers));

        if ($triggers === []) {
            $this->line('OPS_ALERT_DISPATCH=NONE');

            return self::SUCCESS;
        }

        foreach ($triggers as $trigger) {
            $this->routeOne($router, $trigger['category'], $trigger['level'], $count, $dryRun);
        }

        return self::SUCCESS;
    }

    private function routeOne(
        OpsExternalAlertRouter $router,
        string $category,
        string $level,
        ?int $failedJobs,
        bool $dryRun,
    ): int {
        $subject = "Health {$level} ({$category})";
        $body = "JetPakistan operational health alert\n"
            ."category={$category}\n"
            ."level={$level}\n"
            .'failed_jobs='.($failedJobs === null ? 'n/a' : (string) $failedJobs)."\n"
            .'generated_at='.now()->utc()->toIso8601String()."\n";

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

    private function resolveFailedJobsCount(bool $probe): ?int
    {
        if (! $probe) {
            return null;
        }

        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return -1;
        }
    }
}
