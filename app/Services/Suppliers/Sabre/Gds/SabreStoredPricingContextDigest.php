<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Console\Commands\SabreInspectBookingPricingContextCommand;

/**
 * B16: Safe scalar digest of Sabre pricing linkage preserved on a stored normalized offer snapshot (no live Sabre calls,
 * no raw shop JSON). Used by {@see SabreInspectBookingPricingContextCommand}.
 * B2A: {@see self::assessBrandedFareOptionReadiness()} scores per branded-fare rows only (does not loosen booking gates).
 * 11G: {@see self::assessReadiness()} accepts BFM/GDS priced-itinerary linkage (itinerary ref + pricing_information_index,
 * including 0) without formal NDC-style offer/pricing_information_ref when channel is GDS / v4 shop.
 */
final class SabreStoredPricingContextDigest
{
    private const VALUE_CAP = 100;

    /** @var list<string> */
    private const PII_KEY_SUBSTRINGS = [
        'email', 'phone', 'passport', 'document', 'nationality', 'address', 'contact',
        'first_name', 'last_name', 'given', 'surname', 'dob', 'birth', 'gender',
    ];

    /**
     * @param  array<string, mixed>  $snapshot  Normalized offer snapshot (booking meta slice)
     * @return array<string, mixed>
     */
    public function digest(array $snapshot): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        if ($ctx !== [] && $ids !== []) {
            $ctx = app(SabreFlightSearchNormalizer::class)->syncShopContextLinkageFromIdentifiers($ctx, $ids);
        }
        $fareExcerpt = is_array($raw['sabre_fare_excerpt'] ?? null) ? $raw['sabre_fare_excerpt'] : [];
        $merged = $this->mergeFlatScalars($ctx, $ids);

        $supplierOfferId = trim((string) ($snapshot['supplier_offer_id'] ?? $snapshot['offer_id'] ?? ''));
        $offerHash = $supplierOfferId !== '' ? substr(hash('sha256', $supplierOfferId), 0, 12) : '';

        $ig = (int) ($ctx['itinerary_group_index'] ?? 0);
        $ii = (int) ($ctx['itinerary_index'] ?? 0);
        $itinRef = trim((string) ($ctx['itinerary_ref'] ?? ''));
        $ipIdx = (int) ($ctx['itinerary_pricing_index'] ?? 0);
        $piIdx = (int) ($ctx['pricing_information_index'] ?? 0);

        $readiness = $this->assessReadinessFromParts($ctx, $ids, $snapshot);
        $hasExplicitPiRef = ($readiness['has_pricing_information_ref'] ?? false) === true;
        $hasExplicitOfferRef = ($readiness['has_offer_reference'] ?? false) === true;

        $selectedPricingPresent = $hasExplicitPiRef || $hasExplicitOfferRef;

        $fareNodePresent = $fareExcerpt !== []
            || (is_array($snapshot['fare_breakdown'] ?? null) && $snapshot['fare_breakdown'] !== []);

        $paxInfoCount = $this->passengerInfoCountFromSnapshot($snapshot);

        $fcRefs = is_array($ctx['fare_component_refs'] ?? null) ? $ctx['fare_component_refs'] : [];
        $fcdRefs = is_array($ctx['fare_component_desc_refs'] ?? null) ? $ctx['fare_component_desc_refs'] : [];
        $fbcFromCtx = is_array($ctx['fare_basis_codes'] ?? null) ? $ctx['fare_basis_codes'] : [];
        $fbcFromFb = $this->fareBasisFromFareBreakdown($snapshot);
        $fbcFromHandoff = $this->fareBasisFromSabreBookingContext($snapshot);
        $fbc = array_values(array_unique(array_filter(array_merge(
            array_map(static fn ($v): string => strtoupper(trim((string) $v)), $fbcFromCtx),
            $fbcFromFb,
            $fbcFromHandoff
        ), static fn (string $s): bool => $s !== '')));

        $validating = strtoupper(trim((string) ($ctx['validating_carrier'] ?? $snapshot['validating_carrier'] ?? '')));

        $pricingKeys = $this->scalarKeysFromMap($this->filterPricingSlice($merged));
        $fareKeys = $this->scalarKeysFromMap($this->filterFareSlice($merged, $fareExcerpt));
        $paxKeys = []; // BFM passengerInfo nodes are not persisted on the snapshot
        $fcKeys = ['fare_component_refs', 'fare_component_desc_refs', 'leg_refs', 'schedule_refs', 'baggage_refs'];

        $candidates = $this->collectRefCandidates($merged);
        $bfmPolicy = $this->assessBfmV4LinkagePolicy($snapshot);

