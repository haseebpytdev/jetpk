<?php

namespace App\Support\Ops;

/**
 * Decides which opt-in ops alerts to fire from disk + failed_jobs aggregates.
 * Never retries jobs or includes sensitive payloads.
 */
final class OpsHealthAlertTriggers
{
    /**
     * @return list<array{category: string, level: string}>
     */
    public static function forDiskAndFailedJobs(string $diskLevel, int $failedJobs): array
    {
        $alerts = [];
        $level = strtoupper($diskLevel);

        if (in_array($level, ['HIGH', 'CRITICAL'], true)) {
            $alerts[] = [
                'category' => OpsExternalAlertRouter::LEVEL_DISK,
                'level' => $level,
            ];
        }

        // Independent of disk: any failed_jobs backlog must surface as queue_failure.
        if ($failedJobs > 0) {
            $alerts[] = [
                'category' => OpsExternalAlertRouter::LEVEL_QUEUE,
                'level' => $failedJobs >= 10 ? 'HIGH' : 'WARNING',
            ];
        }

        return $alerts;
    }
}
