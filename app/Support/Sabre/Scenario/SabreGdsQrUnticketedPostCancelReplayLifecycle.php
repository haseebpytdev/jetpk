<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Zero-call replay of Phase 14/15 post-cancel evidence with corrected zero-segment classification.
 */
final class SabreGdsQrUnticketedPostCancelReplayLifecycle
{
    public const MODE = 'post-cancel-replay';

    public const ARTIFACT_DIRECTORY = 'sabre-gds-qr-unticketed-post-cancel-replay';

    public const PRODUCTION_PRIOR_CANCELLATION_LIFECYCLE_RUN_ID = '5f265d7f-834f-4f4b-8376-4df358a4e9d7';

    public const PRODUCTION_POST_CANCEL_RETRIEVE_LIFECYCLE_RUN_ID = '019da711-5074-4bb6-8558-43485975be89';

    public const PRODUCTION_RETRIEVE_ATTEMPT_ID = 9;

    public const PRODUCTION_BOOKING_ID = 3;

    public const PRODUCTION_SUPPLIER_BOOKING_ID = 2;

    public const CONFIRM_LOCAL_CLOSURE = 'APPROVE-LOCAL-SABRE-GDS-POST-CANCEL-ZERO-SEGMENT-CLOSURE';

    public const CONFIRM_REPLAY_BOOKING = 'CONFIRM-SABRE-GDS-REPLAY-CLOSURE-BOOKING-3';

