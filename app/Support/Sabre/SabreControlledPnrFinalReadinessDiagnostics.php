<?php

namespace App\Support\Sabre;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Carbon;

/**
 * F9P: Read-only final controlled Sabre PNR retry readiness after F9N fresh context + F9O strong linkage (no supplier HTTP, no DB mutation).
 * F9R: Blocks retry readiness when F9Q allowance was consumed and Sabre returned a post-final-retry host failure.
 */
final class SabreControlledPnrFinalReadinessDiagnostics
{
    public function __construct(
        protected SabreControlledPnrSellabilityDiagnostics $sellabilityDiagnostics,
        protected SabreControlledPnrStrongRevalidationLinkageDiagnostics $linkageDiagnostics,
        protected SabreBookingService $sabreBookingService,
        protected SabreControlledFreshPnrContextApply $freshContextApply,
        protected SabreControlledStrongRevalidationLinkageApply $strongLinkageApply,
        protected SabreControlledFinalPnrRetryAllowanceGate $finalPnrRetryAllowanceGate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspectBooking(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings', 'tickets']);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $sellability = $this->sellabilityDiagnostics->inspectBooking($booking, false);
        $linkageInspect = $this->linkageDiagnostics->inspectBooking($booking, false);
        $payloadDigest = $this->sabreBookingService->inspectControlledPnrPayloadDigestForBooking($booking);

        $freshRecord = $this->freshContextApply->extractRecord($meta);
        $strongRecord = $this->strongLinkageApply->extractRecord($meta);

        $freshContextPresent = $freshRecord !== null && ($freshRecord['applied'] ?? false) === true;
        $strongLinkagePresent = $strongRecord !== null && ($strongRecord['applied'] ?? false) === true;
        $strongRecheckRequired = is_array($strongRecord)
            && (($strongRecord['recheck_required'] ?? false) === true || ($strongRecord['applied'] ?? false) !== true);

        $freshness = self::evaluateFinalFreshness(
            (string) ($linkageInspect['last_revalidated_at'] ?? $sellability['last_revalidated_at'] ?? $meta['last_revalidated_at'] ?? ''),
            (string) ($linkageInspect['selected_offer_created_at'] ?? $sellability['selected_offer_created_at'] ?? ''),
            (string) ($freshRecord['applied_at'] ?? ''),
        );

        $matrix = is_array($linkageInspect['strong_linkage_matrix'] ?? null)
            ? $linkageInspect['strong_linkage_matrix']
            : [];
        $comparison = is_array($payloadDigest['context_comparison'] ?? null)
            ? $payloadDigest['context_comparison']
            : [];

        $linkageStrength = (string) ($linkageInspect['current_revalidation_linkage_strength'] ?? 'none');
        $weakRevalidationRisk = ($linkageInspect['weak_revalidation_risk'] ?? true) === true;

        $strongLinkageReady = $linkageStrength === 'strong'
            && $strongLinkagePresent
            && ! $weakRevalidationRisk
            && ($matrix['strong_revalidation_candidate'] ?? false) === true
            && ! $strongRecheckRequired;

        $schemaStatus = (string) ($payloadDigest['cpnr_schema_validation_status'] ?? 'not_run');
        $hardPayloadRisk = ($sellability['hard_payload_risk'] ?? false) === true;
        $payloadClean = ($payloadDigest['post_f9i_payload_digest_clean'] ?? false) === true
            || ($sellability['post_f9i_payload_digest_clean'] ?? false) === true;

        $existingRetryAllowancesConsumed = $this->allControlledRetriesConsumed($meta);
        $allowancePresent = SabreControlledFinalPnrRetryAllowanceGate::allowancePresentInMeta($meta);
        $allowanceValid = SabreControlledFinalPnrRetryAllowanceGate::isAllowanceValidInMeta($meta, $booking);
        $allowanceRecord = is_array($meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY] ?? null)
            ? $meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY]
            : [];

        $finalPnrRetryBlockers = $this->buildFinalPnrRetryBlockers(
            $booking,
            $freshContextPresent,
            $strongLinkagePresent,
            $strongLinkageReady,
            $strongRecheckRequired,
            ($freshness['final_freshness_ready'] ?? false) === true,
            $schemaStatus,
            $hardPayloadRisk,
            $payloadClean,
        );

        $containment = $this->finalPnrRetryAllowanceGate->assessPostFinalRetryContainment($booking);
        $contained = ($containment['contained'] ?? false) === true;

        $finalPnrRetryReady = $finalPnrRetryBlockers === [];
        $newExplicitRetryApprovalRequired = $finalPnrRetryReady && ! $allowanceValid && ! $contained;

