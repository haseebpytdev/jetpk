<?php

namespace App\Support\Sabre;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreBookingOfferRefreshService;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabreControlledPnrContextDigest;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\FlightSearch\SabreOfferFreshness;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Carbon;

/**
 * F9M: Read-only controlled Sabre CPNR sellability diagnostics (context freshness, segment/brand/fare matrices, host vs payload risk).
 * No supplier HTTP by default; optional fresh shop probe via command only. No raw payloads, PII, or DB mutation.
 */
final class SabreControlledPnrSellabilityDiagnostics
{
    public const LANE_REFRESH_REQUIRED = 'refresh_required_before_retry';

    public const LANE_WEAK_REVALIDATION = 'selected_offer_not_strongly_revalidated';

    public const LANE_RBD_FARE_BASIS = 'rbd_or_fare_basis_not_sellable';

    public const LANE_BRAND_QUALIFIER = 'brand_qualifier_requires_adjustment';

    public const LANE_PRICING_QUALIFIER = 'pricing_qualifier_missing_or_unsupported';

    public const LANE_HOST_INVENTORY = 'host_inventory_or_pcc_entitlement_issue';

    public const LANE_NO_SAFE_RETRY = 'no_safe_retry_recommended';

    private const SEGMENT_MAX = 6;

    /** @var list<string> */
    private const FORBIDDEN_KEY_SUBSTRINGS = [
        'raw_payload', 'request_body', 'response_body', 'password', 'secret', 'credential',
        'passport', 'email', 'phone', 'first_name', 'last_name', 'givenname', 'surname',
        'personname', 'contactnumbers', 'document',
    ];

    public function __construct(
        protected SabrePassengerRecordsApplicationResultDigest $applicationDigest,
        protected SabreBookingService $sabreBookingService,
        protected SabreControlledPnrContextDigest $contextDigest,
        protected SabreSafeRefreshContext $safeRefreshContext,
        protected SabreOfferFreshness $offerFreshness,
        protected SabreStoredPricingContextDigest $pricingContextDigest,
        protected SabreBookingOfferRefreshService $offerRefreshService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspectBooking(Booking $booking, bool $freshProbe = false): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $applicationInspect = $this->applicationDigest->inspectBooking($booking);
        $payloadDigest = $this->sabreBookingService->inspectControlledPnrPayloadDigestForBooking($booking);
        $contextClassify = $this->contextDigest->classify($booking);
        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        $pricingReadiness = $this->sabreBookingService->assessAutoPnrPricingContextReadinessForBooking($booking);

        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        $validatedSnapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $safeRefreshCtx = $this->safeRefreshContext->fromMeta($meta) ?? [];
        $storedPricingDigest = $snapshot !== [] ? $this->pricingContextDigest->digest($snapshot) : [];

        $hostWarning = $this->extractHostNoFaresWarning($applicationInspect);
        $freshnessMeta = $snapshot !== []
            ? $this->offerFreshness->buildOfferFreshnessMeta($snapshot, null, $meta, true)
            : [];
        $freshnessStatus = strtolower(trim((string) ($freshnessMeta['freshness_status'] ?? '')));

        $hasStrongLinkage = ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) === true
            || $this->hasControlledStrongLinkageApply($meta);
        $legacySignal = $this->contextDigest->hasLegacySuccessRevalidationSignal($meta);
        $linkageStrength = $hasStrongLinkage ? 'strong' : ($legacySignal ? 'legacy' : 'none');

        $contextFreshness = $this->buildContextFreshness($meta, $contextClassify, $safeRefreshAssess, $freshnessMeta, $linkageStrength, $legacySignal);
        $segmentMatrix = $this->buildSegmentSellabilityMatrix($payloadDigest, $validatedSnapshot, $safeRefreshCtx);
        $fareBrandMatrix = $this->buildFareBrandMatrix(
            $payloadDigest,
            $snapshot,
            $validatedSnapshot,
            $safeRefreshCtx,
            $storedPricingDigest,
            $meta,
        );

