<?php

namespace App\Support\Bookings;

/**
 * Maps PIA NDC OrderRetrieve normalized rows into booking meta PNR itinerary sidecar (PIA-NDC-OPS1).
 */
final class PiaNdcPnrItinerarySyncMapper
{
    public const SOURCE = 'pia_ndc_order_retrieve';

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public static function applyRetrieveToBookingMeta(array $meta, array $normalized): array
    {
        $segments = is_array($normalized['segments'] ?? null) ? $normalized['segments'] : [];
        $syncedAt = now()->toIso8601String();
        $existingSync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];

        if ($segments === []) {
            $ticketNumbers = is_array($normalized['ticket_numbers'] ?? null) ? $normalized['ticket_numbers'] : [];
            if ($ticketNumbers !== [] || ($normalized['has_blocking_ticket_numbers'] ?? false) === true) {
                $meta['pnr_itinerary_sync'] = array_merge($existingSync, [
                    'status' => 'partial',
                    'source' => self::SOURCE,
                    'attempted_at' => $syncedAt,
                    'reason_code' => 'retrieve_without_segment_rows',
                    'message' => 'Supplier ticket data is present; itinerary segment rows were not mapped from retrieve.',
                ]);
            }

            return $meta;
        }

        $snapshotSegments = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($segment['departure_airport'] ?? $segment['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($segment['arrival_airport'] ?? $segment['destination'] ?? '')));
            $carrier = strtoupper(trim((string) ($segment['carrier'] ?? '')));
            $flightNumber = trim((string) ($segment['flight_number'] ?? ''));
            $snapshotSegments[] = array_filter([
                'origin' => $origin !== '' ? $origin : null,
                'destination' => $destination !== '' ? $destination : null,
                'departure_at' => $segment['departure_at'] ?? null,
                'arrival_at' => $segment['arrival_at'] ?? null,
                'airline_code' => $carrier !== '' ? $carrier : null,
                'flight_number' => $flightNumber !== '' ? $flightNumber : null,
                'segment_status' => 'HK',
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        if ($snapshotSegments === []) {
            $meta['pnr_itinerary_sync'] = array_merge($existingSync, [
                'status' => 'partial',
                'source' => self::SOURCE,
                'attempted_at' => $syncedAt,
                'reason_code' => 'retrieve_segments_unmappable',
                'message' => 'Retrieve returned segments but none could be mapped for admin itinerary display.',
            ]);

            return $meta;
        }

        $meta['pnr_itinerary_snapshot'] = [
            'source' => self::SOURCE,
            'synced_at' => $syncedAt,
            'segments' => $snapshotSegments,
            'order_id' => trim((string) ($normalized['order_id'] ?? '')) ?: null,
            'pnr' => trim((string) ($normalized['pnr'] ?? $normalized['order_id'] ?? '')) ?: null,
        ];
        $meta['pnr_itinerary_sync'] = [
            'status' => 'synced',
            'source' => self::SOURCE,
            'synced_at' => $syncedAt,
            'segment_count' => count($snapshotSegments),
        ];

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function piaNdcSupplierTicketingEvidence(array $meta): bool
    {
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        if (($context['has_blocking_ticket_numbers'] ?? false) === true) {
            return true;
        }

        $ticketNumbers = is_array($context['ticket_numbers'] ?? null) ? $context['ticket_numbers'] : [];
        if ($ticketNumbers !== []) {
            return true;
        }

        $ticketingMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_TICKETING] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_TICKETING]
            : [];

        return strtolower(trim((string) ($ticketingMeta['status'] ?? ''))) === 'success'
            || strtolower(trim((string) ($context['ticketing_status'] ?? ''))) === 'ticketed';
    }
}
