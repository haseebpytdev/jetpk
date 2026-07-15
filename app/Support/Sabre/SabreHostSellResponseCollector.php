<?php

namespace App\Support\Sabre;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use Illuminate\Support\Str;

/**
 * Collects safe structured Sabre GDS host sell diagnostics (no raw payloads / PII).
 */
final class SabreHostSellResponseCollector
{
    /**
     * @param  array<string, mixed>  $result  createBooking / checkout result slice
     * @return array<string, mixed>
     */
    public static function collect(Booking $booking, array $result, ?array $classification = null): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = self::resolveOfferSnapshot($meta);
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $digest = $snapshot !== [] ? app(SabreStoredPricingContextDigest::class)->digest($snapshot) : [];

        $connectionId = (int) ($result['supplier_connection_id'] ?? $meta['supplier_connection_id'] ?? 0);
        $pccMeta = self::safePccFingerprint($connectionId);

        $marketing = self::carrierList($segments, 'marketing');
        $operating = self::carrierList($segments, 'operating');
        $flightNumbers = self::flightNumbers($segments);
        $bookingClasses = self::bookingClasses($snapshot, $segments);
        $fareBasis = self::fareBasisCodes($snapshot, $segments, $digest);
        $departureDates = self::departureDates($segments);
        $brandCode = trim((string) ($meta['selected_brand_code'] ?? data_get($meta, 'selected_fare_family_option.brand_code', '')));

        $classification ??= SabreHostSellClassifier::classify(array_merge(
            array_intersect_key($result, array_flip([
                'error_code', 'http_status', 'airline_segment_status', 'halt_on_status_received',
                'pnr_present_in_response_body', 'pnr', 'probable_issue', 'response_error_messages',
                'application_error_messages', 'messages', 'message', 'controlled_pnr_certification_status',
            ])),
            ['pnr_present' => trim((string) ($booking->pnr ?? $result['pnr'] ?? '')) !== ''],
        ));

        $route = self::formatRoute($booking, $segments, $snapshot);