        $hardPayloadRisk = ($payloadDigest['hard_no_fares_rbd_carrier_risk'] ?? false) === true;
        $hostUnresolved = $hostWarning !== null;
        $staleContextRisk = $this->isStaleContextRisk($contextFreshness, $freshnessStatus);
        $weakRevalidationRisk = $linkageStrength !== 'strong';
        $brandQualifierRisk = ($fareBrandMatrix['brand_consistency'] ?? null) === false;
        $fareBasisLinkageRisk = ($fareBrandMatrix['fare_basis_consistency'] ?? null) === false;
        $missingPriceQuoteLinkage = $this->missingPriceQuoteLinkage($pricingReadiness, $storedPricingDigest);
        $rbdSellabilityUnknown = $hostUnresolved && ! $hardPayloadRisk && $linkageStrength !== 'strong';

        $freshProbeResult = $freshProbe ? $this->probeFreshSellability($booking) : null;
        if ($freshProbeResult !== null && ($freshProbeResult['match_found'] ?? false) === false) {
            $staleContextRisk = true;
        }

        $allRetriesConsumed = $this->allControlledRetriesConsumed($meta);
        $hostSellabilityRisk = $hostUnresolved && ! $hardPayloadRisk;

        $lane = $this->classifyRecommendedLane(
            staleContextRisk: $staleContextRisk,
            weakRevalidationRisk: $weakRevalidationRisk,
            brandQualifierRisk: $brandQualifierRisk,
            missingPriceQuoteLinkage: $missingPriceQuoteLinkage,
            fareBasisLinkageRisk: $fareBasisLinkageRisk,
            segmentMatrix: $segmentMatrix,
            freshProbeResult: $freshProbeResult,
            hostUnresolved: $hostUnresolved,
            hardPayloadRisk: $hardPayloadRisk,
            allRetriesConsumed: $allRetriesConsumed,
        );

        $certifiedRoute = is_array($meta['certified_route_selection'] ?? null) ? $meta['certified_route_selection'] : [];

