<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * Canonical Sabre GDS PNR itinerary sync state from stored booking meta only (no HTTP).
 */
final class SabreGdsPnrItinerarySyncResolver
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function isSynced(Booking $booking, ?array $meta = null): bool
    {
        $meta = $meta ?? (is_array($booking->meta) ? $booking->meta : []);
        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];

        if (($sync['synced'] ?? false) === true) {
            return true;
        }

        if (strtolower(trim((string) ($sync['status'] ?? ''))) === 'synced') {
            return true;
        }

        return $this->snapshotBelongsToCurrentPnr($booking, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function snapshotBelongsToCurrentPnr(Booking $booking, array $meta): bool
    {
        $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : [];
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        if ($segments === []) {
            return false;
        }

        $currentPnr = $this->resolveCurrentPnr($booking);
        if ($currentPnr === '') {
            return false;
        }

        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $snapshotPnr = strtoupper(trim((string) ($snapshot['pnr'] ?? ($sync['pnr'] ?? ''))));
        if ($snapshotPnr !== '' && $snapshotPnr !== $currentPnr) {
            return false;
        }

        return true;
    }

    public function resolveCurrentPnr(Booking $booking): string
    {
        $pnr = strtoupper(trim((string) ($booking->pnr ?? '')));
        if ($pnr !== '') {
            return $pnr;
        }

        return strtoupper(trim((string) ($booking->supplier_reference ?? '')));
    }
}
