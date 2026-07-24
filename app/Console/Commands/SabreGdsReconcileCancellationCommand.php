<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancellationReconciliationService;
use Illuminate\Console\Command;

/**
 * Reconcile local booking state from stored Sabre GDS confirmed-cancellation evidence (no supplier HTTP).
 */
class SabreGdsReconcileCancellationCommand extends Command
{
    protected $signature = 'sabre:gds-reconcile-cancellation
                            {--booking= : Booking ID to reconcile}';

    protected $description = 'Reconcile booking cancellation state from stored Sabre GDS evidence (no live supplier calls)';

    public function handle(SabreGdsCancellationReconciliationService $service): int
    {
        $bookingId = $this->option('booking');
        if ($bookingId === null || ! is_numeric($bookingId)) {
            $this->error('--booking is required.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $result = $service->reconcileFromStoredEvidence($booking, [
            'source' => 'sabre_gds_reconcile_cancellation_command',
        ]);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($result['success'] ?? false) === true ? self::SUCCESS : self::FAILURE;
    }
}
