<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\FlightSearch\SabreOfferFreshness;
use App\Support\Security\SensitiveDataRedactor;
use Carbon\CarbonImmutable;

/**
 * C3: Re-shop a stored booking itinerary and optionally refresh {@code meta.flight_offer_snapshot}
 * (admin/local inspect only — no PNR, no payment changes).
 * C4: {@see validateCurrentSnapshotAgainstFreshItinerary()} — full-itinerary confirmation for certification PNR guard fallback.
 */
class SabreBookingOfferRefreshService
{
    private const PRICE_UNCHANGED_THRESHOLD = 0.01;

    /** @var list<string> */
    public const REFRESH_STAGE_DIAGNOSTIC_KEYS = [
        'refresh_stage',
        'refresh_exception_class',
        'refresh_exception_code',
        'refresh_exception_message_safe',
        'fresh_search_attempted',
        'fresh_search_result_present',
        'fresh_search_error_code',
        'match_attempted',
        'match_found',
        'apply_refresh_attempted',
        'meta_stamp_attempted',
    ];

    public function __construct(
        protected FlightSearchService $flightSearch,
        protected SabreSegmentFreshShopSellabilityService $segmentExtractor,
    ) {}

    /**
     * C4: Full-itinerary re-shop vs stored snapshot (dry-run only). Stricter than {@see refresh()} {@code can_apply}:
     * requires same RBD list and unchanged supplier total before {@code can_trust_for_pnr}.
     *
     * @return array{
     *     full_itinerary_match: bool,
     *     same_rbd: bool,
     *     price_changed: bool,
     *     can_trust_for_pnr: bool,
     *     reasons: list<string>,
     *     match_confidence: string,
     *     existing_rbd_list: list<string>,
     *     fresh_rbd_list: list<string>
     * }
     */
    public function validateCurrentSnapshotAgainstFreshItinerary(Booking $booking): array
    {
        $dry = $this->dryRunMatchAgainstFreshShop($booking);
        $reasons = is_array($dry['reasons'] ?? null) ? $dry['reasons'] : [];
        $error = trim((string) ($dry['error'] ?? ''));

        if ($error !== '') {
            return [
                'full_itinerary_match' => false,
                'same_rbd' => false,
                'price_changed' => false,
                'can_trust_for_pnr' => false,
                'reasons' => $reasons !== [] ? $reasons : [$error],
                'match_confidence' => '',
                'existing_rbd_list' => is_array($dry['existing_rbd_list'] ?? null) ? $dry['existing_rbd_list'] : [],
                'fresh_rbd_list' => is_array($dry['fresh_rbd_list'] ?? null) ? $dry['fresh_rbd_list'] : [],
            ];
        }

        $fullMatch = ($dry['match_found'] ?? false) === true
            && (string) ($dry['match_confidence'] ?? '') === 'high'
            && ($dry['same_route_chain'] ?? false) === true
            && ($dry['same_flight_numbers'] ?? false) === true
            && ($dry['same_departure_times'] ?? false) === true;

        $existingRbd = is_array($dry['existing_rbd_list'] ?? null) ? $dry['existing_rbd_list'] : [];
        $freshRbd = is_array($dry['fresh_rbd_list'] ?? null) ? $dry['fresh_rbd_list'] : [];
        $sameRbd = $existingRbd !== [] && $existingRbd === $freshRbd;
        $priceChanged = ($dry['price_changed'] ?? false) === true;

        $trustReasons = [];
        if (! $fullMatch) {
            $trustReasons[] = 'full_itinerary_match_failed';
        }
        if (! $sameRbd) {
            $trustReasons[] = 'rbd_list_mismatch';
        }
        if ($priceChanged) {
            $trustReasons[] = 'price_changed';
        }

        $canTrust = $fullMatch && $sameRbd && ! $priceChanged;
        $confirmedReasons = ['full_itinerary_confirmed'];
        if (! $canTrust && SabreOfferRefreshAcceptance::acceptanceAllowsFullItineraryTrust($booking, [
            'full_itinerary_match' => $fullMatch,
            'same_rbd' => $sameRbd,
            'price_changed' => $priceChanged,
        ])) {
            $canTrust = true;
            $confirmedReasons = ['full_itinerary_confirmed', 'offer_refresh_accepted'];
        }

        return [
            'full_itinerary_match' => $fullMatch,
            'same_rbd' => $sameRbd,
            'price_changed' => $priceChanged,
            'can_trust_for_pnr' => $canTrust,
            'reasons' => $canTrust ? $confirmedReasons : ($trustReasons !== [] ? $trustReasons : $reasons),
            'match_confidence' => (string) ($dry['match_confidence'] ?? ''),
            'existing_rbd_list' => $existingRbd,
            'fresh_rbd_list' => $freshRbd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(Booking $booking, bool $apply): array
    {
        $stageDiag = $this->initialRefreshStageDiagnostics();

        try {
            $dry = $this->dryRunMatchAgainstFreshShop($booking);
            $stageDiag = $this->mergeStageDiagnostics($stageDiag, $dry);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $error = trim((string) ($dry['error'] ?? ''));

            if ($error !== '') {
                return $this->basePayload($booking, $meta, array_merge([
                    'error' => $error,
                    'match_found' => false,
                    'match_confidence' => '',
                    'can_apply' => false,
                ], array_intersect_key($dry, array_flip([
                    'reasons', 'existing_route_chain', 'existing_flight_numbers', 'existing_rbd_list',
                    'existing_fare_basis_list', 'existing_supplier_total', 'currency',
                ])), $this->publicStageDiagnostics($stageDiag)));
            }

            $payload = $this->basePayload($booking, $meta, array_merge([
                'match_found' => $dry['match_found'],
                'match_confidence' => $dry['match_confidence'],
                'existing_route_chain' => $dry['existing_route_chain'],
                'fresh_route_chain' => $dry['fresh_route_chain'],
                'existing_flight_numbers' => $dry['existing_flight_numbers'],
                'fresh_flight_numbers' => $dry['fresh_flight_numbers'],
                'existing_rbd_list' => $dry['existing_rbd_list'],
                'fresh_rbd_list' => $dry['fresh_rbd_list'],
                'existing_fare_basis_list' => $dry['existing_fare_basis_list'],
                'fresh_fare_basis_list' => $dry['fresh_fare_basis_list'],
                'existing_supplier_total' => $dry['existing_supplier_total'],
                'fresh_supplier_total' => $dry['fresh_supplier_total'],
                'price_changed' => $dry['price_changed'],
                'price_delta' => $dry['price_delta'],
                'currency' => $dry['currency'],
                'can_apply' => $dry['can_apply'],
                'reasons' => $dry['reasons'],
                'applied' => false,
                'same_route_chain' => $dry['same_route_chain'],
                'same_flight_numbers' => $dry['same_flight_numbers'],
                'same_departure_times' => $dry['same_departure_times'],
            ], is_array($dry['fresh_offer'] ?? null) ? [] : [], $this->publicStageDiagnostics($stageDiag)));

            if (! ($dry['match_found'] ?? false) || ! is_array($dry['fresh_offer'] ?? null)) {
                return $payload;
            }

            if (! $apply) {
                return $payload;
            }

            if (! ($dry['can_apply'] ?? false)) {
                $payload['reasons'] = [...(is_array($dry['reasons'] ?? null) ? $dry['reasons'] : []), 'apply_blocked'];

                return $payload;
            }

            $stageDiag['refresh_stage'] = 'applying_refreshed_offer';
            $stageDiag['apply_refresh_attempted'] = true;

            /** @var array<string, mixed> $freshOffer */
            $freshOffer = $dry['fresh_offer'];
            $existingSegments = is_array($dry['existing_segments'] ?? null) ? $dry['existing_segments'] : [];
            $existingRbd = is_array($dry['existing_rbd_list'] ?? null) ? $dry['existing_rbd_list'] : [];
            $existingTotal = (float) ($dry['existing_supplier_total'] ?? 0);
            $priceChanged = ($dry['price_changed'] ?? false) === true;
            $snapshot = is_array($dry['snapshot'] ?? null) ? $dry['snapshot'] : [];
            $summary = $this->buildPreviousSnapshotSummary($snapshot, $existingSegments, $existingTotal);
            $refreshedAt = now()->toIso8601String();

            $redactedFreshOffer = SensitiveDataRedactor::redact($freshOffer);
            $meta['flight_offer_snapshot'] = $redactedFreshOffer;
            $meta['flight_offer_snapshot_refreshed_at'] = $refreshedAt;
            foreach (['normalized_offer_snapshot', 'validated_offer_snapshot'] as $aliasKey) {
                if (array_key_exists($aliasKey, $meta)) {
                    $meta[$aliasKey] = $redactedFreshOffer;
                }
            }
            $meta['previous_offer_snapshot_summary'] = $summary;
            $meta['offer_refresh_status'] = 'refreshed';
            $meta['offer_refresh_reason'] = $this->refreshReason($existingRbd, is_array($dry['fresh_rbd_list'] ?? null) ? $dry['fresh_rbd_list'] : [], $priceChanged);
            $meta['offer_refresh_price_changed'] = $priceChanged;
            $meta['offer_refresh_requires_customer_confirmation'] = $priceChanged;
            if ($priceChanged) {
                SabreOfferRefreshAcceptance::writePriceChangeMeta(
                    $meta,
                    $existingTotal,
                    (float) ($dry['fresh_supplier_total'] ?? 0),
                    (string) ($dry['currency'] ?? ''),
                );
            } else {
                $meta[SabreOfferRefreshAcceptance::META_ACCEPTED] = false;
                unset(
                    $meta[SabreOfferRefreshAcceptance::META_OLD_SUPPLIER_TOTAL],
                    $meta[SabreOfferRefreshAcceptance::META_NEW_SUPPLIER_TOTAL],
                    $meta[SabreOfferRefreshAcceptance::META_PRICE_DELTA],
                    $meta[SabreOfferRefreshAcceptance::META_CURRENCY],
                    $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_AT],
                    $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_BY],
                );
                $meta = app(SabreOfferFreshness::class)->stampBookingMetaAfterSuccessfulOfferRefresh(
                    $meta,
                    CarbonImmutable::parse($refreshedAt),
                );
            }

            $stageDiag['refresh_stage'] = 'stamping_booking_meta';
            $stageDiag['meta_stamp_attempted'] = true;

            $booking->meta = $meta;
            $booking->save();
            app(SabreSafeRefreshContext::class)->stampAfterSuccessfulRefresh($booking->fresh());

            $payload = array_merge($payload, $this->publicStageDiagnostics($stageDiag));
            $payload['applied'] = true;
            $payload['offer_refresh_status'] = 'refreshed';
            $payload['offer_refresh_requires_customer_confirmation'] = $priceChanged;

            return $payload;
        } catch (\Throwable $e) {
            $meta = is_array($booking->meta) ? $booking->meta : [];

            return $this->basePayload($booking, $meta, array_merge(
                $this->exceptionRefreshPayload($e, $stageDiag),
                ['match_found' => false, 'match_confidence' => '', 'can_apply' => false, 'applied' => false],
            ));
        }
    }

