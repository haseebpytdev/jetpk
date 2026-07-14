<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Sabre\SabreControlledPnrFinalReadinessDiagnostics;
use App\Support\Sabre\SabreControlledPnrSellabilityDiagnostics;

/**
 * F9N: Controlled fresh shop context apply eligibility and safe meta marker (no PNR/ticketing/cancellation).
 */
final class SabreControlledFreshPnrContextApply
{
    public const META_KEY = 'controlled_fresh_pnr_context_apply';

    public const APPLIED_BY_CONTROLLED_COMMAND = 'controlled_command';

    public const APPLY_REASON = 'fresh_probe_ready_to_apply_after_no_fares_rbd_carrier';

    public const RERUN_REASON = 'final_freshness_after_strong_linkage';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreControlledPnrManualReviewApproval $manualReviewApproval,
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
        protected SabreControlledPnrContextDigest $contextDigest,
        protected SabreBookingService $sabreBookingService,
    ) {}

    public static function confirmPhraseForBooking(Booking $booking): string
    {
        return 'APPLY-FRESH-CONTEXT-FOR-BOOKING-'.$booking->id;
    }

    public function isAlreadyApplied(array $meta): bool
    {
        $record = $this->extractRecord($meta);

        return ($record['applied'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $refresh
     * @return array<string, mixed>
     */
    public function buildProbeFromRefresh(array $refresh): array
    {
        $existingRbd = is_array($refresh['existing_rbd_list'] ?? null) ? $refresh['existing_rbd_list'] : [];
        $freshRbd = is_array($refresh['fresh_rbd_list'] ?? null) ? $refresh['fresh_rbd_list'] : [];
        $existingFb = is_array($refresh['existing_fare_basis_list'] ?? null) ? $refresh['existing_fare_basis_list'] : [];
        $freshFb = is_array($refresh['fresh_fare_basis_list'] ?? null) ? $refresh['fresh_fare_basis_list'] : [];
        $error = trim((string) ($refresh['error'] ?? ''));

        return [
            'probe_status' => $error !== '' ? 'error' : 'ok',
            'probe_error_summary' => $error !== '' ? substr($error, 0, 120) : null,
            'match_found' => ($refresh['match_found'] ?? false) === true,
            'match_confidence' => (string) ($refresh['match_confidence'] ?? ''),
            'same_flight_numbers' => ($refresh['same_flight_numbers'] ?? false) === true,
            'same_rbd_list' => SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList($existingRbd, $freshRbd),
            'fare_basis_match' => SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList($existingFb, $freshFb),
            'price_changed' => ($refresh['price_changed'] ?? false) === true,
            'existing_rbd_list' => $existingRbd,
            'fresh_rbd_list' => $freshRbd,
            'existing_fare_basis_list' => $existingFb,
            'fresh_fare_basis_list' => $freshFb,
            'reasons' => is_array($refresh['reasons'] ?? null) ? array_values($refresh['reasons']) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array{ready: bool, blockers: list<string>}
     */
    public function evaluateProbeReadiness(array $probe): array
    {
        $blockers = [];

        if (($probe['probe_status'] ?? '') !== 'ok') {
            $blockers[] = 'fresh_probe_error';
        }

        if (($probe['match_found'] ?? false) !== true) {
            $blockers[] = 'fresh_probe_match_not_found';
        }

        if (strtolower(trim((string) ($probe['match_confidence'] ?? ''))) !== 'high') {
            $blockers[] = 'fresh_probe_match_confidence_not_high';
        }

        if (($probe['same_flight_numbers'] ?? false) !== true) {
            $blockers[] = 'fresh_probe_flight_number_mismatch';
        }

        if (($probe['same_rbd_list'] ?? false) !== true) {
            $blockers[] = 'fresh_probe_rbd_mismatch';
        }

        if (($probe['fare_basis_match'] ?? false) !== true) {
            $blockers[] = 'fresh_probe_fare_basis_mismatch';
        }

        $reasons = is_array($probe['reasons'] ?? null) ? $probe['reasons'] : [];
        if (! in_array('ready_to_apply', $reasons, true)) {
            $blockers[] = 'fresh_probe_not_ready_to_apply';
        }

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    /**
     * @param  array<string, mixed>  $probe
     * @param  array<string, mixed>  $sellability
     * @return array{
     *     eligible: bool,
     *     blockers: list<string>,
     *     probe_ready: bool,
     *     controlled_pnr_manual_review_approved: bool,
     *     fare_change_accepted: bool,
     *     has_usable_controlled_pnr_context: bool,
     *     cpnr_schema_validation_status: string,
     * }
     */
    public function evaluateEligibility(
        Booking $booking,
        array $probe,
        array $sellability,
        bool $dryRun,
        bool $confirmAcknowledgesFareContext,
    ): array {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings', 'tickets']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $blockers = [];

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

        $isFinalFreshnessRerun = $this->isFinalFreshnessRerunEligible($booking, $meta, $sellability);
        if ($this->isAlreadyApplied($meta) && ! $isFinalFreshnessRerun) {
            $blockers[] = 'fresh_context_already_applied';
        }

        $manualReviewApproved = $this->manualReviewApproval->isApproved($meta);
        if (! $manualReviewApproved) {
            $blockers[] = 'controlled_pnr_manual_review_not_approved';
        }

        $contextClassify = $this->contextDigest->classify($booking);
        $hasUsableContext = ($contextClassify['has_usable_controlled_pnr_context'] ?? false) === true;
        if (! $hasUsableContext) {
            $blockers[] = 'controlled_context_unusable';
        }

        if (! $isFinalFreshnessRerun
            && ($sellability['recommended_lane'] ?? '') !== SabreControlledPnrSellabilityDiagnostics::LANE_REFRESH_REQUIRED) {
            $blockers[] = 'sellability_lane_not_refresh_required';
        }

        if (($sellability['hard_payload_risk'] ?? false) === true) {
            $blockers[] = 'hard_payload_risk';
        }

        $payloadDigest = $this->sabreBookingService->inspectControlledPnrPayloadDigestForBooking($booking);
        $schemaStatus = (string) ($payloadDigest['cpnr_schema_validation_status'] ?? 'not_run');
        if ($schemaStatus !== 'pass') {
            $blockers[] = 'cpnr_schema_validation_not_pass';
        }

        $fareChangeAccepted = $this->fareChangeAcceptance->isAccepted($meta);
        $probePriceChanged = ($probe['price_changed'] ?? false) === true;
        if ($probePriceChanged && ! $fareChangeAccepted) {
            if ($dryRun || ! $confirmAcknowledgesFareContext) {
                $blockers[] = 'fresh_probe_price_change_requires_acceptance';
            }
        }

        $probeReadiness = $this->evaluateProbeReadiness($probe);
        foreach ($probeReadiness['blockers'] as $probeBlocker) {
            $blockers[] = $probeBlocker;
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'probe_ready' => ($probeReadiness['ready'] ?? false) === true,
            'controlled_pnr_manual_review_approved' => $manualReviewApproved,
            'fare_change_accepted' => $fareChangeAccepted,
            'has_usable_controlled_pnr_context' => $hasUsableContext,
            'cpnr_schema_validation_status' => $schemaStatus,
            'final_freshness_rerun' => $isFinalFreshnessRerun,
        ];
    }

    /**
     * @param  array<string, mixed>  $probe
     * @param  array<string, mixed>|null  $priorRecord
     * @return array<string, mixed>
     */
    public function buildApplyRecord(Booking $booking, array $probe, ?array $priorRecord = null): array
    {
        $record = [
            'applied' => true,
            'applied_at' => now()->toIso8601String(),
            'applied_by' => self::APPLIED_BY_CONTROLLED_COMMAND,
            'booking_reference' => (string) ($booking->booking_reference ?? $booking->reference_code ?? ''),
            'reason' => self::APPLY_REASON,
            'match_confidence' => 'high',
            'same_flight_numbers' => ($probe['same_flight_numbers'] ?? false) === true,
            'same_rbd_list' => ($probe['same_rbd_list'] ?? false) === true,
            'fare_basis_match' => ($probe['fare_basis_match'] ?? false) === true,
        ];

        if (is_array($priorRecord) && ($priorRecord['applied'] ?? false) === true) {
            $record['rerun'] = true;
            $record['rerun_reason'] = self::RERUN_REASON;
            $record['prior_applied_at'] = (string) ($priorRecord['applied_at'] ?? '');
            $record['rerun_count'] = ((int) ($priorRecord['rerun_count'] ?? 0)) + 1;
        }

        return $record;
    }

    /**
     * F9P: allow controlled fresh-context re-run when F9N+F9O applied and final freshness window expired.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $sellability
     */
    public function isFinalFreshnessRerunEligible(Booking $booking, array $meta, array $sellability): bool
    {
        if (! $this->isAlreadyApplied($meta)) {
            return false;
        }

        $strongRecord = $meta[SabreControlledStrongRevalidationLinkageApply::META_KEY] ?? null;
        if (! is_array($strongRecord) || ($strongRecord['applied'] ?? false) !== true) {
            return false;
        }

        if (($strongRecord['recheck_required'] ?? false) === true) {
            return false;
        }

        if ($this->detectExistingPnr($booking)) {
            return false;
        }

        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return false;
        }

        if ($booking->status === BookingStatus::Cancelled || $this->isTicketed($booking)) {
            return false;
        }

        $freshRecord = $this->extractRecord($meta) ?? [];
        $freshness = SabreControlledPnrFinalReadinessDiagnostics::evaluateFinalFreshness(
            (string) ($sellability['last_revalidated_at'] ?? $meta['last_revalidated_at'] ?? ''),
            (string) ($sellability['selected_offer_created_at'] ?? ''),
            (string) ($freshRecord['applied_at'] ?? ''),
        );

        return ($freshness['final_freshness_ready'] ?? false) === false;
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
}
