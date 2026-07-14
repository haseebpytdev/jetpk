<?php

namespace App\Support\Bookings;

/**
 * Resolve a short, stable supplier_offer_id for booking_hold_sessions scalar storage.
 * Full provider references (e.g. PIA NDC raw_reference / offer_ref_id) belong in validated_offer_snapshot.
 */
final class BookingHoldSessionSupplierOfferIdResolver
{
    private const LEGACY_VARCHAR_LIMIT = 255;

    /**
     * @param  array<string, mixed>  $normalizedOffer
     */
    public static function resolve(array $normalizedOffer, string $fallbackOfferId = ''): ?string
    {
        foreach (['supplier_offer_id', 'offer_id', 'id'] as $key) {
            $value = trim((string) ($normalizedOffer[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $rawReference = trim((string) ($normalizedOffer['raw_reference'] ?? ''));
        if ($rawReference !== '' && strlen($rawReference) <= self::LEGACY_VARCHAR_LIMIT) {
            return $rawReference;
        }

        $fallback = trim($fallbackOfferId);

        return $fallback !== '' ? $fallback : null;
    }
}
