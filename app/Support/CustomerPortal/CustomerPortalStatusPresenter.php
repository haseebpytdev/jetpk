<?php

namespace App\Support\CustomerPortal;

use App\Models\Booking;
use App\Support\Bookings\PaymentOperationalStatus;
use App\Support\Bookings\SupplierOperationalStatus;
use App\Support\Bookings\TicketingOperationalStatus;
use App\Support\Payments\BookingPayableResolver;

/**
 * Portal-safe status labels for customer dashboard JSON.
 */
class CustomerPortalStatusPresenter
{
    /**
     * @return array{code: string, label: string, terminal?: bool}
     */
    public static function bookingStatus(Booking $booking): array
    {
        $status = (string) ($booking->status?->value ?? $booking->status ?? 'unknown');
        $label = str_replace('_', ' ', ucfirst($status));

        return [
            'code' => $status,
            'label' => $label,
            'terminal' => in_array($status, ['cancelled', 'failed', 'confirmed', 'ticketed'], true),
        ];
    }

    /**
     * @return array{code: string, label: string, terminal?: bool}
     */
    public static function paymentStatus(Booking $booking): array
    {
        $operational = PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
        $code = (string) ($operational['code'] ?? 'unpaid');

        return [
            'code' => $code,
            'label' => (string) ($operational['label'] ?? $code),
            'terminal' => in_array($code, ['paid', 'refunded', 'rejected'], true),
        ];
    }

    /**
     * @return array{code: string, label: string, terminal?: bool}
     */
    public static function ticketingStatus(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasPnr = filled($booking->pnr)
            || ($booking->relationLoaded('supplierBookings')
                ? $booking->supplierBookings->contains(fn ($sb) => filled($sb->pnr))
                : false);
        $provider = (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? ''));

        $operational = TicketingOperationalStatus::fromValues(
            (string) ($booking->ticketing_status ?? 'not_started'),
            (string) ($booking->payment_status ?? 'unpaid'),
            $hasPnr,
            $booking->relationLoaded('tickets') ? $booking->tickets->isNotEmpty() : false,
            $provider,
            (string) ($booking->cancellation_status ?? ''),
        );

        return [
            'code' => (string) ($operational['code'] ?? 'not_started'),
            'label' => (string) ($operational['label'] ?? 'not_started'),
            'terminal' => in_array((string) ($operational['code'] ?? ''), ['issued', 'voided', 'failed', 'not_supported'], true),
        ];
    }

    /**
     * @return array{code: string, label: string}
     */
    public static function supplierStatus(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasPnr = filled($booking->pnr);
        $provider = (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? ''));
        $operational = SupplierOperationalStatus::fromValues(
            (string) ($booking->supplier_booking_status ?? 'not_started'),
            $provider,
            $hasPnr,
            $meta,
        );

        return [
            'code' => (string) ($operational['code'] ?? 'not_started'),
            'label' => (string) ($operational['label'] ?? 'not_started'),
        ];
    }

    public static function customerPayable(Booking $booking): float
    {
        return BookingPayableResolver::customerPayableTotal($booking);
    }
}
