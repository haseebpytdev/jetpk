<?php

namespace App\Support\Sabre;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabreHostRejectionFingerprint;

/**
 * Deterministic Sabre GDS host sell fingerprint with occurrence tracking in booking meta.
 */
final class SabreHostSellFingerprint
{
    public const FINGERPRINT_VERSION = 'sabre_host_sell_fingerprint_v1';

    /**
     * @param  array<string, mixed>  $diagnostics  {@see SabreHostSellResponseCollector::collect()}
     * @return array<string, mixed>|null
     */
    public static function buildAndRegister(Booking $booking, array $diagnostics): ?array
    {
        $safeReason = strtolower(trim((string) ($diagnostics['safe_reason_code'] ?? '')));
        if ($safeReason === '' || $safeReason === SabreHostSellClassifier::OUTCOME_SELL_CONFIRMED) {
            return null;
        }

        if (! SabreHostSellClassifier::isDefinitiveSameOfferRejection(
            $safeReason,
            is_array($diagnostics['airline_segment_statuses'] ?? null)
                ? (string) ($diagnostics['airline_segment_statuses'][0] ?? '')
                : null,
        ) && $safeReason !== SabreHostSellClassifier::OUTCOME_HOST_NEED_NEED_STATUS) {
            // Still fingerprint NN/HL for diagnostics, but retry guard uses definitive list
        }

        $matchFields = self::extractMatchFields($booking, $diagnostics);
        if ($matchFields['origin'] === '' || $matchFields['destination'] === '') {
            return null;
        }

        $hash = self::computeHash($matchFields);
        $now = now()->toIso8601String();

        $entry = array_merge($matchFields, [
            'fingerprint_version' => self::FINGERPRINT_VERSION,
            'fingerprint_hash' => $hash,
            'host_error_family' => SabreHostSellClassifier::hostErrorFamilyForReason($safeReason),
            'safe_reason_code' => $safeReason,
            'retry_policy' => (string) ($diagnostics['retry_policy'] ?? SabreHostSellClassifier::RETRY_NO_RETRY_SAME_OFFER),
            'segment_status' => is_array($diagnostics['airline_segment_statuses'] ?? null)
                ? ($diagnostics['airline_segment_statuses'][0] ?? null)
                : null,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'occurrence_count' => 1,
            'latest_booking_id' => $booking->id,
            'latest_safe_reason_code' => $safeReason,
            'latest_retry_policy' => (string) ($diagnostics['retry_policy'] ?? ''),
            'recorded_at' => $now,
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $registry = is_array($meta['sabre_host_sell_fingerprint_registry'] ?? null)
            ? $meta['sabre_host_sell_fingerprint_registry']
            : [];

        if (isset($registry[$hash]) && is_array($registry[$hash])) {
            $prior = $registry[$hash];
            $entry['first_seen_at'] = (string) ($prior['first_seen_at'] ?? $now);
            $entry['occurrence_count'] = (int) ($prior['occurrence_count'] ?? 0) + 1;
        }

        $registry[$hash] = $entry;
        $meta['sabre_host_sell_fingerprint_registry'] = $registry;
        $meta['sabre_host_sell_fingerprint_latest'] = $entry;
        $booking->forceFill(['meta' => $meta])->save();

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    public static function findPriorRejectionForOffer(Booking $booking, array $offer, ?string $segmentStatus = null): ?array
    {
        $offerFields = SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($offer);
        if ($offerFields['origin'] === '' || $offerFields['destination'] === '') {
            return null;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $registry = is_array($meta['sabre_host_sell_fingerprint_registry'] ?? null)
            ? $meta['sabre_host_sell_fingerprint_registry']
            : [];

        $candidateHashes = [];
        if ($segmentStatus !== null && $segmentStatus !== '') {
            $candidateHashes[] = self::computeHash(self::mergeOfferFieldsWithStatus($offerFields, $segmentStatus));
        }
        $candidateHashes[] = self::computeHash($offerFields);

        foreach ($candidateHashes as $hash) {
            if (isset($registry[$hash]) && is_array($registry[$hash])) {
                return $registry[$hash];
            }
        }

        $latest = is_array($meta['sabre_host_sell_fingerprint_latest'] ?? null)
            ? $meta['sabre_host_sell_fingerprint_latest']
            : null;
        if (is_array($latest) && self::offerBaseMatches($latest, $offerFields)) {
            return $latest;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $offerFields
     */
    protected static function offerBaseMatches(array $stored, array $offerFields): bool
    {
        $storedBase = [
            'origin' => strtoupper(trim((string) ($stored['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($stored['destination'] ?? ''))),
            'segment_fingerprints' => self::stringList($stored['segment_fingerprints'] ?? []),
            'booking_classes' => self::stringList($stored['booking_classes'] ?? $stored['booking_classes_by_segment'] ?? []),
            'fare_basis_codes' => self::stringList($stored['fare_basis_codes'] ?? $stored['fare_basis_codes_by_segment'] ?? []),
        ];
        $offerBase = [
            'origin' => strtoupper(trim((string) ($offerFields['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($offerFields['destination'] ?? ''))),
            'segment_fingerprints' => self::stringList($offerFields['segment_fingerprints'] ?? []),
            'booking_classes' => self::stringList($offerFields['booking_classes_by_segment'] ?? []),
            'fare_basis_codes' => self::stringList($offerFields['fare_basis_codes_by_segment'] ?? []),
        ];

        return $storedBase === $offerBase;
    }

    /**
     * @param  array<string, mixed>  $matchFields
     */
    public static function computeHash(array $matchFields): string
    {
        $canonical = [
            'origin' => strtoupper(trim((string) ($matchFields['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($matchFields['destination'] ?? ''))),
            'validating_carrier' => strtoupper(trim((string) ($matchFields['validating_carrier'] ?? ''))),
            'segment_fingerprints' => self::stringList($matchFields['segment_fingerprints'] ?? []),
            'booking_classes' => self::stringList($matchFields['booking_classes'] ?? $matchFields['booking_classes_by_segment'] ?? []),
            'fare_basis_codes' => self::stringList($matchFields['fare_basis_codes'] ?? $matchFields['fare_basis_codes_by_segment'] ?? []),
            'brand_code' => strtoupper(trim((string) ($matchFields['brand_code'] ?? ''))),
            'travel_date' => trim((string) ($matchFields['travel_date'] ?? '')),
            'host_error_family' => strtoupper(trim((string) ($matchFields['host_error_family'] ?? ''))),
            'segment_status' => strtoupper(trim((string) ($matchFields['segment_status'] ?? ''))),
        ];
        ksort($canonical);

        return substr(hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    protected static function extractMatchFields(Booking $booking, array $diagnostics): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = [];
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $candidate = $meta[$key] ?? null;
            if (is_array($candidate) && $candidate !== []) {
                $snapshot = $candidate;
                break;
            }
        }

        $base = $snapshot !== []
            ? SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($snapshot)
            : [];

        $route = trim((string) ($diagnostics['route'] ?? ''));
        if ($route !== '' && str_contains($route, '→')) {
            [$origin, $destination] = array_map('trim', explode('→', $route, 2));
            $base['origin'] = strtoupper($origin);
            $base['destination'] = strtoupper($destination);
        }

        $departureDates = is_array($diagnostics['departure_dates'] ?? null) ? $diagnostics['departure_dates'] : [];

        return array_merge($base, [
            'validating_carrier' => $diagnostics['validating_carrier'] ?? $base['validating_carrier'] ?? null,
            'booking_classes' => $diagnostics['booking_classes'] ?? $base['booking_classes_by_segment'] ?? [],
            'fare_basis_codes' => $diagnostics['fare_basis_codes'] ?? $base['fare_basis_codes_by_segment'] ?? [],
            'brand_code' => $diagnostics['brand_code'] ?? trim((string) ($meta['selected_brand_code'] ?? '')),
            'travel_date' => $departureDates[0] ?? null,
            'host_error_family' => SabreHostSellClassifier::hostErrorFamilyForReason((string) ($diagnostics['safe_reason_code'] ?? '')),
            'segment_status' => is_array($diagnostics['airline_segment_statuses'] ?? null)
                ? ($diagnostics['airline_segment_statuses'][0] ?? null)
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $offerFields
     * @return array<string, mixed>
     */
    protected static function mergeOfferFieldsWithStatus(array $offerFields, ?string $segmentStatus): array
    {
        $digest = app(SabreStoredPricingContextDigest::class);
        $merged = $offerFields;
        if ($segmentStatus !== null && $segmentStatus !== '') {
            $merged['segment_status'] = strtoupper($segmentStatus);
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    protected static function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            $s = strtoupper(trim((string) $value));
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values($out);
    }
}