        $out = [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 0) ?: null,
            'endpoint_path' => $payloadDigest['endpoint_path'] ?? ($certifiedRoute['endpoint_path'] ?? null),
            'payload_style' => $payloadDigest['payload_style'] ?? ($certifiedRoute['payload_style'] ?? null),
            'current_application_error_code' => $applicationInspect['sabre_application_first_error_code'] ?? null,
            'current_host_warning_code' => $hostWarning['code'] ?? null,
            'current_host_warning_message_summary' => $hostWarning['message'] ?? null,
            'pnr_present' => trim((string) ($booking->pnr ?? '')) !== '',
            'supplier_reference_present' => trim((string) ($booking->supplier_reference ?? '')) !== '',
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'live_supplier_call_attempted' => $freshProbe,
            'pnr_create_attempted' => false,
        ];

        $out = array_merge($out, $contextFreshness, [
            'segment_sellability_matrix' => $segmentMatrix,
            'fare_brand_matrix' => $fareBrandMatrix,
            'host_no_fares_rbd_carrier_status' => $hostUnresolved ? 'unresolved' : ($hostWarning === null && ($applicationInspect['application_error_digest_available'] ?? false) ? 'resolved' : 'unknown'),
            'hard_payload_risk' => $hardPayloadRisk,
            'host_sellability_risk' => $hostSellabilityRisk,
            'stale_context_risk' => $staleContextRisk,
            'weak_revalidation_risk' => $weakRevalidationRisk,
            'rbd_sellability_unknown' => $rbdSellabilityUnknown,
            'brand_qualifier_risk' => $brandQualifierRisk,
            'fare_basis_linkage_risk' => $fareBasisLinkageRisk,
            'missing_price_quote_linkage' => $missingPriceQuoteLinkage,
            'recommended_lane' => $lane,
            'recommended_next_action' => $this->recommendedNextActionForLane($lane, $hostUnresolved, $hardPayloadRisk),
            'post_f9i_payload_digest_clean' => ($payloadDigest['post_f9i_payload_digest_clean'] ?? false) === true,
            'payload_digest_status' => $payloadDigest['digest_status'] ?? null,
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval' => $this->freshContextApplyRequiresNewApproval($meta),
        ]);

        if ($freshProbeResult !== null) {
            $out['fresh_probe'] = $freshProbeResult;
        }

        return SensitiveDataRedactor::redact($this->stripForbiddenKeys($out));
    }

    /**
     * @return array<string, mixed>
     */
    public function probeFreshSellability(Booking $booking): array
    {
        try {
            $refresh = $this->offerRefreshService->refresh($booking, false);
        } catch (\Throwable $e) {
            return [
                'probe_status' => 'error',
                'probe_error_summary' => substr($e->getMessage(), 0, 120),
                'match_found' => false,
                'live_supplier_call_attempted' => true,
            ];
        }

        $existingRbd = is_array($refresh['existing_rbd_list'] ?? null) ? $refresh['existing_rbd_list'] : [];
        $freshRbd = is_array($refresh['fresh_rbd_list'] ?? null) ? $refresh['fresh_rbd_list'] : [];
        $existingFb = is_array($refresh['existing_fare_basis_list'] ?? null) ? $refresh['existing_fare_basis_list'] : [];
        $freshFb = is_array($refresh['fresh_fare_basis_list'] ?? null) ? $refresh['fresh_fare_basis_list'] : [];

        return array_filter([
            'probe_status' => isset($refresh['error']) ? 'error' : 'ok',
            'probe_error_summary' => isset($refresh['error']) ? substr((string) $refresh['error'], 0, 120) : null,
            'match_found' => ($refresh['match_found'] ?? false) === true,
            'match_confidence' => $refresh['match_confidence'] ?? null,
            'same_flight_numbers' => ($refresh['same_flight_numbers'] ?? null),
            'same_rbd_list' => self::sameNormalizedStringList($existingRbd, $freshRbd),
            'fare_basis_match' => self::sameNormalizedStringList($existingFb, $freshFb),
            'price_changed' => ($refresh['price_changed'] ?? false) === true,
            'existing_fare_basis_list' => $existingFb !== []
                ? array_slice($existingFb, 0, self::SEGMENT_MAX)
                : null,
            'fresh_fare_basis_list' => $freshFb !== []
                ? array_slice($freshFb, 0, self::SEGMENT_MAX)
                : null,
            'existing_rbd_list' => $existingRbd !== []
                ? array_slice($existingRbd, 0, self::SEGMENT_MAX)
                : null,
            'fresh_rbd_list' => $freshRbd !== []
                ? array_slice($freshRbd, 0, self::SEGMENT_MAX)
                : null,
            'reasons' => is_array($refresh['reasons'] ?? null) ? array_slice($refresh['reasons'], 0, 8) : null,
            'brand_probe_supported' => false,
            'live_supplier_call_attempted' => true,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * @param  list<mixed>  $existing
     * @param  list<mixed>  $fresh
     */
    public static function sameNormalizedStringList(array $existing, array $fresh): bool
    {
        $normalizedExisting = self::normalizedStringList($existing);
        $normalizedFresh = self::normalizedStringList($fresh);

        if ($normalizedExisting === [] || $normalizedFresh === []) {
            return false;
        }

        return $normalizedExisting === $normalizedFresh;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function normalizedStringList(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $normalized = strtoupper(trim((string) $value));
            if ($normalized === '') {
                continue;
            }
            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function hasControlledStrongLinkageApply(array $meta): bool
    {
        $record = $meta[SabreControlledStrongRevalidationLinkageApply::META_KEY] ?? null;

        return is_array($record) && ($record['applied'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $applicationInspect
     * @return array{code: string, message: string}|null
     */
    protected function extractHostNoFaresWarning(array $applicationInspect): ?array
    {
        $warnings = is_array($applicationInspect['safe_warnings'] ?? null) ? $applicationInspect['safe_warnings'] : [];
        foreach ($warnings as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['code'] ?? ''));
            $message = trim((string) ($row['message'] ?? ''));
            $combined = strtoupper($code.' '.$message);
            if (str_contains($combined, 'NO FARES/RBD/CARRIER') || str_contains($combined, 'NO FARES')) {
                return [
                    'code' => $code !== '' ? substr($code, 0, 120) : 'WARN.SWS.HOST.ERROR_IN_RESPONSE',
                    'message' => $message !== '' ? substr($message, 0, 220) : 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER',
                ];
            }
        }

        foreach (['sabre_last_create_error_message', 'sabre_application_first_error_message'] as $key) {
            $msg = strtoupper(trim((string) ($applicationInspect[$key] ?? '')));
            if ($msg !== '' && str_contains($msg, 'NO FARES')) {
                return [
                    'code' => (string) ($applicationInspect['sabre_application_first_error_code'] ?? 'ERR.SP.PROVIDER_ERROR'),
                    'message' => substr(trim((string) ($applicationInspect[$key] ?? '')), 0, 220),
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $contextClassify
     * @param  array<string, mixed>  $safeRefreshAssess
     * @param  array<string, mixed>  $freshnessMeta
     * @return array<string, mixed>
     */
    protected function buildContextFreshness(
        array $meta,
        array $contextClassify,
        array $safeRefreshAssess,
        array $freshnessMeta,
        string $linkageStrength,
        bool $legacySignal,
    ): array {
        $searchCreatedAt = trim((string) ($meta['search_created_at'] ?? $freshnessMeta['search_created_at'] ?? ''));
        $selectedOfferCreatedAt = trim((string) (
            $meta['offer_validated_at'] ?? $meta['validated_at'] ?? $meta['checkout_created_at'] ?? ''
        ));
        $lastRevalidatedAt = trim((string) ($meta['last_revalidated_at'] ?? ''));
        $offerRefreshRefreshedAt = trim((string) ($meta['offer_refresh_refreshed_at'] ?? ''));

        return [
            'search_created_at' => $searchCreatedAt !== '' ? $searchCreatedAt : null,
            'selected_offer_created_at' => $selectedOfferCreatedAt !== '' ? $selectedOfferCreatedAt : null,
            'last_revalidated_at' => $lastRevalidatedAt !== '' ? $lastRevalidatedAt : null,
            'offer_refresh_status' => trim((string) ($meta['offer_refresh_status'] ?? '')) ?: null,
            'offer_refresh_reason' => trim((string) ($meta['offer_refresh_reason'] ?? '')) ?: null,
            'minutes_since_revalidation' => $this->minutesSince($lastRevalidatedAt),
            'minutes_since_offer_refresh' => $this->minutesSince($offerRefreshRefreshedAt),
            'revalidation_linkage_strength' => $linkageStrength,
            'legacy_revalidation_signal_used' => $legacySignal,
            'safe_refresh_context_complete' => ($safeRefreshAssess['safe_refresh_context_complete'] ?? false) === true,
            'validated_offer_snapshot_present' => ($contextClassify['validated_offer_snapshot_present'] ?? false) === true,
            'pricing_snapshot_present' => ($contextClassify['pricing_snapshot_present'] ?? false) === true,
            'raw_payload_present' => ($contextClassify['raw_payload_present'] ?? false) === true,
            'freshness_status' => trim((string) ($freshnessMeta['freshness_status'] ?? '')) ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadDigest
     * @param  array<string, mixed>  $validatedSnapshot
     * @param  array<string, mixed>  $safeRefreshCtx
     * @return list<array<string, mixed>>
     */
    protected function buildSegmentSellabilityMatrix(array $payloadDigest, array $validatedSnapshot, array $safeRefreshCtx): array
    {
        $payloadSegments = is_array($payloadDigest['airbook_segment_digest'] ?? null)
            ? $payloadDigest['airbook_segment_digest']
            : [];
        $selectedSummary = is_array($payloadDigest['selected_context_summary'] ?? null)
            ? $payloadDigest['selected_context_summary']
            : [];
        $validatedSegs = array_values(is_array($validatedSnapshot['segments'] ?? null) ? $validatedSnapshot['segments'] : []);
        $safeSegs = array_values(is_array($safeRefreshCtx['selected_segments'] ?? null) ? $safeRefreshCtx['selected_segments'] : []);

        $max = max(count($payloadSegments), count($validatedSegs), count($safeSegs), 1);
        $max = min($max, self::SEGMENT_MAX);
        $matrix = [];

        for ($i = 0; $i < $max; $i++) {
            $payloadRow = is_array($payloadSegments[$i] ?? null) ? $payloadSegments[$i] : [];
            $validatedRow = is_array($validatedSegs[$i] ?? null) ? $validatedSegs[$i] : [];
            $safeRow = is_array($safeSegs[$i] ?? null) ? $safeSegs[$i] : [];

            $bookingClass = $this->firstNonEmptyString([
                $payloadRow['booking_class'] ?? null,
                $payloadRow['res_book_desig_code'] ?? null,
                $validatedRow['booking_class'] ?? null,
                $safeRow['booking_class'] ?? null,
                is_array($selectedSummary['rbd_by_segment'] ?? null) ? ($selectedSummary['rbd_by_segment'][$i] ?? null) : null,
            ]);
            $fareBasis = $this->firstNonEmptyString([
                $payloadRow['fare_basis_snapshot'] ?? null,
                $validatedRow['fare_basis_code'] ?? null,
                $safeRow['fare_basis'] ?? null,
                is_array($selectedSummary['fare_basis_by_segment'] ?? null) ? ($selectedSummary['fare_basis_by_segment'][$i] ?? null) : null,
            ]);

            $riskReasons = [];
            if ($bookingClass !== null && $payloadRow !== [] && ($payloadRow['booking_class'] ?? '') !== '' && $validatedRow !== []) {
                $valRbd = strtoupper(trim((string) ($validatedRow['booking_class'] ?? '')));
                if ($valRbd !== '' && $valRbd !== strtoupper($bookingClass)) {
                    $riskReasons[] = 'rbd_validated_payload_mismatch';
                }
            }
            if ($fareBasis !== null && $payloadRow !== [] && $validatedRow !== []) {
                $valFb = strtoupper(trim((string) ($validatedRow['fare_basis_code'] ?? '')));
                if ($valFb !== '' && strtoupper($fareBasis) !== $valFb) {
                    $riskReasons[] = 'fare_basis_validated_payload_mismatch';
                }
            }

            $segmentMatch = $riskReasons === [];
            $segmentSource = $payloadRow !== [] ? 'payload' : ($validatedRow !== [] ? 'validated_snapshot' : ($safeRow !== [] ? 'safe_refresh' : 'selected_context'));

            $matrix[] = array_filter([
                'index' => $i,
                'marketing_carrier' => $this->firstNonEmptyString([
                    $payloadRow['marketing_carrier'] ?? null,
                    $validatedRow['carrier'] ?? null,
                    $safeRow['carrier'] ?? null,
                ]),
                'operating_carrier' => $payloadRow['operating_carrier'] ?? null,
                'flight_number' => $this->firstNonEmptyString([
                    $payloadRow['flight_number'] ?? null,
                    $validatedRow['flight_number'] ?? null,
                    $safeRow['flight_number'] ?? null,
                ]),
                'origin' => $this->firstNonEmptyString([
                    $payloadRow['origin'] ?? null,
                    $validatedRow['origin'] ?? null,
                    $safeRow['origin'] ?? null,
                ]),
                'destination' => $this->firstNonEmptyString([
                    $payloadRow['destination'] ?? null,
                    $validatedRow['destination'] ?? null,
                    $safeRow['destination'] ?? null,
                ]),
                'departure_date' => $payloadRow['departure_date'] ?? $this->datePart((string) ($validatedRow['departure_at'] ?? $safeRow['departure_at'] ?? '')),
                'departure_time' => $payloadRow['departure_time'] ?? $this->timePart((string) ($validatedRow['departure_at'] ?? $safeRow['departure_at'] ?? '')),
                'booking_class' => $bookingClass,
                'res_book_desig_code' => $bookingClass,
                'fare_basis' => $fareBasis,
                'cabin' => trim((string) ($validatedRow['cabin'] ?? $safeRefreshCtx['cabin'] ?? '')) ?: null,
                'status_action_code' => $payloadRow['action_code'] ?? $payloadRow['status_code'] ?? null,
                'number_in_party' => $payloadRow['number_in_party'] ?? null,
                'marriage_group' => $payloadRow['marriage_group'] ?? null,
                'segment_source' => $segmentSource,
                'segment_context_match' => $segmentMatch,
                'segment_risk_reasons' => $riskReasons,
            ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>  $payloadDigest
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $validatedSnapshot
     * @param  array<string, mixed>  $safeRefreshCtx
     * @param  array<string, mixed>  $storedPricingDigest
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function buildFareBrandMatrix(
        array $payloadDigest,
        array $snapshot,
        array $validatedSnapshot,
        array $safeRefreshCtx,
        array $storedPricingDigest,
        array $meta,
    ): array {
        $airprice = is_array($payloadDigest['airprice_digest'] ?? null) ? $payloadDigest['airprice_digest'] : [];
        $selectedSummary = is_array($payloadDigest['selected_context_summary'] ?? null) ? $payloadDigest['selected_context_summary'] : [];
        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        $fareBreakdown = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];

        $brandSelected = $this->normalizeBrand($selectedSummary['brand_code'] ?? $payloadDigest['selected_context_brand_code'] ?? null);
        $brandValidated = $this->normalizeBrand(
            data_get($validatedSnapshot, 'brand_code')
            ?? data_get($validatedSnapshot, 'fare_family_code')
            ?? data_get($validatedSnapshot, 'raw_payload.brand_code')
        );
        $brandSafeRefresh = $this->normalizeBrand(data_get($safeRefreshCtx, 'brand_code'));
        $brandPayload = $this->normalizeBrand($airprice['brand_code'] ?? $airprice['wire_brand_code'] ?? $payloadDigest['payload_airprice_brand_code'] ?? null);

        $brandValues = array_values(array_unique(array_filter([$brandSelected, $brandValidated, $brandSafeRefresh, $brandPayload])));
        $brandConsistency = count($brandValues) <= 1 ? (count($brandValues) === 1 ? true : null) : false;

        $fbSelected = $this->firstFareBasisFromSegments($snapshot);
        $fbValidated = $this->firstFareBasisFromSegments($validatedSnapshot);
        $fbPayload = $this->firstNonEmptyString([
            is_array($selectedSummary['fare_basis_by_segment'] ?? null) ? ($selectedSummary['fare_basis_by_segment'][0] ?? null) : null,
            is_array($airprice['fare_basis_codes'] ?? null) ? ($airprice['fare_basis_codes'][0] ?? null) : null,
        ]);
        if ($fbPayload === null && is_array($storedPricingDigest['fare_basis_codes'] ?? null)) {
            $fbPayload = $storedPricingDigest['fare_basis_codes'][0] ?? null;
        }

        $fbValues = array_values(array_unique(array_filter([
            $fbSelected !== null ? strtoupper($fbSelected) : null,
            $fbValidated !== null ? strtoupper($fbValidated) : null,
            $fbPayload !== null ? strtoupper((string) $fbPayload) : null,
        ])));
        $fareBasisConsistency = count($fbValues) <= 1 ? (count($fbValues) === 1 ? true : null) : false;

        $typeCodes = is_array($airprice['type_codes'] ?? null) ? $airprice['type_codes'] : ['ADT'];

        $matrix = [
            'validating_carrier' => $this->firstNonEmptyString([
                $payloadDigest['validating_carrier'] ?? null,
                $airprice['validating_carrier'] ?? null,
                $storedPricingDigest['validating_carrier'] ?? null,
                $snapshot['validating_carrier'] ?? null,
            ]),
            'payload_airprice_validating_carrier' => $airprice['validating_carrier'] ?? null,
            'brand_code_selected_context' => $brandSelected,
            'brand_code_validated_snapshot' => $brandValidated,
            'brand_code_safe_refresh' => $brandSafeRefresh,
            'brand_code_payload' => $brandPayload,
            'brand_consistency' => $brandConsistency,
            'fare_basis_selected_context' => $fbSelected,
            'fare_basis_validated_snapshot' => $fbValidated,
            'fare_basis_payload' => $fbPayload,
            'fare_basis_consistency' => $fareBasisConsistency,
            'pricing_currency' => $this->firstNonEmptyString([
                $airprice['currency'] ?? null,
                $pricingSnapshot['currency'] ?? null,
                $pricingSnapshot['supplier_currency'] ?? null,
                $fareBreakdown['currency'] ?? null,
            ]),
            'total_fare' => isset($pricingSnapshot['supplier_total']) ? (float) $pricingSnapshot['supplier_total'] : (isset($fareBreakdown['supplier_total']) ? (float) $fareBreakdown['supplier_total'] : null),
            'tax_total' => isset($pricingSnapshot['taxes']) ? (float) $pricingSnapshot['taxes'] : null,
            'passenger_type_codes' => array_slice($typeCodes, 0, 6),
            'passenger_type_count' => count($typeCodes),
            'fare_component_count' => (int) ($storedPricingDigest['fare_component_count'] ?? 0),
            'pricing_information_index' => $storedPricingDigest['pricing_information_index'] ?? $payloadDigest['pricing_information_index'] ?? null,
            'itinerary_ref' => $storedPricingDigest['itinerary_ref'] ?? $payloadDigest['itinerary_ref'] ?? null,
            'leg_refs' => is_array($storedPricingDigest['fare_component_refs'] ?? null) ? array_slice($storedPricingDigest['fare_component_refs'], 0, 6) : null,
            'schedule_refs' => is_array($payloadDigest['schedule_refs'] ?? null) ? array_slice($payloadDigest['schedule_refs'], 0, 6) : null,
            'fare_rule_refs' => null,
            'fare_component_refs' => is_array($storedPricingDigest['fare_component_refs'] ?? null)
                ? array_slice($storedPricingDigest['fare_component_refs'], 0, 6)
                : null,
        ];

        return array_filter(
            $matrix,
            static fn ($v, $k) => in_array($k, ['brand_consistency', 'fare_basis_consistency'], true)
                || ($v !== null && $v !== [] && $v !== ''),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, mixed>  $pricingReadiness
     * @param  array<string, mixed>  $storedPricingDigest
     */
    protected function missingPriceQuoteLinkage(array $pricingReadiness, array $storedPricingDigest): bool
    {
        if (($pricingReadiness['has_revalidation_linkage_complete'] ?? false) === true) {
            return false;
        }

        $hasPiRef = ($pricingReadiness['has_pricing_information_ref'] ?? false) === true;
        $hasOfferRef = ($pricingReadiness['has_offer_reference'] ?? false) === true;
        $hasFcRefs = is_array($storedPricingDigest['fare_component_refs'] ?? null)
            && $storedPricingDigest['fare_component_refs'] !== [];

        return ! $hasPiRef && ! $hasOfferRef && ! $hasFcRefs;
    }

    /**
     * @param  array<string, mixed>  $contextFreshness
     */
    protected function isStaleContextRisk(array $contextFreshness, string $freshnessStatus): bool
    {
        if (in_array($freshnessStatus, ['stale', 'expired'], true)) {
            return true;
        }

        $minutes = $contextFreshness['minutes_since_revalidation'] ?? null;
        if (is_int($minutes) && $minutes > (int) config('ota.offer_freshness.stale_after_seconds', 600) / 60) {
            return true;
        }

        return strtolower(trim((string) ($contextFreshness['offer_refresh_status'] ?? ''))) === 'stale';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function freshContextApplyRequiresNewApproval(array $meta): bool
    {
        $record = $meta['controlled_fresh_pnr_context_apply'] ?? null;
        if (! is_array($record)) {
            return false;
        }

        return ($record['applied'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function allControlledRetriesConsumed(array $meta): bool
    {
        $f9f = is_array($meta[SabreControlledPnrRetryAllowanceGate::META_KEY] ?? null)
            ? $meta[SabreControlledPnrRetryAllowanceGate::META_KEY] : [];
        $f9j = is_array($meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] ?? null)
            ? $meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] : [];
        $f9l = is_array($meta[SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY] ?? null)
            ? $meta[SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY] : [];

        return ($f9f['used'] ?? false) === true
            && ($f9j['used'] ?? false) === true
            && ($f9l['used'] ?? false) === true;
    }

    /**
     * @param  list<array<string, mixed>>  $segmentMatrix
     * @param  array<string, mixed>|null  $freshProbeResult
     */
    protected function classifyRecommendedLane(
        bool $staleContextRisk,
        bool $weakRevalidationRisk,
        bool $brandQualifierRisk,
        bool $missingPriceQuoteLinkage,
        bool $fareBasisLinkageRisk,
        array $segmentMatrix,
        ?array $freshProbeResult,
        bool $hostUnresolved,
        bool $hardPayloadRisk,
        bool $allRetriesConsumed,
    ): string {
        if ($staleContextRisk || ($freshProbeResult !== null && ($freshProbeResult['match_found'] ?? false) === false)) {
            return self::LANE_REFRESH_REQUIRED;
        }

        if ($weakRevalidationRisk) {
            return self::LANE_WEAK_REVALIDATION;
        }

        if ($brandQualifierRisk) {
            return self::LANE_BRAND_QUALIFIER;
        }

        if ($missingPriceQuoteLinkage) {
            return self::LANE_PRICING_QUALIFIER;
        }

        $segmentRbdMismatch = false;
        foreach ($segmentMatrix as $row) {
            $reasons = is_array($row['segment_risk_reasons'] ?? null) ? $row['segment_risk_reasons'] : [];
            if ($reasons !== []) {
                $segmentRbdMismatch = true;
                break;
            }
        }

        $probeRbdMismatch = $freshProbeResult !== null
            && ($freshProbeResult['same_rbd_list'] ?? null) === false;

        if ($fareBasisLinkageRisk || $segmentRbdMismatch || $probeRbdMismatch) {
            return self::LANE_RBD_FARE_BASIS;
        }

        if ($allRetriesConsumed && $hostUnresolved && ! $hardPayloadRisk) {
            return self::LANE_NO_SAFE_RETRY;
        }

        if ($hostUnresolved && ! $hardPayloadRisk) {
            return self::LANE_HOST_INVENTORY;
        }

        return self::LANE_HOST_INVENTORY;
    }

    protected function recommendedNextActionForLane(string $lane, bool $hostUnresolved, bool $hardPayloadRisk): string
    {
        return match ($lane) {
            self::LANE_REFRESH_REQUIRED => 'Refresh or re-shop itinerary before any controlled PNR retry; stale or non-matching fresh shop context.',
            self::LANE_WEAK_REVALIDATION => 'Obtain strong BFM revalidation linkage (pricing/offer refs) before retry; legacy success signal alone is insufficient.',
            self::LANE_BRAND_QUALIFIER => 'Align brand qualifier across selected, validated, safe-refresh, and payload AirPrice contexts before retry.',
            self::LANE_PRICING_QUALIFIER => 'Restore pricing_information_ref, offer_ref, or fare_component refs on stored snapshot before retry.',
            self::LANE_RBD_FARE_BASIS => 'Re-shop for sellable RBD/fare-basis combination; segment or fare-basis mismatch detected across contexts.',
            self::LANE_NO_SAFE_RETRY => 'All controlled retry allowances consumed; host sellability still unresolved — staff review, re-shop, or alternate fare required (no further automatic retry).',
            self::LANE_HOST_INVENTORY => $hardPayloadRisk
                ? 'Fix payload digest hard risks before retry.'
                : 'Payload context is clean but Sabre host reports NO FARES/RBD/CARRIER — likely inventory, PCC entitlement, or true sellability limit; re-shop or alternate fare.',
            default => 'Review sellability diagnostics with F9H payload digest and F9G application-error inspect before any retry.',
        };
    }

    protected function minutesSince(?string $iso): ?int
    {
        if ($iso === null || trim($iso) === '') {
            return null;
        }

        try {
            $at = Carbon::parse($iso);

            return max(0, (int) $at->diffInMinutes(now()));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function firstFareBasisFromSegments(array $snapshot): ?string
    {
        $segs = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        foreach ($segs as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $fb = trim((string) ($seg['fare_basis_code'] ?? $seg['fare_basis'] ?? ''));
            if ($fb !== '') {
                return $fb;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    protected function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                return trim($c);
            }
        }

        return null;
    }

    protected function normalizeBrand(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtoupper(substr(trim($value), 0, 32));
    }

    protected function datePart(string $iso): ?string
    {
        if ($iso === '') {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('Y-m-d');
        } catch (\Throwable) {
            return strlen($iso) >= 10 ? substr($iso, 0, 10) : null;
        }
    }

    protected function timePart(string $iso): ?string
    {
        if ($iso === '') {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripForbiddenKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $keyStr = (string) $key;
            $lower = strtolower($keyStr);
            $forbidden = false;
            foreach (self::FORBIDDEN_KEY_SUBSTRINGS as $frag) {
                if (str_contains($lower, $frag)) {
                    $forbidden = true;
                    break;
                }
            }
            if ($forbidden) {
                continue;
            }
            if (is_array($value)) {
                $out[$keyStr] = array_is_list($value)
                    ? array_map(fn ($item) => is_array($item) ? $this->stripForbiddenKeys($item) : $item, $value)
                    : $this->stripForbiddenKeys($value);
            } else {
                $out[$keyStr] = $value;
            }
        }

        return $out;
    }
}
