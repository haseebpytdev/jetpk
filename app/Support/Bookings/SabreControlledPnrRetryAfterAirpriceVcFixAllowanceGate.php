<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Support\Sabre\SabreCpnrIatiWireSchemaValidator;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;

/**
 * F9J: One-shot controlled retry after F9I AirPrice ValidatingCarrier fix when F9F allowance
 * was already consumed, prior live attempt failed with NO FARES/RBD/CARRIER, and rebuilt payload
 * digest is structurally clean.
 *
 * Applies only to sabre:controlled-create-pnr with exact confirm phrase. Does not weaken
 * general retry protection, public checkout, admin generic retry, or ticketing/cancellation paths.
 */
final class SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate
{
    public const META_KEY = 'controlled_supplier_retry_allowance_after_airprice_vc_fix';

    public const REASON_CLEAN_AIRPRICE_VC_AFTER_NO_FARES_RBD_CARRIER = 'clean_airprice_validating_carrier_payload_after_no_fares_rbd_carrier';

    public const USED_BY_CONTROLLED_PNR_COMMAND = 'controlled_pnr_command';

    public const USED_FOR_CONTROLLED_PNR_CREATE_AFTER_AIRPRICE_VC_FIX = 'controlled_pnr_create_after_airprice_validating_carrier_fix';

    public const PREVIOUS_ERROR_CODE = 'ERR.SP.PROVIDER_ERROR';

    public const PREVIOUS_HOST_MESSAGE = 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER';

    public const REQUIRED_PAYLOAD_DIGEST = 'hard_no_fares_rbd_carrier_risk=false';

    public function __construct(
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
        protected SabreSafeRefreshContext $safeRefreshContext,
        protected SabrePassengerRecordsPayloadDigest $payloadDigest,
        protected SabreControlledPnrRetryAllowanceGate $f9fRetryAllowanceGate,
    ) {}

