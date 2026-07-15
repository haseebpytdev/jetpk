<?php

namespace App\Support\FlightSearch;

use App\Enums\SupplierProvider;
use App\Support\Bookings\PiaNdcBrandedFareDedup;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;

/**
 * Display-layer grouping: identical supplier itineraries become one parent card with fare options.
 * Raw search storage may keep separate offers; consolidation runs when building public results.
 */
class ItineraryFareConsolidator
{
    public static function enabled(): bool
    {
        return (bool) config('ota.itinerary_fare_consolidation_enabled', true);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    public static function consolidate(array $offers): array
    {
        if (! self::enabled() || $offers === []) {
            return $offers;
        }

        $buckets = [];
        $order = [];
        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $signature = self::signatureForOffer($offer);
            if ($signature === null) {
                $order[] = ['type' => 'single', 'offer' => $offer];

                continue;
            }
            if (! isset($buckets[$signature])) {
                $buckets[$signature] = [];
                $order[] = ['type' => 'bucket', 'signature' => $signature];
            }
            $buckets[$signature][] = $offer;
        }

        $out = [];
        foreach ($order as $entry) {
            if ($entry['type'] === 'single') {
                $out[] = $entry['offer'];

                continue;
            }
            $signature = (string) $entry['signature'];
            $group = $buckets[$signature] ?? [];
            if (count($group) < 2 || self::shouldSkipGroup($group)) {
                foreach ($group as $member) {
                    $out[] = $member;
                }

                continue;
            }
            $parent = self::buildConsolidatedParent($group, $signature);
            if ($parent !== null) {
                $out[] = $parent;
            } else {
                foreach ($group as $member) {
                    $out[] = $member;
                }
            }
        }

        return $out;
    }

