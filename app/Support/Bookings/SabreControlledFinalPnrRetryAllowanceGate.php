<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Support\Sabre\SabreControlledPnrFinalReadinessDiagnostics;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Illuminate\Support\Carbon;

/**
 * F9Q: One-shot explicit controlled PNR retry allowance after F9P final readiness is green.
 * F9R: Post-final-retry host-failure containment when allowance consumed and Sabre rejects sellability.
 * Written by sabre:allow-final-controlled-pnr-retry; consumed on sabre:controlled-create-pnr only.
 * Does not weaken general retry protection, public checkout, or ticketing/cancellation paths.
 */
final class SabreControlledFinalPnrRetryAllowanceGate
{
    public const META_KEY = 'controlled_final_pnr_retry_allowance';

    public const ALLOWED_BY = 'controlled_command';

    public const REASON = 'final_readiness_green_after_fresh_strong_linkage';

    public const USED_FOR = 'controlled_pnr_create_after_final_readiness';

    public const CREATE_CONFIRM_PREFIX = 'CREATE-PNR-FOR-BOOKING-';

    public const POST_FINAL_RETRY_HOST_FAILURE_CODE = 'NO_FARES_RBD_CARRIER';

    /** @var list<string> */
    public const POST_FINAL_RETRY_CONTAINMENT_BLOCKERS = [
        'final_retry_allowance_used',
        'post_final_retry_host_failure',
        'no_safe_retry_without_remediation',
    ];

    public const POST_FINAL_RETRY_CONTAINMENT_RECOMMENDED_NEXT_ACTION = 'Staff review / Sabre host/PCC/QR/RBD/fare basis/brand qualifier investigation.';

    public const POST_FINAL_RETRY_CONTAINMENT_BLOCKED_MESSAGE = 'Post-final-retry host failure contained — no safe controlled PNR retry without staff remediation.';

    public const POST_FINAL_RETRY_CONTAINMENT_ERROR_CODE = 'post_final_retry_host_failure_contained';

    public function __construct(
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
        protected SabreSafeRefreshContext $safeRefreshContext,
    ) {}

    public static function confirmPhraseForBooking(Booking $booking): string
    {
        return 'ALLOW-FINAL-CONTROLLED-PNR-RETRY-FOR-BOOKING-'.$booking->id;
    }

    public static function createConfirmPhraseForBooking(Booking $booking): string
    {
        return self::CREATE_CONFIRM_PREFIX.$booking->id;
    }

