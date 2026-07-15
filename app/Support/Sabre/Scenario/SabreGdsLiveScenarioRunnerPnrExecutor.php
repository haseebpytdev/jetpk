<?php

namespace App\Support\Sabre\Scenario;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Enums\SupplierProvider;
use App\Models\SupplierBookingAttempt;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierCertificationGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierFareBasisPayloadPreflight;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategySelector;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Preflight + single live Sabre GDS PNR create for {@see SabreGdsLiveScenarioRunner} (operator-approved only).
 */
final class SabreGdsLiveScenarioRunnerPnrExecutor
{
    public const REASON_OPERATOR_APPROVAL_MISSING = 'scenario_runner_operator_approval_missing';

    public const REASON_BOOKING_ALREADY_HAS_PNR = 'booking_already_has_pnr';

    public const REASON_SUPPLIER_REFERENCE_EXISTS = 'supplier_reference_exists';

    public const REASON_COMPLETION_NOT_READY = 'completion_not_ready';

    public const REASON_BOOKING_PAYLOAD_NOT_READY = 'booking_payload_not_ready';

    public const REASON_NO_SELECTED_STRATEGY = 'no_selected_strategy';

    public const REASON_DUPLICATE_LOCK_FAILED = 'duplicate_lock_failed';

    public const REASON_MIXED_CARRIER_GATE_BLOCKED = 'mixed_carrier_certification_gate_blocked';

    public const REASON_MIXED_CARRIER_APPROVAL_MISSING = SabreGdsMixedCarrierCertificationGate::REASON_APPROVAL_MISSING;

    public const REASON_MIXED_CARRIER_PAYLOAD_MAPPING_INCOMPLETE = SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_INCOMPLETE;

    public const REASON_MIXED_CARRIER_PAYLOAD_MAPPING_UNAVAILABLE = SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_PAYLOAD_MAPPING_UNAVAILABLE;

    public const REASON_MIXED_CARRIER_V24_COMMANDPRICING_SCHEMA_INVALID = SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SCHEMA_INVALID;

    public const REASON_MIXED_CARRIER_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING = SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_COMMANDPRICING_SEGMENTSELECT_PAIRING_MISSING;

    public const REASON_MIXED_CARRIER_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING = SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_SEGMENTSELECT_PAIRING_MISSING;

    public const REASON_MIXED_CARRIER_V24_BRAND_RPH_SCHEMA_INVALID = SabreGdsMixedCarrierFareBasisPayloadPreflight::REASON_V24_BRAND_RPH_SCHEMA_INVALID;

