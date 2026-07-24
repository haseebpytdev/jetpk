<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrItinerarySyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controlled QR unticketed post-cancel retrieve lifecycle (plan default; one retrieve max on send).
 */
final class SabreGdsQrUnticketedPostCancelRetrieveLifecycle
{
    public const MODE = 'post-cancel-retrieve';

    public const CONFIRM_PRODUCTION = 'APPROVE-LIVE-SABRE-GDS-POST-CANCEL-RETRIEVE';

    public const CONFIRM_RETRIEVE = 'LIVE-SABRE-GDS-RETRIEVE-ONE-CANCELLED-PNR';

    public const CONFIRM_NO_TICKETING = 'CONFIRM-SABRE-TICKETING-DISABLED';

    public const ARTIFACT_DIRECTORY = 'sabre-gds-qr-unticketed-post-cancel-retrieve';

    public const PRODUCTION_TARGET_BOOKING_ID = 3;

    public const PRODUCTION_TARGET_SUPPLIER_BOOKING_ID = 2;

    public const MAX_RETRIEVE_CALLS = 1;

    public function __construct(
        private readonly SabreGdsQrUnticketedPostCancelRetrieveIdentityResolver $identityResolver,
        private readonly SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment $segmentAssessment,
        private readonly SabreGdsQrUnticketedPostCancelLocalClosureService $localClosureService,
        private readonly SabrePnrItinerarySyncService $pnrItinerarySyncService,
        private readonly SabreGdsRevalidationProbeDbSnapshot $dbSnapshot,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $send = ($options['send'] ?? false) === true;
        $lifecycleRunId = trim((string) ($options['lifecycle_run_id'] ?? ''));
        if ($lifecycleRunId === '') {
            $lifecycleRunId = (string) Str::uuid();
        }

        $bookingId = (int) ($options['booking_id'] ?? 0);
        $priorCancellationLifecycleRunId = trim((string) ($options['prior_cancellation_lifecycle_run_id'] ?? ''));
        $gate = $this->evaluateGate($options, $send, $bookingId, $priorCancellationLifecycleRunId);
        if (($gate['allowed'] ?? false) !== true) {
            return $this->finalizeArtifact($lifecycleRunId, $send ? 'send' : 'plan', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId > 0 ? $bookingId : null,
                'error' => 'gate_blocked',
                'gate' => $gate,
            ]);
        }

        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            return $this->finalizeArtifact($lifecycleRunId, $send ? 'send' : 'plan', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId,
                'error' => 'booking_not_found',
            ]);
        }

        $expectedSupplierBookingId = $send
            ? (int) ($options['supplier_booking_id'] ?? self::PRODUCTION_TARGET_SUPPLIER_BOOKING_ID)
            : (is_numeric($options['supplier_booking_id'] ?? null) ? (int) $options['supplier_booking_id'] : null);

        $identity = $this->identityResolver->resolve(
            $booking,
            $expectedSupplierBookingId,
            $priorCancellationLifecycleRunId,
            $send && app()->environment('production'),
        );

        if (($identity['identity_checks_passed'] ?? false) !== true) {
            return $this->finalizeArtifact($lifecycleRunId, $send ? 'send' : 'plan', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId,
                'error' => 'identity_checks_failed',
                'identity_checks' => $identity,
            ]);
        }

        $dbBefore = $this->dbSnapshot->capture();
        $bookingStateBefore = $this->captureBookingState($booking, (int) ($identity['supplier_booking_id'] ?? 0));

        if (! $send) {
            $plan = $this->buildPlanArtifact(
                $lifecycleRunId,
                $bookingId,
                $identity,
                $priorCancellationLifecycleRunId,
                $dbBefore,
                $bookingStateBefore,
            );

            return $this->finalizeArtifact($lifecycleRunId, 'plan', $plan);
        }

        $sync = $this->resolveRetrieveSyncResult($booking, $options);
        $retrieveDispatched = true;
        $retrieveReceived = ($sync['synced'] ?? null) !== null
            || isset($sync['error'])
            || isset($sync['reason_code'])
            || isset($sync['map_preview']);

        $priorArtifact = $this->loadPriorCancellationArtifact($priorCancellationLifecycleRunId);
        $closureContext = $this->segmentAssessment->buildClosureContextFromIdentity(
            $identity,
            $priorArtifact ?? [],
            $send ? self::PRODUCTION_TARGET_BOOKING_ID : $bookingId,
            $send
                ? self::PRODUCTION_TARGET_SUPPLIER_BOOKING_ID
                : (int) ($identity['supplier_booking_id'] ?? 0),
        );

        $assessment = $this->segmentAssessment->assessFromSyncResult($sync, $closureContext);
        $manualReconciliation = ($assessment['manual_reconciliation_required'] ?? ($assessment['retrieve_ambiguous'] ?? false)) === true;
        $retrieveOutcomeState = 'retrieve_not_attempted';
        if ($retrieveDispatched) {
            $retrieveOutcomeState = (string) ($assessment['retrieve_outcome_state'] ?? ($manualReconciliation ? 'retrieve_ambiguous' : 'retrieve_confirmed'));
        }

        $closureVerified = false;
        $closureResult = null;
        if (($assessment['post_cancel_retrieve_confirmed'] ?? false) === true && ! $manualReconciliation) {
            $closureResult = $this->localClosureService->applyVerifiedPostCancelClosure(
                $booking->fresh(),
                (int) ($identity['supplier_booking_id'] ?? 0),
                [
                    'lifecycle_run_id' => $lifecycleRunId,
                    'prior_cancellation_lifecycle_run_id' => $priorCancellationLifecycleRunId,
                ],
            );
            $closureVerified = ($closureResult['closure_applied'] ?? false) === true;
        }

        $booking->refresh();
        $dbAfter = $this->dbSnapshot->capture();
        $bookingStateAfter = $this->captureBookingState($booking, (int) ($identity['supplier_booking_id'] ?? 0));

        return $this->finalizeArtifact($lifecycleRunId, 'send', [
            'lifecycle_run_id' => $lifecycleRunId,
            'command_mode' => self::MODE,
            'booking_id' => $bookingId,
            'supplier_booking_id' => $identity['supplier_booking_id'] ?? null,
            'prior_cancellation_lifecycle_run_id' => $priorCancellationLifecycleRunId,
            'locator_present' => ($identity['locator_present'] ?? false) === true,
            'locator_sha256' => $identity['locator_sha256'] ?? null,
            'prior_cancellation_confirmed' => ($identity['prior_cancellation_confirmed'] ?? false) === true,
            'prior_cancellation_ambiguous' => ($identity['prior_cancellation_ambiguous'] ?? false) === true,
            'retrieve_request_dispatched' => $retrieveDispatched,
            'retrieve_response_received' => $retrieveReceived,
            'retrieve_outcome_state' => $retrieveOutcomeState,
            'supplier_retrieve_call_count' => $retrieveDispatched ? 1 : 0,
            'retrieve_retry_count' => 0,
            'active_segment_count' => (int) ($assessment['active_segment_count'] ?? 0),
            'inactive_segment_count' => (int) ($assessment['inactive_segment_count'] ?? 0),
            'cancelled_segment_count' => (int) ($assessment['cancelled_segment_count'] ?? 0),
            'post_cancel_retrieve_confirmed' => ($assessment['post_cancel_retrieve_confirmed'] ?? false) === true,
            'cancellation_closure_verified' => $closureVerified,
            'manual_reconciliation_required' => $manualReconciliation || (($closureResult['success'] ?? true) === false && ($assessment['post_cancel_retrieve_confirmed'] ?? false) === true),
            'booking_status_before' => $bookingStateBefore['booking_status'] ?? null,
            'booking_status_after' => $bookingStateAfter['booking_status'] ?? null,
            'booking_cancellation_status_before' => $bookingStateBefore['booking_cancellation_status'] ?? null,
            'booking_cancellation_status_after' => $bookingStateAfter['booking_cancellation_status'] ?? null,
            'supplier_booking_status_before' => $bookingStateBefore['supplier_booking_row_status'] ?? null,
            'supplier_booking_status_after' => $bookingStateAfter['supplier_booking_row_status'] ?? null,
            'db_snapshot_before' => $dbBefore,
            'db_snapshot_after' => $dbAfter,
            'cancellation_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'void_attempted' => false,
            'refund_attempted' => false,
            'segment_assessment_reason' => $assessment['assessment_reason'] ?? null,
            'local_closure' => $closureResult,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function evaluateGate(
        array $options,
        bool $send,
        int $bookingId,
        string $priorCancellationLifecycleRunId,
    ): array {
        $reasons = [];
        if ($bookingId <= 0) {
            $reasons[] = 'booking_id_required';
        }
        if ($priorCancellationLifecycleRunId === '') {
            $reasons[] = 'prior_cancellation_lifecycle_run_id_required';
        }
        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            $reasons[] = 'ticketing_enabled';
        }
        if ($this->containsDeniedLocator($options)) {
            $reasons[] = 'denylisted_locator_reference';
        }

        if ($send) {
            if (! app()->environment('production')) {
                $reasons[] = 'send_requires_production_environment';
            }
            if ($bookingId !== self::PRODUCTION_TARGET_BOOKING_ID) {
                $reasons[] = 'production_booking_id_must_be_3';
            }
            if (trim((string) ($options['confirm_production'] ?? '')) !== self::CONFIRM_PRODUCTION) {
                $reasons[] = 'confirm_production_missing';
            }
            if (trim((string) ($options['confirm_retrieve'] ?? '')) !== self::CONFIRM_RETRIEVE) {
                $reasons[] = 'confirm_retrieve_missing';
            }
            if (trim((string) ($options['confirm_no_ticketing'] ?? '')) !== self::CONFIRM_NO_TICKETING) {
                $reasons[] = 'confirm_no_ticketing_missing';
            }
            if ($priorCancellationLifecycleRunId !== SabreGdsQrUnticketedPostCancelPriorCancellationGate::PRODUCTION_PRIOR_CANCELLATION_LIFECYCLE_RUN_ID) {
                $reasons[] = 'production_prior_cancellation_lifecycle_run_id_mismatch';
            }
        }

        $existing = $this->existingLifecycleState($options);
        if (($existing['blocked'] ?? false) === true) {
            $reasons[] = 'lifecycle_idempotency_'.(string) ($existing['state'] ?? 'blocked');
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
            'deny_locators' => SabreGdsQrUnticketedCancelIdentityResolver::DENY_LOCATORS,
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function containsDeniedLocator(array $options): bool
    {
        $haystack = strtolower(json_encode($options, JSON_THROW_ON_ERROR));
        foreach (SabreGdsQrUnticketedCancelIdentityResolver::DENY_LOCATORS as $locator) {
            if (str_contains($haystack, strtolower($locator))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, int>  $dbBefore
     * @param  array<string, mixed>  $bookingStateBefore
     * @return array<string, mixed>
     */
    protected function buildPlanArtifact(
        string $lifecycleRunId,
        int $bookingId,
        array $identity,
        string $priorCancellationLifecycleRunId,
        array $dbBefore,
        array $bookingStateBefore,
    ): array {
        return [
            'lifecycle_run_id' => $lifecycleRunId,
            'command_mode' => self::MODE,
            'probe_mode' => 'plan',
            'booking_id' => $bookingId,
            'supplier_booking_id' => $identity['supplier_booking_id'] ?? self::PRODUCTION_TARGET_SUPPLIER_BOOKING_ID,
            'prior_cancellation_lifecycle_run_id' => $priorCancellationLifecycleRunId,
            'locator_present' => ($identity['locator_present'] ?? false) === true,
            'locator_matches' => ($identity['locator_matches'] ?? false) === true,
            'locator_denylisted' => ($identity['locator_denylisted'] ?? false) === true,
            'locator_sha256' => $identity['locator_sha256'] ?? null,
            'prior_cancellation_confirmed' => ($identity['prior_cancellation_confirmed'] ?? false) === true,
            'prior_cancellation_ambiguous' => ($identity['prior_cancellation_ambiguous'] ?? false) === true,
            'retrieve_planned' => ($identity['identity_checks_passed'] ?? false) === true,
            'maximum_retrieve_calls' => self::MAX_RETRIEVE_CALLS,
            'automatic_retrieve_retry' => false,
            'cancellation_planned' => false,
            'pnr_create_planned' => false,
            'ticketing_planned' => false,
            'airticket_planned' => false,
            'void_planned' => false,
            'refund_planned' => false,
            'retrieve_outcome_state' => 'retrieve_not_attempted',
            'supplier_retrieve_call_count' => 0,
            'retrieve_retry_count' => 0,
            'manual_reconciliation_required' => false,
            'db_snapshot_before' => $dbBefore,
            'db_snapshot_after' => $dbBefore,
            'database_mutation_detected' => false,
            'booking_status_before' => $bookingStateBefore['booking_status'] ?? null,
            'booking_status_after' => $bookingStateBefore['booking_status'] ?? null,
            'booking_cancellation_status_before' => $bookingStateBefore['booking_cancellation_status'] ?? null,
            'booking_cancellation_status_after' => $bookingStateBefore['booking_cancellation_status'] ?? null,
            'supplier_booking_status_before' => $bookingStateBefore['supplier_booking_row_status'] ?? null,
            'supplier_booking_status_after' => $bookingStateBefore['supplier_booking_row_status'] ?? null,
            'cancellation_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'void_attempted' => false,
            'refund_attempted' => false,
            'final_lifecycle_state' => 'planned',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function captureBookingState(Booking $booking, int $supplierBookingId): array
    {
        $booking->loadMissing('supplierBookings');
        $supplierRow = $booking->supplierBookings->firstWhere('id', $supplierBookingId);

        return [
            'booking_status' => (string) ($booking->status->value ?? $booking->status),
            'booking_cancellation_status' => $booking->cancellation_status,
            'booking_supplier_booking_status_column' => (string) ($booking->supplier_booking_status ?? ''),
            'supplier_booking_row_status' => (string) ($supplierRow->status ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function finalizeArtifact(string $lifecycleRunId, string $mode, array $payload): array
    {
        $payload['artifact_written_at'] = now()->toIso8601String();
        $relative = self::ARTIFACT_DIRECTORY.'/'.$lifecycleRunId.'-'.$mode.'.json';
        $written = app(SabreGdsPrivateLifecycleArtifactWriter::class)->write($relative, $payload);
        $payload['artifact_mode_expected'] = $written['mode_expected'];
        $payload['artifact_mode_actual'] = $written['mode_actual'];
        $payload['artifact_path'] = $written['relative_path'];

        $state = match ($payload['retrieve_outcome_state'] ?? null) {
            'retrieve_ambiguous' => 'ambiguous',
            'retrieve_completed' => ($payload['cancellation_closure_verified'] ?? false) === true ? 'completed' : 'in_progress',
            default => $mode === 'send' ? 'in_progress' : 'planned',
        };
        Cache::put('sabre_gds_qr_unticketed_post_cancel_retrieve_lifecycle_state_'.$lifecycleRunId, $state, 86400);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function resolveRetrieveSyncResult(Booking $booking, array $options): array
    {
        if (is_array($options['test_sync_result'] ?? null)) {
            return $options['test_sync_result'];
        }

        return $this->pnrItinerarySyncService->sync($booking->fresh(), false);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadPriorCancellationArtifact(string $priorLifecycleRunId): ?array
    {
        if ($priorLifecycleRunId === '') {
            return null;
        }
        $relative = SabreGdsQrUnticketedCancelLifecycle::ARTIFACT_DIRECTORY.'/'.$priorLifecycleRunId.'-send.json';
        if (! Storage::disk('local')->exists($relative)) {
            return null;
        }
        $decoded = json_decode((string) Storage::disk('local')->get($relative), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{blocked: bool, state?: string}
     */
    protected function existingLifecycleState(array $options): array
    {
        $lifecycleRunId = trim((string) ($options['lifecycle_run_id'] ?? ''));
        if ($lifecycleRunId === '') {
            return ['blocked' => false];
        }
        $state = Cache::get('sabre_gds_qr_unticketed_post_cancel_retrieve_lifecycle_state_'.$lifecycleRunId);
        if (in_array($state, ['in_progress', 'completed', 'ambiguous'], true)) {
            return ['blocked' => true, 'state' => (string) $state];
        }

        return ['blocked' => false];
    }
}