        $out = [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? $booking->booking_reference ?? ''),
            'pnr_present' => trim((string) ($booking->pnr ?? '')) !== '',
            'supplier_reference_present' => trim((string) ($booking->supplier_reference ?? '')) !== '',
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'controlled_fresh_context_apply_present' => $freshContextPresent,
            'controlled_strong_revalidation_linkage_apply_present' => $strongLinkagePresent,
            'strong_revalidation_linkage_ready' => $strongLinkageReady,
            'strong_linkage_recheck_required' => $strongRecheckRequired,
            'weak_revalidation_risk' => $weakRevalidationRisk,
            'stale_context_risk' => ($sellability['stale_context_risk'] ?? true) === true,
            'minutes_since_revalidation' => $freshness['minutes_since_revalidation'],
            'final_freshness_ready' => ($freshness['final_freshness_ready'] ?? false) === true,
            'final_freshness_blockers' => $freshness['final_freshness_blockers'],
            'payload_digest_status' => (string) ($payloadDigest['digest_status'] ?? $sellability['payload_digest_status'] ?? ''),
            'cpnr_schema_validation_status' => $schemaStatus,
            'post_f9i_payload_digest_clean' => $payloadClean,
            'hard_payload_risk' => $hardPayloadRisk,
            'brand_match' => $comparison['brand_match'] ?? ($matrix['brand_match'] ?? null),
            'fare_basis_match' => $comparison['fare_basis_match'] ?? ($matrix['fare_basis_match'] ?? null),
            'rbd_match' => $comparison['rbd_match'] ?? ($matrix['rbd_match'] ?? null),
            'route_match' => $comparison['route_match'] ?? null,
            'date_match' => $comparison['date_match'] ?? null,
            'existing_retry_allowances_consumed' => $existingRetryAllowancesConsumed,
            'new_explicit_retry_approval_required' => $newExplicitRetryApprovalRequired,
            'controlled_final_pnr_retry_allowance_present' => $allowancePresent,
            'controlled_final_pnr_retry_allowance_valid' => $allowanceValid,
            'controlled_final_pnr_retry_allowance_expires_at' => isset($allowanceRecord['expires_at'])
                ? (string) $allowanceRecord['expires_at']
                : null,
            'final_pnr_retry_ready' => $finalPnrRetryReady,
            'final_pnr_retry_blockers' => $finalPnrRetryBlockers,
            'controlled_final_pnr_retry_allowance_used' => ($containment['controlled_final_pnr_retry_allowance_used'] ?? false) === true,
            'final_controlled_create_attempted' => ($containment['final_controlled_create_attempted'] ?? false) === true,
            'final_controlled_create_failed' => ($containment['final_controlled_create_failed'] ?? false) === true,
            'post_final_retry_host_failure' => ($containment['post_final_retry_host_failure'] ?? false) === true,
            'post_final_retry_host_failure_code' => $containment['post_final_retry_host_failure_code'] ?? null,
            'no_safe_retry_without_remediation' => ($containment['no_safe_retry_without_remediation'] ?? false) === true,
            'recommended_next_action' => $this->recommendedNextAction(
                $strongLinkageReady,
                ($freshness['final_freshness_ready'] ?? false) === true,
                $strongRecheckRequired,
                $finalPnrRetryReady,
                $finalPnrRetryBlockers,
                $allowanceValid,
                $contained,
            ),
        ];

        return SensitiveDataRedactor::redact($out);
    }

    /**
     * F9P final freshness window (independent of F9M stale_context_risk).
     *
     * @return array{
     *     final_freshness_ready: bool,
     *     final_freshness_blockers: list<string>,
     *     minutes_since_revalidation: int|null,
     *     freshness_anchor_at: string|null
     * }
     */
    public static function evaluateFinalFreshness(
        ?string $lastRevalidatedAt,
        ?string $selectedOfferCreatedAt,
        ?string $freshContextAppliedAt,
    ): array {
        $anchor = self::mostRecentTimestamp(
            $lastRevalidatedAt,
            $selectedOfferCreatedAt,
            $freshContextAppliedAt,
        );

        $minutes = self::minutesSince($anchor);
        $maxMinutes = (int) config('ota.controlled_final_pnr_freshness.max_minutes', 15);

        if ($anchor === null || $minutes === null) {
            return [
                'final_freshness_ready' => false,
                'final_freshness_blockers' => ['final_refresh_required'],
                'minutes_since_revalidation' => $minutes,
                'freshness_anchor_at' => $anchor,
            ];
        }

        $ready = $minutes <= $maxMinutes;

        return [
            'final_freshness_ready' => $ready,
            'final_freshness_blockers' => $ready ? [] : ['final_refresh_required'],
            'minutes_since_revalidation' => $minutes,
            'freshness_anchor_at' => $anchor,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    protected function buildFinalPnrRetryBlockers(
        Booking $booking,
        bool $freshContextPresent,
        bool $strongLinkagePresent,
        bool $strongLinkageReady,
        bool $strongRecheckRequired,
        bool $finalFreshnessReady,
        string $schemaStatus,
        bool $hardPayloadRisk,
        bool $payloadClean,
    ): array {
        $blockers = [];

        if (trim((string) ($booking->pnr ?? '')) !== '') {
            $blockers[] = 'existing_pnr_present';
        }

        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            $blockers[] = 'existing_supplier_reference_present';
        }

        if ($booking->status === BookingStatus::Cancelled) {
            $blockers[] = 'cancelled_booking_blocked';
        }

        if ($this->bookingIsTicketed($booking)) {
            $blockers[] = 'ticketed_booking_blocked';
        }

        if (! $freshContextPresent) {
            $blockers[] = 'fresh_context_apply_missing';
        }

        if (! $strongLinkagePresent) {
            $blockers[] = 'strong_linkage_apply_missing';
        }

        if ($strongRecheckRequired) {
            $blockers[] = 'strong_linkage_recheck_required';
        }

        if (! $strongLinkageReady) {
            $blockers[] = 'strong_revalidation_linkage_not_ready';
        }

        if (! $finalFreshnessReady) {
            $blockers[] = 'final_refresh_required';
        }

        if ($schemaStatus !== 'pass') {
            $blockers[] = 'cpnr_schema_validation_not_pass';
        }

        if ($hardPayloadRisk) {
            $blockers[] = 'hard_payload_risk';
        }

        if (! $payloadClean) {
            $blockers[] = 'post_f9i_payload_digest_not_clean';
        }

        $containment = $this->finalPnrRetryAllowanceGate->assessPostFinalRetryContainment($booking);
        if (($containment['contained'] ?? false) === true) {
            $blockers = array_merge(
                $blockers,
                is_array($containment['blockers'] ?? null) ? $containment['blockers'] : [],
            );
        }

        return array_values(array_unique($blockers));
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
     * @param  list<string>  $blockers
     */
    protected function recommendedNextAction(
        bool $strongLinkageReady,
        bool $finalFreshnessReady,
        bool $strongRecheckRequired,
        bool $finalPnrRetryReady,
        array $blockers,
        bool $allowanceValid = false,
        bool $postFinalRetryContained = false,
    ): string {
        if ($postFinalRetryContained) {
            return 'Staff review / Sabre host/PCC/QR/RBD/fare basis/brand qualifier investigation.';
        }

        if ($finalPnrRetryReady && $allowanceValid) {
            return 'Final controlled PNR retry allowance is valid; run sabre:controlled-create-pnr --dry-run then live create with exact confirm on server SSH only.';
        }

        if ($finalPnrRetryReady) {
            return 'Final controlled PNR retry readiness is green; run sabre:allow-final-controlled-pnr-retry with exact confirm before live create.';
        }

        if ($strongRecheckRequired) {
            return 'Re-run controlled strong revalidation linkage apply after fresh context refresh before requesting a new controlled PNR retry approval.';
        }

        if ($strongLinkageReady && ! $finalFreshnessReady) {
            return 'Run controlled fresh-context apply immediately before requesting a new controlled PNR retry approval.';
        }

        if (in_array('cpnr_schema_validation_not_pass', $blockers, true)) {
            return 'Fix CPNR schema validation before final controlled PNR retry readiness.';
        }

        if (in_array('strong_revalidation_linkage_not_ready', $blockers, true)
            || in_array('strong_linkage_apply_missing', $blockers, true)) {
            return 'Complete controlled strong BFM revalidation linkage apply before final readiness check.';
        }

        return 'Resolve final PNR retry blockers before requesting a new controlled PNR retry approval.';
    }

    protected function bookingIsTicketed(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ticketed) {
            return true;
        }

        $booking->loadMissing(['supplierBookings', 'tickets']);

        return $booking->supplierBookings->contains(
            fn ($item) => (string) $item->status === 'ticketed',
        ) || $booking->tickets->isNotEmpty();
    }

    protected static function minutesSince(?string $iso): ?int
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

    protected static function mostRecentTimestamp(?string ...$candidates): ?string
    {
        $latest = null;
        $latestAt = null;

        foreach ($candidates as $iso) {
            if ($iso === null || trim($iso) === '') {
                continue;
            }

            try {
                $at = Carbon::parse($iso);
                if ($latestAt === null || $at->greaterThan($latestAt)) {
                    $latestAt = $at;
                    $latest = $iso;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $latest;
    }
}