    public function __construct(
        private readonly SabreGdsQrUnticketedPostCancelReplayEvidenceLoader $evidenceLoader,
        private readonly SabreGdsQrUnticketedPostCancelRetrieveIdentityResolver $identityResolver,
        private readonly SabreGdsQrUnticketedPostCancelRetrieveSegmentAssessment $segmentAssessment,
        private readonly SabreGdsQrUnticketedPostCancelLocalClosureService $localClosureService,
        private readonly SabreGdsQrUnticketedPostCancelRetrieveAttemptCorrectionService $attemptCorrectionService,
        private readonly SabreGdsRevalidationProbeDbSnapshot $dbSnapshot,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $applyLocalClosure = ($options['apply_local_closure'] ?? false) === true;
        $dryRun = ! $applyLocalClosure;
        $lifecycleRunId = trim((string) ($options['lifecycle_run_id'] ?? ''));
        if ($lifecycleRunId === '') {
            $lifecycleRunId = (string) Str::uuid();
        }

        $bookingId = (int) ($options['booking_id'] ?? 0);
        $supplierBookingId = (int) ($options['supplier_booking_id'] ?? 0);
        $priorLifecycleRunId = trim((string) ($options['prior_cancellation_lifecycle_run_id'] ?? ''));
        $retrieveLifecycleRunId = trim((string) ($options['post_cancel_retrieve_lifecycle_run_id'] ?? ''));
        $retrieveAttemptId = (int) ($options['retrieve_attempt_id'] ?? 0);

        $gate = $this->evaluateGate($options, $applyLocalClosure, $bookingId, $supplierBookingId, $priorLifecycleRunId, $retrieveLifecycleRunId, $retrieveAttemptId);
        if (($gate['allowed'] ?? false) !== true) {
            return $this->finalizeArtifact($lifecycleRunId, $dryRun ? 'dry-run' : 'apply', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'error' => 'gate_blocked',
                'gate' => $gate,
            ]);
        }

        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            return $this->finalizeArtifact($lifecycleRunId, $dryRun ? 'dry-run' : 'apply', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId,
                'error' => 'booking_not_found',
            ]);
        }

        $identity = $this->identityResolver->resolve(
            $booking,
            $supplierBookingId,
            $priorLifecycleRunId,
            $applyLocalClosure && app()->environment('production'),
        );
        if (($identity['identity_checks_passed'] ?? false) !== true) {
            return $this->finalizeArtifact($lifecycleRunId, $dryRun ? 'dry-run' : 'apply', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId,
                'error' => 'identity_checks_failed',
                'identity_checks' => $identity,
            ]);
        }

        $evidence = $this->evidenceLoader->load($priorLifecycleRunId, $retrieveLifecycleRunId, $retrieveAttemptId);
        $priorArtifact = is_array($evidence['prior_cancellation_artifact'] ?? null) ? $evidence['prior_cancellation_artifact'] : [];
        $retrieveArtifact = is_array($evidence['post_cancel_retrieve_artifact'] ?? null) ? $evidence['post_cancel_retrieve_artifact'] : [];
        $safeSummary = is_array($evidence['retrieve_attempt_safe_summary'] ?? null) ? $evidence['retrieve_attempt_safe_summary'] : [];

        if ($safeSummary === []) {
            return $this->finalizeArtifact($lifecycleRunId, $dryRun ? 'dry-run' : 'apply', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId,
                'error' => 'retrieve_attempt_safe_summary_missing',
            ]);
        }

        $closureContext = $this->segmentAssessment->buildClosureContextFromIdentity(
            $identity,
            $priorArtifact,
            $bookingId,
            $supplierBookingId,
        );

        $assessment = $this->segmentAssessment->assessFromPersistedSafeSummary(
            $safeSummary,
            $closureContext,
            $retrieveArtifact,
        );

        $dbBefore = $this->dbSnapshot->capture();
        $bookingStateBefore = $this->captureBookingState($booking, $supplierBookingId);

        $closureResult = null;
        $attemptCorrection = null;
        if ($applyLocalClosure
            && ($assessment['post_cancel_retrieve_confirmed'] ?? false) === true
            && ($assessment['manual_reconciliation_required'] ?? true) === false) {
            $closureResult = $this->localClosureService->applyVerifiedPostCancelClosure(
                $booking->fresh(),
                $supplierBookingId,
                [
                    'lifecycle_run_id' => $lifecycleRunId,
                    'prior_cancellation_lifecycle_run_id' => $priorLifecycleRunId,
                    'source' => 'qr_unticketed_post_cancel_replay_phase_16',
                ],
            );
            $attemptCorrection = $this->attemptCorrectionService->applySuccessCorrection(
                $retrieveAttemptId,
                $bookingId,
                $lifecycleRunId,
            );
        }

        $booking->refresh();
        $dbAfter = $this->dbSnapshot->capture();
        $bookingStateAfter = $this->captureBookingState($booking, $supplierBookingId);
        $databaseMutationDetected = $this->dbSnapshot->assertUnchanged($dbBefore, $dbAfter) !== null;

        return $this->finalizeArtifact($lifecycleRunId, $dryRun ? 'dry-run' : 'apply', [
            'lifecycle_run_id' => $lifecycleRunId,
            'command_mode' => self::MODE,
            'replay_mode' => $dryRun ? 'dry_run' : 'apply_local_closure',
            'booking_id' => $bookingId,
            'supplier_booking_id' => $supplierBookingId,
            'prior_cancellation_lifecycle_run_id' => $priorLifecycleRunId,
            'post_cancel_retrieve_lifecycle_run_id' => $retrieveLifecycleRunId,
            'retrieve_attempt_id' => $retrieveAttemptId,
            'prior_cancellation_confirmed' => ($closureContext['prior_cancellation_confirmed'] ?? false) === true,
            'retrieve_outcome_state' => $assessment['retrieve_outcome_state'] ?? 'retrieve_ambiguous',
            'post_cancel_retrieve_confirmed' => ($assessment['post_cancel_retrieve_confirmed'] ?? false) === true,
            'cancellation_closure_verified' => $applyLocalClosure
                ? (($closureResult['closure_applied'] ?? false) === true)
                : (($assessment['cancellation_closure_verified'] ?? false) === true),
            'manual_reconciliation_required' => ($assessment['manual_reconciliation_required'] ?? true) === true,
            'active_segment_count' => (int) ($assessment['active_segment_count'] ?? 0),
            'inactive_segment_count' => (int) ($assessment['inactive_segment_count'] ?? 0),
            'cancelled_segment_count' => (int) ($assessment['cancelled_segment_count'] ?? 0),
            'segment_assessment_reason' => $assessment['assessment_reason'] ?? null,
            'classification_blockers' => $assessment['classification_blockers'] ?? [],
            'retrieve_evidence_http_status' => (int) ($safeSummary['http_status'] ?? 0),
            'retrieve_evidence_segment_count' => (int) ($safeSummary['segment_count'] ?? 0),
            'retrieve_evidence_mappable_segment_count' => (int) ($safeSummary['mappable_segment_count'] ?? 0),
            'retrieve_evidence_safe_to_map_preview' => (bool) ($safeSummary['safe_to_map_preview'] ?? false),
            'supplier_call_count' => 0,
            'cancellation_attempted' => false,
            'retrieve_attempted' => false,
            'database_mutation_detected' => $dryRun ? $databaseMutationDetected : ($closureResult !== null),
            'booking_status_before' => $bookingStateBefore['booking_status'] ?? null,
            'booking_status_after' => $bookingStateAfter['booking_status'] ?? null,
            'booking_cancellation_status_before' => $bookingStateBefore['booking_cancellation_status'] ?? null,
            'booking_cancellation_status_after' => $bookingStateAfter['booking_cancellation_status'] ?? null,
            'supplier_booking_status_before' => $bookingStateBefore['supplier_booking_row_status'] ?? null,
            'supplier_booking_status_after' => $bookingStateAfter['supplier_booking_row_status'] ?? null,
            'db_snapshot_before' => $dbBefore,
            'db_snapshot_after' => $dryRun ? $dbBefore : $dbAfter,
            'local_closure' => $closureResult,
            'retrieve_attempt_correction' => $attemptCorrection !== null ? [
                'attempt_id' => $attemptCorrection->id,
                'status' => $attemptCorrection->status,
            ] : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function evaluateGate(
        array $options,
        bool $applyLocalClosure,
        int $bookingId,
        int $supplierBookingId,
        string $priorLifecycleRunId,
        string $retrieveLifecycleRunId,
        int $retrieveAttemptId,
    ): array {
        $reasons = [];
        if ($bookingId <= 0) {
            $reasons[] = 'booking_id_required';
        }
        if ($supplierBookingId <= 0) {
            $reasons[] = 'supplier_booking_id_required';
        }
        if ($priorLifecycleRunId === '') {
            $reasons[] = 'prior_cancellation_lifecycle_run_id_required';
        }
        if ($retrieveLifecycleRunId === '') {
            $reasons[] = 'post_cancel_retrieve_lifecycle_run_id_required';
        }
        if ($retrieveAttemptId <= 0) {
            $reasons[] = 'retrieve_attempt_id_required';
        }
        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            $reasons[] = 'ticketing_enabled';
        }

        if ($applyLocalClosure) {
            if (! app()->environment('production')) {
                $reasons[] = 'apply_requires_production_environment';
            }
            if ($bookingId !== self::PRODUCTION_BOOKING_ID) {
                $reasons[] = 'production_booking_id_must_be_3';
            }
            if ($supplierBookingId !== self::PRODUCTION_SUPPLIER_BOOKING_ID) {
                $reasons[] = 'production_supplier_booking_id_must_be_2';
            }
            if ($priorLifecycleRunId !== self::PRODUCTION_PRIOR_CANCELLATION_LIFECYCLE_RUN_ID) {
                $reasons[] = 'production_prior_cancellation_lifecycle_run_id_mismatch';
            }
            if ($retrieveLifecycleRunId !== self::PRODUCTION_POST_CANCEL_RETRIEVE_LIFECYCLE_RUN_ID) {
                $reasons[] = 'production_post_cancel_retrieve_lifecycle_run_id_mismatch';
            }
            if ($retrieveAttemptId !== self::PRODUCTION_RETRIEVE_ATTEMPT_ID) {
                $reasons[] = 'production_retrieve_attempt_id_must_be_9';
            }
            if (trim((string) ($options['confirm_local_closure'] ?? '')) !== self::CONFIRM_LOCAL_CLOSURE) {
                $reasons[] = 'confirm_local_closure_missing';
            }
            if (trim((string) ($options['confirm_replay_booking'] ?? '')) !== self::CONFIRM_REPLAY_BOOKING) {
                $reasons[] = 'confirm_replay_booking_missing';
            }
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
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
        $payload['artifact_path'] = $written['relative_path'];

        Cache::put('sabre_gds_qr_unticketed_post_cancel_replay_'.$lifecycleRunId, $mode, 86400);

        return $payload;
    }
}
