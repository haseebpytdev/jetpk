<?php

namespace App\Console\Commands;

use App\Services\Bookings\UnpaidBookingExpiryService;
use Illuminate\Console\Command;

class ExpireUnpaidBookingsCommand extends Command
{
    protected $signature = 'ota:expire-unpaid-bookings {--limit= : Max bookings to process}';

    protected $description = 'Expire unpaid standard flight bookings past their payment_due_at (idempotent; paid bookings never expire)';

    public function handle(UnpaidBookingExpiryService $expiryService): int
    {
        $limit = $this->option('limit');
        $result = $expiryService->processDueBatch($limit !== null ? (int) $limit : null);

        if ($result['expired'] > 0 || $result['scanned'] > 0) {
            $this->info(sprintf(
                'Unpaid expiry: scanned=%d expired=%d skipped=%d',
                $result['scanned'],
                $result['expired'],
                $result['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
