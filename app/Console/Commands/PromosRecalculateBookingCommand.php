<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Payments\BookingPaymentService;
use App\Support\Payments\BookingPayableResolver;
use Illuminate\Console\Command;

class PromosRecalculateBookingCommand extends Command
{
    protected $signature = 'promos:recalculate-booking {booking_id : Booking id}';

    protected $description = 'Recalculate booking balance due using promo-adjusted payable totals';

    public function handle(BookingPaymentService $bookingPaymentService): int
    {
        $booking = Booking::query()->with(['fareBreakdown', 'payments'])->findOrFail((int) $this->argument('booking_id'));

        $before = [
            'payable' => BookingPayableResolver::customerPayableTotal($booking),
            'balance' => BookingPayableResolver::balanceDue($booking),
            'promo' => $booking->promo_code,
        ];

        $bookingPaymentService->recalculateBookingPaymentStatus($booking->fresh(['fareBreakdown', 'payments']));

        $booking->refresh();
        $after = [
            'payable' => BookingPayableResolver::customerPayableTotal($booking),
            'balance' => BookingPayableResolver::balanceDue($booking),
            'promo' => $booking->promo_code,
        ];

        $this->table(['', 'Before', 'After'], [
            ['customer_payable', $before['payable'], $after['payable']],
            ['balance_due', $before['balance'], $after['balance']],
            ['promo_code', $before['promo'] ?? '—', $after['promo'] ?? '—'],
        ]);

        if (filled($booking->promo_code)) {
            $this->line('Promo discount: '.number_format((float) $booking->promo_discount_amount, 2));
        }

        return self::SUCCESS;
    }
}
