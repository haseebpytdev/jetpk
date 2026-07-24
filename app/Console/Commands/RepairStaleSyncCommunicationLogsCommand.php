<?php

namespace App\Console\Commands;

use App\Models\CommunicationLog;
use App\Services\Communication\StaleSynchronousCommunicationLogRepairService;
use Illuminate\Console\Command;

class RepairStaleSyncCommunicationLogsCommand extends Command
{
    protected $signature = 'ota:repair-stale-sync-communication-logs
        {--booking-id= : Limit repair assessment to a booking id}
        {--log-id= : Repair a specific communication log id}
        {--apply : Apply repairs (default is dry-run)}';

    protected $description = 'Repair stale queued customer communication logs after synchronous mail delivery';

    public function handle(StaleSynchronousCommunicationLogRepairService $repairService): int
    {
        if ((string) config('queue.default') !== 'sync') {
            $this->error('QUEUE_CONNECTION is not sync; refusing to repair stale synchronous mail logs.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $logId = $this->option('log-id');
        $bookingId = $this->option('booking-id');

        if ($logId !== null) {
            $log = CommunicationLog::query()->find((int) $logId);
            if ($log === null) {
                $this->error('Communication log #'.$logId.' was not found.');

                return self::FAILURE;
            }

            return $this->reportOutcome($repairService->repair($log, $apply));
        }

        $query = CommunicationLog::query()
            ->where('channel', 'email')
            ->where('status', 'queued')
            ->where('meta->recipient_type', 'customer')
            ->orderBy('id');

        if ($bookingId !== null) {
            $query->where('booking_id', (int) $bookingId);
        }

        $logs = $query->get();
        if ($logs->isEmpty()) {
            $this->info('No eligible stale synchronous communication logs found.');

            return self::SUCCESS;
        }

        $counts = [
            'repaired' => 0,
            'needs_manual_review' => 0,
            'not_eligible' => 0,
            'skipped' => 0,
        ];

        foreach ($logs as $log) {
            $result = $repairService->repair($log, $apply);
            $outcome = (string) ($result['outcome'] ?? 'skipped');
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            $this->line(sprintf(
                '#%d booking=%s event=%s => %s (%s)',
                $log->id,
                $log->booking_id ?? 'n/a',
                $log->event,
                $outcome,
                $result['message'] ?? ''
            ));
        }

        $mode = $apply ? 'apply' : 'dry-run';
        $this->newLine();
        $this->info(sprintf(
            'Completed %s: repaired=%d needs_manual_review=%d not_eligible=%d skipped=%d',
            $mode,
            $counts['repaired'],
            $counts['needs_manual_review'],
            $counts['not_eligible'],
            $counts['skipped'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array{outcome: string, message: string}  $result
     */
    protected function reportOutcome(array $result): int
    {
        $this->info(($result['outcome'] ?? 'skipped').': '.($result['message'] ?? ''));

        return match ($result['outcome'] ?? '') {
            'repaired', 'needs_manual_review', 'skipped' => self::SUCCESS,
            default => self::FAILURE,
        };
    }
}
