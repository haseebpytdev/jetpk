<?php

namespace App\Console\Commands;

use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Services\GroupTicketing\GroupReservationService;
use Illuminate\Console\Command;

class ReleaseExpiredGroupBookingsCommand extends Command
{
    protected $signature = 'group-ticketing:release-expired';

    protected $description = 'Release unpaid group bookings past their payment deadline';

    public function handle(GroupReservationService $reservationService): int
    {
        $released = 0;

        GroupBooking::query()
            ->whereIn('status', [
                GroupBookingStatus::ReservedAwaitingPayment,
                GroupBookingStatus::PaymentPending,
            ])
            ->whereNull('payment_submitted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($bookings) use ($reservationService, &$released): void {
                foreach ($bookings as $booking) {
                    $before = $booking->status;
                    $result = $reservationService->releaseUnpaidBooking($booking, 'unpaid_timeout');
                    if ($result->released_at !== null && $before !== $result->status) {
                        $released++;
                    } elseif ($result->released_at !== null) {
                        $released++;
                    }
                }
            });

        if ($released > 0) {
            $this->info('Released '.$released.' expired group booking(s).');
        }

        return self::SUCCESS;
    }
}
