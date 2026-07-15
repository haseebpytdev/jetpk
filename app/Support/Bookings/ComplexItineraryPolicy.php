<?php

namespace App\Support\Bookings;

use App\Models\Booking;

/**
 * R5: Return/multi-city and other complex itineraries defer live Sabre PNR until certified.
 */
class ComplexItineraryPolicy
{
    public const DEFER_REASON = 'complex_itinerary_not_certified';

    public const ERROR_CODE = 'sabre_complex_itinerary_pnr_deferred';

    public static function isComplex(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return self::isComplexFromMeta($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function isComplexFromMeta(array $meta): bool
    {
        if ((bool) ($meta['complex_itinerary_requires_staff_confirmation'] ?? false)) {
            return true;
        }

        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $tripType = strtolower(trim((string) ($criteria['trip_type'] ?? '')));
        if (in_array($tripType, ['round_trip', 'multi_city'], true)) {
            return true;
        }

        $criteriaSegments = $criteria['segments'] ?? null;
        if (is_array($criteriaSegments) && $criteriaSegments !== []) {
            return true;
        }

        return self::journeyGroupCountFromMeta($meta) > 1;
    }

    public static function complexItineraryPnrEnabled(): bool
    {
        return (bool) config('suppliers.sabre.complex_itinerary_pnr_enabled', false);
    }

    /**
     * Public checkout always defers complex Sabre PNR; admin defers unless config allows explicit trials.
     */
    public static function shouldDeferSabrePnr(Booking $booking, bool $isPublicCheckout): bool
    {
        if (! self::isComplex($booking)) {
            return false;
        }

        if ($isPublicCheckout) {
            return true;
        }

        return ! self::complexItineraryPnrEnabled();
    }

    public static function adminDeferMessage(): string
    {
        return 'Supplier PNR deferred — return/multi-city itinerary requires staff confirmation.';
    }

    public static function publicCheckoutNotice(): string
    {
        return 'Your booking request has been received. This itinerary requires staff confirmation before airline hold/PNR.';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected static function journeyGroupCountFromMeta(array $meta): int
    {
        foreach (['validated_offer_snapshot', 'normalized_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $offer = $meta[$key] ?? null;
            if (! is_array($offer)) {
                continue;
            }
            $journeys = $offer['journeys_display'] ?? null;
            if (is_array($journeys) && $journeys !== []) {
                return count($journeys);
            }
        }

        $journeys = $meta['journeys_display'] ?? null;

        return is_array($journeys) ? count($journeys) : 0;
    }
}
