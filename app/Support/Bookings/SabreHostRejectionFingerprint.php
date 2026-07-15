<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabrePnrCertificationSupport;

/**
 * Sprint 11K-I: Safe host-rejection itinerary fingerprint for persistence and pre-checkout matching (no PII/raw payloads).
 */
final class SabreHostRejectionFingerprint
{
    public const FINGERPRINT_VERSION = 'sabre_host_rejection_fingerprint_v1';

    /** @var list<string> */
    private const PERSISTABLE_HOST_ERROR_FAMILIES = [
        SabreHostErrorClassifier::HOST_ERROR_FAMILY_UC_SEGMENT_STATUS,
        SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
        SabreHostErrorClassifier::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
        SabreHostErrorClassifier::HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT,
    ];

    /** @var list<string> */
    private const FORBIDDEN_OUTPUT_SUBSTRINGS = [
        'createpassengernamerecordrq',
        'passengername',
        'formofpayment',
        'telephone',
        'bookingsignature',
        'response_error_messages',
        'raw_payload',
        'access_token',
        'client_secret',
    ];

    /**
     * @param  array<string, mixed>  $classification  Persisted {@see SabreHostErrorClassifier::buildPersistedSlice()} output
     * @return array<string, mixed>|null
     */
    public static function buildForPersistence(Booking $booking, array $classification): ?array
    {
        if (! self::shouldPersistFingerprint($classification)) {
            return null;
        }

        $snapshot = self::resolveOfferSnapshotFromBooking($booking);
        if ($snapshot === []) {
            return null;
        }

        $matchFields = self::extractMatchFieldsFromSnapshot($snapshot);
        if ($matchFields['origin'] === '' || $matchFields['destination'] === '') {
            return null;
        }

        $hostFamily = (string) ($classification['host_error_family'] ?? '');
        $safeReason = (string) ($classification['safe_reason_code'] ?? '');
        $retryPolicy = (string) ($classification['retry_policy'] ?? SabreHostErrorClassifier::RETRY_NO_RETRY_SAME_OFFER);
        $sourceLayer = (string) ($classification['source_layer'] ?? SabreHostErrorClassifier::LAYER_UNKNOWN);

        $fingerprint = array_merge($matchFields, [
            'fingerprint_version' => self::FINGERPRINT_VERSION,
            'fingerprint_hash' => self::computeFingerprintHash($matchFields),
            'host_error_family' => $hostFamily,
            'safe_reason_code' => $safeReason,
            'retry_policy' => $retryPolicy,
            'recommended_admin_action' => (string) ($classification['recommended_admin_action']
                ?? $classification['admin_summary']
                ?? ''),
            'recorded_at' => now()->toIso8601String(),
            'source_booking_id' => $booking->id,
            'source_layer' => $sourceLayer,
        ], self::completionContextSlice($booking));

        $sanitized = self::sanitizeFingerprint($fingerprint);

        return $sanitized !== [] ? $sanitized : null;
    }

    /**
     * @param  array<string, mixed>  $offer  Search or booking offer snapshot
     * @return array<string, mixed>
     */
    public static function extractMatchFieldsFromOffer(array $offer): array
    {
        return self::extractMatchFieldsFromSnapshot($offer);
    }

