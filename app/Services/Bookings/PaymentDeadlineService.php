<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Server-authoritative payment deadline for unpaid standard flight bookings.
 *
 * effective_deadline = min(
 *   submitted_at + business payment window,
 *   earliest_supplier_deadline − safety_buffer
 * ) when a supplier deadline exists; otherwise the business window alone.
 */
class PaymentDeadlineService
{
    public function businessWindowMinutes(): int
    {
        return max(1, (int) config('ota.payment_window_minutes', 120));
    }

    public function safetyBufferMinutes(): int
    {
        return max(0, (int) config('ota.payment_deadline_safety_buffer_minutes', 15));
    }

    /**
     * Earliest supplier-side payment/PNR deadline, if any.
     */
    public function resolveSupplierDeadline(Booking $booking): ?CarbonInterface
    {
        $candidates = [];

        foreach ([
            $booking->payment_required_by,
            $booking->price_guarantee_expires_at,
            $booking->pnr_expires_at,
        ] as $value) {
            if ($value instanceof CarbonInterface) {
                $candidates[] = $value->copy();
            }
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        foreach (['supplier_pnr_expires_at', 'pnr_expires_at', 'ticketing_time_limit', 'payment_time_limit'] as $key) {
            $raw = $meta[$key] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }
            try {
                $candidates[] = Carbon::parse($raw);
            } catch (\Throwable) {
                // Ignore unparseable meta timestamps.
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (CarbonInterface $a, CarbonInterface $b): int => $a <=> $b);

        return $candidates[0];
    }

    public function computeEffectiveDeadline(Booking $booking, ?CarbonInterface $from = null): CarbonInterface
    {
        $anchor = $from
            ?? ($booking->submitted_at instanceof CarbonInterface ? $booking->submitted_at->copy() : null)
            ?? now();

        $businessDeadline = $anchor->copy()->addMinutes($this->businessWindowMinutes());
        $supplierDeadline = $this->resolveSupplierDeadline($booking);

        if ($supplierDeadline === null) {
            return $businessDeadline;
        }

        $bufferedSupplier = $supplierDeadline->copy()->subMinutes($this->safetyBufferMinutes());

        // Never produce a deadline in the past relative to the anchor solely due to buffer;
        // keep at least one minute of window when supplier deadline is still ahead of anchor.
        if ($bufferedSupplier->lessThanOrEqualTo($anchor)) {
            if ($supplierDeadline->greaterThan($anchor)) {
                $bufferedSupplier = $anchor->copy()->addMinute();
            } else {
                return $businessDeadline->lessThan($supplierDeadline) ? $businessDeadline : $supplierDeadline->copy();
            }
        }

        return $businessDeadline->lessThan($bufferedSupplier) ? $businessDeadline : $bufferedSupplier;
    }

    /**
     * Persist payment_due_at when missing or when force-refreshing after submit.
     */
    public function applyDeadline(Booking $booking, bool $force = false): Booking
    {
        if (! $force && $booking->payment_due_at !== null) {
            return $booking;
        }

        $deadline = $this->computeEffectiveDeadline($booking);
        $booking->payment_due_at = $deadline;
        $booking->save();

        return $booking->fresh();
    }
}
