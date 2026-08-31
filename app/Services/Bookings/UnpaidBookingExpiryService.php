<?php

namespace App\Services\Bookings;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotent unpaid booking expiry with final payment-state recheck under row lock.
 *
 * Does NOT call live supplier cancel unless ota.unpaid_booking_expiry.supplier_cancel_enabled
 * is explicitly true (default false). Paid / ticketed bookings are never expired.
 */
class UnpaidBookingExpiryService
{
    public function __construct(
        protected BookingService $bookingService,
        protected PaymentDeadlineService $paymentDeadlineService,
    ) {}

    /**
     * @return list<BookingStatus>
     */
    public function eligibleStatuses(): array
    {
        return [
            BookingStatus::Pending,
            BookingStatus::Confirmed,
            BookingStatus::PaymentPending,
            BookingStatus::FareReview,
        ];
    }

    public function isPaidBarrier(Booking $booking): bool
    {
        if (in_array((string) ($booking->payment_status ?? ''), ['paid'], true)) {
            return true;
        }

        if (in_array($booking->status, [
            BookingStatus::Paid,
            BookingStatus::TicketingPending,
            BookingStatus::Ticketed,
            BookingStatus::Refunded,
            BookingStatus::Cancelled,
            BookingStatus::Expired,
        ], true)) {
            return true;
        }

        return $booking->payments()
            ->where('status', BookingPaymentStatus::Verified)
            ->exists();
    }

    public function isDueForExpiry(Booking $booking): bool
    {
        if ($booking->payment_due_at === null) {
            return false;
        }

        if ($booking->payment_due_at->isFuture()) {
            return false;
        }

        if (! in_array($booking->status, $this->eligibleStatuses(), true)) {
            return false;
        }

        return ! $this->isPaidBarrier($booking);
    }

    /**
     * @return array{expired: bool, reason: string, supplier_cancel_attempted: bool}
     */
    public function expireIfDue(Booking $booking): array
    {
        $result = [
            'expired' => false,
            'reason' => 'not_due',
            'supplier_cancel_attempted' => false,
        ];

        try {
            $outcome = DB::transaction(function () use ($booking, &$result): array {
                /** @var Booking|null $locked */
                $locked = Booking::query()->lockForUpdate()->find($booking->id);
                if ($locked === null) {
                    return ['expired' => false, 'reason' => 'missing', 'supplier_cancel_attempted' => false];
                }

                $locked->loadMissing('payments');

                if ($locked->status === BookingStatus::Expired) {
                    return ['expired' => false, 'reason' => 'already_expired', 'supplier_cancel_attempted' => false];
                }

                if ($this->isPaidBarrier($locked)) {
                    return ['expired' => false, 'reason' => 'paid_barrier', 'supplier_cancel_attempted' => false];
                }

                if ($locked->payment_due_at === null) {
                    return ['expired' => false, 'reason' => 'no_deadline', 'supplier_cancel_attempted' => false];
                }

                // Final authoritative recheck immediately before mutate.
                if ($locked->payment_due_at->isFuture()) {
                    return ['expired' => false, 'reason' => 'deadline_not_reached', 'supplier_cancel_attempted' => false];
                }

                if (! in_array($locked->status, $this->eligibleStatuses(), true)) {
                    return ['expired' => false, 'reason' => 'status_ineligible', 'supplier_cancel_attempted' => false];
                }

                $meta = is_array($locked->meta) ? $locked->meta : [];
                $meta['expiry'] = [
                    'source' => 'unpaid_payment_deadline',
                    'expired_at' => now()->toIso8601String(),
                    'payment_due_at' => $locked->payment_due_at?->toIso8601String(),
                    'payment_status_at_expiry' => (string) ($locked->payment_status ?? ''),
                    'supplier_pnr_present' => trim((string) ($locked->pnr ?? '')) !== '',
                    'supplier_cancel_enabled' => (bool) config('ota.unpaid_booking_expiry.supplier_cancel_enabled', false),
                ];
                $locked->meta = $meta;
                $locked->save();

                    $this->bookingService->changeStatus(
                    $locked,
                    BookingStatus::Expired,
                    null,
                    'Unpaid payment deadline expired',
                    ['source' => 'ota:expire-unpaid-bookings'],
                );

                return ['expired' => true, 'reason' => 'expired', 'supplier_cancel_attempted' => false];
            });
        } catch (Throwable $e) {
            Log::warning('booking.unpaid_expiry_failed', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);

            return ['expired' => false, 'reason' => 'error:'.$e->getMessage(), 'supplier_cancel_attempted' => false];
        }

        // Communication is owned by BookingService::changeStatus → sendBookingExpired.

        return $outcome;
    }

    /**
     * @return array{scanned: int, expired: int, skipped: int}
     */
    public function processDueBatch(?int $limit = null): array
    {
        if (! (bool) config('ota.unpaid_booking_expiry.enabled', true)) {
            return ['scanned' => 0, 'expired' => 0, 'skipped' => 0];
        }

        $limit = $limit ?? max(1, (int) config('ota.unpaid_booking_expiry.batch_size', 50));
        $scanned = 0;
        $expired = 0;
        $skipped = 0;

        Booking::query()
            ->whereIn('status', array_map(static fn (BookingStatus $s): string => $s->value, $this->eligibleStatuses()))
            ->where(function ($q): void {
                $q->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', ['paid']);
            })
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Booking $booking) use (&$scanned, &$expired, &$skipped): void {
                $scanned++;
                $result = $this->expireIfDue($booking);
                if ($result['expired']) {
                    $expired++;
                } else {
                    $skipped++;
                }
            });

        return compact('scanned', 'expired', 'skipped');
    }
}