    /**
     * @return array{
     *     eligible: bool,
     *     blockers: list<string>,
     *     final_pnr_retry_ready: bool,
     *     final_freshness_ready: bool,
     *     final_pnr_retry_blockers: list<string>,
     *     existing_retry_allowances_consumed: bool
     * }
     */
    public function evaluateAllowanceEligibility(Booking $booking, bool $forLive = false, bool $confirmProvided = false): array
    {
        $booking->loadMissing(['supplierBookings', 'tickets', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $blockers = [];

        if ($forLive && ! $confirmProvided) {
            $blockers[] = 'missing_or_invalid_confirm_phrase';
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)
            || (bool) config('suppliers.sabre.cancel_enabled', false)) {
            $blockers[] = 'ticketing_or_cancel_enabled';
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

        if (! $this->allPriorRetryAllowancesConsumed($meta)) {
            $blockers[] = 'prior_retry_allowances_not_consumed';
        }

        if ($this->isAllowanceValid($meta, $booking)) {
            $blockers[] = 'unused_allowance_already_active';
        }

        $containment = $this->assessPostFinalRetryContainment($booking);
        if (($containment['contained'] ?? false) === true) {
            $blockers[] = 'post_final_retry_host_failure_contained';
        }

        $finalReadiness = app(SabreControlledPnrFinalReadinessDiagnostics::class)->inspectBooking($booking);
        $finalPnrRetryReady = ($finalReadiness['final_pnr_retry_ready'] ?? false) === true;
        $finalFreshnessReady = ($finalReadiness['final_freshness_ready'] ?? false) === true;

        if (! $finalPnrRetryReady) {
            $blockers[] = 'final_pnr_retry_not_ready';
        }

        if (! $finalFreshnessReady) {
            $blockers[] = 'final_freshness_expired';
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'final_pnr_retry_ready' => $finalPnrRetryReady,
            'final_freshness_ready' => $finalFreshnessReady,
            'final_pnr_retry_blockers' => is_array($finalReadiness['final_pnr_retry_blockers'] ?? null)
                ? array_values($finalReadiness['final_pnr_retry_blockers'])
                : [],
            'existing_retry_allowances_consumed' => ($finalReadiness['existing_retry_allowances_consumed'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $finalReadiness
     * @return array<string, mixed>
     */
    public function buildAllowanceRecord(Booking $booking, array $finalReadiness): array
    {
        $now = now();
        $maxMinutes = (int) config('ota.controlled_final_pnr_retry_allowance.max_minutes', 15);

        return [
            'allowed' => true,
            'used' => false,
            'allowed_at' => $now->toIso8601String(),
            'allowed_by' => self::ALLOWED_BY,
            'booking_reference' => (string) ($booking->reference_code ?? $booking->booking_reference ?? ''),
            'reason' => self::REASON,
            'final_readiness_checked_at' => $now->toIso8601String(),
            'expires_at' => $now->copy()->addMinutes($maxMinutes)->toIso8601String(),
            'requires_exact_create_confirm' => self::createConfirmPhraseForBooking($booking),
            'ticketing_enabled' => false,
            'cancellation_enabled' => false,
            'final_pnr_retry_ready_at_allowance' => ($finalReadiness['final_pnr_retry_ready'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function allowancePresentInMeta(array $meta): bool
    {
        return is_array($meta[self::META_KEY] ?? null);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function allowancePresent(array $meta): bool
    {
        return self::allowancePresentInMeta($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function isAllowanceValidInMeta(array $meta, Booking $booking): bool
    {
        $record = $meta[self::META_KEY] ?? null;
        if (! is_array($record)) {
            return false;
        }

        if (($record['allowed'] ?? false) !== true || ($record['used'] ?? false) === true) {
            return false;
        }

        $bookingRef = (string) ($booking->reference_code ?? $booking->booking_reference ?? '');
        $recordRef = (string) ($record['booking_reference'] ?? '');
        if ($recordRef !== '' && $bookingRef !== '' && $recordRef !== $bookingRef) {
            return false;
        }

        $expiresAt = (string) ($record['expires_at'] ?? '');
        if ($expiresAt === '') {
            return false;
        }

        try {
            if (Carbon::parse($expiresAt)->isPast()) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function isAllowanceValid(array $meta, Booking $booking): bool
    {
        return self::isAllowanceValidInMeta($meta, $booking);
    }

    /**
     * @param  array<string, mixed>  $controlledOperationContext
     * @return array{
     *     present: bool,
     *     valid: bool,
     *     expires_at: string|null,
     *     blockers: list<string>
     * }
     */
    public function assessAvailability(
        Booking $booking,
        array $controlledOperationContext = [],
        bool $forLive = false,
    ): array {
        $booking->loadMissing(['supplierBookings', 'tickets', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $present = $this->allowancePresent($meta);
        $valid = $present && $this->isAllowanceValid($meta, $booking);
        $blockers = [];

        if (! $present) {
            $blockers[] = 'final_retry_allowance_missing';
        } elseif (! $valid) {
            $record = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
            if (($record['used'] ?? false) === true) {
                $blockers[] = 'final_retry_allowance_already_used';
            } else {
                $blockers[] = 'final_retry_allowance_expired_or_invalid';
            }
        }

        $containment = $this->assessPostFinalRetryContainment($booking);
        if (($containment['contained'] ?? false) === true) {
            $blockers = array_merge($blockers, self::POST_FINAL_RETRY_CONTAINMENT_BLOCKERS);
        }

        if ($forLive) {
            if (($controlledOperationContext['controlled_pnr_create'] ?? false) !== true) {
                $blockers[] = 'controlled_pnr_create_not_set';
            }

            $expectedConfirm = self::createConfirmPhraseForBooking($booking);
            if ((string) ($controlledOperationContext['controlled_approval_confirm_phrase'] ?? '') !== $expectedConfirm) {
                $blockers[] = 'exact_create_confirm_phrase_missing';
            }

            if (! $this->allPriorRetryAllowancesConsumed($meta)) {
                $blockers[] = 'prior_retry_allowances_not_consumed';
            }

            $finalReadiness = app(SabreControlledPnrFinalReadinessDiagnostics::class)->inspectBooking($booking);
            if (($finalReadiness['final_pnr_retry_ready'] ?? false) !== true) {
                $blockers[] = 'final_pnr_retry_not_ready';
            }

            if (($finalReadiness['final_freshness_ready'] ?? false) !== true) {
                $blockers[] = 'final_freshness_expired';
            }
        }

        $record = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        return [
            'present' => $present,
            'valid' => $valid && $blockers === [],
            'expires_at' => isset($record['expires_at']) ? (string) $record['expires_at'] : null,
            'blockers' => array_values(array_unique($blockers)),
        ];
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
        if ($attemptSource !== 'controlled_pnr_command' || ! $allowControlledStaffPnr) {
            return false;
        }

        if (($controlledOperationContext['controlled_pnr_create'] ?? false) !== true) {
            return false;
        }

        if (($controlledOperationContext['controlled_manual_review_approved'] ?? false) !== true) {
            return false;
        }

        $expectedConfirm = self::createConfirmPhraseForBooking($booking);
        if ((string) ($controlledOperationContext['controlled_approval_confirm_phrase'] ?? '') !== $expectedConfirm) {
            return false;
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)
            || (bool) config('suppliers.sabre.cancel_enabled', false)) {
            return false;
        }

        $booking->loadMissing(['supplierBookings', 'tickets', 'supplierBookingAttempts']);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if (($this->assessPostFinalRetryContainment($booking)['contained'] ?? false) === true) {
            return false;
        }

        if (! $this->allPriorRetryAllowancesConsumed($meta)) {
            return false;
        }

        if (! $this->isAllowanceValid($meta, $booking)) {
            return false;
        }

        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== 'sabre' || (int) ($meta['supplier_connection_id'] ?? 0) <= 0) {
            return false;
        }

        if ($this->detectExistingPnr($booking)) {
            return false;
        }

        if ($booking->status === BookingStatus::Cancelled || $this->isTicketed($booking)) {
            return false;
        }

        if (! $this->isManualReviewApproved($meta)) {
            return false;
        }

        if (! $this->fareChangeAcceptance->isAccepted($meta)) {
            return false;
        }

        if (! $this->hasRequiredControlledContext($meta)) {
            return false;
        }

        $finalReadiness = app(SabreControlledPnrFinalReadinessDiagnostics::class)->inspectBooking($booking);
        if (($finalReadiness['final_pnr_retry_ready'] ?? false) !== true
            || ($finalReadiness['final_freshness_ready'] ?? false) !== true) {
            return false;
        }

        $readiness = is_array($controlledOperationContext['readiness_snapshot'] ?? null)
            ? $controlledOperationContext['readiness_snapshot']
            : [];

        if (($readiness['eligible'] ?? false) !== true
            || ($readiness['can_attempt_supplier_pnr'] ?? false) !== true
            || ($readiness['live_supplier_call_allowed'] ?? false) !== true
            || ($readiness['has_usable_controlled_pnr_context'] ?? false) !== true) {
            return false;
        }

        $readinessBlockers = is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : [];
        if ($readinessBlockers !== []) {
            return false;
        }

        if (($readiness['has_existing_pnr'] ?? false) === true
            || ($readiness['is_ticketed'] ?? false) === true
            || ($readiness['is_cancelled'] ?? false) === true) {
            return false;
        }

        return $meaningfulAttempt !== null;
    }

    public function recordUsage(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        if (($record['used'] ?? false) === true) {
            return;
        }

        $meta[self::META_KEY] = array_merge($record, [
            'used' => true,
            'used_at' => now()->toIso8601String(),
            'used_for' => self::USED_FOR,
            'create_attempted' => true,
        ]);

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * F9R: Persist safe host-failure outcome on allowance record after F9Q live create without locator.
     *
     * @param  array<string, mixed>  $result
     */
    public function recordHostFailureOutcome(Booking $booking, array $result): void
    {
        if ($this->detectExistingPnr($booking)) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
        if (($record['used'] ?? false) !== true) {
            return;
        }

        $hostFailureCode = $this->resolvePostFinalRetryHostFailureCode($meta, $result);
        if ($hostFailureCode === null) {
            return;
        }

        $meta[self::META_KEY] = array_merge($record, [
            'final_controlled_create_failed' => true,
            'post_final_retry_host_failure' => true,
            'post_final_retry_host_failure_code' => $hostFailureCode,
            'no_safe_retry_without_remediation' => true,
            'host_failure_recorded_at' => now()->toIso8601String(),
        ]);

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * F9R: Read-only post-final-retry containment assessment (no supplier HTTP, no DB mutation).
     *
     * @return array{
     *     contained: bool,
     *     controlled_final_pnr_retry_allowance_used: bool,
     *     final_controlled_create_attempted: bool,
     *     final_controlled_create_failed: bool,
     *     post_final_retry_host_failure: bool,
     *     post_final_retry_host_failure_code: string|null,
     *     no_safe_retry_without_remediation: bool,
     *     blockers: list<string>
     * }
     */
    public function assessPostFinalRetryContainment(Booking $booking): array
    {
        $booking->loadMissing(['supplierBookings', 'tickets']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        $allowanceUsed = ($record['used'] ?? false) === true;
        $createAttempted = ($record['create_attempted'] ?? false) === true
            || ($allowanceUsed && (string) ($record['used_for'] ?? '') === self::USED_FOR);
        $noPnr = ! $this->detectExistingPnr($booking);

        $hostFailureFromRecord = ($record['post_final_retry_host_failure'] ?? false) === true;
        $hostFailureFromDigest = $noPnr && $this->applicationDigestIndicatesPostFinalHostFailure($meta);
        $hostFailure = $noPnr && ($hostFailureFromRecord || $hostFailureFromDigest);

        $hostFailureCode = null;
        if ($hostFailure) {
            $hostFailureCode = is_string($record['post_final_retry_host_failure_code'] ?? null)
                && trim((string) $record['post_final_retry_host_failure_code']) !== ''
                ? (string) $record['post_final_retry_host_failure_code']
                : self::POST_FINAL_RETRY_HOST_FAILURE_CODE;
        }

        $createFailed = $createAttempted && $noPnr && $hostFailure;
        $noSafeRetry = $createFailed;
        $contained = $allowanceUsed && $createAttempted && $createFailed && $noSafeRetry;

        $blockers = $contained ? self::POST_FINAL_RETRY_CONTAINMENT_BLOCKERS : [];

        return [
            'contained' => $contained,
            'controlled_final_pnr_retry_allowance_used' => $allowanceUsed,
            'final_controlled_create_attempted' => $createAttempted,
            'final_controlled_create_failed' => $createFailed,
            'post_final_retry_host_failure' => $hostFailure,
            'post_final_retry_host_failure_code' => $hostFailureCode,
            'no_safe_retry_without_remediation' => $noSafeRetry,
            'blockers' => $blockers,
        ];
    }

    /**
     * F9R: Align controlled-create dry-run output when post-final-retry containment is active (read-only; no gate weakening).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $containment  assessPostFinalRetryContainment() result
     * @return array<string, mixed>
     */
    public function applyPostFinalRetryContainmentOutputAlignment(array $payload, array $containment): array
    {
        if (($containment['contained'] ?? false) !== true) {
            return $payload;
        }

        $payload['eligible'] = false;
        $payload['can_attempt_supplier_pnr'] = false;
        $payload['live_supplier_call_allowed'] = false;
        $payload['exact_create_confirmation_required'] = false;
        $payload['recommended_next_action'] = self::POST_FINAL_RETRY_CONTAINMENT_RECOMMENDED_NEXT_ACTION;
        $payload['classification'] = 'controlled_pnr_create_blocked_post_final_retry_host_failure';
        $payload['reason_code'] = self::POST_FINAL_RETRY_CONTAINMENT_ERROR_CODE;
        $payload['blocked_message'] = self::POST_FINAL_RETRY_CONTAINMENT_BLOCKED_MESSAGE;
        $payload['error_code'] = self::POST_FINAL_RETRY_CONTAINMENT_ERROR_CODE;
        $payload['error_message'] = self::POST_FINAL_RETRY_CONTAINMENT_BLOCKED_MESSAGE;
        $payload['blockers'] = array_values(array_unique(array_merge(
            is_array($payload['blockers'] ?? null) ? $payload['blockers'] : [],
            is_array($containment['blockers'] ?? null) ? $containment['blockers'] : self::POST_FINAL_RETRY_CONTAINMENT_BLOCKERS,
        )));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $result
     */
    protected function resolvePostFinalRetryHostFailureCode(array $meta, array $result): ?string
    {
        $record = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
        if (is_string($record['post_final_retry_host_failure_code'] ?? null)
            && trim((string) $record['post_final_retry_host_failure_code']) !== '') {
            return (string) $record['post_final_retry_host_failure_code'];
        }

        if ($this->applicationDigestIndicatesPostFinalHostFailure($meta)) {
            return self::POST_FINAL_RETRY_HOST_FAILURE_CODE;
        }

        $errorCode = strtolower(trim((string) ($result['error_code'] ?? '')));
        $errorMessage = strtoupper(trim((string) ($result['message'] ?? $result['error_message'] ?? '')));
        if ($errorCode === 'sabre_booking_application_error'
            && (str_contains($errorMessage, 'NO FARES/RBD/CARRIER') || str_contains($errorMessage, 'NO FARES'))) {
            return self::POST_FINAL_RETRY_HOST_FAILURE_CODE;
        }

        if (($result['application_error_digest_available'] ?? false) === true
            && str_contains($errorMessage, 'UNABLE TO PERFORM AIR BOOKING STEP')) {
            return self::POST_FINAL_RETRY_HOST_FAILURE_CODE;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function applicationDigestIndicatesPostFinalHostFailure(array $meta): bool
    {
        $digest = is_array($meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY] ?? null)
            ? $meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY]
            : [];

        if ($digest === []) {
            return false;
        }

        $messages = $this->collectDigestMessages($digest);
        if ($this->messagesIndicateNoFaresRbdCarrier($messages)) {
            return true;
        }

        $status = strtolower(trim((string) ($digest['status'] ?? '')));
        $appStatus = strtolower(trim((string) ($digest['application_status'] ?? '')));
        if (in_array($status, ['incomplete_no_locator'], true)
            || in_array($appStatus, ['incomplete', 'notprocessed'], true)) {
            $errorCode = strtoupper(trim((string) ($meta['sabre_last_create_error_code'] ?? '')));
            $errorMessage = strtoupper(trim((string) ($meta['sabre_last_create_error_message'] ?? '')));
            if ($errorCode === 'ERR.SP.PROVIDER_ERROR'
                && str_contains($errorMessage, 'UNABLE TO PERFORM AIR BOOKING STEP')
                && $this->messagesIndicateNoFaresRbdCarrier($this->collectMetaHostMessages($meta))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return list<string>
     */
    protected function collectDigestMessages(array $digest): array
    {
        $messages = [];
        foreach (['errors', 'warnings', 'messages'] as $bucket) {
            $rows = is_array($digest[$bucket] ?? null) ? $digest[$bucket] : [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (is_string($row['message'] ?? null) && trim($row['message']) !== '') {
                    $messages[] = (string) $row['message'];
                }
                if (is_string($row['code'] ?? null) && trim($row['code']) !== '') {
                    $messages[] = (string) $row['code'];
                }
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    protected function collectMetaHostMessages(array $meta): array
    {
        $messages = [];
        foreach (['sabre_last_create_error_message', 'sabre_last_create_warnings'] as $key) {
            $value = $meta[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $messages[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $messages[] = $item;
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * @param  list<string>  $messages
     */
    protected function messagesIndicateNoFaresRbdCarrier(array $messages): bool
    {
        $blob = strtoupper(implode(' ', $messages));

        return str_contains($blob, 'NO FARES/RBD/CARRIER')
            || (str_contains($blob, 'NO FARES') && str_contains($blob, 'RBD'))
            || (str_contains($blob, 'UNABLE TO PERFORM AIR BOOKING STEP')
                && str_contains($blob, 'ERR.SP.PROVIDER_ERROR')
                && str_contains($blob, 'NO FARES'));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function allowanceAlreadyUsed(array $meta): bool
    {
        $record = $meta[self::META_KEY] ?? null;
        if (! is_array($record)) {
            return false;
        }

        return ($record['used'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function allPriorRetryAllowancesConsumed(array $meta): bool
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
