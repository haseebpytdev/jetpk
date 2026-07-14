<?php

namespace App\Support\Sabre;

use App\Support\Bookings\SabreHostRejectionFingerprint;

/**
 * Compare failed vs refreshed offer snapshots for meaningful retry decisions.
 */
final class SabreHostSellReshopComparator
{
    /**
     * @param  array<string, mixed>  $failedSnapshot
     * @param  array<string, mixed>  $refreshedSnapshot
     * @return array<string, mixed>
     */
    public static function compare(array $failedSnapshot, array $refreshedSnapshot): array
    {
        $failed = SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($failedSnapshot);
        $fresh = SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($refreshedSnapshot);

        $sameItinerary = self::segmentFingerprintsEqual(
            $failed['segment_fingerprints'] ?? [],
            $fresh['segment_fingerprints'] ?? [],
        );
        $sameBookingClass = self::stringListsEqual(
            $failed['booking_classes_by_segment'] ?? [],
            $fresh['booking_classes_by_segment'] ?? [],
        );
        $sameFareBasis = self::stringListsEqual(
            $failed['fare_basis_codes_by_segment'] ?? [],
            $fresh['fare_basis_codes_by_segment'] ?? [],
        );
        $sameBrand = self::brandCode($failedSnapshot) === self::brandCode($refreshedSnapshot);
        $priceChanged = self::priceChanged($failedSnapshot, $refreshedSnapshot);
        $carrierOrFlightChanged = ! self::segmentFingerprintsEqual(
            $failed['segment_fingerprints'] ?? [],
            $fresh['segment_fingerprints'] ?? [],
        );

        $retryMeaningful = ! $sameItinerary
            || ! $sameBookingClass
            || ! $sameFareBasis
            || $priceChanged
            || $carrierOrFlightChanged;

        return [
            'same_itinerary' => $sameItinerary,
            'same_fare_class' => $sameBookingClass,
            'same_brand' => $sameBrand,
            'changed_itinerary' => ! $sameItinerary,
            'changed_booking_class' => ! $sameBookingClass,
            'changed_fare_basis' => ! $sameFareBasis,
            'changed_price' => $priceChanged,
            'changed_carrier_or_flight' => $carrierOrFlightChanged,
            'retry_meaningful' => $retryMeaningful,
            'reshop_recommended' => ! $sameItinerary || ! $sameBookingClass || ! $sameFareBasis,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected static function brandCode(array $snapshot): string
    {
        return strtoupper(trim((string) (
            $snapshot['brand_code']
            ?? data_get($snapshot, 'raw_payload.sabre_booking_context.brand_code')
            ?? ''
        )));
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    protected static function priceChanged(array $a, array $b): bool
    {
        $amountA = self::normalizeAmount($a['total_amount'] ?? $a['price'] ?? null);
        $amountB = self::normalizeAmount($b['total_amount'] ?? $b['price'] ?? null);
        if ($amountA === null || $amountB === null) {
            return false;
        }

        return abs($amountA - $amountB) > 0.009;
    }

    protected static function normalizeAmount(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    protected static function stringListsEqual(array $a, array $b): bool
    {
        $normA = array_map(static fn ($v) => strtoupper(trim((string) $v)), $a);
        $normB = array_map(static fn ($v) => strtoupper(trim((string) $v)), $b);

        return $normA === $normB;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    protected static function segmentFingerprintsEqual(array $a, array $b): bool
    {
        return self::stringListsEqual($a, $b);
    }
}
