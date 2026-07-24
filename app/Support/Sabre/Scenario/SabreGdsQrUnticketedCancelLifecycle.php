<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelService;
use App\Support\Sabre\Scenario\SabreGdsRevalidationProbeDbSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controlled QR unticketed PNR cancellation lifecycle (plan default; live send requires production tokens).
 */
final class SabreGdsQrUnticketedCancelLifecycle
{
    public const MODE = 'cancel';

    public const CONFIRM_PRODUCTION = 'APPROVE-LIVE-SABRE-GDS-UNTICKETED-CANCELLATION';

    public const CONFIRM_CANCELLATION = 'LIVE-SABRE-GDS-CANCEL-ONE-UNTICKETED-PNR';

    public const CONFIRM_NO_TICKETING = 'CONFIRM-SABRE-TICKETING-DISABLED';

    public const ARTIFACT_DIRECTORY = 'sabre-gds-qr-unticketed-cancel';

    public const PRODUCTION_TARGET_BOOKING_ID = 3;

    public const PRODUCTION_TARGET_SUPPLIER_BOOKING_ID = 2;

    public const MAX_CANCELLATION_CALLS = 1;

    public function __construct(
        private readonly SabreGdsQrUnticketedCancelIdentityResolver $identityResolver,
        private readonly SabreGdsQrUnticketedSupplierCancelAttemptRecorder $cancelAttemptRecorder,
        private readonly SabreGdsCancelService $cancelService,
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
        $gate = $this->evaluateGate($options, $send, $bookingId);
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

        $identity = $this->identityResolver->resolve($booking, $expectedSupplierBookingId);

        if ($send && ($identity['identity_checks_passed'] ?? false) !== true) {
            return $this->finalizeArtifact($lifecycleRunId, 'send', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId,
                'error' => 'identity_checks_failed',
                'identity_checks' => $identity,
            ]);
        }

