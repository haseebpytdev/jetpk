<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;

/**
 * Canonical Sabre GDS PNR cancelled/released state from stored booking data only (no HTTP).
 */
final class SabreGdsPnrCancellationStateResolver
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function isPnrCancelledOrReleased(Booking $booking, ?array $meta = null): bool
    {
        if ($booking->status === BookingStatus::Cancelled || $booking->cancelled_at !== null) {
            return true;
        }

        $supplierStatus = strtolower(trim((string) ($booking->supplier_booking_status ?? '')));
        if (in_array($supplierStatus, ['cancelled', 'released', 'pnr_released', 'option_pnr_released'], true)) {
            return true;
        }

        $meta = $meta ?? (is_array($booking->meta) ? $booking->meta : []);

        $cancelMeta = is_array($meta[SabreGdsCancelReadiness::META_KEY] ?? null)
            ? $meta[SabreGdsCancelReadiness::META_KEY]
            : [];
        if (in_array((string) ($cancelMeta['status'] ?? ''), ['cancelled', 'verified', 'released'], true)) {
            return true;
        }
        if (($cancelMeta['supplier_cancel_verified'] ?? false) === true) {
            return true;
        }

        foreach (['sabre_cancel_outcome', 'sabre_cancel'] as $legacyKey) {
            $legacy = is_array($meta[$legacyKey] ?? null) ? $meta[$legacyKey] : [];
            if (($legacy['supplier_cancel_verified'] ?? false) === true) {
                return true;
            }
            if (in_array((string) ($legacy['status'] ?? ''), ['cancelled', 'verified', 'released'], true)) {
                return true;
            }
        }

        return $this->latestSuccessfulCancelAttempt($booking) !== null;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array{source: string, label: string, at: ?string}
     */
    public function evidence(Booking $booking, ?array $meta = null): array
    {
        $meta = $meta ?? (is_array($booking->meta) ? $booking->meta : []);

        if ($booking->status === BookingStatus::Cancelled || $booking->cancelled_at !== null) {
            return [
                'source' => 'booking_status',
                'label' => 'Booking marked cancelled',
                'at' => $booking->cancelled_at?->toIso8601String(),
            ];
        }

        $cancelMeta = is_array($meta[SabreGdsCancelReadiness::META_KEY] ?? null)
            ? $meta[SabreGdsCancelReadiness::META_KEY]
            : [];
        if (in_array((string) ($cancelMeta['status'] ?? ''), ['cancelled', 'verified', 'released'], true)
            || ($cancelMeta['supplier_cancel_verified'] ?? false) === true) {
            return [
                'source' => SabreGdsCancelReadiness::META_KEY,
                'label' => (string) ($cancelMeta['classification'] ?? 'Sabre GDS PNR cancelled'),
                'at' => is_string($cancelMeta['cancelled_at'] ?? null) ? (string) $cancelMeta['cancelled_at'] : null,
            ];
        }

        $attempt = $this->latestSuccessfulCancelAttempt($booking);
        if ($attempt instanceof SupplierBookingAttempt) {
            return [
                'source' => 'supplier_booking_attempt',
                'label' => 'Sabre cancel/release attempt succeeded',
                'at' => ($attempt->completed_at ?? $attempt->attempted_at)?->toIso8601String(),
            ];
        }

        return [
            'source' => '',
            'label' => '',
            'at' => null,
        ];
    }

    public static function releaseConfirmPhrase(Booking $booking): string
    {
        return 'RELEASE-PNR-FOR-BOOKING-'.$booking->id;
    }

    protected function latestSuccessfulCancelAttempt(Booking $booking): ?SupplierBookingAttempt
    {
        $booking->loadMissing('supplierBookingAttempts');

        return $booking->supplierBookingAttempts
            ->filter(function (SupplierBookingAttempt $attempt): bool {
                if (strtolower((string) $attempt->provider) !== SupplierProvider::Sabre->value) {
                    return false;
                }

                $action = strtolower(trim((string) $attempt->action));

                return in_array($action, ['cancel_booking', 'release_pnr', 'cancel_pnr'], true)
                    && strtolower((string) $attempt->status) === 'success';
            })
            ->sortByDesc(fn (SupplierBookingAttempt $attempt) => $attempt->completed_at ?? $attempt->attempted_at ?? $attempt->created_at)
            ->first();
    }
}