    /**
     * @param  array<string, mixed>  $matchFields
     */
    public static function computeFingerprintHash(array $matchFields): string
    {
        $canonical = self::canonicalMatchPayload($matchFields);
        ksort($canonical);

        return substr(hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $classification
     */
    public static function shouldPersistFingerprint(array $classification): bool
    {
        $family = strtoupper(trim((string) ($classification['host_error_family'] ?? '')));
        if ($family === '' || ! in_array($family, self::PERSISTABLE_HOST_ERROR_FAMILIES, true)) {
            return false;
        }

        $safeReason = strtolower(trim((string) ($classification['safe_reason_code'] ?? '')));
        if ($safeReason === SabreHostErrorClassifier::REASON_UNKNOWN) {
            return false;
        }

        return SabreHostErrorClassifier::hostErrorFamilyForReason($safeReason) === $family;
    }

    /**
     * @param  array<string, mixed>  $stored  Persisted fingerprint from booking meta
     * @param  array<string, mixed>  $offerFields  {@see extractMatchFieldsFromOffer()}
     */
    public static function offerMatchesStoredFingerprint(array $stored, array $offerFields): bool
    {
        $storedHash = trim((string) ($stored['fingerprint_hash'] ?? ''));
        if ($storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, self::computeFingerprintHash($offerFields));
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveOfferSnapshotFromBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snapshot = $meta[$key] ?? null;
            if (is_array($snapshot) && $snapshot !== []) {
                return $snapshot;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private static function extractMatchFieldsFromSnapshot(array $snapshot): array
    {
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $origin = '';
        $destination = '';
        if ($segments !== []) {
            $first = is_array($segments[0]) ? $segments[0] : [];
            $last = is_array($segments[array_key_last($segments)]) ? $segments[array_key_last($segments)] : [];
            $origin = strtoupper(trim((string) ($first['origin'] ?? $snapshot['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($last['destination'] ?? $snapshot['destination'] ?? '')));
        } else {
            $origin = strtoupper(trim((string) ($snapshot['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($snapshot['destination'] ?? '')));
        }

        $digest = app(SabreStoredPricingContextDigest::class)->digest($snapshot);
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $shopCtx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $bookingCtx = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];

        $rbd = self::stringList(
            is_array($shopCtx['booking_classes_by_segment'] ?? null) ? $shopCtx['booking_classes_by_segment'] : [],
        );
        if ($rbd === []) {
            $rbd = self::stringList(
                is_array($bookingCtx['booking_classes_by_segment'] ?? null) ? $bookingCtx['booking_classes_by_segment'] : [],
            );
        }
        if ($rbd === []) {
            $rbd = self::bookingClassesFromSegments($segments);
        }

        $fbc = self::stringList(is_array($digest['fare_basis_codes'] ?? null) ? $digest['fare_basis_codes'] : []);
        if ($fbc === []) {
            $fbc = self::stringList(
                is_array($shopCtx['fare_basis_codes_by_segment'] ?? null) ? $shopCtx['fare_basis_codes_by_segment'] : [],
            );
        }
        if ($fbc === []) {
            $fbc = self::stringList(
                is_array($bookingCtx['fare_basis_codes_by_segment'] ?? null) ? $bookingCtx['fare_basis_codes_by_segment'] : [],
            );
        }
        if ($fbc === []) {
            $fbc = self::fareBasisFromSegments($segments);
        }

        $validating = strtoupper(trim((string) ($digest['validating_carrier'] ?? $snapshot['validating_carrier'] ?? '')));

        return [
            'origin' => $origin,
            'destination' => $destination,
            'segment_count' => count($segments) > 0 ? count($segments) : null,
            'marketing_carriers' => self::carrierChain($segments, 'marketing'),
            'operating_carriers' => self::carrierChain($segments, 'operating'),
            'validating_carrier' => $validating !== '' ? $validating : null,
            'booking_classes_by_segment' => $rbd,
            'fare_basis_codes_by_segment' => $fbc,
            'segment_fingerprints' => self::segmentFingerprints($segments),
        ];
    }

    /**
     * @param  array<string, mixed>  $matchFields
     * @return array<string, mixed>
     */
    private static function canonicalMatchPayload(array $matchFields): array
    {
        return [
            'origin' => strtoupper(trim((string) ($matchFields['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($matchFields['destination'] ?? ''))),
            'segment_count' => isset($matchFields['segment_count']) ? (int) $matchFields['segment_count'] : null,
            'marketing_carriers' => self::stringList($matchFields['marketing_carriers'] ?? []),
            'operating_carriers' => self::stringList($matchFields['operating_carriers'] ?? []),
            'validating_carrier' => strtoupper(trim((string) ($matchFields['validating_carrier'] ?? ''))),
            'booking_classes_by_segment' => self::stringList($matchFields['booking_classes_by_segment'] ?? []),
            'fare_basis_codes_by_segment' => self::stringList($matchFields['fare_basis_codes_by_segment'] ?? []),
            'segment_fingerprints' => self::stringList($matchFields['segment_fingerprints'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     * @return array<string, mixed>
     */
    private static function sanitizeFingerprint(array $fingerprint): array
    {
        $encoded = json_encode($fingerprint, JSON_THROW_ON_ERROR);
        foreach (self::FORBIDDEN_OUTPUT_SUBSTRINGS as $forbidden) {
            if (stripos($encoded, $forbidden) !== false) {
                return [];
            }
        }

        return $fingerprint;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private static function segmentFingerprints(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $date = substr(trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '')), 0, 10);
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['marketing_carrier'] ?? $seg['airline_code'] ?? '')));
            if ($origin === '' || $dest === '') {
                continue;
            }
            $out[] = $origin.'-'.$dest.'|'.$date.'|'.$carrier;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private static function carrierChain(array $segments, string $kind): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            if ($kind === 'operating') {
                $c = strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['operating_airline'] ?? '')));
            } else {
                $c = strtoupper(trim((string) ($seg['carrier'] ?? $seg['marketing_carrier'] ?? $seg['airline_code'] ?? '')));
            }
            if ($c !== '') {
                $out[] = $c;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private static function bookingClassesFromSegments(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $rbd = strtoupper(trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? '')));
            if ($rbd !== '') {
                $out[] = $rbd;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private static function fareBasisFromSegments(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $fbc = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
            if ($fbc !== '') {
                $out[] = $fbc;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function completionContextSlice(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $completion = is_array($meta['auto_pnr_context_completion'] ?? null) ? $meta['auto_pnr_context_completion'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $readiness = app(SabrePnrCertificationSupport::class)->buildReadiness($booking);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $validating = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
        $tripType = app(SabrePnrCertificationSupport::class)->detectTripType($booking);

        $bookingClassCount = isset($completion['booking_classes_by_segment_count'])
            ? (int) $completion['booking_classes_by_segment_count']
            : (isset($handoff['booking_classes_by_segment']) && is_array($handoff['booking_classes_by_segment'])
                ? count($handoff['booking_classes_by_segment'])
                : null);
        $fareBasisCount = isset($completion['fare_basis_codes_by_segment_count'])
            ? (int) $completion['fare_basis_codes_by_segment_count']
            : (isset($handoff['fare_basis_codes_by_segment']) && is_array($handoff['fare_basis_codes_by_segment'])
                ? count($handoff['fare_basis_codes_by_segment'])
                : null);

        return array_filter([
            'trip_type' => $tripType !== '' ? $tripType : null,
            'carrier_chain' => $carriers !== [] ? $carriers : null,
            'validating_carrier' => $validating !== '' ? $validating : null,
            'segment_count' => isset($readiness['segment_count']) ? (int) $readiness['segment_count'] : null,
            'booking_classes_by_segment_count' => $bookingClassCount,
            'fare_basis_codes_by_segment_count' => $fareBasisCount,
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values): array
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
