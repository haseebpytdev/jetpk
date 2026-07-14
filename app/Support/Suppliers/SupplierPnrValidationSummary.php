<?php

namespace App\Support\Suppliers;

use App\Models\Booking;
use App\Support\Bookings\SabreGdsAutoPnrLifecycleService;
use App\Support\Bookings\SabreGdsPnrCancellationStateResolver;
use App\Support\Bookings\SupplierLifecycleContextResolver;

/**
 * Safe PNR/order validation summary after supplier create (no secrets / raw payload).
 */
final class SupplierPnrValidationSummary
{
    /**
     * @return array<string, mixed>
     */
    public function build(Booking $booking): array
    {
        $booking->loadMissing(['tickets', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));
        $pnrCreated = $pnr !== '';
        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $retrieveAttempted = ($sync['attempted'] ?? false) === true
            || ($sync['last_attempt_at'] ?? null) !== null
            || ($sync['status'] ?? '') !== '';
        $retrieveStatus = match (true) {
            ($sync['status'] ?? '') === 'synced' || ($sync['synced'] ?? false) === true => 'success',
            ($sync['status'] ?? '') === 'partial' => 'partial',
            $retrieveAttempted => 'failed',
            default => 'not_attempted',
        };
        $carrierLocator = trim((string) ($sync['airline_locator_value'] ?? $meta['airline_locator'] ?? ''));
        $ticketed = $booking->tickets->isNotEmpty()
            || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'partially_ticketed'], true);
        $cancelled = app(SabreGdsPnrCancellationStateResolver::class)->isPnrCancelledOrReleased($booking, $meta);

        $lifecycle = app(SupplierLifecycleContextResolver::class)->resolve($booking);
        if ($lifecycle['handler_key'] === SupplierLifecycleContextResolver::HANDLER_SABRE_GDS) {
            $adminLifecycle = app(SabreGdsAutoPnrLifecycleService::class)->resolveForAdmin($booking);
            if (($adminLifecycle['pnr_created'] ?? false) === true) {
                $pnrCreated = true;
            }
        }

        return [
            'pnr_created' => $pnrCreated,
            'locator_present' => $pnrCreated,
            'retrieve_attempted' => $retrieveAttempted,
            'retrieve_status' => $retrieveStatus === 'not_attempted' ? 'failed' : $retrieveStatus,
            'carrier_locator_present' => $carrierLocator !== '',
            'cancellation_eligible' => $pnrCreated && ! $ticketed && ! $cancelled,
            'ticketing_required' => false,
        ];
    }
}