    public function __construct(
        protected SabreBookingService $sabreBooking,
        protected SabreGdsAutoPnrContextCompletionService $contextCompletion,
        protected SabreGdsPnrCreateStrategySelector $strategySelector,
        protected SabreGdsMixedCarrierCertificationGate $mixedCarrierGate,
        protected SabreGdsMixedCarrierFareBasisPayloadPreflight $mixedCarrierPayloadPreflight,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Booking $booking, bool $operatorApproved, array $options = []): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'supplierBookingAttempts']);
        $booking->refresh();

        $block = $this->preflight($booking, $operatorApproved);
        if ($block !== null) {
            return $block;
        }

        $completion = $this->contextCompletion->completeForBooking($booking);
        if (($completion['public_auto_pnr_attempt_ready'] ?? false) === true) {
            $this->contextCompletion->persistCompletedContext($booking->fresh(), $completion);
        } else {
            $this->contextCompletion->persistCompletionDiagnostics($booking->fresh(), $completion);
        }
        $booking->refresh();

        $block = $this->preflightAfterCompletion($booking, $completion);
        if ($block !== null) {
            return $block;
        }

        $mixedCertApproved = ($options['mixed_carrier_certification_approved'] ?? false) === true;
        $tripType = app(SabrePnrCertificationSupport::class)->detectTripType($booking);
        $mixedPreflightProof = [];

        $strategySelection = $this->strategySelector->selectForScenarioRunner($booking->fresh([
            'passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'supplierBookingAttempts',
        ]), [
            'strategy' => strtolower(trim((string) ($options['strategy'] ?? 'auto'))) ?: 'auto',
            'mixed_carrier_certification_approved' => $mixedCertApproved,
        ]);
        $selectedStrategy = trim((string) ($strategySelection['selected_strategy'] ?? ''));

        if ($this->mixedCarrierGate->isMixedCarrierTripType($tripType)) {
            if (! $mixedCertApproved) {
                return $this->blockedResult(
                    self::REASON_MIXED_CARRIER_APPROVAL_MISSING,
                    $booking,
                    $completion,
                    $strategySelection,
                    $this->mixedCarrierGate->safeSummarySlice($this->mixedCarrierGate->evaluate($booking, [
                        'completion' => $completion,
                        'trip_type' => $tripType,
                        'scenario_live_pnr_create_approved' => $operatorApproved,
                        'mixed_carrier_certification_approved' => false,
                        'scenario_runner_override_applied' => ($strategySelection['scenario_runner_override_applied'] ?? false) === true,
                    ])),
                );
            }

            $gateEvaluation = $this->mixedCarrierGate->evaluate($booking, [
                'completion' => $completion,
                'trip_type' => $tripType,
                'selected_strategy' => $selectedStrategy,
                'scenario_live_pnr_create_approved' => $operatorApproved,
                'mixed_carrier_certification_approved' => true,
                'scenario_runner_override_applied' => ($strategySelection['scenario_runner_override_applied'] ?? false) === true,
            ]);
            if (($gateEvaluation['allowed'] ?? false) !== true) {
                return $this->blockedResult(
                    self::REASON_MIXED_CARRIER_GATE_BLOCKED,
                    $booking,
                    $completion,
                    $strategySelection,
                    $this->mixedCarrierGate->safeSummarySlice($gateEvaluation),
                );
            }

            $payloadPreflight = $this->mixedCarrierPayloadPreflight->evaluate($booking, [
                'completion' => $completion,
                'trip_type' => $tripType,
                'selected_strategy' => $selectedStrategy,
                'scenario_live_pnr_create_approved' => $operatorApproved,
                'mixed_carrier_certification_approved' => true,
                'scenario_runner_override_applied' => ($strategySelection['scenario_runner_override_applied'] ?? false) === true,
            ]);
            if (($payloadPreflight['allowed'] ?? false) !== true) {
                $reasonCode = trim((string) ($payloadPreflight['block_reason'] ?? self::REASON_MIXED_CARRIER_PAYLOAD_MAPPING_INCOMPLETE));
                $payloadSummary = $this->mixedCarrierPayloadPreflight->safeSummarySlice($payloadPreflight);
                $this->persistMixedCarrierPreflightBlockedAttempt($booking, $reasonCode, $payloadSummary);

                return $this->blockedResult(
                    $reasonCode,
                    $booking,
                    $completion,
                    array_merge($strategySelection, [
                        'selected_strategy' => null,
                    ]),
                    $payloadSummary,
                );
            }

            $mixedPreflightProof = $this->mixedCarrierPayloadPreflight->attemptProofSlice($payloadPreflight);
            $this->persistMixedCarrierPreflightProof($booking, $mixedPreflightProof);
            $booking->refresh();
        }

        if ($selectedStrategy === '') {
            return $this->blockedResult(
                self::REASON_NO_SELECTED_STRATEGY,
                $booking,
                $completion,
                $strategySelection,
            );
        }

        if (is_array($strategySelection['unexpected_strategy_priority'] ?? null)) {
            return $this->blockedResult(
                SabreGdsPnrCreateStrategySelector::SAFE_REASON_UNEXPECTED_STRATEGY_PRIORITY,
                $booking,
                $completion,
                array_merge($strategySelection, [
                    'unexpected_strategy_priority' => $strategySelection['unexpected_strategy_priority'],
                ]),
            );
        }

        try {
            $result = $this->sabreBooking->createBookingForScenarioRunner(
                $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'supplierBookingAttempts']),
                $selectedStrategy,
                $strategySelection,
                $completion,
                $options,
            );
        } catch (\Throwable) {
            return $this->blockedResult(
                'scenario_runner_pnr_create_failed',
                $booking->fresh(),
                $completion,
                $strategySelection,
            );
        }

        $booking->refresh();

        return array_merge($result, [
            'scenario_live_pnr_create_approved' => true,
            'selected_strategy' => $selectedStrategy,
            'pnr_strategy_used' => $selectedStrategy,
            'payload_schema' => $result['payload_schema'] ?? $selectedStrategy,
            'pnr_attempted' => ($result['live_call_attempted'] ?? false) === true,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'auto_pnr_context_completion' => $completion,
            'gds_strategy_selection' => $strategySelection,
            'scenario_runner_override_applied' => ($strategySelection['scenario_runner_override_applied'] ?? false) === true,
            'mixed_carrier_preflight_proof' => $mixedPreflightProof !== [] ? $mixedPreflightProof : null,
        ], $mixedPreflightProof);
    }

    /**
     * @param  array<string, mixed>  $proof
     */
    protected function persistMixedCarrierPreflightProof(Booking $booking, array $proof): void
    {
        if ($proof === []) {
            return;
        }

        try {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $meta[SabreGdsMixedCarrierFareBasisPayloadPreflight::META_PREFLIGHT_PROOF_KEY] = $proof;
            $booking->forceFill(['meta' => $meta])->save();
        } catch (\Throwable) {
            // Non-fatal: proof persistence must not block live create.
        }
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected function persistMixedCarrierPreflightBlockedAttempt(Booking $booking, string $reasonCode, array $safeSummary): void
    {
        try {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $connId = (int) ($meta['supplier_connection_id'] ?? 0);
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connId > 0 ? $connId : null,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'error_code' => $reasonCode,
                'error_message' => 'Mixed-carrier payload preflight blocked before live Passenger Records create.',
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => 'scenario_runner',
                    'attempt_source' => 'scenario_runner',
                    'reason_code' => $reasonCode,
                    'block_reason' => $reasonCode,
                    'live_call_attempted' => false,
                    'pnr_attempted' => false,
                    'ticketing_attempted' => false,
                    'airticket_attempted' => false,
                ], $safeSummary)),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        } catch (\Throwable) {
            // fail-safe: preflight persistence must not break scenario runner
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function preflight(Booking $booking, bool $operatorApproved): ?array
    {
        if (! $operatorApproved) {
            return $this->blockedResult(self::REASON_OPERATOR_APPROVAL_MISSING, $booking);
        }

        if (trim((string) ($booking->pnr ?? '')) !== '') {
            return $this->blockedResult(self::REASON_BOOKING_ALREADY_HAS_PNR, $booking);
        }

        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return $this->blockedResult(self::REASON_SUPPLIER_REFERENCE_EXISTS, $booking);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>|null
     */
    protected function preflightAfterCompletion(Booking $booking, array $completion): ?array
    {
        $status = trim((string) ($completion['auto_pnr_context_completion_status'] ?? ''));
        if (! in_array($status, [
            SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
            SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
        ], true)) {
            return $this->blockedResult(self::REASON_COMPLETION_NOT_READY, $booking, $completion);
        }

        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            return $this->blockedResult(self::REASON_COMPLETION_NOT_READY, $booking, $completion);
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $handoffReady = ($handoff['ready_for_booking_payload'] ?? false) === true;
        $completionReady = ($completion['public_auto_pnr_attempt_ready'] ?? false) === true;
        if (! $handoffReady && $completionReady) {
            $handoffReady = count($completion['completed_booking_classes_by_segment'] ?? []) >= (int) ($completion['segment_count'] ?? 1)
                && count($completion['completed_fare_basis_codes_by_segment'] ?? []) >= (int) ($completion['segment_count'] ?? 1);
        }
        if (! $handoffReady) {
            return $this->blockedResult(self::REASON_BOOKING_PAYLOAD_NOT_READY, $booking, $completion);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $completion
     * @param  array<string, mixed>|null  $strategySelection
     * @return array<string, mixed>
     */
    protected function blockedResult(
        string $reasonCode,
        Booking $booking,
        ?array $completion = null,
        ?array $strategySelection = null,
        ?array $mixedCarrierSummary = null,
    ): array {
        $selectedStrategy = trim((string) ($strategySelection['selected_strategy'] ?? ''));

        return array_merge([
            'success' => false,
            'status' => 'needs_review',
            'reason_code' => $reasonCode,
            'error_code' => $reasonCode,
            'block_reason' => $reasonCode,
            'live_call_attempted' => false,
            'pnr_attempted' => false,
            'public_auto_pnr_attempted' => false,
            'scenario_live_pnr_create_approved' => $reasonCode !== self::REASON_OPERATOR_APPROVAL_MISSING,
            'selected_strategy' => in_array($reasonCode, [
                self::REASON_MIXED_CARRIER_PAYLOAD_MAPPING_INCOMPLETE,
                self::REASON_MIXED_CARRIER_PAYLOAD_MAPPING_UNAVAILABLE,
            ], true) ? null : ($selectedStrategy !== '' ? $selectedStrategy : null),
            'pnr_strategy_used' => null,
            'payload_schema' => null,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'auto_pnr_context_completion' => $completion,
            'gds_strategy_selection' => $strategySelection,
            'candidate_exclusion_diagnostics' => is_array($strategySelection['candidate_exclusion_diagnostics'] ?? null)
                ? $strategySelection['candidate_exclusion_diagnostics']
                : null,
            'scenario_runner_override_applied' => is_bool($strategySelection['scenario_runner_override_applied'] ?? null)
                ? $strategySelection['scenario_runner_override_applied']
                : null,
            'unexpected_strategy_priority' => is_array($strategySelection['unexpected_strategy_priority'] ?? null)
                ? $strategySelection['unexpected_strategy_priority']
                : null,
            'booking_id' => $booking->id,
        ], is_array($mixedCarrierSummary) ? $mixedCarrierSummary : []);
    }
}