        return array_merge([
            'booking_id' => null,
            'supplier_offer_id_short_hash' => $offerHash,
            'itinerary_group_index' => $ig,
            'itinerary_index' => $ii,
            'itinerary_ref' => $this->capScalar($itinRef),
            'itinerary_pricing_index' => $ipIdx,
            'pricing_information_index' => $piIdx,
            'selected_pricing_node_present' => $selectedPricingPresent ? 'yes' : 'no',
            'selected_fare_node_present' => $fareNodePresent ? 'yes' : 'no',
            'selected_passenger_info_count' => $paxInfoCount,
            'fare_component_count' => max(count($fcRefs), count($fbc)),
            'fare_component_refs' => $this->capList($fcRefs),
            'fare_component_desc_refs' => $this->capList($fcdRefs),
            'fare_basis_codes' => $this->capList($fbc),
            'validating_carrier' => $this->capScalar($validating),
            'pricing_node_scalar_keys' => $pricingKeys,
            'fare_node_scalar_keys' => $fareKeys,
            'passenger_info_scalar_keys' => $paxKeys,
            'fare_component_scalar_keys' => $fcKeys,
            'pricing_information_ref_candidates' => $candidates['pricing_information_ref'],
            'pricing_information_id_candidates' => $candidates['pricing_information_id'],
            'offer_reference_candidates' => $candidates['offer_reference'],
            'fare_reference_candidates' => $candidates['fare_reference'],
            'price_quote_reference_candidates' => $candidates['price_quote_reference'],
            'itinerary_reference_candidates' => $candidates['itinerary_reference'],
            'revalidation_reference_candidates' => $candidates['revalidation_reference'],
        ], $this->readinessDigestScalars($readiness), $bfmPolicy);
    }

    /**
     * Sprint 11F/11G: Probe BFM v4 /offers/shop index+descriptor linkage when formal pricing/offer ref tokens are absent.
     * Aligns with {@see assessReadiness()} BFM/GDS policy; kept for admin diagnostics.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     priced_itinerary_sequence_present: bool,
     *     air_pricing_info_index_present: bool,
     *     offer_reference_unavailable_in_bfm_v4: bool,
     *     pricing_context_policy_used: string,
     *     bfm_index_linkage_sufficient: bool,
     *     re_shop_required: bool
     * }
     */
    public function assessBfmV4LinkagePolicy(array $snapshot): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        if ($ctx !== [] && $ids !== []) {
            $ctx = app(SabreFlightSearchNormalizer::class)->syncShopContextLinkageFromIdentifiers($ctx, $ids);
        }

        $readiness = $this->assessReadinessFromParts($ctx, $ids, $snapshot);
        $hasFormalPi = ($readiness['has_pricing_information_ref'] ?? false) === true;
        $hasFormalOffer = ($readiness['has_offer_reference'] ?? false) === true;
        $policyUsed = (string) ($readiness['pricing_context_policy'] ?? '');

        $pricedItinerarySequencePresent = ($readiness['bfm_itinerary_reference_present'] ?? false) === true;
        $airPricingInfoIndexPresent = ($readiness['bfm_pricing_information_index_present'] ?? false) === true;

        $bfmIndexLinkageSufficient = ($readiness['auto_pnr_pricing_context_ready'] ?? false) === true
            && in_array($policyUsed, ['bfm_gds_priced_itinerary', 'formal_ref_linkage'], true);

        $offerReferenceUnavailableInBfmV4 = ! $hasFormalOffer
            && ! $hasFormalPi
            && ($pricedItinerarySequencePresent || $airPricingInfoIndexPresent || $ids !== []);

        if ($policyUsed === '') {
            if ($hasFormalPi || $hasFormalOffer) {
                $policyUsed = 'formal_ref_linkage';
            } elseif ($bfmIndexLinkageSufficient) {
                $policyUsed = 'bfm_gds_priced_itinerary';
            } else {
                $policyUsed = 'formal_ref_required_missing';
            }
        }
        $pricingContextPolicyUsed = $policyUsed;

        $reShopRequired = ($readiness['auto_pnr_pricing_context_ready'] ?? false) !== true
            && ! $hasFormalPi
            && ! $hasFormalOffer
            && ! $bfmIndexLinkageSufficient;

        return [
            'priced_itinerary_sequence_present' => $pricedItinerarySequencePresent,
            'air_pricing_info_index_present' => $airPricingInfoIndexPresent,
            'offer_reference_unavailable_in_bfm_v4' => $offerReferenceUnavailableInBfmV4,
            'pricing_context_policy_used' => $pricingContextPolicyUsed,
            'bfm_index_linkage_sufficient' => $bfmIndexLinkageSufficient,
            're_shop_required' => $reShopRequired,
        ];
    }

    /**
     * Sprint 11F: Safe ref/index scalar paths from stored booking snapshots (no PII, no nested JSON blobs).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    public function collectSafeRefKeyPaths(array $snapshot, array $meta = []): array
    {
        $paths = [];
        foreach ([
            'meta' => $meta,
            'snapshot' => $snapshot,
            'raw_payload' => is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [],
            'sabre_shop_identifiers' => is_array($snapshot['raw_payload']['sabre_shop_identifiers'] ?? null)
                ? $snapshot['raw_payload']['sabre_shop_identifiers']
                : [],
            'sabre_shop_context' => is_array($snapshot['raw_payload']['sabre_shop_context'] ?? null)
                ? $snapshot['raw_payload']['sabre_shop_context']
                : [],
            'sabre_booking_context' => is_array($snapshot['raw_payload']['sabre_booking_context'] ?? null)
                ? $snapshot['raw_payload']['sabre_booking_context']
                : (is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : []),
        ] as $prefix => $map) {
            if (! is_array($map)) {
                continue;
            }
            foreach ($map as $k => $v) {
                if (! is_string($k) || $this->isBlockedKey($k) || ! $this->isRefOrIndexKey($k)) {
                    continue;
                }
                if (is_scalar($v) && trim((string) $v) !== '') {
                    $paths[] = $prefix.'.'.$k.'='.$this->capScalar((string) $v);
                }
            }
        }

        sort($paths);

        return array_values(array_unique(array_slice($paths, 0, 64)));
    }

    protected function isRefOrIndexKey(string $k): bool
    {
        $lk = strtolower($k);
        foreach ([
            'pricing', 'offer', 'itinerary', 'sequence', 'priced', 'quote', 'ref', 'index',
            'itemid', 'item_id', 'subsource', 'validating',
        ] as $frag) {
            if (str_contains($lk, $frag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * C5: Whether stored shop context has explicit BFM pricing/offer linkage for auto revalidate/PNR pricing (no live Sabre).
     *
     * @param  array<string, mixed>  $snapshot  Normalized offer snapshot (booking meta slice)
     * @return array<string, mixed>
     */
    public function assessReadiness(array $snapshot): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        if ($ctx !== [] && $ids !== []) {
            $ctx = app(SabreFlightSearchNormalizer::class)->syncShopContextLinkageFromIdentifiers($ctx, $ids);
        }

        return $this->assessReadinessFromParts($ctx, $ids, $snapshot);
    }

    /**
     * Sprint 11D: Restore missing pricing/offer refs on a stored snapshot from shop identifiers (no live Sabre).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     snapshot: array<string, mixed>,
     *     applied_fields: list<string>,
     *     readiness_before: array<string, mixed>,
     *     readiness_after: array<string, mixed>
     * }
     */
    public function rebuildSnapshotPricingLinkage(array $snapshot): array
    {
        $before = $this->assessReadiness($snapshot);
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];
        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        if ($handoff === [] && is_array($snapshot['sabre_booking_context'] ?? null)) {
            $handoff = $snapshot['sabre_booking_context'];
        }

        if ($ctx !== [] && $ids !== []) {
            $ctx = app(SabreFlightSearchNormalizer::class)->syncShopContextLinkageFromIdentifiers($ctx, $ids);
        }

        $applied = [];
        $this->applyLinkageScalarFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'pricing_information_ref', $applied);
        $this->applyLinkageScalarFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'offer_ref', $applied, 'offer_reference');
        $this->applyLinkageScalarFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'itinerary_ref', $applied, 'itinerary_reference');
        $this->applyNumericLinkageFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'pricing_information_index', $applied);
        $this->applyNumericLinkageFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'itinerary_index', $applied);
        $this->applyLinkageScalarFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'validating_carrier', $applied);
        $this->applyLinkageScalarFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'distribution_channel', $applied);
        $this->applyLinkageScalarFromHandoffOrRawTopLevel($ctx, $handoff, $raw, 'shop_endpoint_path', $applied);
        $this->applyPerSegmentArraysFromHandoff($ctx, $handoff, $applied);

        $merged = $this->mergeFlatScalars($ctx, $ids);
        $candidates = $this->collectRefCandidates($merged);

        if (trim((string) ($ctx['pricing_information_ref'] ?? '')) === ''
            && trim((string) ($ctx['pricing_information_id'] ?? '')) === '') {
            $pi = $this->firstCandidateScalar($candidates['pricing_information_ref'])
                ?? $this->firstCandidateScalar($candidates['pricing_information_id']);
            if ($pi !== null) {
                $ctx['pricing_information_ref'] = $pi;
                $applied[] = 'pricing_information_ref';
            }
        }

        if (trim((string) ($ctx['offer_ref'] ?? '')) === ''
            && trim((string) ($ctx['offer_id'] ?? '')) === '') {
            $offer = $this->firstCandidateScalar($candidates['offer_reference']);
            if ($offer !== null) {
                $ctx['offer_ref'] = $offer;
                $applied[] = 'offer_ref';
            }
        }

        if (trim((string) ($ctx['itinerary_ref'] ?? '')) === '') {
            $itin = $this->firstCandidateScalar($candidates['itinerary_reference']);
            if ($itin !== null) {
                $ctx['itinerary_ref'] = $itin;
                $applied[] = 'itinerary_ref';
            }
        }

        $raw['sabre_shop_context'] = $ctx;
        $snapshot['raw_payload'] = $raw;

        $after = $this->assessReadiness($snapshot);

        return [
            'snapshot' => $snapshot,
            'applied_fields' => array_values(array_unique($applied)),
            'readiness_before' => $before,
            'readiness_after' => $after,
        ];
    }

    /**
     * @param  list<string>  $entries
     */
    protected function firstCandidateScalar(array $entries): ?string
    {
        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            $value = $this->candidateEntryValue($entry);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function candidateEntryValue(string $entry): string
    {
        $pos = strpos($entry, '=');

        return $this->capScalar($pos === false ? $entry : substr($entry, $pos + 1));
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $applied
     */
    protected function applyLinkageScalarFromHandoffOrRawTopLevel(
        array &$ctx,
        array $handoff,
        array $raw,
        string $contextKey,
        array &$applied,
        ?string $alternateRawKey = null,
    ): void {
        if (trim((string) ($ctx[$contextKey] ?? '')) !== '') {
            return;
        }
        $rawKey = $alternateRawKey ?? $contextKey;
        $fromRaw = trim((string) ($raw[$rawKey] ?? ''));
        $handoffKey = $alternateRawKey ?? ($contextKey === 'offer_ref' ? 'offer_reference' : $contextKey);
        $fromHandoff = trim((string) ($handoff[$handoffKey] ?? $handoff[$contextKey] ?? ''));
        $value = $fromRaw !== '' ? $fromRaw : $fromHandoff;
        if ($value === '') {
            return;
        }
        $ctx[$contextKey] = substr($value, 0, 120);
        $applied[] = $contextKey;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $applied
     */
    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $handoff
     * @param  list<string>  $applied
     */
    protected function applyPerSegmentArraysFromHandoff(array &$ctx, array $handoff, array &$applied): void
    {
        foreach (['booking_classes_by_segment', 'fare_basis_codes_by_segment', 'leg_refs', 'schedule_refs'] as $key) {
            $fromCtx = is_array($ctx[$key] ?? null) ? $ctx[$key] : [];
            if ($fromCtx !== []) {
                continue;
            }
            $fromHandoff = is_array($handoff[$key] ?? null) ? $handoff[$key] : [];
            if ($fromHandoff === []) {
                continue;
            }
            $ctx[$key] = $fromHandoff;
            $applied[] = $key;
        }
    }

    protected function applyNumericLinkageFromHandoffOrRawTopLevel(
        array &$ctx,
        array $handoff,
        array $raw,
        string $contextKey,
        array &$applied,
    ): void {
        if (array_key_exists($contextKey, $ctx) && is_numeric($ctx[$contextKey])) {
            return;
        }
        $fromRaw = array_key_exists($contextKey, $raw) && is_numeric($raw[$contextKey]) ? (int) $raw[$contextKey] : null;
        $fromHandoff = array_key_exists($contextKey, $handoff) && is_numeric($handoff[$contextKey]) ? (int) $handoff[$contextKey] : null;
        $value = $fromRaw ?? $fromHandoff;
        if ($value === null) {
            return;
        }
        $ctx[$contextKey] = $value;
        $applied[] = $contextKey;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $handoff
     */
    protected function isBfmGdsChannel(array $ctx, array $raw, array $handoff): bool
    {
        foreach ([$ctx, $raw, $handoff] as $map) {
            if (strcasecmp(trim((string) ($map['distribution_channel'] ?? '')), 'GDS') === 0) {
                return true;
            }
            $path = strtolower(trim((string) ($map['shop_endpoint_path'] ?? '')));
            if ($path !== '' && str_contains($path, '/v4/offers/shop')) {
                return true;
            }
        }

        if ($ctx === []) {
            return false;
        }

        foreach (['pricing_subsource', 'fare_source', 'itinerary_source'] as $key) {
            $v = strtolower(trim((string) ($ctx[$key] ?? '')));
            if ($v !== '' && str_contains($v, 'ndc')) {
                return false;
            }
        }

        if ($this->resolvePricingInformationIndex($ctx, $raw, $handoff) === null) {
            return false;
        }

        return trim((string) ($ctx['itinerary_ref'] ?? '')) !== ''
            || trim((string) ($raw['itinerary_reference'] ?? '')) !== ''
            || trim((string) ($handoff['itinerary_reference'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $handoff
     */
    protected function resolvePricingInformationIndex(array $ctx, array $raw, array $handoff): ?int
    {
        foreach ([$ctx, $handoff, $raw] as $map) {
            if (array_key_exists('pricing_information_index', $map) && is_numeric($map['pricing_information_index'])) {
                return (int) $map['pricing_information_index'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $handoff
     */
    protected function segmentSliceCount(array $snapshot, array $handoff): int
    {
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        if ($segments !== []) {
            return count($segments);
        }

        return max(0, (int) ($handoff['segment_slice_count'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $handoff
     * @return array<int|string, mixed>
     */
    protected function mergedPerSegmentList(array $ctx, array $handoff, string $key): array
    {
        $fromCtx = is_array($ctx[$key] ?? null) ? $ctx[$key] : [];
        $fromHandoff = is_array($handoff[$key] ?? null) ? $handoff[$key] : [];

        return $fromHandoff !== [] ? $fromHandoff : $fromCtx;
    }

    /**
     * @param  array<int|string, mixed>  $bySeg
     */
    protected function perSegmentStringListComplete(array $bySeg, int $expectedCount): bool
    {
        if ($expectedCount <= 0) {
            return false;
        }
        $filled = 0;
        for ($i = 0; $i < $expectedCount; $i++) {
            if (isset($bySeg[$i]) && trim((string) $bySeg[$i]) !== '') {
                $filled++;
            }
        }

        return $filled === $expectedCount;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, string>
     */
    protected function perSegmentListFromSnapshotSegments(array $snapshot, string $kind): array
    {
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $out = [];
        foreach ($segments as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            if ($kind === 'booking') {
                $value = strtoupper(trim((string) (
                    $seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? $seg['resBookDesigCode'] ?? ''
                )));
            } else {
                $value = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? '')));
            }
            if ($value !== '') {
                $out[(int) $i] = $value;
            }
        }

        return $out;
    }

    /**
     * B2A: Readiness for one Sabre branded-fare option (metadata only; does not change {@see assessReadiness()} gates).
     *
     * @param  array<string, mixed>  $option  Row from {@see SabreFlightSearchNormalizer::buildBrandedFaresFromItinerary()}
     * @return array{
     *   has_revalidation_linkage: bool,
     *   has_segment_booking_linkage: bool,
     *   ready_for_revalidation: bool,
     *   ready_for_booking_payload: bool,
     *   readiness_reasons: list<string>
     * }
     */
    public function assessBrandedFareOptionReadiness(array $option): array
    {
        $reasons = [];
        $linkage = is_array($option['linkage_summary'] ?? null) ? $option['linkage_summary'] : [];

        $piIndex = isset($option['pricing_information_index']) && is_numeric($option['pricing_information_index'])
            ? (int) $option['pricing_information_index']
            : null;
        $priceTotal = (float) ($option['price_total'] ?? 0);
        $currency = trim((string) ($option['currency'] ?? ''));

        $hasPricingRef = ($linkage['explicit_pricing_ref_present'] ?? false) === true
            || trim((string) ($option['pricing_information_ref'] ?? '')) !== '';
        $hasPricingId = ($linkage['has_pricing_information_id'] ?? false) === true
            || trim((string) ($option['pricing_information_id'] ?? '')) !== '';
        $hasOfferRef = ($linkage['has_offer_ref'] ?? false) === true
            || trim((string) ($option['offer_ref'] ?? '')) !== '';
        $hasOfferId = ($linkage['has_offer_id'] ?? false) === true
            || trim((string) ($option['supplier_offer_id'] ?? '')) !== '';
        $stableOfferLinkage = $hasOfferRef || $hasOfferId;
        $hasStablePricingRef = $hasPricingRef || $stableOfferLinkage;

        $itinPresent = ($linkage['itinerary_ref_present'] ?? false) === true;
        $fcRefCount = (int) ($linkage['fare_component_ref_count'] ?? 0);
        $enoughShopContext = $piIndex !== null
            && $itinPresent
            && ($hasStablePricingRef || ($hasPricingId && $fcRefCount > 0));

        if ($piIndex === null) {
            $reasons[] = 'missing_pricing_information_index';
        }
        if ($priceTotal <= 0) {
            $reasons[] = 'missing_price';
        }
        if ($currency === '') {
            $reasons[] = 'missing_currency';
        }

        $indexOnly = $piIndex !== null
            && ! $hasPricingRef
            && ! $stableOfferLinkage
            && ! ($hasPricingId && $fcRefCount > 0 && $itinPresent);
        if ($indexOnly) {
            $reasons[] = 'index_only_linkage';
        }
        if (! $hasStablePricingRef && ! $enoughShopContext) {
            $reasons[] = 'missing_pricing_ref';
        }

        $hasRevalidationLinkage = $hasStablePricingRef || $enoughShopContext;

        $fareBasisList = is_array($option['fare_basis_codes'] ?? null) ? $option['fare_basis_codes'] : [];
        $fareBasisBySeg = is_array($option['fare_basis_codes_by_segment'] ?? null) ? $option['fare_basis_codes_by_segment'] : [];
        $hasFareBasis = $this->nonEmptyStringList($fareBasisList) || $this->nonEmptyStringList($fareBasisBySeg);
        if (! $hasFareBasis) {
            $reasons[] = 'missing_fare_basis';
        }

        $bookingBySeg = is_array($option['booking_classes_by_segment'] ?? null) ? $option['booking_classes_by_segment'] : [];
        $segSliceCount = (int) ($option['segment_slice_count'] ?? count($bookingBySeg));
        $bookingFilled = 0;
        foreach ($bookingBySeg as $bc) {
            if (is_string($bc) && trim($bc) !== '') {
                $bookingFilled++;
            }
        }
        $hasSegmentBookingLinkage = $segSliceCount > 0 && $bookingFilled === $segSliceCount;
        if ($segSliceCount > 0 && $bookingFilled < $segSliceCount) {
            $reasons[] = 'missing_booking_class';
        }
        if ($segSliceCount === 0) {
            $reasons[] = 'missing_segment_booking_slices';
        }

        $readyForRevalidation = $piIndex !== null
            && $priceTotal > 0
            && $currency !== ''
            && $hasRevalidationLinkage
            && ! $indexOnly;

        $readyForBookingPayload = $readyForRevalidation
            && $hasFareBasis
            && $hasSegmentBookingLinkage;

        return [
            'has_revalidation_linkage' => $hasRevalidationLinkage,
            'has_segment_booking_linkage' => $hasSegmentBookingLinkage,
            'ready_for_revalidation' => $readyForRevalidation,
            'ready_for_booking_payload' => $readyForBookingPayload,
            'readiness_reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  mixed  $list
     */
    protected function nonEmptyStringList($list): bool
    {
        if (! is_array($list)) {
            return false;
        }
        foreach ($list as $v) {
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $ids
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function assessReadinessFromParts(array $ctx, array $ids, array $snapshot): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        if ($handoff === [] && is_array($snapshot['sabre_booking_context'] ?? null)) {
            $handoff = $snapshot['sabre_booking_context'];
        }

        $merged = $this->mergeFlatScalars($ctx, $ids);
        $candidates = $this->collectRefCandidates($merged);

        $hasPricingInformationRef = trim((string) ($ctx['pricing_information_ref'] ?? '')) !== ''
            || trim((string) ($ctx['pricing_information_id'] ?? '')) !== ''
            || trim((string) ($raw['pricing_information_ref'] ?? '')) !== ''
            || trim((string) ($handoff['pricing_information_ref'] ?? '')) !== ''
            || $this->identifierHasExplicitPricingRef($ids)
            || $candidates['pricing_information_ref'] !== [];

        $hasOfferReference = trim((string) ($ctx['offer_ref'] ?? '')) !== ''
            || trim((string) ($ctx['offer_id'] ?? '')) !== ''
            || trim((string) ($raw['offer_reference'] ?? '')) !== ''
            || trim((string) ($handoff['offer_reference'] ?? '')) !== ''
            || $this->identifierHasExplicitOfferRef($ids)
            || $candidates['offer_reference'] !== [];

        $hasItineraryReference = trim((string) ($ctx['itinerary_ref'] ?? '')) !== ''
            || trim((string) ($raw['itinerary_reference'] ?? '')) !== ''
            || trim((string) ($handoff['itinerary_reference'] ?? '')) !== ''
            || trim((string) ($ids['itinerary_id'] ?? '')) !== ''
            || $candidates['itinerary_reference'] !== [];

        $pricingInformationIndex = $this->resolvePricingInformationIndex($ctx, $raw, $handoff);
        $bfmPricingIndexPresent = $pricingInformationIndex !== null;

        $fcRefs = is_array($ctx['fare_component_refs'] ?? null) ? $ctx['fare_component_refs'] : [];
        $fcdRefs = is_array($ctx['fare_component_desc_refs'] ?? null) ? $ctx['fare_component_desc_refs'] : [];
        $legRefs = is_array($ctx['leg_refs'] ?? null) ? $ctx['leg_refs'] : [];
        $scheduleRefs = is_array($ctx['schedule_refs'] ?? null) ? $ctx['schedule_refs'] : [];
        $fbcFromCtx = is_array($ctx['fare_basis_codes'] ?? null) ? $ctx['fare_basis_codes'] : [];
        $fbcFromFb = $this->fareBasisFromFareBreakdown($snapshot);
        $fbcFromHandoff = $this->fareBasisFromSabreBookingContext($snapshot);
        $fbc = array_values(array_unique(array_filter(array_merge(
            array_map(static fn ($v): string => strtoupper(trim((string) $v)), $fbcFromCtx),
            $fbcFromFb,
            $fbcFromHandoff
        ), static fn (string $s): bool => $s !== '')));

        $segmentCount = $this->segmentSliceCount($snapshot, $handoff);
        $bookingBySeg = $this->mergedPerSegmentList($ctx, $handoff, 'booking_classes_by_segment');
        $fareBasisBySeg = $this->mergedPerSegmentList($ctx, $handoff, 'fare_basis_codes_by_segment');
        if ($segmentCount > 0 && ! $this->perSegmentStringListComplete($bookingBySeg, $segmentCount)) {
            $bookingBySeg = $this->perSegmentListFromSnapshotSegments($snapshot, 'booking');
        }
        if ($segmentCount > 0 && ! $this->perSegmentStringListComplete($fareBasisBySeg, $segmentCount)) {
            $fareBasisBySeg = $this->perSegmentListFromSnapshotSegments($snapshot, 'fare_basis');
        }
        $hasBookingClassesBySegment = $this->perSegmentStringListComplete($bookingBySeg, $segmentCount);
        $hasFareBasisBySegment = $this->perSegmentStringListComplete($fareBasisBySeg, $segmentCount);

        $validating = strtoupper(trim((string) ($ctx['validating_carrier'] ?? $snapshot['validating_carrier'] ?? $handoff['validating_carrier'] ?? '')));
        $hasValidatingCarrier = $validating !== '';
        $hasFareBasisCodes = $fbc !== [] || $hasFareBasisBySegment;
        $hasFareComponentRefs = $fcRefs !== [];
        $hasFareComponentDescRefs = $fcdRefs !== [];
        $hasLegScheduleRefs = $legRefs !== [] && $scheduleRefs !== [];
        $hasSelectedPassengerInfo = $this->passengerInfoCountFromSnapshot($snapshot) > 0;

        $hasRevalidationLinkageComplete = $hasPricingInformationRef
            && $hasOfferReference
            && $hasItineraryReference;

        $formalReady = $hasRevalidationLinkageComplete
            && $hasValidatingCarrier
            && $hasFareBasisCodes;

        $isBfmGds = $this->isBfmGdsChannel($ctx, $raw, $handoff);
        $bfmSegmentDescriptorsOk = $segmentCount <= 1
            || $hasFareComponentRefs
            || $hasLegScheduleRefs;
        $bfmReady = $isBfmGds
            && $hasItineraryReference
            && $bfmPricingIndexPresent
            && $hasValidatingCarrier
            && $hasBookingClassesBySegment
            && $hasFareBasisCodes
            && ($segmentCount <= 0 || $hasFareBasisBySegment || $fbc !== [])
            && $bfmSegmentDescriptorsOk;

        $autoReady = $formalReady || $bfmReady;

        if ($formalReady) {
            $policy = 'formal_ref_linkage';
            $formalPiRequired = true;
            $formalOfferRequired = true;
        } elseif ($isBfmGds) {
            $policy = $bfmReady ? 'bfm_gds_priced_itinerary' : 'bfm_gds_priced_itinerary_incomplete';
            $formalPiRequired = false;
            $formalOfferRequired = false;
        } else {
            $policy = 'formal_ref_required_missing';
            $formalPiRequired = true;
            $formalOfferRequired = true;
        }

        $missing = [];
        if ($isBfmGds && ! $bfmReady) {
            if (! $hasItineraryReference) {
                $missing[] = 'itinerary_reference';
            }
            if (! $bfmPricingIndexPresent) {
                $missing[] = 'pricing_information_index';
            }
            if (! $hasValidatingCarrier) {
                $missing[] = 'validating_carrier';
            }
            if (! $hasBookingClassesBySegment) {
                $missing[] = 'booking_classes_by_segment';
            }
            if (! $hasFareBasisCodes || ($segmentCount > 0 && ! $hasFareBasisBySegment && $fbc === [])) {
                $missing[] = 'fare_basis_codes_by_segment';
            }
            if ($segmentCount >= 2 && ! $bfmSegmentDescriptorsOk) {
                $missing[] = 'leg_refs_schedule_refs';
            }
        } elseif (! $formalReady && ! $isBfmGds) {
            if (! $hasPricingInformationRef) {
                $missing[] = 'pricing_information_ref';
            }
            if (! $hasOfferReference) {
                $missing[] = 'offer_reference';
            }
            if (! $hasItineraryReference) {
                $missing[] = 'itinerary_reference';
            }
            if (! $hasValidatingCarrier) {
                $missing[] = 'validating_carrier';
            }
            if (! $hasFareBasisCodes) {
                $missing[] = 'fare_basis_codes';
            }
            if (! $hasFareComponentRefs) {
                $missing[] = 'fare_component_refs';
            }
        }

        return [
            'has_selected_passenger_info' => $hasSelectedPassengerInfo,
            'has_pricing_information_ref' => $hasPricingInformationRef,
            'has_offer_reference' => $hasOfferReference,
            'has_itinerary_reference' => $hasItineraryReference,
            'has_fare_component_refs' => $hasFareComponentRefs,
            'has_fare_component_desc_refs' => $hasFareComponentDescRefs,
            'has_validating_carrier' => $hasValidatingCarrier,
            'has_fare_basis_codes' => $hasFareBasisCodes,
            'has_revalidation_linkage_complete' => $hasRevalidationLinkageComplete,
            'auto_pnr_pricing_context_ready' => $autoReady,
            'missing_pricing_context_fields' => array_values($missing),
            'pricing_context_policy' => $policy,
            'bfm_itinerary_reference_present' => $hasItineraryReference,
            'bfm_pricing_information_index_present' => $bfmPricingIndexPresent,
            'bfm_pricing_information_index' => $pricingInformationIndex,
            'formal_offer_reference_required' => $formalOfferRequired,
            'formal_pricing_information_ref_required' => $formalPiRequired,
        ];
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return array<string, bool|list<string>>
     */
    protected function readinessDigestScalars(array $readiness): array
    {
        $out = [];
        foreach ([
            'has_selected_passenger_info',
            'has_pricing_information_ref',
            'has_offer_reference',
            'has_itinerary_reference',
            'has_fare_component_refs',
            'has_fare_component_desc_refs',
            'has_validating_carrier',
            'has_fare_basis_codes',
            'has_revalidation_linkage_complete',
            'auto_pnr_pricing_context_ready',
        ] as $key) {
            $out[$key] = ($readiness[$key] ?? false) === true;
        }
        $missing = is_array($readiness['missing_pricing_context_fields'] ?? null)
            ? $readiness['missing_pricing_context_fields']
            : [];
        $out['missing_pricing_context_fields'] = array_values(array_slice(array_map(
            static fn ($v): string => substr(trim((string) $v), 0, 64),
            $missing
        ), 0, 16));

        return $out;
    }

    /**
     * @param  array<string, mixed>  $ids
     */
    protected function identifierHasExplicitOfferRef(array $ids): bool
    {
        foreach ($ids as $k => $v) {
            if (! is_string($k) || (! is_string($v) && ! is_numeric($v))) {
                continue;
            }
            $lk = strtolower($k);
            if (! str_contains($lk, 'offer')) {
                continue;
            }
            if (str_contains($lk, 'ref') || str_contains($lk, 'item') || str_ends_with($lk, '_id') || $lk === 'offer_id') {
                return trim((string) $v) !== '';
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    public function withBookingId(int $bookingId, array $digest): array
    {
        $digest['booking_id'] = $bookingId;

        return $digest;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $ids
     * @return array<string, scalar>
     */
    protected function mergeFlatScalars(array $ctx, array $ids): array
    {
        $out = [];
        foreach ([$ctx, $ids] as $map) {
            foreach ($map as $k => $v) {
                if (! is_string($k) || $this->isBlockedKey($k)) {
                    continue;
                }
                if (is_scalar($v) && $v !== '') {
                    $out[$k] = $v;
                }
            }
        }

        return $out;
    }

    protected function isBlockedKey(string $k): bool
    {
        $l = strtolower($k);
        foreach (self::PII_KEY_SUBSTRINGS as $frag) {
            if (str_contains($l, $frag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, scalar>  $flat
     * @return array<string, list<string>>
     */
    protected function collectRefCandidates(array $flat): array
    {
        $buckets = [
            'pricing_information_ref' => [],
            'pricing_information_id' => [],
            'offer_reference' => [],
            'fare_reference' => [],
            'price_quote_reference' => [],
            'itinerary_reference' => [],
            'revalidation_reference' => [],
        ];
        foreach ($flat as $k => $v) {
            if (! is_string($v) && ! is_numeric($v)) {
                continue;
            }
            $s = $this->capScalar((string) $v);
            if ($s === '') {
                continue;
            }
            $lk = strtolower($k);
            if (preg_match('/^pricing_\d+_ref$/', $lk) === 1) {
                $buckets['pricing_information_ref'][] = $k.'='.$s;
            }
            if (preg_match('/^pricing_\d+_id$/', $lk) === 1) {
                $buckets['pricing_information_id'][] = $k.'='.$s;
            }
            if (str_contains($lk, 'revalid')) {
                $buckets['revalidation_reference'][] = $k.'='.$s;
            }
            if (str_contains($lk, 'itinerary') && (str_contains($lk, 'ref') || $lk === 'itinerary_id' || str_ends_with($lk, '_id'))) {
                $buckets['itinerary_reference'][] = $k.'='.$s;
            }
            if (str_contains($lk, 'pricinginformation') || str_contains($lk, 'pricing_information') || ($lk === 'pricing_0_ref' || str_contains($lk, 'pricing_0_pricing'))) {
                if (str_contains($lk, 'ref') && ! str_contains($lk, 'offer')) {
                    $buckets['pricing_information_ref'][] = $k.'='.$s;
                }
                if (str_contains($lk, 'id') && str_contains($lk, 'pricing')) {
                    $buckets['pricing_information_id'][] = $k.'='.$s;
                }
            }
            if (str_contains($lk, 'offer') && (str_contains($lk, 'ref') || str_contains($lk, 'item') || str_contains($lk, 'id'))) {
                $buckets['offer_reference'][] = $k.'='.$s;
            }
            if (str_contains($lk, 'fare') && str_contains($lk, 'ref')) {
                $buckets['fare_reference'][] = $k.'='.$s;
            }
            if (str_contains($lk, 'pricequote') || str_contains($lk, 'price_quote')) {
                $buckets['price_quote_reference'][] = $k.'='.$s;
            }
        }
        foreach ($buckets as &$list) {
            $list = array_values(array_unique(array_slice($list, 0, 16)));
        }

        return $buckets;
    }

    /**
     * @param  array<string, scalar>  $merged
     * @return array<string, scalar>
     */
    protected function filterPricingSlice(array $merged): array
    {
        $out = [];
        foreach ($merged as $k => $v) {
            $lk = strtolower($k);
            if (str_contains($lk, 'pricing') || str_contains($lk, 'offer') || str_contains($lk, 'order')) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, scalar>  $merged
     * @param  array<string, mixed>  $fareExcerpt
     * @return array<string, scalar>
     */
    protected function filterFareSlice(array $merged, array $fareExcerpt): array
    {
        $out = [];
        foreach ($fareExcerpt as $k => $v) {
            if (is_scalar($v)) {
                $out[(string) $k] = $v;
            }
        }
        foreach ($merged as $k => $v) {
            $lk = strtolower($k);
            if (str_contains($lk, 'fare') || str_contains($lk, 'validating')) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, scalar>  $map
     * @return list<string>
     */
    protected function scalarKeysFromMap(array $map): array
    {
        $keys = array_keys($map);
        sort($keys);

        return array_slice($keys, 0, 48);
    }

    /**
     * @param  array<string, mixed>  $ids
     */
    protected function identifierHasExplicitPricingRef(array $ids): bool
    {
        foreach ($ids as $k => $v) {
            if (! is_string($k) || (! is_string($v) && ! is_numeric($v))) {
                continue;
            }
            $lk = strtolower($k);
            if (! str_contains($lk, 'pricing')) {
                continue;
            }
            if (str_contains($lk, 'ref') || str_contains($lk, 'pricinginformationref') || str_contains($lk, 'pricingref')) {
                return trim((string) $v) !== '';
            }
            if (str_contains($lk, 'offeritemid') || str_contains($lk, 'offer_item_id')) {
                return trim((string) $v) !== '';
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $ids
     */
    protected function hasPricingKeyedIdentifiers(array $ids): bool
    {
        foreach ($ids as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if (str_contains(strtolower($k), 'pricing') && $v !== '' && $v !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function hasPricingKeyedScalars(array $ctx): bool
    {
        foreach ($ctx as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if (str_contains(strtolower($k), 'pricing') && (is_scalar($v) && (string) $v !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    protected function fareBasisFromSabreBookingContext(array $snapshot): array
    {
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $handoff = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        $bySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : [];
        $out = [];
        foreach ($bySeg as $c) {
            if (is_string($c) || is_numeric($c)) {
                $s = strtoupper(trim((string) $c));
                if ($s !== '') {
                    $out[] = substr($s, 0, 32);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    protected function fareBasisFromFareBreakdown(array $snapshot): array
    {
        $fb = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $codes = is_array($fb['fare_basis_codes'] ?? null) ? $fb['fare_basis_codes'] : [];
        $out = [];
        foreach ($codes as $c) {
            if (is_string($c) || is_numeric($c)) {
                $s = strtoupper(trim((string) $c));
                if ($s !== '') {
                    $out[] = substr($s, 0, 32);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function passengerInfoCountFromSnapshot(array $snapshot): int
    {
        $fb = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $pc = is_array($fb['passenger_counts'] ?? null) ? $fb['passenger_counts'] : [];
        $sum = 0;
        foreach (['adults', 'children', 'infants'] as $k) {
            $sum += max(0, (int) ($pc[$k] ?? 0));
        }

        return $sum;
    }

    /**
     * @param  array<int|string, mixed>  $list
     * @return list<string>
     */
    protected function capList(array $list): array
    {
        $out = [];
        foreach (array_slice($list, 0, 24) as $item) {
            if (is_scalar($item)) {
                $out[] = $this->capScalar((string) $item);
            }
        }

        return $out;
    }

    protected function capScalar(string $v): string
    {
        $v = trim($v);
        if (strlen($v) <= self::VALUE_CAP) {
            return $v;
        }

        return substr($v, 0, self::VALUE_CAP);
    }
}
