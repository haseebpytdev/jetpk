<?php

namespace App\Support\Sabre;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreBookingOfferRefreshService;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledPnrContextDigest;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Carbon;

/**
 * F9O: Read-only controlled Sabre BFM strong revalidation linkage diagnostics (no PNR, no DB mutation by default).
 */
final class SabreControlledPnrStrongRevalidationLinkageDiagnostics
{
    public const LANE_STRONG_READY = 'strong_revalidation_linkage_ready';

    public const LANE_APPLY_REQUIRED = 'strong_revalidation_apply_required';

    public const LANE_PROBE_REQUIRED = 'revalidation_probe_required';

    public const LANE_RESPONSE_UNUSABLE = 'revalidation_response_unusable';

    public const LANE_NO_SAFE_RETRY = 'no_safe_retry_recommended';

    /** @var list<string> */
    private const FORBIDDEN_KEY_SUBSTRINGS = [
        'raw_payload', 'request_body', 'response_body', 'password', 'secret', 'credential',
        'passport', 'email', 'phone', 'first_name', 'last_name', 'givenname', 'surname',
        'personname', 'contactnumbers', 'document',
    ];

    public function __construct(
        protected SabreControlledPnrSellabilityDiagnostics $sellabilityDiagnostics,
        protected SabreBookingService $sabreBookingService,
        protected SabreControlledPnrContextDigest $contextDigest,
        protected SabreSafeRefreshContext $safeRefreshContext,
        protected SabreStoredPricingContextDigest $pricingContextDigest,
        protected SabreBookingOfferRefreshService $offerRefreshService,
        protected SabreControlledFreshPnrContextApply $freshContextApply,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspectBooking(Booking $booking, bool $probeRevalidate = false): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $sellability = $this->sellabilityDiagnostics->inspectBooking($booking, false);
        $pricingReadiness = $this->sabreBookingService->assessAutoPnrPricingContextReadinessForBooking($booking);
        $payloadDigest = $this->sabreBookingService->inspectControlledPnrPayloadDigestForBooking($booking);
        $contextClassify = $this->contextDigest->classify($booking);

        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        $storedDigest = $snapshot !== [] ? $this->pricingContextDigest->digest($snapshot) : [];
        $validatedSnapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $safeRefreshCtx = $this->safeRefreshContext->fromMeta($meta) ?? [];

        $formalStrong = ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) === true;
        $bfmReady = ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $appliedStrong = $this->hasControlledStrongLinkageApply($meta);
        $legacySignal = ($sellability['legacy_revalidation_signal_used'] ?? false) === true;

        $linkageStrength = $formalStrong || $appliedStrong
            ? 'strong'
            : (($legacySignal || $bfmReady) ? 'legacy' : 'none');

        $freshContextRecord = $this->freshContextApply->extractRecord($meta);
        $matrix = $this->buildStrongLinkageMatrix(
            $pricingReadiness,
            $storedDigest,
            $payloadDigest,
            $snapshot,
            $validatedSnapshot,
            $safeRefreshCtx,
            $meta,
        );

        $probeResult = $probeRevalidate ? $this->probeRevalidationLinkage($booking) : null;
        if ($probeResult !== null && ($probeResult['probe_status'] ?? '') === 'ok' && ($probeResult['probe_strong_candidate'] ?? false) === true) {
            $matrix['strong_revalidation_candidate'] = true;
            foreach (['itinerary_ref_present', 'pricing_information_index_present', 'validating_carrier_present'] as $key) {
                if (($probeResult[$key] ?? null) === true) {
                    $matrix[$key] = true;
                }
            }
        }

        $riskFields = $this->buildRiskFields($pricingReadiness, $matrix, $sellability, $linkageStrength);
        $lane = $this->classifyRecommendedLane(
            $sellability,
            $matrix,
            $linkageStrength,
            $appliedStrong,
            $freshContextRecord,
            $probeResult,
        );