    public function reasonCode(): string
    {
        return self::REASON_CLEAN_AIRPRICE_VC_AFTER_NO_FARES_RBD_CARRIER;
    }

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
        return $this->assessAvailability(
            $booking,
            is_array($controlledOperationContext['post_f9i_payload_digest_summary'] ?? null)
                ? $controlledOperationContext['post_f9i_payload_digest_summary']
                : null,
            $controlledOperationContext,
            $meaningfulAttempt,
            $attemptSource,
            $allowControlledStaffPnr,
        )['available'];
    }

    /**
     * @param  array<string, mixed>|null  $digestSummary
     * @param  array<string, mixed>  $controlledOperationContext
     * @return array{available: bool, blockers: list<string>, previous_no_fares_rbd_carrier_error_present: bool, post_f9i_payload_digest_clean: bool}
     */
    public function assessAvailability(
        Booking $booking,
        ?array $digestSummary,
        array $controlledOperationContext = [],
        ?SupplierBookingAttempt $meaningfulAttempt = null,
        string $attemptSource = 'controlled_pnr_command',
        bool $allowControlledStaffPnr = true,
    ): array {
        $blockers = [];
        $previousNoFares = false;
        $digestClean = false;

        if ($attemptSource !== 'controlled_pnr_command' || ! $allowControlledStaffPnr) {
            $blockers[] = 'non_controlled_command_context';
        }

        if (($controlledOperationContext['controlled_pnr_create'] ?? false) !== true) {
            $blockers[] = 'controlled_pnr_create_not_set';
        }

        if (($controlledOperationContext['controlled_manual_review_approved'] ?? false) !== true) {
            $blockers[] = 'controlled_manual_review_not_approved';
        }

        $expectedConfirm = 'CREATE-PNR-FOR-BOOKING-'.$booking->id;
        if ((string) ($controlledOperationContext['controlled_approval_confirm_phrase'] ?? '') !== $expectedConfirm) {
            $blockers[] = 'exact_confirm_phrase_missing';
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

        if ($this->retryAllowanceFullyConsumed($meta)
            && ! $this->retryAllowanceAvailableForRecovery($meta, $meaningfulAttempt)) {
            $blockers[] = 'f9j_retry_allowance_already_used';
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

        if ($meaningfulAttempt === null) {
            $meaningfulAttempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
                $booking->supplierBookingAttempts,
            );
        }

        $previousNoFares = $this->priorOutcomeIndicatesNoFaresRbdCarrier($meaningfulAttempt, $meta);
        if (! $previousNoFares) {
            $blockers[] = 'no_prior_no_fares_rbd_carrier_error';
        }

        if ($digestSummary === null || $digestSummary === []) {
            $blockers[] = 'payload_digest_summary_missing';
        } else {
            $digestClean = $this->payloadDigest->isPostF9iCleanForControlledRetry($digestSummary);
            if (! $digestClean) {
                foreach ($this->payloadDigest->postF9iCleanBlockers($digestSummary) as $digestBlocker) {
                    $blockers[] = $digestBlocker;
                }
            }
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'available' => $blockers === [],
            'blockers' => $blockers,
            'previous_no_fares_rbd_carrier_error_present' => $previousNoFares,
            'post_f9i_payload_digest_clean' => $digestClean,
        ];
    }

    public function recordUsage(Booking $booking, SupplierBookingAttempt $meaningfulAttempt): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($this->retryAllowanceFullyConsumed($meta)) {
            return;
        }

        $meta[self::META_KEY] = [
            'used' => true,
            'used_at' => now()->toIso8601String(),
            'used_by' => self::USED_BY_CONTROLLED_PNR_COMMAND,
            'used_for' => self::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_AIRPRICE_VC_FIX,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'previous_error_code' => self::PREVIOUS_ERROR_CODE,
            'previous_host_message' => self::PREVIOUS_HOST_MESSAGE,
            'required_payload_digest' => self::REQUIRED_PAYLOAD_DIGEST,
            'schema_validation_failed' => false,
            'host_application_results_received' => false,
        ];

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * F9K: Record schema-only failure without permanently consuming host retry allowance.
     *
     * @param  array<string, mixed>|null  $schemaSummary
     */
    public function recordSchemaValidationOutcome(Booking $booking, bool $failed, ?array $schemaSummary = null): void
    {
        if (! $failed) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        $meta[self::META_KEY] = array_merge($existing, [
            'used' => true,
            'used_at' => $existing['used_at'] ?? now()->toIso8601String(),
            'used_by' => self::USED_BY_CONTROLLED_PNR_COMMAND,
            'used_for' => self::USED_FOR_CONTROLLED_PNR_CREATE_AFTER_AIRPRICE_VC_FIX,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'schema_validation_failed' => true,
            'host_application_results_received' => false,
            'schema_validation_pointer' => is_string($schemaSummary['cpnr_schema_validation_pointer'] ?? null)
                ? substr((string) $schemaSummary['cpnr_schema_validation_pointer'], 0, 240)
                : null,
            'schema_validation_message_summary' => is_string($schemaSummary['cpnr_schema_validation_message_summary'] ?? null)
                ? substr((string) $schemaSummary['cpnr_schema_validation_message_summary'], 0, 240)
                : null,
        ]);

        $booking->forceFill(['meta' => $meta])->save();
    }

    public function markHostApplicationResultsReceived(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
        if ($existing === [] || ($existing['used'] ?? false) !== true) {
            return;
        }

        $meta[self::META_KEY] = array_merge($existing, [
            'host_application_results_received' => true,
            'schema_validation_failed' => false,
        ]);

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function retryAllowanceAlreadyUsed(array $meta): bool
    {
        return $this->retryAllowanceFullyConsumed($meta);
    }

    /**
     * F9K: True when the one-shot allowance is fully spent (host reached or non-recoverable use).
     *
     * @param  array<string, mixed>  $meta
     */
    public function retryAllowanceFullyConsumed(array $meta): bool
    {
        $record = $meta[self::META_KEY] ?? null;
        if (! is_array($record) || ($record['used'] ?? false) !== true) {
            return false;
        }

        if (($record['schema_validation_failed'] ?? false) === true
            && ($record['host_application_results_received'] ?? false) !== true) {
            return false;
        }

        return true;
    }

    /**
     * F9K: Allow one more controlled retry after schema-only failure (including pre-F9K meta backfill).
     *
     * @param  array<string, mixed>  $meta
     */
    public function retryAllowanceAvailableForRecovery(
        array $meta,
        ?SupplierBookingAttempt $meaningfulAttempt = null,
    ): bool {
        $record = $meta[self::META_KEY] ?? null;
        if (! is_array($record) || ($record['used'] ?? false) !== true) {
            return false;
        }

        if (($record['schema_validation_failed'] ?? false) === true
            && ($record['host_application_results_received'] ?? false) !== true) {
            return true;
        }

        if (($record['host_application_results_received'] ?? false) !== true
            && ($record['schema_validation_failed'] ?? false) !== true
            && $meaningfulAttempt !== null
            && $this->attemptIndicatesSchemaValidationFailure($meaningfulAttempt)) {
            return true;
        }

        return false;
    }

    protected function attemptIndicatesSchemaValidationFailure(SupplierBookingAttempt $attempt): bool
    {
        $errorCode = strtolower(trim((string) ($attempt->error_code ?? '')));
        $safeSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $errorMessage = trim((string) ($attempt->error_message ?? ''));
        if ($errorMessage === '' && is_string($safeSummary['error_message'] ?? null)) {
            $errorMessage = trim((string) $safeSummary['error_message']);
        }

        $appDigestAvailable = ($safeSummary['application_error_digest_available'] ?? false) === true;

        return app(SabreCpnrIatiWireSchemaValidator::class)->outcomeLooksLikeCpnrSchemaValidationFailure(
            $errorCode !== '' ? $errorCode : strtolower(trim((string) ($safeSummary['error_code'] ?? ''))),
            $errorMessage,
            $appDigestAvailable,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function priorOutcomeIndicatesNoFaresRbdCarrier(
        ?SupplierBookingAttempt $attempt,
        array $meta,
    ): bool {
        if ($attempt !== null && $this->attemptIndicatesNoFaresRbdCarrier($attempt)) {
            return true;
        }

        $storedDigest = is_array($meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY] ?? null)
            ? $meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY]
            : null;
        if ($storedDigest !== null && $storedDigest !== []) {
            return $this->applicationDigestIndicatesNoFaresRbdCarrier($storedDigest);
        }

        return false;
    }

    protected function attemptIndicatesNoFaresRbdCarrier(SupplierBookingAttempt $attempt): bool
    {
        $errorCode = strtolower(trim((string) ($attempt->error_code ?? '')));
        $safeSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];

        if ($errorCode !== 'sabre_booking_application_error') {
            $summaryError = strtolower(trim((string) ($safeSummary['error_code'] ?? '')));
            if ($summaryError !== 'sabre_booking_application_error') {
                return false;
            }
        }

        if (SabrePnrFailureClassifier::safeSummaryIndicatesFareRbdCarrierHostRejection($safeSummary)) {
            return true;
        }

        $firstErrorCode = strtoupper(trim((string) ($safeSummary['sabre_application_first_error_code'] ?? '')));
        if ($firstErrorCode === self::PREVIOUS_ERROR_CODE
            && SabrePnrFailureClassifier::safeSummaryIndicatesFareRbdCarrierHostRejection(
                $safeSummary,
                strtoupper((string) ($safeSummary['sabre_application_first_error_message'] ?? '')),
            )) {
            return true;
        }

        $messages = [];
        if (is_string($safeSummary['sabre_application_first_error_message'] ?? null)) {
            $messages[] = (string) $safeSummary['sabre_application_first_error_message'];
        }
        if (is_array($safeSummary['response_error_messages'] ?? null)) {
            foreach ($safeSummary['response_error_messages'] as $msg) {
                if (is_string($msg) && trim($msg) !== '') {
                    $messages[] = $msg;
                }
            }
        }

        $blob = strtoupper(implode(' ', $messages));

        return str_contains($blob, 'NO FARES/RBD/CARRIER')
            || (str_contains($blob, 'UNABLE TO PERFORM AIR BOOKING STEP')
                && str_contains($blob, 'ERR.SP.PROVIDER_ERROR'));
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function applicationDigestIndicatesNoFaresRbdCarrier(array $digest): bool
    {
        $errors = is_array($digest['errors'] ?? null) ? $digest['errors'] : [];
        $messages = [];
        foreach ($errors as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (is_string($row['message'] ?? null) && trim($row['message']) !== '') {
                $messages[] = (string) $row['message'];
            }
            if (is_string($row['code'] ?? null) && strtoupper(trim($row['code'])) === self::PREVIOUS_ERROR_CODE) {
                $messages[] = self::PREVIOUS_ERROR_CODE;
            }
        }

        $blob = strtoupper(implode(' ', $messages));

        return str_contains($blob, 'NO FARES/RBD/CARRIER')
            || (str_contains($blob, 'UNABLE TO PERFORM AIR BOOKING STEP')
                && str_contains($blob, self::PREVIOUS_ERROR_CODE));
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
        if (! $this->isCertifiedRouteSelectionValid($certifiedRoute)) {
            return false;
        }

        return true;
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