    /**
     * Shared C3 dry-run: full-itinerary search + best match (no booking meta writes).
     *
     * @return array<string, mixed>
     */
    protected function dryRunMatchAgainstFreshShop(Booking $booking): array
    {
        $stageDiag = $this->initialRefreshStageDiagnostics();

        try {
            $stageDiag['refresh_stage'] = 'resolving_context';
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $snapshot = $this->resolveOfferSnapshot($meta);
            if ($snapshot === null) {
                return array_merge(
                    ['error' => 'missing_offer_snapshot', 'reasons' => ['missing_offer_snapshot']],
                    $this->publicStageDiagnostics($stageDiag),
                );
            }

            $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
            if ($provider !== SupplierProvider::Sabre->value
                && strtolower(trim((string) ($snapshot['supplier_provider'] ?? ''))) !== SupplierProvider::Sabre->value) {
                return array_merge(
                    ['error' => 'not_sabre_booking', 'reasons' => ['not_sabre_booking']],
                    $this->publicStageDiagnostics($stageDiag),
                );
            }

            $criteria = app(SabreSafeRefreshContext::class)->resolveSearchCriteriaForRefresh($meta);
            if ($criteria === []) {
                return array_merge(
                    ['error' => 'missing_search_criteria', 'reasons' => ['missing_search_criteria']],
                    $this->publicStageDiagnostics($stageDiag),
                );
            }

            $existingSegments = $this->segmentExtractor->extractStoredSegmentsFromOfferSnapshot($snapshot);
            if ($existingSegments === []) {
                return array_merge(
                    ['error' => 'missing_stored_segments', 'reasons' => ['missing_stored_segments']],
                    $this->publicStageDiagnostics($stageDiag),
                );
            }

            $agency = Agency::query()->find($booking->agency_id);
            if ($agency === null) {
                return array_merge(
                    ['error' => 'missing_agency', 'reasons' => ['missing_agency']],
                    $this->publicStageDiagnostics($stageDiag),
                );
            }

            $stageDiag['refresh_stage'] = 'building_search_criteria';
            $searchCriteria = $criteria;
            if (trim((string) ($booking->currency ?? '')) !== '') {
                $searchCriteria['currency'] = $booking->currency;
            }

            $stageDiag['refresh_stage'] = 'calling_flight_search';
            $stageDiag['fresh_search_attempted'] = true;
            $search = $this->flightSearch->searchWithMeta($searchCriteria, $agency, 'admin_offer_refresh');

            $stageDiag['refresh_stage'] = 'parsing_fresh_search_result';
            $allOffers = is_array($search['offers'] ?? null) ? $search['offers'] : [];
            $stageDiag['fresh_search_result_present'] = $allOffers !== [];
            $freshSearchErrorCode = $this->safeFreshSearchErrorCode($search);
            if ($freshSearchErrorCode !== null) {
                $stageDiag['fresh_search_error_code'] = $freshSearchErrorCode;
            }

            $candidates = array_values(array_filter(
                $allOffers,
                fn ($row): bool => is_array($row)
                    && strtolower(trim((string) ($row['supplier_provider'] ?? ''))) === SupplierProvider::Sabre->value,
            ));

            $stageDiag['refresh_stage'] = 'matching_itinerary';
            $stageDiag['match_attempted'] = true;
            $match = $this->findBestMatch($existingSegments, $snapshot, $candidates);
            $stageDiag['match_found'] = ($match['match_found'] ?? false) === true;
            $existingRbd = $this->rbdListFromSegments($existingSegments);
            $existingFb = $this->fareBasisListFromSegments($existingSegments);
            $existingTotal = $this->supplierTotalFromOffer($snapshot);
            $currency = $this->resolveCurrency($snapshot, $booking);

            $comparison = $match['itinerary_comparison'] ?? [];
            $sameRoute = ($comparison['same_route_chain'] ?? false) === true;
            $sameFlights = ($comparison['same_flight_numbers'] ?? false) === true;
            $sameTimes = ($comparison['same_departure_times'] ?? false) === true;

            $payload = array_merge([
                'error' => '',
                'snapshot' => $snapshot,
                'existing_segments' => $existingSegments,
                'match_found' => $match['match_found'],
                'match_confidence' => $match['match_confidence'],
                'same_route_chain' => $sameRoute,
                'same_flight_numbers' => $sameFlights,
                'same_departure_times' => $sameTimes,
                'existing_route_chain' => $this->routeChainFromSegments($existingSegments),
                'fresh_route_chain' => $match['fresh_route_chain'],
                'existing_flight_numbers' => $this->flightNumbersFromSegments($existingSegments),
                'fresh_flight_numbers' => $match['fresh_flight_numbers'],
                'existing_rbd_list' => $existingRbd,
                'fresh_rbd_list' => $match['fresh_rbd_list'],
                'existing_fare_basis_list' => $existingFb,
                'fresh_fare_basis_list' => $match['fresh_fare_basis_list'],
                'existing_supplier_total' => $existingTotal,
                'fresh_supplier_total' => $match['fresh_supplier_total'],
                'price_changed' => false,
                'price_delta' => 0.0,
                'currency' => $currency,
                'can_apply' => false,
                'reasons' => $match['reasons'],
                'fresh_offer' => $match['fresh_offer'],
                'itinerary_comparison' => $comparison,
            ], $this->publicStageDiagnostics($stageDiag));

            if (! $match['match_found'] || ! is_array($match['fresh_offer'])) {
                return $payload;
            }

            $freshTotal = (float) ($match['fresh_supplier_total'] ?? 0);
            $priceChanged = abs($freshTotal - $existingTotal) > self::PRICE_UNCHANGED_THRESHOLD;
            $payload['fresh_supplier_total'] = $freshTotal;
            $payload['price_changed'] = $priceChanged;
            $payload['price_delta'] = round($freshTotal - $existingTotal, 2);
            $payload['can_apply'] = $match['can_apply'];

            return $payload;
        } catch (\Throwable $e) {
            return array_merge(
                $this->exceptionRefreshPayload($e, $stageDiag),
                [
                    'match_found' => false,
                    'match_confidence' => '',
                    'can_apply' => false,
                    'reasons' => ['refresh_exception'],
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $existingSegments
     * @param  array<string, mixed>  $existingSnapshot
     * @param  list<array<string, mixed>>  $candidates
     * @return array{
     *     match_found: bool,
     *     match_confidence: string,
     *     fresh_offer: array<string, mixed>|null,
     *     fresh_route_chain: string,
     *     fresh_flight_numbers: list<string>,
     *     fresh_rbd_list: list<string>,
     *     fresh_fare_basis_list: list<string>,
     *     fresh_supplier_total: float|null,
     *     can_apply: bool,
     *     reasons: list<string>
     * }
     */
    protected function findBestMatch(array $existingSegments, array $existingSnapshot, array $candidates): array
    {
        $empty = [
            'match_found' => false,
            'match_confidence' => '',
            'fresh_offer' => null,
            'fresh_route_chain' => '',
            'fresh_flight_numbers' => [],
            'fresh_rbd_list' => [],
            'fresh_fare_basis_list' => [],
            'fresh_supplier_total' => null,
            'can_apply' => false,
            'reasons' => ['no_matching_offer_in_shop'],
        ];

        $existingVc = strtoupper(trim((string) ($existingSnapshot['validating_carrier'] ?? '')));
        $best = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $freshSegments = $this->segmentExtractor->extractStoredSegmentsFromOfferSnapshot($candidate);
            if ($freshSegments === []) {
                continue;
            }

            $comparison = $this->compareItineraries($existingSegments, $freshSegments);
            if ($comparison['score'] < 0) {
                continue;
            }

            if ($existingVc !== '') {
                $freshVc = strtoupper(trim((string) ($candidate['validating_carrier'] ?? '')));
                if ($freshVc !== '' && $freshVc !== $existingVc) {
                    $comparison['score'] -= 1;
                    $comparison['confidence'] = $comparison['confidence'] === 'high' ? 'medium' : $comparison['confidence'];
                }
            }

            if ($comparison['score'] > $bestScore) {
                $bestScore = $comparison['score'];
                $best = [
                    'candidate' => $candidate,
                    'segments' => $freshSegments,
                    'comparison' => $comparison,
                ];
            }
        }

        if ($best === null) {
            return $empty;
        }

        /** @var array<string, mixed> $offer */
        $offer = $best['candidate'];
        /** @var list<array<string, mixed>> $freshSegments */
        $freshSegments = $best['segments'];
        $comparison = $best['comparison'];
        $freshRbd = $this->rbdListFromSegments($freshSegments);
        $reasons = [];

        $canApply = $comparison['confidence'] === 'high'
            && $comparison['same_route_chain']
            && $comparison['same_flight_numbers']
            && $comparison['same_departure_times']
            && $this->supplierTotalFromOffer($offer) > 0
            && $this->segmentsHaveBookingClass($freshSegments);

        if ($comparison['confidence'] !== 'high') {
            $reasons[] = 'match_confidence_not_high';
        }
        if (! $comparison['same_route_chain']) {
            $reasons[] = 'route_chain_mismatch';
        }
        if (! $comparison['same_flight_numbers']) {
            $reasons[] = 'flight_number_mismatch';
        }
        if (! $comparison['same_departure_times']) {
            $reasons[] = 'departure_time_mismatch';
        }
        if ($this->supplierTotalFromOffer($offer) <= 0) {
            $reasons[] = 'fresh_offer_missing_price';
        }
        if (! $this->segmentsHaveBookingClass($freshSegments)) {
            $reasons[] = 'fresh_offer_missing_rbd';
        }

        if ($canApply) {
            $reasons = ['ready_to_apply'];
        }

        return [
            'match_found' => true,
            'match_confidence' => $comparison['confidence'],
            'fresh_offer' => $offer,
            'fresh_route_chain' => $this->routeChainFromSegments($freshSegments),
            'fresh_flight_numbers' => $this->flightNumbersFromSegments($freshSegments),
            'fresh_rbd_list' => $freshRbd,
            'fresh_fare_basis_list' => $this->fareBasisListFromSegments($freshSegments),
            'fresh_supplier_total' => $this->supplierTotalFromOffer($offer),
            'can_apply' => $canApply,
            'reasons' => $reasons,
            'itinerary_comparison' => $comparison,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $fresh
     * @return array{
     *     score: int,
     *     confidence: string,
     *     same_route_chain: bool,
     *     same_flight_numbers: bool,
     *     same_departure_times: bool
     * }
     */
    protected function compareItineraries(array $existing, array $fresh): array
    {
        if (count($existing) !== count($fresh)) {
            return [
                'score' => -1,
                'confidence' => '',
                'same_route_chain' => false,
                'same_flight_numbers' => false,
                'same_departure_times' => false,
            ];
        }

        $sameRoute = true;
        $sameFlights = true;
        $sameTimes = true;

        foreach ($existing as $i => $stored) {
            $hit = $fresh[$i] ?? null;
            if (! is_array($hit)) {
                return [
                    'score' => -1,
                    'confidence' => '',
                    'same_route_chain' => false,
                    'same_flight_numbers' => false,
                    'same_departure_times' => false,
                ];
            }

            $routeOk = ($stored['origin'] ?? '') === ($hit['origin'] ?? '')
                && ($stored['destination'] ?? '') === ($hit['destination'] ?? '');
            $flightOk = SabreSegmentFreshShopSellabilityService::marketingFlightLabel(
                $stored['carrier'] ?? '',
                $stored['flight_number'] ?? '',
            ) === SabreSegmentFreshShopSellabilityService::marketingFlightLabel(
                $hit['carrier'] ?? '',
                $hit['flight_number'] ?? '',
            );
            $timeOk = $this->sameDepartureInstant(
                (string) ($stored['departure_at'] ?? ''),
                (string) ($hit['departure_at'] ?? ''),
            );

            $sameRoute = $sameRoute && $routeOk;
            $sameFlights = $sameFlights && $flightOk;
            $sameTimes = $sameTimes && $timeOk;
        }

        if (! $sameFlights) {
            return [
                'score' => -1,
                'confidence' => '',
                'same_route_chain' => $sameRoute,
                'same_flight_numbers' => false,
                'same_departure_times' => $sameTimes,
            ];
        }

        $score = 0;
        if ($sameRoute) {
            $score += 2;
        }
        if ($sameFlights) {
            $score += 3;
        }
        if ($sameTimes) {
            $score += 3;
        }

        $confidence = 'low';
        if ($sameRoute && $sameFlights && $sameTimes) {
            $confidence = 'high';
        } elseif ($sameFlights && $sameTimes) {
            $confidence = 'medium';
        }

        return [
            'score' => $score,
            'confidence' => $confidence,
            'same_route_chain' => $sameRoute,
            'same_flight_numbers' => $sameFlights,
            'same_departure_times' => $sameTimes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected function segmentsHaveBookingClass(array $segments): bool
    {
        foreach ($segments as $seg) {
            if (trim((string) ($seg['booking_class'] ?? '')) === '') {
                return false;
            }
        }

        return $segments !== [];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function rbdListFromSegments(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            $cls = strtoupper(trim((string) ($seg['booking_class'] ?? '')));
            $out[] = $cls;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function fareBasisListFromSegments(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            $out[] = trim((string) ($seg['fare_basis_code'] ?? ''));
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<string>
     */
    protected function flightNumbersFromSegments(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            $label = SabreSegmentFreshShopSellabilityService::marketingFlightLabel(
                $seg['carrier'] ?? '',
                $seg['flight_number'] ?? '',
            );
            $out[] = $label !== '' ? $label : trim((string) ($seg['flight_number'] ?? ''));
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected function routeChainFromSegments(array $segments): string
    {
        $legs = [];
        foreach ($segments as $seg) {
            $o = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $d = strtoupper(trim((string) ($seg['destination'] ?? '')));
            if ($o !== '' && $d !== '') {
                $legs[] = $o.'-'.$d;
            }
        }

        return implode(',', $legs);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function supplierTotalFromOffer(array $offer): float
    {
        $fare = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
        $explicit = (float) ($fare['supplier_total'] ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        $source = (float) ($offer['supplier_total_source'] ?? 0);
        if ($source > 0) {
            return $source;
        }

        return (float) ($offer['total'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    protected function buildPreviousSnapshotSummary(array $snapshot, array $segments, float $supplierTotal): array
    {
        return [
            'route_chain' => $this->routeChainFromSegments($segments),
            'flight_numbers' => $this->flightNumbersFromSegments($segments),
            'rbd_list' => $this->rbdListFromSegments($segments),
            'fare_basis_list' => $this->fareBasisListFromSegments($segments),
            'supplier_total' => $supplierTotal,
            'validating_carrier' => strtoupper(trim((string) ($snapshot['validating_carrier'] ?? ''))) ?: null,
            'offer_id' => (string) ($snapshot['id'] ?? $snapshot['offer_id'] ?? ''),
        ];
    }

    /**
     * @param  list<string>  $existingRbd
     * @param  list<string>  $freshRbd
     */
    protected function refreshReason(array $existingRbd, array $freshRbd, bool $priceChanged): string
    {
        if ($priceChanged) {
            return 'rbd_or_price_changed';
        }

        return $existingRbd !== $freshRbd ? 'rbd_or_price_changed' : 'inventory_refresh';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    protected function resolveOfferSnapshot(array $meta): ?array
    {
        $snap = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);

        return $snap !== [] ? $snap : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function basePayload(Booking $booking, array $meta, array $extra): array
    {
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];

        return array_merge([
            'booking_id' => $booking->id,
            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function resolveCurrency(array $snapshot, Booking $booking): string
    {
        $fare = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];

        return trim((string) (
            $booking->currency
            ?? $fare['currency']
            ?? $snapshot['currency']
            ?? 'PKR'
        )) ?: 'PKR';
    }

    protected function sameDepartureInstant(string $storedIso, string $freshIso): bool
    {
        $storedIso = trim($storedIso);
        $freshIso = trim($freshIso);
        if ($storedIso === '' || $freshIso === '') {
            return false;
        }

        try {
            $a = CarbonImmutable::parse($storedIso);
            $b = CarbonImmutable::parse($freshIso);
        } catch (\Throwable) {
            return strtoupper($storedIso) === strtoupper($freshIso);
        }

        return abs($a->diffInSeconds($b, false)) <= 120;
    }

    /**
     * @return array<string, mixed>
     */
    protected function initialRefreshStageDiagnostics(): array
    {
        return [
            'refresh_stage' => '',
            'fresh_search_attempted' => false,
            'fresh_search_result_present' => false,
            'fresh_search_error_code' => null,
            'match_attempted' => false,
            'match_found' => false,
            'apply_refresh_attempted' => false,
            'meta_stamp_attempted' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    protected function mergeStageDiagnostics(array $base, array $overlay): array
    {
        foreach (self::REFRESH_STAGE_DIAGNOSTIC_KEYS as $key) {
            if (! array_key_exists($key, $overlay)) {
                continue;
            }
            $base[$key] = $overlay[$key];
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $diag
     * @return array<string, mixed>
     */
    protected function publicStageDiagnostics(array $diag): array
    {
        $out = [];
        foreach (self::REFRESH_STAGE_DIAGNOSTIC_KEYS as $key) {
            if (! array_key_exists($key, $diag)) {
                continue;
            }
            $value = $diag[$key];
            if ($value === null || $value === '') {
                if (in_array($key, ['fresh_search_error_code', 'refresh_exception_class', 'refresh_exception_message_safe'], true)) {
                    continue;
                }
                if (in_array($key, ['fresh_search_attempted', 'fresh_search_result_present', 'match_attempted', 'match_found', 'apply_refresh_attempted', 'meta_stamp_attempted'], true)) {
                    $out[$key] = (bool) $value;
                } elseif ($key === 'refresh_stage') {
                    continue;
                }

                continue;
            }
            if ($key === 'refresh_exception_code') {
                if (is_int($value) && $value !== 0) {
                    $out[$key] = $value;
                }

                continue;
            }
            $out[$key] = $value;
        }

        if (trim((string) ($diag['refresh_stage'] ?? '')) !== '') {
            $out['refresh_stage'] = (string) $diag['refresh_stage'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $stageDiag
     * @return array<string, mixed>
     */
    protected function exceptionRefreshPayload(\Throwable $e, array $stageDiag): array
    {
        $code = $e->getCode();

        return array_merge(
            $this->publicStageDiagnostics($stageDiag),
            [
                'error' => 'refresh_exception',
                'reasons' => ['refresh_exception'],
                'refresh_exception_class' => class_basename($e),
                'refresh_exception_code' => is_int($code) && $code !== 0 ? $code : null,
                'refresh_exception_message_safe' => SensitiveDataRedactor::sanitizeErrorMessage($e->getMessage()),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $search
     */
    protected function safeFreshSearchErrorCode(array $search): ?string
    {
        $warnings = is_array($search['warnings'] ?? null) ? $search['warnings'] : [];
        foreach ($warnings as $warning) {
            if (! is_string($warning)) {
                continue;
            }
            $normalized = strtolower(trim($warning));
            if ($normalized === '') {
                continue;
            }
            if (str_contains($normalized, 'token') || str_contains($normalized, 'password') || str_contains($normalized, 'credential')) {
                continue;
            }

            return substr(preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?? 'search_warning', 0, 64);
        }

        return null;
    }
}
