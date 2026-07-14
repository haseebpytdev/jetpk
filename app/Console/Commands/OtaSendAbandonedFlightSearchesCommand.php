<?php

namespace App\Console\Commands;

use App\Models\FlightSearchMarketingSnapshot;
use App\Services\Marketing\AbandonedFlightSearchEmailSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class OtaSendAbandonedFlightSearchesCommand extends Command
{
    protected $signature = 'ota:send-abandoned-flight-searches
                            {--batch= : Override send batch size (default from config)}';

    protected $description = 'Send abandoned flight search follow-up emails for ready snapshots';

    public function handle(AbandonedFlightSearchEmailSender $sender): int
    {
        if (! (bool) config('ota.abandoned_search_followup.enabled', true)) {
            $this->warn('Abandoned search follow-up is disabled (ota.abandoned_search_followup.enabled).');

            return self::SUCCESS;
        }

        $batch = $this->option('batch');
        $batchSize = $batch !== null && $batch !== ''
            ? max(1, (int) $batch)
            : max(1, (int) config('ota.abandoned_search_followup.send_batch_size', 50));

        $stats = [
            'checked' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $snapshots = FlightSearchMarketingSnapshot::query()
            ->with(['agency.agencySetting'])
            ->where('status', FlightSearchMarketingSnapshot::STATUS_READY)
            ->orderBy('send_after_at')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        foreach ($snapshots as $snapshot) {
            $stats['checked']++;

            try {
                $result = $sender->send($snapshot);
                $stats[$result['outcome']]++;
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::warning('abandoned_flight_search.send_row_unhandled', [
                    'snapshot_id' => $snapshot->id,
                    'exception' => $e::class,
                ]);
                report($e);

                if (! $snapshot->markFailed('send_unhandled: '.$e::class)) {
                    Log::notice('abandoned_flight_search.send_row_not_ready', [
                        'snapshot_id' => $snapshot->id,
                    ]);
                }
            }
        }

        $this->info(sprintf(
            'Abandoned flight search emails: checked=%d sent=%d skipped=%d failed=%d',
            $stats['checked'],
            $stats['sent'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