        $out = [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? $booking->booking_reference ?? ''),
            'pnr_present' => ($sellability['pnr_present'] ?? false) === true,
            'supplier_reference_present' => ($sellability['supplier_reference_present'] ?? false) === true,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'live_supplier_call_attempted' => $probeRevalidate,
            'pnr_create_attempted' => false,
            'current_revalidation_linkage_strength' => $linkageStrength,
            'legacy_revalidation_signal_used' => $legacySignal,
            'weak_revalidation_risk' => $linkageStrength !== 'strong',
            'stale_context_risk' => ($sellability['stale_context_risk'] ?? true) === true,
            'controlled_fresh_context_apply_present' => $freshContextRecord !== null,
            'controlled_fresh_context_apply_applied_at' => is_array($freshContextRecord)
                ? ($freshContextRecord['applied_at'] ?? null)
                : null,
            'controlled_strong_revalidation_linkage_apply_present' => $appliedStrong,
            'selected_offer_created_at' => $sellability['selected_offer_created_at'] ?? null,
            'last_revalidated_at' => $sellability['last_revalidated_at'] ?? null,
            'minutes_since_revalidation' => $sellability['minutes_since_revalidation'] ?? null,
            'safe_refresh_context_complete' => ($sellability['safe_refresh_context_complete'] ?? false) === true,
            'validated_offer_snapshot_present' => ($contextClassify['validated_offer_snapshot_present'] ?? false) === true,
            'pricing_snapshot_present' => ($contextClassify['pricing_snapshot_present'] ?? false) === true,
            'raw_payload_present' => ($contextClassify['raw_payload_present'] ?? false) === true,
            'strong_linkage_matrix' => $matrix,
            'selected_offer_not_strongly_revalidated' => ($sellability['recommended_lane'] ?? '') === SabreControlledPnrSellabilityDiagnostics::LANE_WEAK_REVALIDATION,
            'strong_bfm_linkage_missing' => ! ($matrix['strong_revalidation_candidate'] ?? false) && $linkageStrength !== 'strong',
            'revalidation_payload_unusable' => ($riskFields['revalidation_payload_unusable'] ?? false) === true,
            'revalidation_total_fare_missing' => ($riskFields['revalidation_total_fare_missing'] ?? false) === true,
            'revalidation_validating_carrier_missing' => ($riskFields['revalidation_validating_carrier_missing'] ?? false) === true,
            'revalidation_pricing_info_missing' => ($riskFields['revalidation_pricing_info_missing'] ?? false) === true,
            'revalidation_segment_refs_missing' => ($riskFields['revalidation_segment_refs_missing'] ?? false) === true,
            'recommended_lane' => $lane,
            'recommended_next_action' => $this->recommendedNextActionForLane($lane, $matrix, $sellability),
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval' => ($sellability['controlled_pnr_retry_after_fresh_context_apply_requires_new_approval'] ?? false) === true,
        ];

        if ($probeResult !== null) {
            $out['revalidation_probe'] = $probeResult;
        }

        return SensitiveDataRedactor::redact($this->stripForbiddenKeys($out));
    }

    /**
     * @return array<string, mixed>
     */
    public function probeRevalidationLinkage(Booking $booking): array
    {
        try {
            $refresh = $this->offerRefreshService->refresh($booking, false);
        } catch (\Throwable $e) {
            return [
                'probe_status' => 'error',
                'probe_type' => 'shop_refresh_not_true_revalidate',
                'probe_error_summary' => substr($e->getMessage(), 0, 120),
                'probe_strong_candidate' => false,
                'live_supplier_call_attempted' => true,
            ];
        }

        $error = trim((string) ($refresh['error'] ?? ''));
        $matchFound = ($refresh['match_found'] ?? false) === true;
        $freshOffer = is_array($refresh['fresh_offer'] ?? null) ? $refresh['fresh_offer'] : [];

        $probeMatrix = $freshOffer !== []
            ? $this->buildStrongLinkageMatrix(
                $this->pricingContextDigest->assessReadiness($freshOffer),
                $this->pricingContextDigest->digest($freshOffer),
                [],
                $freshOffer,
                $freshOffer,
                [],
                [],
            )
            : [];

        return array_filter([
            'probe_status' => $error !== '' ? 'error' : ($matchFound ? 'ok' : 'no_match'),
            'probe_type' => 'shop_refresh_not_true_revalidate',
            'probe_error_summary' => $error !== '' ? substr($error, 0, 120) : null,
            'match_found' => $matchFound,
            'match_confidence' => (string) ($refresh['match_confidence'] ?? ''),
            'same_flight_numbers' => ($refresh['same_flight_numbers'] ?? null),
            'same_rbd_list' => SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList(
                is_array($refresh['existing_rbd_list'] ?? null) ? $refresh['existing_rbd_list'] : [],
                is_array($refresh['fresh_rbd_list'] ?? null) ? $refresh['fresh_rbd_list'] : [],
            ),
            'fare_basis_match' => SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList(
                is_array($refresh['existing_fare_basis_list'] ?? null) ? $refresh['existing_fare_basis_list'] : [],
                is_array($refresh['fresh_fare_basis_list'] ?? null) ? $refresh['fresh_fare_basis_list'] : [],
            ),
            'price_changed' => ($refresh['price_changed'] ?? false) === true,
            'probe_strong_candidate' => ($probeMatrix['strong_revalidation_candidate'] ?? false) === true,
            'itinerary_ref_present' => ($probeMatrix['itinerary_ref_present'] ?? false) === true,
            'pricing_information_index_present' => ($probeMatrix['pricing_information_index_present'] ?? false) === true,
            'validating_carrier_present' => ($probeMatrix['validating_carrier_present'] ?? false) === true,
            'fare_component_refs_present' => ($probeMatrix['fare_component_refs_present'] ?? false) === true,
            'strong_revalidation_blockers' => is_array($probeMatrix['strong_revalidation_blockers'] ?? null)
                ? $probeMatrix['strong_revalidation_blockers']
                : [],
            'live_supplier_call_attempted' => true,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $pricingReadiness
     * @param  array<string, mixed>  $storedDigest
     * @param  array<string, mixed>  $payloadDigest
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $validatedSnapshot
     * @param  array<string, mixed>  $safeRefreshCtx
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function buildStrongLinkageMatrix(
        array $pricingReadiness,
        array $storedDigest,
        array $payloadDigest,
        array $snapshot,
        array $validatedSnapshot,
        array $safeRefreshCtx,
        array $meta = [],
    ): array {
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $segmentCount = count($segments);
        $fareBrand = is_array($payloadDigest['selected_context_summary'] ?? null)
            ? $payloadDigest['selected_context_summary']
            : [];

        $itineraryRefPresent = trim((string) ($storedDigest['itinerary_ref'] ?? '')) !== ''
            || ($pricingReadiness['bfm_itinerary_reference_present'] ?? false) === true
            || ($pricingReadiness['has_itinerary_reference'] ?? false) === true;

        $legRefs = is_array($storedDigest['leg_refs'] ?? null) ? $storedDigest['leg_refs'] : [];
        if ($legRefs === [] && is_array($ctx = data_get($snapshot, 'raw_payload.sabre_shop_context.leg_refs'))) {
            $legRefs = $ctx;
        }
        $scheduleRefs = is_array($payloadDigest['schedule_refs'] ?? null) ? $payloadDigest['schedule_refs'] : [];
        if ($scheduleRefs === [] && is_array($ctx = data_get($snapshot, 'raw_payload.sabre_shop_context.schedule_refs'))) {
            $scheduleRefs = $ctx;
        }
        $fcRefs = is_array($storedDigest['fare_component_refs'] ?? null) ? $storedDigest['fare_component_refs'] : [];

        $piIndexPresent = ($pricingReadiness['bfm_pricing_information_index_present'] ?? false) === true
            || array_key_exists('pricing_information_index', $storedDigest);

        $validatingPresent = ($pricingReadiness['has_validating_carrier'] ?? false) === true;
        $fcRefsPresent = ($pricingReadiness['has_fare_component_refs'] ?? false) === true || $fcRefs !== [];
        $legRefsPresent = $legRefs !== [];
        $scheduleRefsPresent = $scheduleRefs !== [];

        $rbdMatch = ($payloadDigest['rbd_match'] ?? null) === true
            || ($payloadDigest['airbook_rbd_complete'] ?? false) === true
            || $this->snapshotBookingClassesComplete($snapshot);
        $fareBasisMatch = ($payloadDigest['fare_basis_present'] ?? false) === true
            || ($pricingReadiness['has_fare_basis_codes'] ?? false) === true;
        $brandMatch = ($payloadDigest['brand_match'] ?? null);
        if ($brandMatch === null) {
            $brandMatch = trim((string) ($snapshot['brand_code'] ?? $snapshot['fare_family_code'] ?? '')) !== '';
        }

        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        $pricingTotal = isset($pricingSnapshot['supplier_total']) ? (float) $pricingSnapshot['supplier_total'] : null;
        $pricingTotalMatch = $pricingTotal !== null && $pricingTotal > 0 ? true : null;

        $segmentCountMatch = $segmentCount > 0
            && (int) ($payloadDigest['segment_count'] ?? $segmentCount) === $segmentCount;

        $blockers = [];
        if (! $itineraryRefPresent) {
            $blockers[] = 'missing_itinerary_ref';
        }
        if (! $piIndexPresent) {
            $blockers[] = 'missing_pricing_information_index';
        }
        if (! $validatingPresent) {
            $blockers[] = 'missing_validating_carrier';
        }
        if ($segmentCount >= 2 && ! $fcRefsPresent && ! ($legRefsPresent && $scheduleRefsPresent)) {
            $blockers[] = 'missing_segment_descriptor_refs';
        }
        if (! $fareBasisMatch) {
            $blockers[] = 'fare_basis_mismatch';
        }
        if ($rbdMatch === false) {
            $blockers[] = 'rbd_mismatch';
        }
        if ($brandMatch === false) {
            $blockers[] = 'brand_mismatch';
        }
        if (! $segmentCountMatch) {
            $blockers[] = 'segment_count_mismatch';
        }

        $formalComplete = ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) === true;
        $bfmReady = ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $strongCandidate = ($formalComplete || $bfmReady) && $blockers === [];

        return [
            'itinerary_ref_present' => $itineraryRefPresent,
            'leg_refs_present' => $legRefsPresent,
            'schedule_refs_present' => $scheduleRefsPresent,
            'pricing_information_index_present' => $piIndexPresent,
            'fare_component_refs_present' => $fcRefsPresent,
            'fare_component_count' => (int) ($storedDigest['fare_component_count'] ?? 0),
            'passenger_info_refs_present' => ($pricingReadiness['has_selected_passenger_info'] ?? false) === true,
            'fare_basis_refs_present' => ($pricingReadiness['has_fare_basis_codes'] ?? false) === true,
            'brand_refs_present' => trim((string) ($fareBrand['brand_code'] ?? $payloadDigest['selected_context_brand_code'] ?? '')) !== '',
            'validating_carrier_present' => $validatingPresent,
            'segment_count_match' => $segmentCountMatch,
            'rbd_match' => $rbdMatch,
            'fare_basis_match' => $fareBasisMatch,
            'brand_match' => $brandMatch,
            'pricing_total_match' => $pricingTotalMatch,
            'strong_revalidation_candidate' => $strongCandidate,
            'strong_revalidation_blockers' => $blockers,
            'pricing_context_policy' => (string) ($pricingReadiness['pricing_context_policy'] ?? ''),
            'auto_pnr_pricing_context_ready' => $bfmReady,
            'formal_revalidation_linkage_complete' => $formalComplete,
        ];
    }

    protected function snapshotBookingClassesComplete(array $snapshot): bool
    {
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        if ($segments === []) {
            return false;
        }
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                return false;
            }
            $rbd = trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? $seg['rbd'] ?? ''));
            if ($rbd === '') {
                return false;
            }
        }

        return true;
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
     * @param  array<string, mixed>  $pricingReadiness
     * @param  array<string, mixed>  $matrix
     * @param  array<string, mixed>  $sellability
     * @return array<string, mixed>
     */
    protected function buildRiskFields(array $pricingReadiness, array $matrix, array $sellability, string $linkageStrength): array
    {
        return [
            'revalidation_payload_unusable' => ($matrix['strong_revalidation_blockers'] ?? []) !== []
                && $linkageStrength !== 'strong',
            'revalidation_total_fare_missing' => ($matrix['pricing_total_match'] ?? null) === false,
            'revalidation_validating_carrier_missing' => ($matrix['validating_carrier_present'] ?? false) !== true,
            'revalidation_pricing_info_missing' => ($matrix['pricing_information_index_present'] ?? false) !== true
                && ($pricingReadiness['has_pricing_information_ref'] ?? false) !== true,
            'revalidation_segment_refs_missing' => ($matrix['itinerary_ref_present'] ?? false) !== true
                || ($matrix['segment_count_match'] ?? false) !== true,
        ];
    }

    /**
     * @param  array<string, mixed>  $sellability
     * @param  array<string, mixed>  $matrix
     * @param  array<string, mixed>|null  $freshContextRecord
     * @param  array<string, mixed>|null  $probeResult
     */
    protected function classifyRecommendedLane(
        array $sellability,
        array $matrix,
        string $linkageStrength,
        bool $appliedStrong,
        ?array $freshContextRecord,
        ?array $probeResult,
    ): string {
        if (($sellability['pnr_present'] ?? false) === true
            || ($sellability['supplier_reference_present'] ?? false) === true) {
            return self::LANE_NO_SAFE_RETRY;
        }

        if ($linkageStrength === 'strong' || $appliedStrong) {
            return self::LANE_STRONG_READY;
        }

        if (($matrix['strong_revalidation_candidate'] ?? false) === true) {
            $freshApplied = is_array($freshContextRecord) && ($freshContextRecord['applied'] ?? false) === true;
            if ($freshApplied && ($sellability['stale_context_risk'] ?? true) === false) {
                return self::LANE_APPLY_REQUIRED;
            }
        }

        if ($probeResult !== null && ($probeResult['probe_status'] ?? '') === 'error') {
            return self::LANE_RESPONSE_UNUSABLE;
        }

        if ($probeResult === null && ! ($matrix['strong_revalidation_candidate'] ?? false)) {
            return self::LANE_PROBE_REQUIRED;
        }

        if (($sellability['recommended_lane'] ?? '') === SabreControlledPnrSellabilityDiagnostics::LANE_NO_SAFE_RETRY) {
            return self::LANE_NO_SAFE_RETRY;
        }

        return self::LANE_APPLY_REQUIRED;
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<string, mixed>  $sellability
     */
    protected function recommendedNextActionForLane(string $lane, array $matrix, array $sellability): string
    {
        return match ($lane) {
            self::LANE_STRONG_READY => 'Strong BFM revalidation linkage present; controlled PNR retry still requires explicit new approval (no automatic retry).',
            self::LANE_APPLY_REQUIRED => 'Run controlled strong linkage apply (dry-run first) to persist BFM pricing/offer refs on booking meta.',
            self::LANE_PROBE_REQUIRED => 'Run read-only probe (--probe-revalidate) to confirm fresh shop BFM linkage before apply.',
            self::LANE_RESPONSE_UNUSABLE => 'Fresh shop/revalidation probe failed or returned unusable linkage; re-shop or staff review required.',
            self::LANE_NO_SAFE_RETRY => 'No safe strong-linkage or retry path recommended at this time.',
            default => 'Review strong linkage matrix and F9M sellability before any controlled PNR retry.',
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