        return array_filter([
            'booking_id' => $booking->id,
            'booking_reference' => trim((string) ($booking->reference_code ?? '')),
            'supplier_connection_id' => $connectionId > 0 ? $connectionId : null,
            'pcc_hash' => $pccMeta['pcc_hash'] ?? null,
            'pcc_last2' => $pccMeta['pcc_last2'] ?? null,
            'validating_carrier' => strtoupper(trim((string) ($digest['validating_carrier'] ?? $snapshot['validating_carrier'] ?? ''))) ?: null,
            'marketing_carriers' => $marketing !== [] ? $marketing : null,
            'operating_carriers' => $operating !== [] ? $operating : null,
            'route' => $route,
            'segment_count' => count($segments) > 0 ? count($segments) : ($result['segment_count'] ?? null),
            'flight_numbers' => $flightNumbers !== [] ? $flightNumbers : null,
            'booking_classes' => $bookingClasses !== [] ? $bookingClasses : null,
            'fare_basis_codes' => $fareBasis !== [] ? $fareBasis : null,
            'brand_code' => $brandCode !== '' ? strtoupper($brandCode) : null,
            'selected_fare_family' => trim((string) ($meta['selected_brand_name'] ?? data_get($meta, 'selected_fare_family_option.name', ''))) ?: null,
            'departure_dates' => $departureDates !== [] ? $departureDates : null,
            'response_http_status' => $result['http_status'] ?? null,
            'application_results_status' => $result['application_results_status'] ?? null,
            'host_warning_codes' => self::truncateList($result['host_warning_sabre_codes'] ?? $result['response_error_codes'] ?? []),
            'host_warning_messages' => self::truncateList($result['host_warning_messages_truncated'] ?? $result['response_error_messages'] ?? []),
            'host_error_messages' => self::truncateList($result['response_error_messages'] ?? []),
            'airline_segment_statuses' => self::segmentStatuses($result, $classification),
            'halt_on_status_received' => ($result['halt_on_status_received'] ?? $classification['halt_on_status_received'] ?? false) === true,
            'pnr_present' => trim((string) ($booking->pnr ?? $result['pnr'] ?? '')) !== ''
                || ($result['pnr_present_in_response_body'] ?? false) === true,
            'retry_policy' => $classification['retry_policy'] ?? null,
            'safe_reason_code' => $classification['safe_reason_code'] ?? null,
            'classifier_version' => $classification['classifier_version'] ?? SabreHostSellClassifier::CLASSIFIER_VERSION,
            'recorded_at' => now()->toIso8601String(),
            'pnr_lane' => SabrePnrLaneDiagnostics::detectPrimaryLane($booking),
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected static function resolveOfferSnapshot(array $meta): array
    {
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snapshot = $meta[$key] ?? null;
            if (is_array($snapshot) && $snapshot !== []) {
                return $snapshot;
            }
        }

        return [];
    }

    /**
     * @return array{pcc_hash: ?string, pcc_last2: ?string}
     */
    protected static function safePccFingerprint(int $connectionId): array
    {
        if ($connectionId <= 0) {
            return ['pcc_hash' => null, 'pcc_last2' => null];
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            return ['pcc_hash' => null, 'pcc_last2' => null];
        }

        $pcc = trim((string) data_get($connection->credentials, 'pcc', data_get($connection->credentials, 'target_city', '')));
        if ($pcc === '') {
            return ['pcc_hash' => null, 'pcc_last2' => null];
        }

        $last2 = strlen($pcc) >= 2 ? strtoupper(substr($pcc, -2)) : strtoupper($pcc);

        return [
            'pcc_hash' => substr(hash('sha256', $pcc), 0, 12),
            'pcc_last2' => $last2,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected static function carrierList(array $segments, string $kind): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $c = $kind === 'operating'
                ? strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['operating_airline'] ?? '')))
                : strtoupper(trim((string) ($seg['carrier'] ?? $seg['marketing_carrier'] ?? $seg['airline_code'] ?? '')));
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
    protected static function flightNumbers(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['marketing_carrier'] ?? '')));
            $num = trim((string) ($seg['flight_number'] ?? ''));
            if ($carrier !== '' && $num !== '') {
                $out[] = $carrier.$num;
            }
        }

        return array_values(array_unique(array_slice($out, 0, 12)));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected static function bookingClasses(array $snapshot, array $segments): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        foreach (['sabre_shop_context', 'sabre_booking_context'] as $ctxKey) {
            $ctx = is_array($raw[$ctxKey] ?? null) ? $raw[$ctxKey] : [];
            $list = is_array($ctx['booking_classes_by_segment'] ?? null) ? $ctx['booking_classes_by_segment'] : [];
            if ($list !== []) {
                return self::stringList($list);
            }
        }

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
     * @param  array<string, mixed>  $digest
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected static function fareBasisCodes(array $snapshot, array $segments, array $digest): array
    {
        $fromDigest = self::stringList($digest['fare_basis_codes'] ?? []);
        if ($fromDigest !== []) {
            return $fromDigest;
        }

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
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected static function departureDates(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $date = substr(trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '')), 0, 10);
            if ($date !== '') {
                $out[] = $date;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $snapshot
     */
    protected static function formatRoute(Booking $booking, array $segments, array $snapshot): ?string
    {
        $route = trim((string) ($booking->route ?? ''));
        if ($route !== '') {
            return $route;
        }

        if ($segments === []) {
            $origin = strtoupper(trim((string) ($snapshot['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($snapshot['destination'] ?? '')));
            if ($origin !== '' && $dest !== '') {
                return $origin.' → '.$dest;
            }

            return null;
        }

        $first = is_array($segments[0]) ? $segments[0] : [];
        $last = is_array($segments[array_key_last($segments)]) ? $segments[array_key_last($segments)] : [];
        $origin = strtoupper(trim((string) ($first['origin'] ?? '')));
        $dest = strtoupper(trim((string) ($last['destination'] ?? '')));

        return ($origin !== '' && $dest !== '') ? $origin.' → '.$dest : null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $classification
     * @return list<string>
     */
    protected static function segmentStatuses(array $result, array $classification): array
    {
        $statuses = [];
        $primary = strtoupper(trim((string) ($result['airline_segment_status'] ?? $classification['segment_status'] ?? '')));
        if ($primary !== '') {
            $statuses[] = $primary;
        }

        foreach ((array) ($result['affected_flight_numbers'] ?? []) as $ignored) {
            // placeholder for multi-segment expansion from safe_summary rows
        }

        return array_values(array_unique($statuses));
    }

    /**
     * @return list<string>
     */
    protected static function truncateList(mixed $values): array
    {
        if (! is_array($values)) {
            return is_scalar($values) && trim((string) $values) !== ''
                ? [Str::limit(trim((string) $values), 120, '')]
                : [];
        }

        $out = [];
        foreach (array_slice($values, 0, 8) as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $text = Str::limit(trim((string) $value), 120, '');
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
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

        return $out;
    }
}
