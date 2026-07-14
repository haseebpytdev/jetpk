<?php

namespace App\Support\Bookings;

use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Duplicate-prevention and safe early-exit for automated supplier PNR creation (Sprint 9D-3).
 */
final class SupplierBookingPreflightGuard
{
    public const PROCESSING_STALE_MINUTES = 30;

    public function __construct(
        protected SabrePnrCertificationSupport $sabrePnrCertificationSupport,
        protected ControlledStaffSabreHostNoopRetryGate $controlledStaffSabreHostNoopRetryGate,
        protected SabreOperationalAllowNnStrategyChangedRetryGate $operationalAllowNnStrategyChangedRetryGate,
        protected SabreOperationalPnrReadiness $operationalPnrReadiness,
        protected SabreControlledPnrApprovalOverrideGate $controlledPnrApprovalOverrideGate,
        protected SabreControlledPnrRetryAllowanceGate $controlledPnrRetryAllowanceGate,
        protected SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate $controlledPnrRetryAfterAirpriceVcFixAllowanceGate,
        protected SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate $controlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate,
        protected SabreControlledFinalPnrRetryAllowanceGate $controlledFinalPnrRetryAllowanceGate,
        protected SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    public function preflightAutomatedCreate(
        Booking $booking,
        User $actor,
        string $source,
        bool $explicitRetry = false,
        bool $allowControlledStaffPnr = false,
        ?array $controlledOperationContext = null,
    ): ?SupplierBookingResultData {
        $booking->loadMissing(['supplierBookings', 'supplierBookingAttempts']);
        $provider = $this->resolveProvider($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if ($this->hasSupplierIdentity($booking)) {
            Log::notice('supplier_booking.duplicate_blocked', [
                'booking_id' => $booking->id,
                'provider' => $provider,
                'action' => 'create_supplier_booking',
                'source' => $source,
                'reason' => 'existing_supplier_identity',
            ]);

            return $this->existingIdentityResult($booking, $provider, $source, $actor);
        }

        $existingRecord = $booking->supplierBookings
            ->first(fn (SupplierBooking $row) => in_array((string) $row->status, ['created', 'pending_ticketing', 'ticketed'], true));
        if ($existingRecord !== null) {
            Log::notice('supplier_booking.existing_reference_reused', [
                'booking_id' => $booking->id,
                'provider' => (string) $existingRecord->provider,
                'action' => 'create_supplier_booking',
                'source' => $source,
            ]);

            return new SupplierBookingResultData(
                success: true,
                status: 'success',
                provider: (string) $existingRecord->provider,
                supplier_reference: $existingRecord->supplier_reference,
                pnr: $existingRecord->pnr,
                safe_summary: SensitiveDataRedactor::redact((array) ($existingRecord->raw_summary ?? [])),
                warnings: ['Supplier booking already exists; returning existing result.'],
            );
        }

        $latestCreateAttempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
            $booking->supplierBookingAttempts,
        );
        if ($latestCreateAttempt !== null && $this->isProcessingAttempt($latestCreateAttempt, $booking, $provider)) {
            $diagnostics = $this->attemptGuard->assertRetryAllowed($booking, $provider);
            $this->recordBlockedAttempt(
                $booking,
                $actor,
                $provider,
                $source,
                (string) ($diagnostics['reason_code'] ?? 'supplier_booking_already_processing'),
                (string) ($diagnostics['error_message'] ?? 'Supplier booking is already in progress for this booking.'),
                null,
                $this->attemptGuard->blockedSafeSummary($diagnostics),
            );

            Log::notice('supplier_booking.duplicate_blocked', [
                'booking_id' => $booking->id,
                'provider' => $provider,
                'action' => 'create_supplier_booking',
                'source' => $source,
                'reason' => 'processing_attempt',
                'attempt_id' => $latestCreateAttempt->id,
            ]);

            return new SupplierBookingResultData(
                success: false,
                status: 'blocked',
                provider: $provider,
                error_code: (string) ($diagnostics['reason_code'] ?? 'supplier_booking_already_processing'),
                error_message: (string) ($diagnostics['error_message'] ?? 'Supplier booking is already in progress for this booking.'),
                safe_summary: $this->attemptGuard->blockedSafeSummary($diagnostics),
            );
        }

        $meaningfulCreateAttempt = $latestCreateAttempt;
        $latestAttempt = $booking->supplierBookingAttempts->sortByDesc('id')->first();
        $successAttempt = $meaningfulCreateAttempt ?? $latestAttempt;
        if ($successAttempt !== null
            && strtolower((string) $successAttempt->status) === 'success'
            && ! $explicitRetry) {
            Log::notice('supplier_booking.duplicate_blocked', [
                'booking_id' => $booking->id,
                'provider' => $provider,
                'action' => 'create_supplier_booking',
                'source' => $source,
                'reason' => 'successful_attempt_exists',
                'attempt_id' => $successAttempt->id,
            ]);

            if (trim((string) ($successAttempt->supplier_reference ?? '')) !== '' || trim((string) ($successAttempt->pnr ?? '')) !== '') {
                return new SupplierBookingResultData(
                    success: true,
                    status: 'success',
                    provider: (string) ($successAttempt->provider ?: $provider),
                    supplier_reference: $successAttempt->supplier_reference,
                    pnr: null,
                    safe_summary: SensitiveDataRedactor::redact((array) ($successAttempt->safe_summary ?? [])),
                    warnings: ['A successful supplier booking attempt already exists for this booking.'],
                );
            }

            $this->recordBlockedAttempt(
                $booking,
                $actor,
                $provider,
                $source,
                'supplier_booking_success_attempt_exists',
                'A successful supplier booking attempt already exists for this booking.',
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'blocked',
                provider: $provider,
                error_code: 'supplier_booking_success_attempt_exists',
                error_message: 'A successful supplier booking attempt already exists for this booking.',
                safe_summary: ['source' => $source, 'reason' => 'successful_attempt_exists'],
            );
        }

        if ($meaningfulCreateAttempt !== null
            && ! $explicitRetry
            && $this->nonRetryableFailedAttempt(
                $meaningfulCreateAttempt,
                $booking,
                $source,
                $allowControlledStaffPnr,
                is_array($controlledOperationContext) ? $controlledOperationContext : [],
            )) {
            Log::notice('supplier_booking.retry_blocked', [
                'booking_id' => $booking->id,
                'provider' => $provider,
                'action' => 'create_supplier_booking',
                'source' => $source,
                'error_code' => $meaningfulCreateAttempt->error_code,
            ]);

            $this->recordBlockedAttempt(
                $booking,
                $actor,
                $provider,
                $source,
                'supplier_booking_retry_not_allowed',
                'Supplier booking retry is not allowed for the latest attempt outcome.',
                $meaningfulCreateAttempt->error_code,
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'blocked',
                provider: $provider,
                error_code: 'supplier_booking_retry_not_allowed',
                error_message: 'Supplier booking retry is not allowed for the latest attempt outcome.',
                safe_summary: [
                    'source' => $source,
                    'reason' => 'non_retryable_failure',
                    'prior_error_code' => $meaningfulCreateAttempt->error_code,
                ],
            );
        }

        if ($this->controlledInitialCreateRequiresSafeRefreshContext(
            $booking,
            $source,
            $allowControlledStaffPnr,
            $meaningfulCreateAttempt,
        )) {
            return $this->skippedResult(
                $booking,
                $actor,
                $provider,
                $source,
                'controlled_initial_create_safe_refresh_context_missing',
                'Controlled supplier PNR create requires complete safe refresh context.',
            );
        }

        if (($meta['defer_supplier_booking_to_manual_review'] ?? false) === true
            && ! $this->sabrePnrCertificationSupport->allowsControlledStaffPnrBypassDeferManualReview(
                $booking,
                $source,
                $allowControlledStaffPnr,
            )
            && ! $this->operationalPnrReadiness->bypassesLegacyDeferManualReview($booking)
            && ! $this->controlledPnrApprovalOverrideGate->allowsDeferOverride(
                $booking,
                $source,
                $allowControlledStaffPnr,
                is_array($controlledOperationContext) ? $controlledOperationContext : [],
            )) {
            return $this->skippedResult($booking, $actor, $provider, $source, 'defer_supplier_booking_to_manual_review', 'Supplier booking is deferred to manual review.');
        }

        return null;
    }

    public function assertManualPnrAllowed(Booking $booking): ?string
    {
        if ($this->hasSupplierIdentity($booking)) {
            return 'Supplier PNR or reference already exists for this booking.';
        }

        $booking->loadMissing('supplierBookings');
        $hasRecord = $booking->supplierBookings->contains(
            fn (SupplierBooking $row) => in_array((string) $row->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );
        if ($hasRecord) {
            return 'Supplier PNR or reference already exists for this booking.';
        }

        return null;
    }

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    public function recordManualPnrAttempt(
        Booking $booking,
        User $actor,
        string $provider,
        string $pnr,
        ?string $supplierReference,
        string $source = 'manual',
    ): SupplierBookingAttempt {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = $meta['supplier_connection_id'] ?? null;
        $cid = is_numeric($cid) ? (int) $cid : null;

        return SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid,
            'provider' => $provider !== '' ? $provider : 'manual',
            'action' => 'mark_manual_pnr',
            'status' => 'success',
            'supplier_reference' => $supplierReference,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => SensitiveDataRedactor::redact([
                'source' => $source,
                'pnr_present' => $pnr !== '',
                'supplier_reference_present' => $supplierReference !== null && $supplierReference !== '',
                'entered_by' => $actor->id,
                'entered_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    protected function hasSupplierIdentity(Booking $booking): bool
    {
        return trim((string) ($booking->pnr ?? '')) !== ''
            || trim((string) ($booking->supplier_reference ?? '')) !== ''
            || trim((string) ($booking->supplier_api_booking_id ?? '')) !== '';
    }

    protected function resolveProvider(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? 'unknown')));
    }

    protected function isProcessingAttempt(SupplierBookingAttempt $attempt, Booking $booking, string $provider): bool
    {
        if (strtolower((string) $attempt->action) !== 'create_pnr') {
            return false;
        }

        $active = $this->attemptGuard->resolveActiveAttempt($booking, $provider, 'create_pnr');

        return $active !== null && (int) $active->id === (int) $attempt->id;
    }

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    /**
     * @param  array<string, mixed>  $controlledOperationContext
     */
    protected function nonRetryableFailedAttempt(
        SupplierBookingAttempt $attempt,
        Booking $booking,
        string $source = 'system',
        bool $allowControlledStaffPnr = false,
        array $controlledOperationContext = [],
    ): bool {
        if (! in_array(strtolower((string) $attempt->status), ['failed', 'manual_review', 'needs_review'], true)) {
            return false;
        }

        $provider = $this->resolveProvider($booking);
        if ($provider !== 'sabre') {
            return false;
        }

        if ($this->controlledPnrRetryAllowanceGate->allows(
            $booking,
            $attempt,
            $source,
            $allowControlledStaffPnr,
            $controlledOperationContext,
        )) {
            return false;
        }

        if ($this->controlledPnrRetryAfterAirpriceVcFixAllowanceGate->allows(
            $booking,
            $attempt,
            $source,
            $allowControlledStaffPnr,
            $controlledOperationContext,
        )) {
            return false;
        }

        if ($this->controlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate->allows(
            $booking,
            $attempt,
            $source,
            $allowControlledStaffPnr,
            $controlledOperationContext,
        )) {
            return false;
        }

        if ($this->controlledFinalPnrRetryAllowanceGate->allows(
            $booking,
            $attempt,
            $source,
            $allowControlledStaffPnr,
            $controlledOperationContext,
        )) {
            return false;
        }

        if ($this->controlledStaffSabreHostNoopRetryGate->allows(
            $booking,
            $attempt,
            $allowControlledStaffPnr,
            $source,
        )) {
            return false;
        }

        $safeSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $errorCode = strtolower(trim((string) ($attempt->error_code ?? '')));

        if (SabreCpnrOperationalAllowNnPolicy::isConfigEnabled()
            && $errorCode === 'sabre_booking_application_error'
            && SabrePnrFailureClassifier::safeSummaryIndicatesPriorNnHaltOnStatusFailure($safeSummary)) {
            return ! $this->operationalAllowNnStrategyChangedRetryGate->allows(
                $booking,
                $attempt,
                $allowControlledStaffPnr,
                $source,
            );
        }

        $classification = SabrePnrFailureClassifier::classify(
            (string) ($attempt->error_code ?? '') !== '' ? (string) $attempt->error_code : null,
            $safeSummary,
        );

        return ($classification['retry_allowed'] ?? true) === false;
    }

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    protected function controlledInitialCreateRequiresSafeRefreshContext(
        Booking $booking,
        string $source,
        bool $allowControlledStaffPnr,
        ?SupplierBookingAttempt $meaningfulCreateAttempt,
    ): bool {
        if ($meaningfulCreateAttempt !== null || ! $allowControlledStaffPnr || ! in_array($source, ['admin', 'staff'], true)) {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (($meta['defer_supplier_booking_to_manual_review'] ?? false) !== true
            || (string) ($meta['supplier_pnr_deferred_reason'] ?? '') !== SabreCertifiedRouteSelector::DEFER_REASON) {
            return false;
        }

        if (! $this->sabrePnrCertificationSupport->allowsControlledStaffPnrBypassDeferManualReview(
            $booking,
            $source,
            $allowControlledStaffPnr,
        )) {
            return false;
        }

        $safeRefresh = app(SabreSafeRefreshContext::class)->assess($meta);

        return ($safeRefresh['safe_refresh_context_complete'] ?? false) !== true;
    }

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    protected function existingIdentityResult(Booking $booking, string $provider, string $source, User $actor): SupplierBookingResultData
    {
        $pnr = trim((string) ($booking->pnr ?? ''));
        $reference = trim((string) ($booking->supplier_reference ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($booking->supplier_api_booking_id ?? ''));
        }

        $this->recordBlockedAttempt(
            $booking,
            $actor,
            $provider,
            $source,
            'supplier_reference_already_exists',
            'Supplier PNR or reference already exists for this booking.',
        );

        Log::notice('supplier_booking.existing_reference_reused', [
            'booking_id' => $booking->id,
            'provider' => $provider,
            'action' => 'create_supplier_booking',
            'source' => $source,
        ]);

        return new SupplierBookingResultData(
            success: true,
            status: 'success',
            provider: $provider !== '' ? $provider : 'unknown',
            supplier_reference: $reference !== '' ? $reference : null,
            pnr: $pnr !== '' ? $pnr : null,
            safe_summary: ['source' => $source, 'reason' => 'existing_supplier_identity'],
            warnings: ['Supplier PNR or reference already exists; returning existing booking identity.'],
        );
    }

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    protected function skippedResult(
        Booking $booking,
        User $actor,
        string $provider,
        string $source,
        string $errorCode,
        string $message,
    ): SupplierBookingResultData {
        $this->recordBlockedAttempt($booking, $actor, $provider, $source, $errorCode, $message);

        return new SupplierBookingResultData(
            success: false,
            status: 'skipped',
            provider: $provider,
            error_code: $errorCode,
            error_message: $message,
            safe_summary: ['source' => $source, 'reason' => $errorCode],
        );
    }

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     */
    protected function recordBlockedAttempt(
        Booking $booking,
        User $actor,
        string $provider,
        string $source,
        string $errorCode,
        string $errorMessage,
        ?string $priorErrorCode = null,
        array $extraSafeSummary = [],
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = $meta['supplier_connection_id'] ?? null;
        $cid = is_numeric($cid) ? (int) $cid : null;

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $cid,
            'provider' => $provider !== '' ? $provider : 'unknown',
            'action' => 'create_pnr',
            'status' => 'blocked',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => SensitiveDataRedactor::redact(array_filter(array_merge([
                'source' => $source,
                'reason' => $errorCode,
                'prior_error_code' => $priorErrorCode,
            ], $extraSafeSummary))),
        ]);
    }
}
