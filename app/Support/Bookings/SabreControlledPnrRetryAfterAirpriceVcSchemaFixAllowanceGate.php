<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Support\Sabre\SabreCpnrIatiWireSchemaValidator;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;

/**
 * F9L: One-shot controlled retry after F9J allowance was consumed by pre-HTTP CPNR schema validation
 * failure and current payload digest/schema validation passes. Separate from F9J NO-FARES retry lane.
 *
 * Applies only to sabre:controlled-create-pnr with exact confirm phrase on live path.
 * Does not weaken general retry protection, public checkout, or ticketing/cancellation paths.
 */
final class SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate
{
    public const META_KEY = 'controlled_supplier_retry_after_airprice_vc_schema_fix';

    public const USED_BY_CONTROLLED_PNR_COMMAND = 'controlled_pnr_command';

    public const USED_FOR_CONTROLLED_PNR_CREATE_AFTER_AIRPRICE_VC_SCHEMA_FIX = 'controlled_pnr_create_after_airprice_vc_schema_fix';

    public const PREVIOUS_F9J_FAILURE = 'sabre_booking_validation_failed';

    public const PREVIOUS_STAGE_PRE_HTTP = 'pre_http_schema_validation';

    public const REQUIRED_SCHEMA_VALIDATION = 'cpnr_schema_validation_status=pass';

