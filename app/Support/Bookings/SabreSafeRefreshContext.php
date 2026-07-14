<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Gds\SabreSegmentFreshShopSellabilityService;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;

/**
 * E2: Durable safe Sabre offer refresh / re-shop context on booking meta (no raw Sabre payloads, PII, or credentials).
 */
final class SabreSafeRefreshContext
{
    public const META_KEY = 'sabre_safe_refresh_context';

    public const VERSION = 1;

    /** @var list<string> */
    private const FORBIDDEN_KEY_SUBSTRINGS = [
        'raw_payload', 'response', 'request', 'token', 'password', 'secret', 'credential',
        'passport', 'email', 'phone', 'first_name', 'last_name', 'nationality', 'address',
    ];

    /** @var list<string> */
    private const REQUIRED_TOP_LEVEL = [
        'supplier',
        'search_criteria',
        'selected_segments',
        'supplier_total',
        'currency',
    ];

    /** @var list<string> */
    private const REQUIRED_SEARCH_CRITERIA = [
        'trip_type',
        'origin',
        'destination',
        'depart_date',
    ];

    /**
     * Build safe refresh context at Sabre fare selection / checkout defer time.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $metaPatch  Partial booking meta being written at checkout
     * @return array<string, mixed>
     */
    public function buildFromCheckout(array $offer, array $criteria, array $metaPatch = []): array
    {
        $now = now()->toIso8601String();
        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot(array_merge(
            $metaPatch,
            ['flight_offer_snapshot' => $offer, 'normalized_offer_snapshot' => $offer],
        ));
        if ($snapshot === []) {
            $snapshot = $offer;
        }

        $segments = app(SabreSegmentFreshShopSellabilityService::class)
            ->extractStoredSegmentsFromOfferSnapshot($snapshot);
        $selectedSegments = $this->summarizeSegments($segments, $snapshot);
        $carrierChain = $this->carrierChainFromSegments($selectedSegments);
        $digest = app(SabreStoredPricingContextDigest::class)->digest($snapshot);
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $shopIds = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($metaPatch['sabre_booking_context'] ?? null) ? $metaPatch['sabre_booking_context'] : [];

        $offerRef = trim((string) (
            $raw['offer_reference']
            ?? $ctx['offer_ref']
            ?? $ctx['offer_id']
            ?? $handoff['offer_reference']
            ?? ''
        ));

        $fare = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $supplierTotal = (float) (
            $metaPatch['supplier_total']
            ?? $fare['supplier_total']
            ?? $snapshot['supplier_total_source']
            ?? $snapshot['total']
            ?? 0
        );
        $currency = trim((string) (
            $metaPatch['supplier_currency']
            ?? $fare['currency']
            ?? $snapshot['currency']
            ?? 'PKR'
        )) ?: 'PKR';

        $passengerCounts = is_array($metaPatch['passenger_counts'] ?? null)
            ? $metaPatch['passenger_counts']
            : [
                'adults' => max(1, (int) ($criteria['adults'] ?? 1)),
                'children' => max(0, (int) ($criteria['children'] ?? 0)),
                'infants' => max(0, (int) ($criteria['infants'] ?? 0)),
            ];

        $context = [
            'version' => self::VERSION,
            'supplier' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => (int) ($metaPatch['supplier_connection_id'] ?? 0) ?: null,
            'selected_payload_style' => $this->nullableScalar($metaPatch['selected_payload_style'] ?? null),
            'endpoint_version' => $this->nullableScalar($metaPatch['selected_endpoint_version'] ?? null),
            'checkout_search_id' => trim((string) ($metaPatch['checkout_search_id'] ?? '')),
            'checkout_offer_id' => trim((string) ($metaPatch['checkout_offer_id'] ?? $metaPatch['original_offer_id'] ?? '')),
            'search_criteria' => $this->sanitizeSearchCriteria($criteria),
            'passenger_counts' => $passengerCounts,
            'trip_type' => trim((string) ($criteria['trip_type'] ?? 'one_way')),
            'origin' => strtoupper(trim((string) ($criteria['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($criteria['destination'] ?? ''))),
            'departure_date' => trim((string) ($criteria['depart_date'] ?? $criteria['departure_date'] ?? '')),
            'return_date' => trim((string) ($criteria['return_date'] ?? '')) ?: null,
            'cabin' => trim((string) ($criteria['cabin'] ?? 'economy')) ?: 'economy',
            'validating_carrier' => strtoupper(trim((string) (
                $snapshot['validating_carrier'] ?? $digest['validating_carrier'] ?? ''
            ))) ?: null,
            'carrier_chain' => $carrierChain,
            'segment_count' => count($selectedSegments),
            'selected_segments' => $selectedSegments,
            'supplier_total' => round($supplierTotal, 2),
            'currency' => $currency,
            'offer_reference' => $offerRef !== '' ? $offerRef : null,
            'shop_identifiers' => $this->safeShopIdentifiers($shopIds, $ctx),
            'offer_validated_at' => trim((string) (
                $metaPatch['offer_validated_at'] ?? $metaPatch['validated_at'] ?? $now
            )),
            'created_at' => $now,
            'refreshed_at' => null,
        ];

        return $this->stripForbiddenKeys($context);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    public function fromMeta(array $meta): ?array
    {
        $ctx = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : null;

        return $ctx !== [] ? $ctx : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *     safe_refresh_context_present: bool,
     *     safe_refresh_context_complete: bool,
     *     safe_refresh_context_missing_fields: list<string>,
     *     can_rebuild_from_safe_context: bool
     * }
     */
    public function assess(array $meta): array
    {
        $context = $this->fromMeta($meta);
        $present = $context !== null;
        $missing = $present ? $this->missingFields($context) : ['sabre_safe_refresh_context'];
        $complete = $present && $missing === [];
        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        $canRebuild = $complete
            && $snapshot !== []
            && is_array($context['selected_segments'] ?? null)
            && $context['selected_segments'] !== [];

        return [
            'safe_refresh_context_present' => $present,
            'safe_refresh_context_complete' => $complete,
            'safe_refresh_context_missing_fields' => $missing,
            'can_rebuild_from_safe_context' => $canRebuild,
        ];
    }

    /**
     * Resolve search criteria for controlled offer refresh (prefer durable safe context when cache is absent).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function resolveSearchCriteriaForRefresh(array $meta): array
    {
        $stored = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $context = $this->fromMeta($meta);
        $fromContext = is_array($context['search_criteria'] ?? null) ? $context['search_criteria'] : [];

        if ($stored === [] && $fromContext !== []) {
            return $this->enrichSearchCriteriaFromContextTopLevel($fromContext, $context);
        }

        if ($fromContext === []) {
            return $stored;
        }

        $merged = $this->mergeSearchCriteriaPreferNonEmpty($fromContext, $stored);

        return $this->enrichSearchCriteriaFromContextTopLevel($merged, $context);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function mergeIntoMeta(array $meta, array $context): array
    {
        $meta[self::META_KEY] = $this->stripForbiddenKeys($context);

        return $meta;
    }

    /**
     * Rebuild safe context from current booking meta after a successful offer refresh.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function rebuildFromBookingMeta(array $meta): array
    {
        $existing = $this->fromMeta($meta) ?? [];
        $criteria = $this->resolveSearchCriteriaForRefresh($meta);
        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        if ($snapshot === []) {
            return $existing;
        }

        $rebuilt = $this->buildFromCheckout($snapshot, $criteria, array_merge($meta, [
            'checkout_search_id' => $meta['checkout_search_id'] ?? ($existing['checkout_search_id'] ?? ''),
            'checkout_offer_id' => $meta['checkout_offer_id'] ?? ($existing['checkout_offer_id'] ?? ''),
            'original_offer_id' => $meta['original_offer_id'] ?? ($existing['checkout_offer_id'] ?? ''),
        ]));
        $rebuilt['created_at'] = (string) ($existing['created_at'] ?? $rebuilt['created_at']);
        $rebuilt['refreshed_at'] = now()->toIso8601String();

        return $rebuilt;
    }

    public function stampAfterSuccessfulRefresh(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($this->fromMeta($meta) === null && strcasecmp((string) ($meta['supplier_provider'] ?? ''), SupplierProvider::Sabre->value) !== 0) {
            return;
        }

        $meta = $this->mergeIntoMeta($meta, $this->rebuildFromBookingMeta($meta));
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    public function missingFields(array $context): array
    {
        $missing = [];
        foreach (self::REQUIRED_TOP_LEVEL as $key) {
            if (! $this->fieldPresent($context, $key)) {
                $missing[] = $key;
            }
        }

        $criteria = is_array($context['search_criteria'] ?? null) ? $context['search_criteria'] : [];
        foreach (self::REQUIRED_SEARCH_CRITERIA as $key) {
            if (! $this->fieldPresent($criteria, $key)) {
                $missing[] = 'search_criteria.'.$key;
            }
        }

        $segments = is_array($context['selected_segments'] ?? null) ? $context['selected_segments'] : [];
        if ($segments === []) {
            $missing[] = 'selected_segments';
        } else {
            foreach ($segments as $idx => $seg) {
                if (! is_array($seg)) {
                    $missing[] = 'selected_segments.'.$idx;

                    continue;
                }
                foreach (['origin', 'destination', 'departure_at', 'carrier', 'flight_number'] as $segKey) {
                    if (trim((string) ($seg[$segKey] ?? '')) === '') {
                        $missing[] = 'selected_segments.'.$idx.'.'.$segKey;
                    }
                }
            }
        }

        if ((float) ($context['supplier_total'] ?? 0) <= 0) {
            $missing[] = 'supplier_total';
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function containsForbiddenKeys(array $data): bool
    {
        return $this->findForbiddenKey($data) !== null;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    private function summarizeSegments(array $segments, array $snapshot): array
    {
        $rawSegments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $out = [];

        foreach ($segments as $idx => $seg) {
            $raw = is_array($rawSegments[$idx] ?? null) ? $rawSegments[$idx] : [];
            $out[] = [
                'carrier' => strtoupper(trim((string) ($seg['carrier'] ?? ''))),
                'flight_number' => trim((string) ($seg['flight_number'] ?? '')),
                'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                'departure_at' => trim((string) ($seg['departure_at'] ?? '')),
                'arrival_at' => trim((string) ($raw['arrival_at'] ?? $raw['arrive_at'] ?? '')) ?: null,
                'booking_class' => strtoupper(trim((string) ($seg['booking_class'] ?? ''))) ?: null,
                'fare_basis' => trim((string) ($seg['fare_basis_code'] ?? '')) ?: null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    private function carrierChainFromSegments(array $segments): array
    {
        $carriers = [];
        foreach ($segments as $seg) {
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? '')));
            if ($carrier !== '') {
                $carriers[] = $carrier;
            }
        }

        return array_values(array_unique($carriers));
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function sanitizeSearchCriteria(array $criteria): array
    {
        $allowed = [
            'trip_type', 'origin', 'destination', 'depart_date', 'departure_date', 'return_date',
            'cabin', 'cabin_class', 'adults', 'children', 'infants', 'currency', 'segments',
        ];
        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $criteria)) {
                continue;
            }
            $value = $criteria[$key];
            if ($key === 'segments' && is_array($value)) {
                $out[$key] = array_values(array_map(function ($row): array {
                    if (! is_array($row)) {
                        return [];
                    }

                    return array_filter([
                        'origin' => isset($row['origin']) ? strtoupper(trim((string) $row['origin'])) : null,
                        'destination' => isset($row['destination']) ? strtoupper(trim((string) $row['destination'])) : null,
                        'departure_date' => trim((string) ($row['departure_date'] ?? $row['depart_date'] ?? '')) ?: null,
                    ], static fn ($v) => $v !== null && $v !== '');
                }, $value));

                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $shopIds
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function safeShopIdentifiers(array $shopIds, array $ctx): array
    {
        $safeKeys = [
            'itinerary_group_index', 'itinerary_index', 'itinerary_ref', 'itinerary_pricing_index',
            'pricing_information_index', 'fare_option_key', 'shop_request_hash',
        ];
        $out = [];
        foreach ([$shopIds, $ctx] as $source) {
            foreach ($safeKeys as $key) {
                if (! array_key_exists($key, $source)) {
                    continue;
                }
                $value = $source[$key];
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $out[$key] = is_string($value) ? trim($value) : $value;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripForbiddenKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);
            if ($this->keyIsForbidden($keyLower)) {
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->stripForbiddenKeys($value);
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findForbiddenKey(array $data, string $prefix = ''): ?string
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if ($this->keyIsForbidden(strtolower((string) $key))) {
                return $path;
            }
            if (is_array($value)) {
                $nested = $this->findForbiddenKey($value, $path);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function keyIsForbidden(string $keyLower): bool
    {
        foreach (self::FORBIDDEN_KEY_SUBSTRINGS as $needle) {
            if (str_contains($keyLower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function fieldPresent(array $bag, string $key): bool
    {
        if (! array_key_exists($key, $bag)) {
            return false;
        }
        $value = $bag[$key];
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }
        if (is_numeric($value)) {
            return true;
        }

        return false;
    }

    private function nullableScalar(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    private function enrichSearchCriteriaFromContextTopLevel(array $criteria, ?array $context): array
    {
        if ($context === null) {
            return $criteria;
        }

        $passengerCounts = is_array($context['passenger_counts'] ?? null) ? $context['passenger_counts'] : [];
        $fallbacks = [
            'origin' => $context['origin'] ?? '',
            'destination' => $context['destination'] ?? '',
            'depart_date' => $context['departure_date'] ?? '',
            'departure_date' => $context['departure_date'] ?? '',
            'return_date' => $context['return_date'] ?? '',
            'trip_type' => $context['trip_type'] ?? '',
            'cabin' => $context['cabin'] ?? '',
            'currency' => $context['currency'] ?? '',
            'adults' => $passengerCounts['adults'] ?? null,
            'children' => $passengerCounts['children'] ?? null,
            'infants' => $passengerCounts['infants'] ?? null,
        ];

        foreach ($fallbacks as $key => $value) {
            if ($this->searchCriteriaValuePresent($criteria[$key] ?? null)) {
                continue;
            }
            if ($this->searchCriteriaValuePresent($value)) {
                $criteria[$key] = $value;
            }
        }

        if (! $this->searchCriteriaValuePresent($criteria['depart_date'] ?? null)
            && $this->searchCriteriaValuePresent($criteria['departure_date'] ?? null)) {
            $criteria['depart_date'] = trim((string) $criteria['departure_date']);
        }

        return $criteria;
    }

    /**
     * Prefer durable context values; overlay stored meta only when stored values are non-empty.
     *
     * @param  array<string, mixed>  $fromContext
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function mergeSearchCriteriaPreferNonEmpty(array $fromContext, array $stored): array
    {
        $merged = $fromContext;
        foreach ($stored as $key => $value) {
            if ($this->searchCriteriaValuePresent($value)) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function searchCriteriaValuePresent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }
        if (is_numeric($value)) {
            return true;
        }

        return false;
    }
}
