<?php

namespace App\Console\Commands;

use App\Services\Marketing\AbandonedFlightSearchProcessor;
use Illuminate\Console\Command;

class OtaProcessAbandonedFlightSearchesCommand extends Command
{
    protected $signature = 'ota:process-abandoned-flight-searches
                            {--batch= : Override batch size (default from config)}';

    protected $description = 'Evaluate pending abandoned flight search snapshots (ready/skipped/expired; no email send)';

    public function handle(AbandonedFlightSearchProcessor $processor): int
    {
        if (! (bool) config('ota.abandoned_search_followup.enabled', true)) {
            $this->warn('Abandoned search follow-up is disabled (ota.abandoned_search_followup.enabled).');

            return self::SUCCESS;
        }

        $batch = $this->option('batch');
        $batchSize = $batch !== null && $batch !== ''
            ? max(1, (int) $batch)
            : null;

        $stats = $processor->process($batchSize);

        $this->info(sprintf(
            'Abandoned flight searches: checked=%d ready=%d skipped=%d expired=%d errors=%d',
            $stats['checked'],
            $stats['ready'],
            $stats['skipped'],
            $stats['expired'],
            $stats['errors'],
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
