<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreStoredPricingContextDigest;
use App\Support\Sabre\SabreControlledPnrStrongRevalidationLinkageDiagnostics;
use Illuminate\Support\Carbon;

/**
 * F9O: Controlled strong BFM revalidation linkage apply (meta/snapshot only; no PNR/ticketing/cancellation).
 * F9O-R1: Apply eligibility follows F9O strong-linkage diagnostic — not F9M sellability lane.
 */
final class SabreControlledStrongRevalidationLinkageApply
{
    public const META_KEY = 'controlled_strong_revalidation_linkage_apply';

    public const APPLIED_BY_CONTROLLED_COMMAND = 'controlled_command';

    public const APPLY_REASON = 'fresh_context_requires_strong_bfm_revalidation_linkage';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreBookingService $sabreBookingService,
        protected SabreStoredPricingContextDigest $pricingContextDigest,
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
    ) {}

    public static function confirmPhraseForBooking(Booking $booking): string
    {
        return 'APPLY-STRONG-REVALIDATION-LINKAGE-FOR-BOOKING-'.$booking->id;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function isAlreadyApplied(array $meta): bool
    {
        $record = $this->extractRecord($meta);

        return ($record['applied'] ?? false) === true;
    }

    /**
     * F9O-R1: Eligibility uses F9O linkage inspect as source of truth; F9M sellability lane is informational only.
     *
     * @param  array<string, mixed>  $sellability
     * @param  array<string, mixed>  $linkageInspect
     * @return array{
     *     eligible: bool,
     *     blockers: list<string>,
     *     f9o_diagnostic_recommended_lane: string,
     *     sellability_recommended_lane: string,
     *     sellability_lane_used_as_hard_gate: bool,
     *     stale_context_risk_hard_blocker: bool,
     *     strong_linkage_candidate: bool,
     *     strong_linkage_blockers: list<string>,
     *     formal_revalidation_linkage_complete_before_apply: bool
     * }
     */
    public function evaluateEligibility(
        Booking $booking,
        array $sellability,
        array $linkageInspect,
        bool $dryRun,
    ): array {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings', 'tickets']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $blockers = [];

        $f9oLane = (string) ($linkageInspect['recommended_lane'] ?? '');
        $sellabilityLane = (string) ($sellability['recommended_lane'] ?? '');

        $matrix = is_array($linkageInspect['strong_linkage_matrix'] ?? null)
            ? $linkageInspect['strong_linkage_matrix']
            : [];
        $matrixBlockers = is_array($matrix['strong_revalidation_blockers'] ?? null)
            ? array_values($matrix['strong_revalidation_blockers'])
            : [];
        $strongCandidate = ($matrix['strong_revalidation_candidate'] ?? false) === true;
        $staleHardBlocker = $this->isStaleContextHardBlocker($linkageInspect, $meta);

        if (! $this->certificationSupport->isSabreBooking($booking)) {
            $blockers[] = 'not_sabre_booking';
        }

        if ($this->detectExistingPnr($booking)) {
            $blockers[] = 'existing_pnr_present';
        }

        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            $blockers[] = 'existing_supplier_reference_present';
        }

        if ($booking->status === BookingStatus::Cancelled) {
            $blockers[] = 'cancelled_booking_blocked';
        }

        if ($this->isTicketed($booking)) {
            $blockers[] = 'ticketed_booking_blocked';
        }

        if ($this->isAlreadyApplied($meta)) {
            $blockers[] = 'strong_linkage_already_applied';
        }

        $freshRecord = $meta[SabreControlledFreshPnrContextApply::META_KEY] ?? null;
        if (! is_array($freshRecord) || ($freshRecord['applied'] ?? false) !== true) {
            $blockers[] = 'fresh_context_apply_missing';
        }

        if ($staleHardBlocker) {
            $blockers[] = 'stale_context_risk';
        }

        if ($f9oLane !== SabreControlledPnrStrongRevalidationLinkageDiagnostics::LANE_APPLY_REQUIRED) {
            $blockers[] = 'f9o_lane_not_apply_required';
        }

        if (! $strongCandidate) {
            $blockers[] = 'strong_linkage_candidate_absent';
            foreach ($matrixBlockers as $b) {
                $blockers[] = 'matrix_'.$b;
            }
        }

        if (($matrix['itinerary_ref_present'] ?? false) !== true) {
            $blockers[] = 'missing_itinerary_ref';
        }
        if (($matrix['leg_refs_present'] ?? false) !== true) {
            $blockers[] = 'missing_leg_refs';
        }
        if (($matrix['schedule_refs_present'] ?? false) !== true) {
            $blockers[] = 'missing_schedule_refs';
        }
        if (($matrix['pricing_information_index_present'] ?? false) !== true) {
            $blockers[] = 'missing_pricing_information_index';
        }
        if (($matrix['fare_component_refs_present'] ?? false) !== true) {
            $blockers[] = 'missing_fare_component_refs';
        }
        if (($matrix['rbd_match'] ?? false) !== true) {
            $blockers[] = 'rbd_mismatch';
        }
        if (($matrix['fare_basis_match'] ?? false) !== true) {
            $blockers[] = 'fare_basis_mismatch';
        }
        if (($matrix['brand_match'] ?? null) === false) {
            $blockers[] = 'brand_mismatch';
        }
        if (($matrix['validating_carrier_present'] ?? false) !== true) {
            $blockers[] = 'validating_carrier_missing';
        }
        if (($matrix['segment_count_match'] ?? false) !== true) {
            $blockers[] = 'segment_count_mismatch';
        }
        if (($matrix['pricing_total_match'] ?? null) === false) {
            $blockers[] = 'pricing_total_mismatch';
        }

        if (($linkageInspect['revalidation_probe']['price_changed'] ?? false) === true
            && ! $this->fareChangeAcceptance->isAccepted($meta)) {
            $blockers[] = 'price_change_requires_acceptance';
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'f9o_diagnostic_recommended_lane' => $f9oLane,
            'sellability_recommended_lane' => $sellabilityLane,
            'sellability_lane_used_as_hard_gate' => false,
            'stale_context_risk_hard_blocker' => $staleHardBlocker,
            'strong_linkage_candidate' => $strongCandidate,
            'strong_linkage_blockers' => $matrixBlockers,
            'formal_revalidation_linkage_complete_before_apply' => ($matrix['formal_revalidation_linkage_complete'] ?? false) === true,
        ];
    }

    /**
     * Hard stale blocker only when F9N apply is absent, context timestamps are missing, or age exceeds controlled apply window.
     *
     * @param  array<string, mixed>  $linkageInspect
     * @param  array<string, mixed>  $meta
     */
    public function isStaleContextHardBlocker(array $linkageInspect, array $meta): bool
    {
        $freshRecord = $meta[SabreControlledFreshPnrContextApply::META_KEY] ?? null;
        if (! is_array($freshRecord) || ($freshRecord['applied'] ?? false) !== true) {
            return true;
        }

        $referenceAt = $this->mostRecentContextTimestamp(
            $linkageInspect['last_revalidated_at'] ?? null,
            $linkageInspect['selected_offer_created_at'] ?? null,
            is_array($freshRecord) ? ($freshRecord['applied_at'] ?? null) : null,
        );

        if ($referenceAt === null) {
            return true;
        }

        $minutes = $this->minutesSince($referenceAt);
        if ($minutes === null) {
            return true;
        }

        $windowMinutes = (int) config('ota.controlled_strong_linkage_apply.max_minutes_after_fresh_context_apply', 180);

        return $minutes > $windowMinutes;
    }

    /**
     * @return array{
     *     applied: bool,
     *     blockers: list<string>,
     *     readiness_before: array<string, mixed>,
     *     readiness_after: array<string, mixed>,
     *     applied_fields: list<string>,
     *     record: array<string, mixed>|null
     * }
     */
    public function applyLinkage(Booking $booking, array $linkageInspect): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        if ($snapshot === []) {
            return [
                'applied' => false,
                'blockers' => ['missing_offer_snapshot'],
                'readiness_before' => [],
                'readiness_after' => [],
                'applied_fields' => [],
                'record' => null,
            ];
        }

        $rebuild = $this->pricingContextDigest->rebuildSnapshotPricingLinkage($snapshot);
        $readinessAfter = is_array($rebuild['readiness_after'] ?? null) ? $rebuild['readiness_after'] : [];
        $autoReady = ($readinessAfter['auto_pnr_pricing_context_ready'] ?? false) === true;
        $formalReady = ($readinessAfter['has_revalidation_linkage_complete'] ?? false) === true;

        if (! $autoReady && ! $formalReady) {
            return [
                'applied' => false,
                'blockers' => ['rebuild_did_not_produce_strong_linkage'],
                'readiness_before' => is_array($rebuild['readiness_before'] ?? null) ? $rebuild['readiness_before'] : [],
                'readiness_after' => $readinessAfter,
                'applied_fields' => [],
                'record' => null,
            ];
        }

        $updatedSnapshot = is_array($rebuild['snapshot'] ?? null) ? $rebuild['snapshot'] : $snapshot;
        $appliedFields = is_array($rebuild['applied_fields'] ?? null) ? $rebuild['applied_fields'] : [];

        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            if (array_key_exists($key, $meta)) {
                $meta[$key] = $updatedSnapshot;
            }
        }

        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $handoff['has_revalidation_linkage'] = true;
        $handoff['ready_for_booking_payload'] = true;
        $handoff['strong_bfm_revalidation_linkage_applied'] = true;
        $meta['sabre_booking_context'] = $handoff;

        $matrix = is_array($linkageInspect['strong_linkage_matrix'] ?? null)
            ? $linkageInspect['strong_linkage_matrix']
            : [];

        $record = $this->buildApplyRecord($booking, $matrix);
        $meta[self::META_KEY] = $record;
        $booking->forceFill(['meta' => $meta]);
        $booking->save();

        return [
            'applied' => true,
            'blockers' => [],
            'readiness_before' => is_array($rebuild['readiness_before'] ?? null) ? $rebuild['readiness_before'] : [],
            'readiness_after' => $readinessAfter,
            'applied_fields' => $appliedFields,
            'record' => $record,
        ];
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array<string, mixed>
     */
    public function buildApplyRecord(Booking $booking, array $matrix): array
    {
        return [
            'applied' => true,
            'applied_at' => now()->toIso8601String(),
            'applied_by' => self::APPLIED_BY_CONTROLLED_COMMAND,
            'booking_reference' => (string) ($booking->booking_reference ?? $booking->reference_code ?? ''),
            'reason' => self::APPLY_REASON,
            'segment_count_match' => ($matrix['segment_count_match'] ?? false) === true,
            'rbd_match' => ($matrix['rbd_match'] ?? false) === true,
            'fare_basis_match' => ($matrix['fare_basis_match'] ?? false) === true,
            'brand_match' => ($matrix['brand_match'] ?? null) !== false,
            'validating_carrier_match' => ($matrix['validating_carrier_present'] ?? false) === true,
        ];
    }

    /**
     * F9P: After F9N final fresh re-run, preserve or invalidate F9O strong-linkage marker when refs drift.
     *
     * @param  array<string, mixed>|null  $priorStrongRecord
     * @param  array<string, mixed>  $matrix
     * @return array{
     *     strong_linkage_preserved: bool,
     *     strong_linkage_recheck_required: bool,
     *     record: array<string, mixed>|null
     * }
     */
    public function preserveOrInvalidateAfterFreshRerun(Booking $booking, ?array $priorStrongRecord, array $matrix): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $preserve = ($matrix['segment_count_match'] ?? false) === true
            && ($matrix['rbd_match'] ?? false) === true
            && ($matrix['fare_basis_match'] ?? false) === true
            && ($matrix['brand_match'] ?? null) !== false
            && ($matrix['validating_carrier_present'] ?? false) === true;

        if ($preserve && is_array($priorStrongRecord) && ($priorStrongRecord['applied'] ?? false) === true) {
            $record = $priorStrongRecord;
            $record['preserved_after_fresh_rerun_at'] = now()->toIso8601String();
            $record['recheck_required'] = false;
            unset($record['invalidated_reason'], $record['invalidated_at']);
            $meta[self::META_KEY] = $record;
        } else {
            $record = is_array($priorStrongRecord) ? $priorStrongRecord : [];
            $record['applied'] = false;
            $record['recheck_required'] = true;
            $record['invalidated_reason'] = 'fresh_rerun_refs_changed';
            $record['invalidated_at'] = now()->toIso8601String();
            $meta[self::META_KEY] = $record;

            $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
            unset($handoff['strong_bfm_revalidation_linkage_applied']);
            $meta['sabre_booking_context'] = $handoff;
        }

        $booking->forceFill(['meta' => $meta]);
        $booking->save();

        return [
            'strong_linkage_preserved' => $preserve,
            'strong_linkage_recheck_required' => ! $preserve,
            'record' => is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    public function extractRecord(array $meta): ?array
    {
        $record = $meta[self::META_KEY] ?? null;

        return is_array($record) ? $record : null;
    }

    protected function detectExistingPnr(Booking $booking): bool
    {
        $booking->loadMissing(['supplierBookings']);

        if (trim((string) ($booking->pnr ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($booking->supplier_api_booking_id ?? '')) !== '') {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );
    }

    protected function isTicketed(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ticketed) {
            return true;
        }

        $booking->loadMissing(['supplierBookings', 'tickets']);

        return $booking->supplierBookings->contains(
            fn ($item) => (string) $item->status === 'ticketed',
        ) || $booking->tickets->isNotEmpty();
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

    protected function mostRecentContextTimestamp(?string ...$candidates): ?string
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