        $dbBefore = $this->dbSnapshot->capture();
        if (! $send) {
            $plan = $this->buildPlanArtifact($lifecycleRunId, $bookingId, $identity, $dbBefore);

            return $this->finalizeArtifact($lifecycleRunId, 'plan', $plan);
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            return $this->finalizeArtifact($lifecycleRunId, 'send', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'booking_id' => $bookingId,
                'error' => 'supplier_connection_missing',
                'identity_checks' => $identity,
                'db_snapshot_before' => $dbBefore,
                'db_snapshot_after' => $dbBefore,
            ]);
        }

        $cancelAttempt = $this->cancelAttemptRecorder->recordStarted(
            $booking,
            $connection,
            $lifecycleRunId,
            is_string($identity['locator_sha256'] ?? null) ? (string) $identity['locator_sha256'] : null,
        );

        $outcome = $this->cancelService->cancelForBooking($booking->fresh(), true, [
            'source' => 'qr_unticketed_cancel_lifecycle',
            'lifecycle_run_id' => $lifecycleRunId,
            'skip_post_cancel_retrieve' => true,
            'skip_internal_cancel_booking_attempt_rows' => true,
            'skip_booking_cancel_service_attempt_row' => true,
            'defer_local_cancellation_closure' => true,
            'qr_unticketed_cancel_attempt_id' => $cancelAttempt->id,
        ]);

        $classification = (string) (data_get($outcome, 'sabre_gds_cancel.classification')
            ?? data_get($outcome, 'post_cancel_verification.classification')
            ?? data_get($outcome, 'classification')
            ?? '');
        $confirmed = in_array($classification, [
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
        ], true) || ($outcome['success'] ?? false) === true;

        $ambiguous = ! $confirmed && ($outcome['live_call_attempted'] ?? $outcome['supplier_call_attempted'] ?? false) === true;
        $outcomeState = 'cancellation_not_attempted';
        $finalState = 'cancellation_not_attempted';
        $manualReconciliation = false;
        $terminalAttemptStatus = 'failed';

        if (($outcome['live_call_attempted'] ?? false) === true || ($outcome['supplier_call_attempted'] ?? false) === true) {
            $outcomeState = 'cancellation_started';
            if ($confirmed) {
                $outcomeState = 'cancellation_confirmed';
                $finalState = 'cancellation_confirmed';
                $terminalAttemptStatus = 'success';
            } elseif ($ambiguous) {
                $outcomeState = 'cancellation_ambiguous';
                $finalState = 'reconciliation_required';
                $manualReconciliation = true;
                $terminalAttemptStatus = 'needs_review';
            } else {
                $outcomeState = 'cancellation_failed_definitively';
                $finalState = 'cancellation_failed_definitively';
                $terminalAttemptStatus = 'failed';
            }
        }

        $this->cancelAttemptRecorder->completeFromCancelOutcome(
            (int) $cancelAttempt->id,
            $booking->fresh(),
            $outcome,
            $terminalAttemptStatus,
            $classification !== '' ? $classification : null,
        );

        $booking->refresh();
        $dbAfter = $this->dbSnapshot->capture();

        return $this->finalizeArtifact($lifecycleRunId, 'send', [
            'lifecycle_run_id' => $lifecycleRunId,
            'command_mode' => self::MODE,
            'booking_id' => $bookingId,
            'supplier_booking_id' => $identity['supplier_booking_id'] ?? null,
            'locator_present' => ($identity['locator_present'] ?? false) === true,
            'locator_sha256' => $identity['locator_sha256'] ?? null,
            'identity_checks' => $identity,
            'ticket_number_count' => $identity['ticket_number_count'] ?? 0,
            'unticketed' => ($identity['unticketed'] ?? false) === true,
            'cancellation_request_dispatched' => ($outcome['live_call_attempted'] ?? false) === true,
            'cancellation_response_received' => ($outcome['supplier_response_received'] ?? $outcome['success'] ?? null) !== null,
            'cancellation_outcome_state' => $outcomeState,
            'final_lifecycle_state' => $finalState,
            'supplier_cancellation_call_count' => ($outcome['live_call_attempted'] ?? false) === true ? 1 : 0,
            'cancellation_retry_count' => 0,
            'manual_reconciliation_required' => $manualReconciliation,
            'db_snapshot_before' => $dbBefore,
            'db_snapshot_after' => $dbAfter,
            'booking_status_before' => $dbBefore['booking_status_'.$bookingId] ?? null,
            'booking_status_after' => (string) ($booking->status->value ?? $booking->status),
            'supplier_booking_status_after' => (string) ($booking->supplier_booking_status ?? ''),
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'void_attempted' => false,
            'refund_attempted' => false,
            'post_cancel_retrieve_attempted' => false,
            'cancel_attempt_id' => $cancelAttempt->id,
            'cancellation_classification' => $classification !== '' ? $classification : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function evaluateGate(array $options, bool $send, int $bookingId): array
    {
        $reasons = [];
        if ($bookingId <= 0) {
            $reasons[] = 'booking_id_required';
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
            if (trim((string) ($options['confirm_cancellation'] ?? '')) !== self::CONFIRM_CANCELLATION) {
                $reasons[] = 'confirm_cancellation_missing';
            }
            if (trim((string) ($options['confirm_no_ticketing'] ?? '')) !== self::CONFIRM_NO_TICKETING) {
                $reasons[] = 'confirm_no_ticketing_missing';
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
     * @return array<string, mixed>
     */
    protected function buildPlanArtifact(string $lifecycleRunId, int $bookingId, array $identity, array $dbBefore): array
    {
        return [
            'lifecycle_run_id' => $lifecycleRunId,
            'command_mode' => self::MODE,
            'probe_mode' => 'plan',
            'booking_id' => $bookingId,
            'supplier_booking_id' => $identity['supplier_booking_id'] ?? self::PRODUCTION_TARGET_SUPPLIER_BOOKING_ID,
            'locator_present' => ($identity['locator_present'] ?? false) === true,
            'locator_matches' => ($identity['locator_matches'] ?? false) === true,
            'locator_denylisted' => ($identity['locator_denylisted'] ?? false) === true,
            'locator_sha256' => $identity['locator_sha256'] ?? null,
            'unticketed' => ($identity['unticketed'] ?? false) === true,
            'ticket_number_count' => (int) ($identity['ticket_number_count'] ?? 0),
            'identity_checks' => $identity,
            'operation_plan' => [
                'cancellation_planned' => ($identity['identity_checks_passed'] ?? false) === true,
                'maximum_cancellation_calls' => self::MAX_CANCELLATION_CALLS,
                'automatic_cancellation_retry' => false,
                'post_cancel_retrieve_planned' => false,
                'ticketing_planned' => false,
                'airticket_planned' => false,
                'void_planned' => false,
                'refund_planned' => false,
            ],
            'cancellation_outcome_state' => 'cancellation_not_attempted',
            'final_lifecycle_state' => 'planned',
            'supplier_cancellation_call_count' => 0,
            'cancellation_retry_count' => 0,
            'manual_reconciliation_required' => false,
            'db_snapshot_before' => $dbBefore,
            'db_snapshot_after' => $dbBefore,
            'database_mutation_detected' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'void_attempted' => false,
            'refund_attempted' => false,
            'post_cancel_retrieve_attempted' => false,
        ];
    }

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId <= 0) {
            return null;
        }

        return SupplierConnection::query()->find($connectionId);
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

        $state = match ($payload['final_lifecycle_state'] ?? $payload['cancellation_outcome_state'] ?? null) {
            'reconciliation_required', 'cancellation_ambiguous' => 'ambiguous',
            'cancellation_confirmed' => 'completed',
            default => $mode === 'send' ? 'in_progress' : 'planned',
        };
        Cache::put('sabre_gds_qr_unticketed_cancel_lifecycle_state_'.$lifecycleRunId, $state, 86400);

        return $payload;
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
        $state = Cache::get('sabre_gds_qr_unticketed_cancel_lifecycle_state_'.$lifecycleRunId);
        if (in_array($state, ['in_progress', 'completed', 'ambiguous', 'reconciliation_required'], true)) {
            return ['blocked' => true, 'state' => (string) $state];
        }

        return ['blocked' => false];
    }
}