    public static function isConsolidatedParent(array $offer): bool
    {
        return (bool) data_get($offer, 'itinerary_fare_group.is_consolidated_parent', false);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public static function resolveGroupedSourceOffer(array $offer, string $sourceOfferId): ?array
    {
        $sourceOfferId = trim($sourceOfferId);
        if ($sourceOfferId === '') {
            return null;
        }

        $members = data_get($offer, 'itinerary_fare_group.members_by_id');
        if (! is_array($members)) {
            return null;
        }

        $member = $members[$sourceOfferId] ?? null;

        return is_array($member) ? $member : null;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public static function offerMatchesBaggageFilter(array $offer, string $bucket): bool
    {
        if ($bucket === '') {
            return true;
        }

        foreach (self::fareOptionBaggageBuckets($offer) as $candidate) {
            if ($candidate === $bucket) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public static function offerMatchesRefundableFilter(array $offer, string $filter): bool
    {
        if ($filter === '') {
            return true;
        }

        if (! self::isConsolidatedParent($offer)) {
            $refundable = (bool) ($offer['refundable'] ?? false);

            return ($filter === '1' && $refundable) || ($filter === '0' && ! $refundable);
        }

        foreach (self::iterFareOptionRows($offer) as $row) {
            $refundable = self::rowIsRefundable($row, $offer);
            if (($filter === '1' && $refundable) || ($filter === '0' && ! $refundable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public static function offerMatchesFareFamilyFilter(array $offer, string $fareFamily): bool
    {
        if ($fareFamily === '') {
            return true;
        }

        $want = strtolower(trim($fareFamily));
        if ($want === '') {
            return true;
        }

        if (! self::isConsolidatedParent($offer)) {
            return strtolower(trim((string) ($offer['fare_family'] ?? ''))) === $want;
        }

        foreach (self::iterFareOptionRows($offer) as $row) {
            $name = strtolower(trim((string) ($row['name'] ?? $row['brand_name'] ?? '')));
            if ($name !== '' && $name === $want) {
                return true;
            }
        }

        return strtolower(trim((string) ($offer['fare_family'] ?? ''))) === $want;
    }

    /**
     * Stable itinerary signature for duplicate detection (display layer only).
     *
     * @param  array<string, mixed>  $offer
     */
    public static function signatureForOffer(array $offer): ?string
    {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        if ($provider === '') {
            return null;
        }

        $segments = self::orderedSegments($offer);
        if ($segments === []) {
            return null;
        }

        $payload = [
            'provider' => $provider,
            'supplier_connection_id' => (int) ($offer['supplier_connection_id'] ?? 0),
            'validating_carrier' => strtoupper(trim((string) ($offer['validating_carrier'] ?? ''))),
            'primary_display_carrier' => strtoupper(trim((string) ($offer['primary_display_carrier'] ?? $offer['airline_code'] ?? ''))),
            'cabin' => strtolower(trim((string) ($offer['cabin'] ?? ''))),
            'stops' => (int) ($offer['stops'] ?? max(0, count($segments) - 1)),
            'passenger_mix' => self::passengerMixKey($offer),
            'segments' => array_map(static function (array $seg): array {
                return [
                    'marketing' => strtoupper(trim((string) ($seg['airline_code'] ?? ''))),
                    'operating' => strtoupper(trim((string) ($seg['operating_airline_code'] ?? $seg['operating_carrier_code'] ?? ''))),
                    'flight_number' => self::normalizeFlightNumber((string) ($seg['flight_number'] ?? '')),
                    'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                    'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                    'departure_at' => self::normalizeWallMinute((string) ($seg['departure_at'] ?? '')),
                    'arrival_at' => self::normalizeWallMinute((string) ($seg['arrival_at'] ?? '')),
                ];
            }, $segments),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $group
     */
    protected static function shouldSkipGroup(array $group): bool
    {
        foreach ($group as $offer) {
            $branded = is_array($offer['branded_fares'] ?? null) ? $offer['branded_fares'] : [];
            if (count($branded) >= 2) {
                return true;
            }
            $fareFamily = is_array($offer['fare_family_options'] ?? null) ? $offer['fare_family_options'] : [];
            if (count($fareFamily) >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $group
     * @return array<string, mixed>|null
     */
    protected static function buildConsolidatedParent(array $group, string $signature): ?array
    {
        usort($group, static function (array $a, array $b): int {
            $priceA = (float) ($a['final_customer_price'] ?? $a['displayed_price'] ?? 0);
            $priceB = (float) ($b['final_customer_price'] ?? $b['displayed_price'] ?? 0);
            if ($priceA > 0 && $priceB > 0 && abs($priceA - $priceB) > 0.01) {
                return $priceA <=> $priceB;
            }

            return strcmp((string) ($a['offer_id'] ?? $a['id'] ?? ''), (string) ($b['offer_id'] ?? $b['id'] ?? ''));
        });

        $parent = $group[0];
        $membersById = [];
        $memberIds = [];
        $fareFamilyOptions = [];

        foreach ($group as $member) {
            $memberId = (string) ($member['offer_id'] ?? $member['id'] ?? '');
            if ($memberId === '') {
                continue;
            }
            $membersById[$memberId] = $member;
            $memberIds[] = $memberId;
            $fareFamilyOptions[] = self::fareFamilyOptionFromMember($member);
        }

        if (count($fareFamilyOptions) < 2) {
            return null;
        }

        $groupedProvider = strtolower(trim((string) ($parent['supplier_provider'] ?? '')));
        if ($groupedProvider === SupplierProvider::PiaNdc->value) {
            $deduped = PiaNdcBrandedFareDedup::dedupeOptions($fareFamilyOptions, $parent, [
                'search_type' => data_get($parent, 'search_criteria.trip_type'),
            ]);
            $fareFamilyOptions = $deduped['options'];
            if (count($fareFamilyOptions) < 2) {
                return null;
            }
        }

        usort($fareFamilyOptions, static function (array $a, array $b): int {
            $priceA = (float) ($a['price_total'] ?? 0);
            $priceB = (float) ($b['price_total'] ?? 0);
            if ($priceA > 0 && $priceB > 0 && abs($priceA - $priceB) > 0.01) {
                return $priceA <=> $priceB;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $parentId = (string) ($parent['offer_id'] ?? $parent['id'] ?? '');
        $parent['fare_family_options'] = $fareFamilyOptions;
        $parent['branded_fares'] = [];
        $parent['itinerary_fare_group'] = [
            'signature_hash' => substr($signature, 0, 16),
            'is_consolidated_parent' => true,
            'parent_offer_id' => $parentId,
            'grouped_provider' => strtolower(trim((string) ($parent['supplier_provider'] ?? ''))),
            'member_offer_ids' => $memberIds,
            'members_by_id' => $membersById,
            'grouped_offer_count' => count($memberIds),
        ];
        $parent['has_grouped_fare_options'] = true;

        return $parent;
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    protected static function fareFamilyOptionFromMember(array $member): array
    {
        $sourceId = (string) ($member['offer_id'] ?? $member['id'] ?? '');
        $baggage = OfferBaggageResolver::resolveFromOffer($member);
        $supplierTotal = (float) (
            $member['supplier_total_source']
            ?? data_get($member, 'fare_breakdown.supplier_total', 0)
            ?? ($member['base_fare'] ?? 0) + ($member['taxes'] ?? 0)
        );
        $currency = strtoupper(trim((string) (
            data_get($member, 'fare_breakdown.currency')
            ?? $member['supplier_currency']
            ?? $member['pricing_currency']
            ?? $member['currency']
            ?? 'PKR'
        )));

        $branded = is_array($member['branded_fares'] ?? null) ? $member['branded_fares'] : [];
        $brandRow = is_array($branded[0] ?? null) ? $branded[0] : [];

        $name = trim((string) (
            $brandRow['name']
            ?? $brandRow['brand_name']
            ?? $member['fare_family']
            ?? ''
        ));
        if ($name === '') {
            $name = self::deriveGroupedOptionName($member, $baggage);
        }

        return array_filter([
            'name' => $name,
            'brand_name' => $name,
            'option_key' => self::fareFamilyOptionKeyForMember($member, $sourceId),
            'source_offer_id' => $sourceId,
            'is_grouped_offer_option' => true,
            'price_total' => $supplierTotal > 0 ? $supplierTotal : null,
            'currency' => $currency !== '' ? $currency : null,
            'carry_on_summary' => $baggage['cabin'],
            'check_in_summary' => $baggage['checked'],
            'baggage_summary' => $baggage['summary'],
            'meal_included' => data_get($member, 'customer_display_fields.meal') ?? null,
            'refundable_display' => (bool) ($member['refundable'] ?? false) ? 'Refundable' : 'Non-refundable',
            'refund_rule' => trim((string) ($member['refund_rule'] ?? '')) ?: null,
            'modification_rule' => trim((string) ($member['change_rule'] ?? '')) ?: null,
            'cancellation_rule' => trim((string) ($member['cancellation_rule'] ?? '')) ?: null,
            'cabin' => strtolower(trim((string) ($member['cabin'] ?? ''))) ?: null,
            'booking_class' => trim((string) ($member['booking_class'] ?? data_get($member, 'customer_display_fields.booking_class', data_get($member, 'provider_context.rbd', '')))) ?: null,
            'fare_basis' => trim((string) ($member['fare_basis'] ?? data_get($member, 'customer_display_fields.fare_basis', data_get($member, 'provider_context.fare_basis', '')))) ?: null,
            'brand_code' => trim((string) (data_get($member, 'provider_context.fare_type_code', ''))) ?: null,
            'departure_fare_key' => trim((string) (
                $brandRow['departure_fare_key']
                ?? data_get($member, 'raw_payload.provider_context.departure_fare_key', '')
            )) ?: null,
            'return_fare_key' => trim((string) (
                $brandRow['return_fare_key']
                ?? data_get($member, 'raw_payload.provider_context.return_fare_key', '')
            )) ?: null,
            'provider_context' => self::providerContextFromMember($member),
            'pia_ndc_provider_backed' => self::memberHasPiaNdcProviderContext($member),
            'selectable' => true,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    protected static function providerContextFromMember(array $member): array
    {
        if (is_array($member['provider_context'] ?? null) && $member['provider_context'] !== []) {
            return $member['provider_context'];
        }

        $raw = is_array($member['raw_payload'] ?? null) ? $member['raw_payload'] : [];
        if (is_array($raw['provider_context'] ?? null) && $raw['provider_context'] !== []) {
            return $raw['provider_context'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $member
     */
    protected static function memberHasPiaNdcProviderContext(array $member): bool
    {
        $provider = strtolower(trim((string) ($member['supplier_provider'] ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return false;
        }

        return PiaNdcFareFamilyPolicy::hasOrderCreateReadyContext(self::providerContextFromMember($member));
    }

    /**
     * @param  array<string, mixed>  $member
     */
    protected static function fareFamilyOptionKeyForMember(array $member, string $sourceId): string
    {
        $ctx = self::providerContextFromMember($member);
        if (
            strtolower(trim((string) ($member['supplier_provider'] ?? ''))) === SupplierProvider::PiaNdc->value
            && PiaNdcFareFamilyPolicy::hasOrderCreateReadyContext($ctx)
        ) {
            $offerRef = trim((string) ($ctx['offer_ref_id'] ?? ''));
            $itemRef = trim((string) ($ctx['offer_item_ref_id'] ?? ''));

            return 'pia-ndc-brand-'.substr(hash('sha256', $offerRef.$itemRef), 0, 12);
        }

        return 'grouped-offer-'.substr(hash('sha256', $sourceId), 0, 12);
    }

    /**
     * @param  array{checked: ?string, cabin: ?string, summary: ?string}  $baggage
     */
    protected static function deriveGroupedOptionName(array $member, array $baggage): string
    {
        $checked = trim((string) ($baggage['checked'] ?? ''));
        if ($checked !== '' && preg_match('/\d+/i', $checked)) {
            return $checked.' Fare';
        }

        $fareFamily = trim((string) ($member['fare_family'] ?? ''));
        if ($fareFamily !== '') {
            return $fareFamily;
        }

        if ((bool) ($member['refundable'] ?? false)) {
            return 'Refundable Fare';
        }

        $cabin = strtolower(trim((string) ($member['cabin'] ?? '')));
        if ($cabin !== '' && $cabin !== 'economy') {
            return ucfirst($cabin).' Fare';
        }

        return 'Economy Fare';
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<string>
     */
    protected static function fareOptionBaggageBuckets(array $offer): array
    {
        if (! self::isConsolidatedParent($offer)) {
            return [self::baggageBucketFromText((string) ($offer['baggage'] ?? ''))];
        }

        $buckets = [];
        foreach (self::iterFareOptionRows($offer) as $row) {
            $checked = trim((string) ($row['check_in_summary'] ?? $row['checked_baggage'] ?? ''));
            $cabin = trim((string) ($row['carry_on_summary'] ?? $row['carry_on'] ?? ''));
            if ($checked !== '') {
                $buckets[] = self::baggageBucketFromText($checked);
            }
            if ($cabin !== '') {
                $buckets[] = self::baggageBucketFromText($cabin);
            }
            $summary = trim((string) ($row['baggage_summary'] ?? ''));
            if ($summary !== '') {
                $buckets[] = self::baggageBucketFromText($summary);
            }
        }

        return array_values(array_unique(array_filter($buckets)));
    }

    protected static function baggageBucketFromText(string $text): string
    {
        $lower = strtolower(trim($text));
        if ($lower === '') {
            return 'no_baggage_info';
        }
        if (str_contains($lower, 'kg')) {
            return 'checked_baggage';
        }

        return 'cabin_baggage';
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    protected static function iterFareOptionRows(array $offer): array
    {
        $rows = is_array($offer['fare_family_options'] ?? null) ? $offer['fare_family_options'] : [];

        return array_values(array_filter($rows, is_array(...)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     */
    protected static function rowIsRefundable(array $row, array $offer): bool
    {
        $display = strtolower(trim((string) ($row['refundable_display'] ?? '')));
        if (str_contains($display, 'non-refund')) {
            return false;
        }
        if (str_contains($display, 'refund')) {
            return true;
        }

        return (bool) ($offer['refundable'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    protected static function orderedSegments(array $offer): array
    {
        $segments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        if ($segments === []) {
            return [];
        }

        if (FlightOfferDisplayPresenter::shouldPreserveOfferSegmentOrder($offer)) {
            return array_values(array_filter($segments, is_array(...)));
        }

        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                self::normalizeWallMinute((string) ($a['departure_at'] ?? '')),
                self::normalizeWallMinute((string) ($b['departure_at'] ?? '')),
            );
        });

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected static function passengerMixKey(array $offer): string
    {
        $counts = is_array(data_get($offer, 'fare_breakdown.passenger_counts'))
            ? data_get($offer, 'fare_breakdown.passenger_counts')
            : [];

        $adults = (int) ($counts['adults'] ?? $counts['adult'] ?? 1);
        $children = (int) ($counts['children'] ?? $counts['child'] ?? 0);
        $infants = (int) ($counts['infants'] ?? $counts['infant'] ?? 0);

        return $adults.'a'.$children.'c'.$infants.'i';
    }

    protected static function normalizeFlightNumber(string $flightNumber): string
    {
        $flightNumber = strtoupper(trim($flightNumber));
        $flightNumber = preg_replace('/\s+/', '', $flightNumber) ?? '';

        return $flightNumber;
    }

    protected static function normalizeWallMinute(string $iso): string
    {
        $iso = trim($iso);
        if ($iso === '') {
            return '';
        }
        $iso = preg_replace('/\.\d+/', '', $iso) ?? $iso;
        $iso = preg_replace('/Z$/i', '', $iso) ?? $iso;
        $iso = str_replace('T', ' ', $iso);

        return strlen($iso) >= 16 ? substr($iso, 0, 16) : $iso;
    }
}
