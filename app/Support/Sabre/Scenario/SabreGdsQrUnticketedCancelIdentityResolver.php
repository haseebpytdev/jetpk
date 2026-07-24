<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Support\Bookings\SupplierBookingAttemptGuard;

/**
 * Resolves QR unticketed cancellation identity from booking + supplier booking rows (no raw locator output).
 */
final class SabreGdsQrUnticketedCancelIdentityResolver
{
    /** @var list<string> */
    public const DENY_LOCATORS = SabreGdsQrUnticketedBookAndRetrieveLifecycle::DENY_LOCATORS;

    public function __construct(
        private readonly SabreGdsCancelReadiness $cancelReadiness,
        private readonly SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Booking $booking, ?int $expectedSupplierBookingId = null): array
    {
        $booking->loadMissing(['tickets', 'supplierBookings', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        $bookingPnr = strtoupper(trim((string) ($booking->pnr ?? '')));
        $bookingSupplierRef = strtoupper(trim((string) ($booking->supplier_reference ?? '')));
        $supplierBooking = $this->resolveSupplierBooking($booking, $expectedSupplierBookingId);
        $supplierBookingId = $supplierBooking?->id;
        $supplierPnr = strtoupper(trim((string) ($supplierBooking?->pnr ?? $supplierBooking?->supplier_reference ?? '')));

        $pnrPresent = $bookingPnr !== '';
        $supplierReferencePresent = $bookingSupplierRef !== '' || $supplierPnr !== '';
        $supplierPnrPresent = $supplierPnr !== '';
        $pnrMatches = $pnrPresent && $supplierPnrPresent && hash_equals($bookingPnr, $supplierPnr);
        if ($pnrMatches && $bookingSupplierRef !== '' && $supplierPnr !== '' && $bookingSupplierRef !== $supplierPnr) {
            $pnrMatches = hash_equals($bookingSupplierRef, $supplierPnr);
        }

        $locator = $pnrPresent ? $bookingPnr : $supplierPnr;
        $locatorSha256 = $locator !== '' ? hash('sha256', $locator) : null;
        $denylisted = $locator !== '' && in_array($locator, array_map('strtoupper', self::DENY_LOCATORS), true);

        $ticketNumberCount = $booking->tickets()->count();
        $readiness = $this->cancelReadiness->evaluate($booking);
        $unticketed = ($readiness['ticketed'] ?? false) !== true && $ticketNumberCount === 0;

        $cancelLock = $this->hasCancellationLock($booking);
        $ambiguousLock = $this->hasAmbiguousCancellationLock($booking);

        $blockers = [];
        if ($provider !== SupplierProvider::Sabre->value) {
            $blockers[] = 'provider_not_sabre';
        }
        if (! $pnrPresent) {
            $blockers[] = 'booking_pnr_missing';
        }
        if (! $supplierPnrPresent) {
            $blockers[] = 'supplier_pnr_missing';
        }
        if (! $pnrMatches) {
            $blockers[] = 'booking_supplier_pnr_mismatch';
        }
        if (! $supplierReferencePresent) {
            $blockers[] = 'supplier_reference_missing';
        }
        if ($expectedSupplierBookingId !== null && $supplierBookingId !== $expectedSupplierBookingId) {
            $blockers[] = 'supplier_booking_id_mismatch';
        }
        if ($ticketNumberCount > 0) {
            $blockers[] = 'ticket_numbers_present';
        }
        if (($readiness['ticketed'] ?? false) === true) {
            $blockers[] = 'booking_ticketed';
        }
        if (($readiness['cancelled'] ?? false) === true) {
            $blockers[] = 'booking_already_cancelled';
        }
        if ($this->supplierBookingCancelled($supplierBooking)) {
            $blockers[] = 'supplier_booking_already_cancelled';
        }
        if ($denylisted) {
            $blockers[] = 'locator_denylisted';
        }
        if ($cancelLock) {
            $blockers[] = 'cancellation_in_progress_lock';
        }
        if ($ambiguousLock) {
            $blockers[] = 'cancellation_ambiguous_lock';
        }
        if (! $unticketed) {
            $blockers[] = 'not_unticketed';
        }

        return [
            'booking_id' => $booking->id,
            'supplier_booking_id' => $supplierBookingId,
            'provider' => $provider,
            'locator_present' => $locator !== '',
            'locator_matches' => $pnrMatches,
            'locator_denylisted' => $denylisted,
            'locator_sha256' => $locatorSha256,
            'booking_pnr_present' => $pnrPresent,
            'supplier_pnr_present' => $supplierPnrPresent,
            'supplier_reference_present' => $supplierReferencePresent,
            'ticket_number_count' => $ticketNumberCount,
            'unticketed' => $unticketed,
            'identity_checks_passed' => $blockers === [],
            'identity_blockers' => $blockers,
            'cancel_readiness' => [
                'cancelled' => ($readiness['cancelled'] ?? false) === true,
                'in_progress' => ($readiness['in_progress'] ?? false) === true,
                'ticketed' => ($readiness['ticketed'] ?? false) === true,
            ],
        ];
    }

    protected function resolveSupplierBooking(Booking $booking, ?int $expectedId): ?SupplierBooking
    {
        $query = SupplierBooking::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('id');
        if ($expectedId !== null) {
            $query->where('id', $expectedId);
        }

        return $query->first();
    }

    protected function supplierBookingCancelled(?SupplierBooking $supplierBooking): bool
    {
        if ($supplierBooking === null) {
            return false;
        }
        $status = strtolower(trim((string) ($supplierBooking->status ?? '')));

        return in_array($status, ['cancelled', 'released', 'void'], true);
    }

    protected function hasCancellationLock(Booking $booking): bool
    {
        $active = $this->attemptGuard->resolveActiveAttempt($booking, SupplierProvider::Sabre->value, 'cancel_pnr');

        return $active instanceof SupplierBookingAttempt;
    }

    protected function hasAmbiguousCancellationLock(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $qrCancel = is_array($meta['qr_unticketed_cancel_lifecycle'] ?? null) ? $meta['qr_unticketed_cancel_lifecycle'] : [];

        return ($qrCancel['cancellation_outcome_state'] ?? '') === 'cancellation_ambiguous'
            || ($qrCancel['final_lifecycle_state'] ?? '') === 'reconciliation_required';
    }
}