    public function __construct(
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
        protected SabreSafeRefreshContext $safeRefreshContext,
        protected SabrePassengerRecordsPayloadDigest $payloadDigest,
        protected SabreControlledPnrRetryAllowanceGate $f9fRetryAllowanceGate,
        protected SabreCpnrIatiWireSchemaValidator $schemaValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $controlledOperationContext
     */
    public function allows(
        Booking $booking,
        SupplierBookingAttempt $meaningfulAttempt,
        string $attemptSource,
        bool $allowControlledStaffPnr,
        array $controlledOperationContext,
    ): bool {
        return $this->assessSchemaRecoveryAvailability(
            $booking,
            is_array($controlledOperationContext['post_f9i_payload_digest_summary'] ?? null)
                ? $controlledOperationContext['post_f9i_payload_digest_summary']
                : null,
            $controlledOperationContext,
            $meaningfulAttempt,
            $attemptSource,
            $allowControlledStaffPnr,
            true,
        )['available'];
    }

    /**
     * @param  array<string, mixed>|null  $digestSummary
     * @param  array<string, mixed>  $controlledOperationContext
     * @return array{
     *     available: bool,
     *     blockers: list<string>,
     *     f9k_schema_recovery_available: bool,
     *     f9k_schema_recovery_blockers: list<string>,
     *     retry_recovery_reason: string|null,
     *     post_f9i_payload_digest_clean: bool
     * }
     */
    public function assessSchemaRecoveryAvailability(
        Booking $booking,
        ?array $digestSummary,
        array $controlledOperationContext = [],
        ?SupplierBookingAttempt $meaningfulAttempt = null,
        string $attemptSource = 'controlled_pnr_command',
        bool $allowControlledStaffPnr = true,
        bool $forLive = false,
    ): array {
        $blockers = [];
        $digestClean = false;
        $retryRecoveryReason = null;

        if ($attemptSource !== 'controlled_pnr_command' || ! $allowControlledStaffPnr) {
            $blockers[] = 'non_controlled_command_context';
        }

        if (($controlledOperationContext['controlled_pnr_create'] ?? false) !== true) {
            $blockers[] = 'controlled_pnr_create_not_set';
        }

        if ($forLive) {
            $expectedConfirm = 'CREATE-PNR-FOR-BOOKING-'.$booking->id;
            if ((string) ($controlledOperationContext['controlled_approval_confirm_phrase'] ?? '') !== $expectedConfirm) {
                $blockers[] = 'exact_confirm_phrase_missing';
            }
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)
            || (bool) config('suppliers.sabre.cancel_enabled', false)) {
            $blockers[] = 'ticketing_or_cancel_enabled';
        }

        $booking->loadMissing(['supplierBookings', 'tickets', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if (! $this->f9fRetryAllowanceGate->retryAllowanceAlreadyUsed($meta)) {
            $blockers[] = 'f9f_retry_allowance_not_used';
        }

        $f9jRecord = $this->readF9jMetaRecord($meta);
        if (($f9jRecord['used'] ?? false) !== true) {
            $blockers[] = 'f9j_retry_allowance_not_used';
        }

        if ($this->schemaFixRecoveryAlreadyUsed($meta)) {
            $blockers[] = 'f9l_schema_recovery_already_used';
        }

        if (($f9jRecord['host_application_results_received'] ?? false) === true) {
            $blockers[] = 'f9j_host_application_results_received';
        }

        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== 'sabre' || (int) ($meta['supplier_connection_id'] ?? 0) <= 0) {
            $blockers[] = 'not_sabre_or_missing_connection';
        }

        if ($this->detectExistingPnr($booking)) {
            $blockers[] = 'existing_pnr_or_supplier_reference';
        }

        if ($booking->status === BookingStatus::Cancelled) {
            $blockers[] = 'booking_cancelled';
        }

        if ($this->isTicketed($booking)) {
            $blockers[] = 'booking_ticketed';
        }

        if (! $this->isManualReviewApproved($meta)) {
            $blockers[] = 'f9c_manual_review_not_approved';
        }

        if (! $this->fareChangeAcceptance->isAccepted($meta)) {
            $blockers[] = 'f9e_fare_change_not_accepted';
        }

        if (! $this->hasRequiredControlledContext($meta)) {
            $blockers[] = 'controlled_pnr_context_incomplete';
        }

        if ($forLive) {
            $readiness = is_array($controlledOperationContext['readiness_snapshot'] ?? null)
                ? $controlledOperationContext['readiness_snapshot']
                : [];

            if (($readiness['eligible'] ?? false) !== true
                || ($readiness['can_attempt_supplier_pnr'] ?? false) !== true
                || ($readiness['live_supplier_call_allowed'] ?? false) !== true
                || ($readiness['has_usable_controlled_pnr_context'] ?? false) !== true) {
                $blockers[] = 'readiness_not_eligible';
            }

            $readinessBlockers = is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : [];
            if ($readinessBlockers !== []) {
                $blockers[] = 'readiness_has_blockers';
            }

            if (($readiness['has_existing_pnr'] ?? false) === true
                || ($readiness['is_ticketed'] ?? false) === true
                || ($readiness['is_cancelled'] ?? false) === true) {
                $blockers[] = 'readiness_identity_block';
            }
        }

        if ($meaningfulAttempt === null) {
            $meaningfulAttempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
                $booking->supplierBookingAttempts,
            );
        }

        if ($this->controlledAttemptReceivedApplicationResults($meaningfulAttempt)) {
            $blockers[] = 'meaningful_attempt_has_application_results';
        }

        $preHttpFailure = $this->f9jPreHttpSchemaFailureProven($meta, $f9jRecord, $meaningfulAttempt);
        if (! $preHttpFailure['proven']) {
            $blockers[] = 'f9j_pre_http_schema_failure_not_proven';
        } else {
            $retryRecoveryReason = $preHttpFailure['reason'];
        }

        if ($digestSummary === null || $digestSummary === []) {
            $blockers[] = 'payload_digest_summary_missing';
        } else {
            $digestClean = $this->payloadDigestCleanForSchemaRecovery($digestSummary);
            if (! $digestClean) {
                foreach ($this->schemaRecoveryDigestBlockers($digestSummary) as $digestBlocker) {
                    $blockers[] = $digestBlocker;
                }
            }
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'available' => $blockers === [],
            'blockers' => $blockers,
            'f9k_schema_recovery_available' => $blockers === [],
            'f9k_schema_recovery_blockers' => $blockers,
            'retry_recovery_reason' => $retryRecoveryReason,
            'post_f9i_payload_digest_clean' => $digestClean,
        ];
    }

