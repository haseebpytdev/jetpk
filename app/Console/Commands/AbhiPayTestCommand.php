<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Payments\PaymentTransactionService;
use Illuminate\Console\Command;

class AbhiPayTestCommand extends Command
{
    protected $signature = 'payments:abhipay-test {--booking=}';

    protected $description = 'Create a test AbhiPay order for a booking (uses configured test/live credentials).';

    public function handle(PaymentTransactionService $paymentTransactionService): int
    {
        $bookingId = $this->option('booking');
        if (! filled($bookingId)) {
            $this->error('Provide --booking={id}');

            return self::FAILURE;
        }

        $booking = Booking::query()->with('fareBreakdown')->find($bookingId);
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        if (! $paymentTransactionService->isAbhiPayAvailableForBooking($booking)) {
            $this->error('AbhiPay is not active/configured for this booking agency.');

            return self::FAILURE;
        }

        try {
            $transaction = $paymentTransactionService->createAbhiPayTransaction($booking, null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('AbhiPay order created.');
        $this->line('client_transaction_id: '.$transaction->client_transaction_id);
        $this->line('gateway_order_id: '.($transaction->gateway_order_id ?? 'n/a'));
        $this->line('payment_url: '.($transaction->gateway_payment_url ?? 'n/a'));

        return self::SUCCESS;
    }
}
