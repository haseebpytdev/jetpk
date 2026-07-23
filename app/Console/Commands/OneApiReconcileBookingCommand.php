<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use Illuminate\Console\Command;

class OneApiReconcileBookingCommand extends Command
{
    protected $signature = 'ota:one-api-reconcile-booking
        {--booking= : Booking ID}
        {--connection= : Supplier connection ID}
        {--dry-run : Summarize only}';

    protected $description = 'Reconcile ambiguous One API booking attempts against supplier read.';

    public function handle(): int
    {
        $bookingId = (int) $this->option('booking');
        if ($bookingId <= 0) {
            $this->error('--booking is required.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        if (strtolower((string) $booking->supplier) !== SupplierProvider::OneApi->value) {
            $this->error('Booking is not One API.');

            return self::FAILURE;
        }

        $attempt = $booking->supplierBookingAttempts()
            ->where('provider', SupplierProvider::OneApi->value)
            ->where('status', 'ambiguous')
            ->latest('id')
            ->first();

        $this->line('ambiguous_attempt='.($attempt?->id ?? 'none'));
        $this->line('pnr='.(string) ($booking->pnr ?? ''));
        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no supplier read issued.');

            return self::SUCCESS;
        }

        $connectionId = (int) $this->option('connection');
        if ($connectionId <= 0) {
            $connectionId = (int) ($booking->supplier_connection_id ?? 0);
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            $this->error('Connection not found for reconcile.');

            return self::FAILURE;
        }

        $this->info('Use ota:one-api-read-reservation with --confirm-live-search when live read is approved.');

        return self::SUCCESS;
    }
}
