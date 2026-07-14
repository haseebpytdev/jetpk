<?php

namespace App\Support\Bookings;

/**
 * Admin/staff operational label for a booking (list card primary status).
 * Ticketing-pending requires a stored PNR or supplier_reference; failed supplier
 * attempts without either surface retry-oriented failure labels.
 *
 * **B80:** {@code needs_review} attempts (Passenger Records outcomes) use the same
 * non-success label path as {@code failed} when there is no PNR/reference.
 * {@code sabre_passenger_records_stale_shop_segment} / {@code sabre_booking_application_error}
 * get staff-facing copy; HTTP 429 and “Too Many Requests” in redacted {@code safe_summary}
 * surface busy/retry (no raw payload exposure).
 */
class BookingOperationalStatus
{
    /**
     * @return array{code: string, label: string, meaning: string}
     */
    public static function fromValues(
        string $status,
        ?string $paymentStatus = null,
        ?string $supplierBookingStatus = null,
        ?string $ticketingStatus = null,
        bool $hasPnr = false,
        ?string $cancellationStatus = null,
        ?string $latestSupplierAttemptStatus = null,
        ?string $latestSupplierAttemptErrorCode = null,
        ?int $latestSupplierAttemptHttpStatus = null,
        bool $latestAttemptSafeSummaryIndicatesTooManyRequests = false,
    ): array {
        $status = strtolower(trim($status));
        $paymentStatus = strtolower(trim((string) $paymentStatus));
        $supplierBookingStatus = strtolower(trim((string) $supplierBookingStatus));
        $ticketingStatus = strtolower(trim((string) $ticketingStatus));
        $cancellationStatus = strtolower(trim((string) $cancellationStatus));
        $latestSupplierAttemptStatus = strtolower(trim((string) $latestSupplierAttemptStatus));

        $code = match (true) {
            $status === 'expired' => 'expired',
            $status === 'failed' => 'failed',
            $cancellationStatus === 'requested' => 'cancel_requested',
            $status === 'cancelled' => 'cancelled',
            $status === 'completed' => 'completed',
            in_array($status, ['ticketed'], true) => 'ticketed',
            ! $hasPnr && in_array($latestSupplierAttemptStatus, ['failed', 'needs_review'], true) => self::supplierAttemptOutcomeCode(
                $latestSupplierAttemptErrorCode,
                $latestSupplierAttemptHttpStatus,
                $latestAttemptSafeSummaryIndicatesTooManyRequests,
            ),
            in_array($ticketingStatus, ['pending', 'ticketing_pending'], true) && $hasPnr => 'ticketing_pending',
            $hasPnr => 'pnr_created',
            in_array($supplierBookingStatus, ['created', 'booked'], true) => 'supplier_booked',
            in_array($status, ['paid', 'payment_pending'], true) || in_array($paymentStatus, ['paid', 'partial'], true) => 'supplier_pending',
            $status === 'confirmed' => 'confirmed',
            in_array($status, ['pending', 'fare_review'], true) => 'pending',
            default => 'draft',
        };

        return [
            'code' => $code,
            'label' => self::label($code),
            'meaning' => self::meaning($code),
        ];
    }

    /**
     * True when redacted attempt summary strings suggest rate limiting (substring only; no values echoed to UI from here).
     *
     * @param  array<string, mixed>|null  $safeSummary
     */
    public static function safeSummaryIndicatesTooManyRequests(?array $safeSummary): bool
    {
        if ($safeSummary === null || $safeSummary === []) {
            return false;
        }
        foreach (['message', 'error_message', 'http_message', 'status_text', 'rest_message'] as $key) {
            $v = $safeSummary[$key] ?? null;
            if (is_string($v) && stripos($v, 'Too Many Requests') !== false) {
                return true;
            }
        }

        return false;
    }

    protected static function supplierAttemptOutcomeCode(?string $errorCode, ?int $httpStatus, bool $tooManyRequestsByMessage): string
    {
        if ($httpStatus === 429 || $tooManyRequestsByMessage) {
            return 'sabre_busy_retry';
        }

        $errorCode = strtolower(trim((string) $errorCode));

        if ($errorCode === 'sabre_passenger_records_stale_shop_segment') {
            return 'flight_no_longer_available';
        }

        if ($errorCode === 'sabre_booking_application_error') {
            return 'sabre_application_error';
        }

        if ($errorCode === 'sabre_booking_connection_error'
            || $errorCode === 'sabre_timeout'
            || str_contains($errorCode, 'timeout')) {
            return 'sabre_timeout_retry';
        }

        return 'supplier_booking_failed';
    }

    public static function label(string $code): string
    {
        return match ($code) {
            'supplier_booking_failed' => 'Supplier booking failed',
            'sabre_busy_retry' => 'Sabre busy / retry later',
            'sabre_timeout_retry' => 'Sabre timeout / retry needed',
            'ticketing_pending' => 'Ticketing pending',
            'pnr_created' => 'PNR created',
            'flight_no_longer_available' => 'Flight no longer available — search again',
            'sabre_application_error' => 'Supplier booking failed — staff review',
            default => str_replace('_', ' ', $code),
        };
    }

    public static function meaning(string $code): string
    {
        return match ($code) {
            'draft' => 'Booking request exists but not yet operationally confirmed.',
            'pending' => 'Awaiting payment/admin review.',
            'confirmed' => 'Admin accepted booking request and fare confirmed.',
            'supplier_pending' => 'Ready to create supplier booking/PNR.',
            'supplier_booked' => 'Supplier booking/PNR exists.',
            'pnr_created' => 'PNR or supplier reference stored; ticketing not active/pending.',
            'supplier_booking_failed' => 'PNR not created; supplier booking failed; retry needed.',
            'sabre_busy_retry' => 'Sabre rate-limited (HTTP 429 or equivalent); retry supplier booking later.',
            'sabre_timeout_retry' => 'Sabre connection timed out; retry supplier booking.',
            'flight_no_longer_available' => 'Segment/fare stale or no longer matches shop; customer should search again.',
            'sabre_application_error' => 'Sabre rejected booking; staff review required.',
            'ticketing_pending' => 'PNR exists; ticketing required.',
            'ticketed' => 'Ticket issued.',
            'completed' => 'Ticket/documents sent and no pending action.',
            'cancel_requested' => 'Cancellation requested.',
            'cancelled' => 'Booking cancelled.',
            'failed' => 'Supplier/payment/ticketing failure needing admin action.',
            'expired' => 'Fare/offer expired before booking completion.',
            default => 'Operational status unavailable.',
        };
    }
}