    /**
     * Safe read-only F9J accounting + F9L recovery signals for CLI/dry-run.
     *
     * @return array<string, mixed>
     */
    public function buildF9jAccountingDiagnostics(
        Booking $booking,
        ?SupplierBookingAttempt $meaningfulAttempt = null,
        ?array $digestSummary = null,
        array $controlledOperationContext = [],
        bool $forLive = false,
    ): array {
        $booking->loadMissing(['supplierBookings', 'tickets', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $f9jRecord = $this->readF9jMetaRecord($meta);

        if ($meaningfulAttempt === null) {
            $meaningfulAttempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
                $booking->supplierBookingAttempts,
            );
        }

        $f9jUsed = ($f9jRecord['used'] ?? false) === true;
        $hostReceived = ($f9jRecord['host_application_results_received'] ?? false) === true;
        $schemaFailedMeta = ($f9jRecord['schema_validation_failed'] ?? false) === true;

        $previousHostMessage = is_string($f9jRecord['previous_host_message'] ?? null)
            ? trim($f9jRecord['previous_host_message'])
            : '';
        $previousErrorCode = is_string($f9jRecord['previous_error_code'] ?? null)
            ? trim($f9jRecord['previous_error_code'])
            : '';

        $noFaresFromMeta = $previousHostMessage !== ''
            && (str_contains(strtoupper($previousHostMessage), 'NO FARES/RBD/CARRIER')
                || str_contains(strtoupper($previousHostMessage), 'UNABLE TO PERFORM AIR BOOKING'));

        $attemptErrorCode = $meaningfulAttempt !== null
            ? strtolower(trim((string) ($meaningfulAttempt->error_code ?? '')))
            : '';
        $attemptSafe = $meaningfulAttempt !== null && is_array($meaningfulAttempt->safe_summary)
            ? $meaningfulAttempt->safe_summary
            : [];
        $appDigestOnAttempt = ($attemptSafe['application_error_digest_available'] ?? false) === true;

        $schemaStage = 'unknown';
        if ($schemaFailedMeta || $this->attemptIndicatesPreHttpSchemaValidationFailure($meaningfulAttempt)) {
            $schemaStage = 'pre_http';
        } elseif ($hostReceived) {
            $schemaStage = 'http';
        }

        $f9lAssess = $this->assessSchemaRecoveryAvailability(
            $booking,
            $digestSummary,
            $controlledOperationContext,
            $meaningfulAttempt,
            'controlled_pnr_command',
            true,
            $forLive,
        );

        return [
            'f9j_accounting_state' => $f9jUsed ? 'consumed' : 'not_used',
            'f9j_used' => $f9jUsed,
            'f9j_used_at' => is_string($f9jRecord['used_at'] ?? null) ? $f9jRecord['used_at'] : null,
            'f9j_used_for' => is_string($f9jRecord['used_for'] ?? null) ? $f9jRecord['used_for'] : null,
            'f9j_previous_error_code' => $previousErrorCode !== '' ? $previousErrorCode : null,
            'f9j_previous_host_message_present' => $previousHostMessage !== '',
            'f9j_previous_no_fares_rbd_carrier_present' => $noFaresFromMeta,
            'f9j_schema_validation_failed' => $schemaFailedMeta
                || $this->attemptIndicatesPreHttpSchemaValidationFailure($meaningfulAttempt),
            'f9j_schema_validation_stage' => $schemaStage,
            'f9j_host_application_results_received' => $hostReceived,
            'f9j_previous_attempt_error_code' => $attemptErrorCode !== '' ? $attemptErrorCode : null,
            'f9k_schema_recovery_available' => ($f9lAssess['f9k_schema_recovery_available'] ?? false) === true,
            'f9k_schema_recovery_blockers' => is_array($f9lAssess['f9k_schema_recovery_blockers'] ?? null)
                ? array_values($f9lAssess['f9k_schema_recovery_blockers'])
                : [],
            'controlled_retry_after_airprice_vc_schema_fix_available' => ($f9lAssess['available'] ?? false) === true,
            'retry_recovery_reason' => $f9lAssess['retry_recovery_reason'] ?? null,
            'f9l_schema_recovery_already_used' => $this->schemaFixRecoveryAlreadyUsed($meta),
            'meaningful_attempt_application_digest_present' => $appDigestOnAttempt,
        ];
    }

    public function recordUsage(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($this->schemaFixRecoveryAlreadyUsed($meta)) {
            return;
        }

        $meta[self::META_KEY] = [
            'used' => true,
            'used_at' => now()->toIso8601String(),
            'used_by' => self::USED_BY_CONTROLLED_PNR_COMMAND,
            'used_for' => self::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_AIRPRICE_VC_SCHEMA_FIX,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'previous_f9j_failure' => self::PREVIOUS_F9J_FAILURE,
            'previous_stage' => self::PREVIOUS_STAGE_PRE_HTTP,
            'required_schema_validation' => self::REQUIRED_SCHEMA_VALIDATION,
        ];

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function schemaFixRecoveryAlreadyUsed(array $meta): bool
    {
        $record = $meta[self::META_KEY] ?? null;

        return is_array($record) && ($record['used'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function readF9jMetaRecord(array $meta): array
    {
        $record = $meta[SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY] ?? null;

        return is_array($record) ? $record : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $f9jRecord
     * @return array{proven: bool, reason: string|null}
     */
    protected function f9jPreHttpSchemaFailureProven(
        array $meta,
        array $f9jRecord,
        ?SupplierBookingAttempt $meaningfulAttempt,
    ): array {
        if (($f9jRecord['schema_validation_failed'] ?? false) === true
            && ($f9jRecord['host_application_results_received'] ?? false) !== true) {
            return ['proven' => true, 'reason' => 'f9j_meta_schema_validation_failed'];
        }

        if ($this->attemptIndicatesPreHttpSchemaValidationFailure($meaningfulAttempt)) {
            return ['proven' => true, 'reason' => 'f9j_attempt_sabre_booking_validation_failed'];
        }

        if (($f9jRecord['used'] ?? false) === true
            && ($f9jRecord['host_application_results_received'] ?? false) !== true
            && $meaningfulAttempt !== null
            && strtolower(trim((string) ($meaningfulAttempt->error_code ?? ''))) === 'sabre_booking_validation_failed'
            && ! $this->controlledAttemptReceivedApplicationResults($meaningfulAttempt)) {
            return ['proven' => true, 'reason' => 'f9j_pre_http_schema_failure_with_current_schema_pass'];
        }

        return ['proven' => false, 'reason' => null];
    }

    protected function attemptIndicatesPreHttpSchemaValidationFailure(?SupplierBookingAttempt $attempt): bool
    {
        if ($attempt === null) {
            return false;
        }

        $safeSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $errorCode = strtolower(trim((string) ($attempt->error_code ?? '')));
        $errorMessage = trim((string) ($attempt->error_message ?? ''));
        if ($errorMessage === '' && is_string($safeSummary['error_message'] ?? null)) {
            $errorMessage = trim((string) $safeSummary['error_message']);
        }

        $appDigestAvailable = ($safeSummary['application_error_digest_available'] ?? false) === true;

        if ($this->schemaValidator->outcomeLooksLikeCpnrSchemaValidationFailure(
            $errorCode !== '' ? $errorCode : strtolower(trim((string) ($safeSummary['error_code'] ?? ''))),
            $errorMessage,
            $appDigestAvailable,
        )) {
            return true;
        }

        if ($appDigestAvailable) {
            return false;
        }

        return in_array($errorCode, ['sabre_booking_validation_failed', 'sabre_booking_payload_validation_failed'], true);
    }

    protected function controlledAttemptReceivedApplicationResults(?SupplierBookingAttempt $attempt): bool
    {
        if ($attempt === null) {
            return false;
        }

        $safeSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];

        if (($safeSummary['application_error_digest_available'] ?? false) === true) {
            return true;
        }

        $errorCode = strtolower(trim((string) ($attempt->error_code ?? '')));

        return $errorCode === 'sabre_booking_application_error';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function payloadDigestCleanForSchemaRecovery(array $summary): bool
    {
        return $this->schemaRecoveryDigestBlockers($summary) === [];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    protected function schemaRecoveryDigestBlockers(array $summary): array
    {
        $blockers = $this->payloadDigest->postF9iCleanBlockers($summary);

        if (($summary['cpnr_schema_validation_status'] ?? 'not_run') !== 'pass') {
            $blockers[] = 'cpnr_schema_validation_not_pass';
        }

        if (($summary['cpnr_schema_validation_failed'] ?? false) === true) {
            $blockers[] = 'cpnr_schema_validation_failed';
        }

        if (($summary['hard_no_fares_rbd_carrier_risk'] ?? false) === true) {
            $blockers[] = 'hard_no_fares_rbd_carrier_risk';
        }

        if (($summary['airprice_validating_carrier_present'] ?? false) !== true) {
            $blockers[] = 'airprice_validating_carrier_missing';
        }

        if (($summary['validating_carrier_match'] ?? false) !== true) {
            $blockers[] = 'validating_carrier_mismatch';
        }

        $brandMatch = $summary['brand_match'] ?? null;
        if ($brandMatch === false) {
            $blockers[] = 'brand_match_false';
        }

        if (($summary['airbook_rbd_complete'] ?? false) !== true) {
            $blockers[] = 'airbook_rbd_incomplete';
        }

        if (($summary['airbook_carrier_complete'] ?? false) !== true) {
            $blockers[] = 'airbook_carrier_incomplete';
        }

        if (($summary['airprice_present'] ?? false) !== true) {
            $blockers[] = 'airprice_missing';
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function hasRequiredControlledContext(array $meta): bool
    {
        $pricingSnapshot = is_array($meta['pricing_snapshot'] ?? null) ? $meta['pricing_snapshot'] : [];
        if ($pricingSnapshot === []) {
            return false;
        }

        $validatedSnapshot = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        if ($validatedSnapshot === []) {
            return false;
        }

        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        if (($safeRefreshAssess['safe_refresh_context_complete'] ?? false) !== true) {
            return false;
        }

        $certifiedRoute = is_array($meta['certified_route_selection'] ?? null) ? $meta['certified_route_selection'] : [];

        return $this->isCertifiedRouteSelectionValid($certifiedRoute);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function isManualReviewApproved(array $meta): bool
    {
        $record = $meta[SabreControlledPnrManualReviewApproval::META_KEY] ?? null;
        if (! is_array($record)) {
            return false;
        }

        return ($record['approved'] ?? false) === true
            && (string) ($record['approved_for'] ?? '') === SabreControlledPnrManualReviewApproval::APPROVED_FOR_CONTROLLED_PNR_CREATE;
    }

    /**
     * @param  array<string, mixed>  $route
     */
    protected function isCertifiedRouteSelectionValid(array $route): bool
    {
        if ($route === []) {
            return false;
        }

        $status = (string) ($route['route_status'] ?? '');
        if (! in_array($status, [
            SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
            SabreCertifiedRouteSelector::STATUS_CERTIFIED,
        ], true)) {
            return false;
        }

        return trim((string) ($route['endpoint_path'] ?? '')) !== ''
            && trim((string) ($route['payload_style'] ?? '')) !== '';
    }

    protected function detectExistingPnr(Booking $booking): bool
    {
        if (trim((string) ($booking->pnr ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($booking->supplier_api_booking_id ?? '')) !== '') {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn (SupplierBooking $item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );
    }

    protected function isTicketed(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ticketed) {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn ($item) => (string) $item->status === 'ticketed',
        ) || $booking->tickets->isNotEmpty();
    }
}
