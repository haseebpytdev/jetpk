<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\Booking;
use App\Models\User;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledPnrApprovalOverrideGate;
use App\Support\Bookings\SabreControlledPnrReadiness;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Sabre\SabreControlledPnrFinalReadinessDiagnostics;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * F9: Controlled Sabre PNR create command shell — dry-run by default; live call only with exact confirmation.
 */
class SabreControlledCreatePnrCommand extends Command
{
    protected $signature = 'sabre:controlled-create-pnr
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--dry-run : Readiness only — no supplier HTTP (default)}
                            {--confirm= : Exact phrase CREATE-PNR-FOR-BOOKING-{id} to allow live attempt}
                            {--json : Emit machine-readable lines only}';

    protected $description = 'Controlled Sabre PNR create shell — dry-run/readiness by default; live call requires exact --confirm';

    public function handle(
        SabreControlledPnrReadiness $readiness,
        SabreBookingService $sabreBookingService,
        SabreControlledPnrApprovalOverrideGate $deferOverrideGate,
    ): int {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->emitPayload([
                'error' => ($this->option('booking') === null && $this->option('reference') === null)
                    ? 'missing_booking_option'
                    : 'booking_not_found',
                'live_supplier_call_attempted' => false,
                'pnr_create_attempted' => false,
                'ticketing_attempted' => false,
                'cancellation_attempted' => false,
                'booking_created_in_supplier' => false,
            ]);

            return self::FAILURE;
        }

        $dryRun = $this->wantsDryRun();
        $confirmProvided = $this->confirmPhraseMatches($booking);
        $evaluation = $readiness->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => ! $dryRun,
            'admin_confirmation_provided' => $confirmProvided,
        ]);

        $bookingMeta = is_array($booking->meta) ? $booking->meta : [];
        $historicalDefer = ($bookingMeta['defer_supplier_booking_to_manual_review'] ?? false) === true;
        $historicalDeferReason = (string) ($bookingMeta['supplier_pnr_deferred_reason'] ?? '');
        $historicalOfferRefreshPriceChanged = ($bookingMeta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] ?? false) === true;
        $historicalOfferRefreshRequiresConfirmation = ($bookingMeta[SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION] ?? false) === true;

        $classification = $this->classificationFromEvaluation($evaluation, $dryRun, $confirmProvided);
        $basePayload = [
            'classification' => $classification,
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'eligible' => (bool) ($evaluation['eligible'] ?? false),
            'can_attempt_supplier_pnr' => (bool) ($evaluation['can_attempt_supplier_pnr'] ?? false),
            'live_supplier_call_allowed' => (bool) ($evaluation['live_supplier_call_allowed'] ?? false),
            'reason_code' => (string) ($evaluation['reason_code'] ?? ''),
            'human_message' => (string) ($evaluation['human_message'] ?? ''),
            'blockers' => is_array($evaluation['blockers'] ?? null) ? array_values($evaluation['blockers']) : [],
            'warnings' => is_array($evaluation['warnings'] ?? null) ? array_values($evaluation['warnings']) : [],
            'recommended_next_action' => (string) ($evaluation['recommended_next_action'] ?? ''),
            'controlled_pnr_manual_review_approved' => (bool) ($evaluation['controlled_pnr_manual_review_approved'] ?? false),
            'controlled_pnr_fare_change_accepted' => (bool) ($evaluation['controlled_pnr_fare_change_accepted'] ?? false),
            'has_usable_controlled_pnr_context' => (bool) ($evaluation['has_usable_controlled_pnr_context'] ?? false),
            'exact_create_confirmation_required' => $dryRun && ($evaluation['can_attempt_supplier_pnr'] ?? false) === true,
            'exact_create_confirm_phrase' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
            'historical_defer_supplier_booking_to_manual_review' => $historicalDefer,
            'historical_supplier_pnr_deferred_reason' => $historicalDeferReason,
            'historical_offer_refresh_price_changed' => $historicalOfferRefreshPriceChanged,
            'historical_offer_refresh_requires_customer_confirmation' => $historicalOfferRefreshRequiresConfirmation,
            'controlled_manual_review_override_used' => false,
            'controlled_supplier_retry_allowance_used' => false,
            'controlled_supplier_retry_allowance_reason' => null,
            'controlled_supplier_retry_after_airprice_vc_fix_used' => false,
            'controlled_supplier_retry_after_airprice_vc_fix_reason' => null,
            'post_f9i_payload_digest_clean' => false,
            'previous_no_fares_rbd_carrier_error_present' => false,
            'controlled_retry_after_airprice_vc_fix_available' => false,
            'controlled_retry_after_airprice_vc_fix_blockers' => [],
            'controlled_retry_after_airprice_vc_schema_fix_available' => false,
            'controlled_supplier_retry_after_airprice_vc_schema_fix_used' => false,
            'f9j_accounting_state' => 'not_used',
            'f9j_used' => false,
            'f9j_used_at' => null,
            'f9j_used_for' => null,
            'f9j_previous_error_code' => null,
            'f9j_previous_host_message_present' => false,
            'f9j_previous_no_fares_rbd_carrier_present' => false,
            'f9j_schema_validation_failed' => false,
            'f9j_schema_validation_stage' => 'unknown',
            'f9j_host_application_results_received' => false,
            'f9k_schema_recovery_available' => false,
            'f9k_schema_recovery_blockers' => [],
            'retry_recovery_reason' => null,
            'controlled_final_pnr_retry_allowance_present' => false,
            'controlled_final_pnr_retry_allowance_valid' => false,
            'controlled_final_pnr_retry_allowance_expires_at' => null,
            'controlled_final_pnr_retry_allowance_used' => false,
            'final_controlled_create_attempted' => false,
            'final_controlled_create_failed' => false,
            'post_final_retry_host_failure' => false,
            'post_final_retry_host_failure_code' => null,
            'no_safe_retry_without_remediation' => false,
            'final_pnr_retry_ready' => false,
            'final_pnr_retry_blockers' => [],
            'cpnr_schema_validation_status' => 'not_run',
            'cpnr_schema_validation_failed' => false,
            'cpnr_schema_validation_pointer' => null,
            'cpnr_schema_validation_message_summary' => null,
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'booking_created_in_supplier' => false,
        ];

        $f9rGate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $f9rContainment = $f9rGate->assessPostFinalRetryContainment(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
        );
        $f9rContained = ($f9rContainment['contained'] ?? false) === true;
        $basePayload['final_controlled_create_attempted'] = ($f9rContainment['final_controlled_create_attempted'] ?? false) === true;
        $basePayload['final_controlled_create_failed'] = ($f9rContainment['final_controlled_create_failed'] ?? false) === true;
        $basePayload['post_final_retry_host_failure'] = ($f9rContainment['post_final_retry_host_failure'] ?? false) === true;
        $basePayload['post_final_retry_host_failure_code'] = $f9rContainment['post_final_retry_host_failure_code'] ?? null;
        $basePayload['no_safe_retry_without_remediation'] = ($f9rContainment['no_safe_retry_without_remediation'] ?? false) === true;
        $basePayload['controlled_final_pnr_retry_allowance_used'] = ($f9rContainment['controlled_final_pnr_retry_allowance_used'] ?? false) === true;

        if ($f9rContained) {
            $basePayload = $f9rGate->applyPostFinalRetryContainmentOutputAlignment($basePayload, $f9rContainment);
        }

        if ($dryRun || ! $confirmProvided || ! ($evaluation['live_supplier_call_allowed'] ?? false) || $f9rContained) {
            if (! $dryRun && ! $confirmProvided) {
                $basePayload['blocked_message'] = 'Missing or invalid --confirm phrase. No supplier call attempted.';
                $basePayload['reason_code'] = 'exact_create_confirmation_required';
            } elseif (! $dryRun && ! ($evaluation['live_supplier_call_allowed'] ?? false)) {
                $basePayload['blocked_message'] = 'Readiness gates blocked live supplier PNR create.';
            } elseif ($dryRun && ($evaluation['can_attempt_supplier_pnr'] ?? false) === true) {
                $basePayload['blocked_message'] = 'Dry-run only — use exact --confirm='.$basePayload['exact_create_confirm_phrase'].' for live supplier create.';
            }

            if ($dryRun) {
                $rebuilt = $sabreBookingService->inspectControlledPnrPayloadDigestForBooking(
                    $booking->fresh(['passengers', 'contact', 'fareBreakdown']),
                );
                $digestSummary = null;
                if (($rebuilt['digest_status'] ?? '') === 'ok') {
                    $digestSummary = app(SabrePassengerRecordsPayloadDigest::class)->commandSummaryFromDigest($rebuilt);
                    $basePayload = array_merge($basePayload, $digestSummary);
                }
                $f9jGate = app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class);
                $f9jAssess = $f9jGate->assessAvailability(
                    $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
                    $digestSummary,
                    [
                        'controlled_pnr_create' => true,
                        'controlled_manual_review_approved' => (bool) ($evaluation['controlled_pnr_manual_review_approved'] ?? false),
                        'controlled_approval_confirm_phrase' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
                        'readiness_snapshot' => $evaluation,
                    ],
                );
                $basePayload['controlled_retry_after_airprice_vc_fix_available'] = ($f9jAssess['available'] ?? false) === true;
                $basePayload['controlled_retry_after_airprice_vc_fix_blockers'] = is_array($f9jAssess['blockers'] ?? null)
                    ? array_values($f9jAssess['blockers'])
                    : [];
                $basePayload['post_f9i_payload_digest_clean'] = ($f9jAssess['post_f9i_payload_digest_clean'] ?? false) === true;
                $basePayload['previous_no_fares_rbd_carrier_error_present'] = ($f9jAssess['previous_no_fares_rbd_carrier_error_present'] ?? false) === true;

                $controlledContext = [
                    'controlled_pnr_create' => true,
                    'controlled_manual_review_approved' => (bool) ($evaluation['controlled_pnr_manual_review_approved'] ?? false),
                    'controlled_approval_confirm_phrase' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
                    'readiness_snapshot' => $evaluation,
                ];
                $f9lDiagnostics = app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)
                    ->buildF9jAccountingDiagnostics(
                        $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
                        null,
                        $digestSummary,
                        $controlledContext,
                        false,
                    );
                $basePayload = array_merge($basePayload, $f9lDiagnostics);
                $f9lAssess = app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)
                    ->assessSchemaRecoveryAvailability(
                        $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
                        $digestSummary,
                        $controlledContext,
                        null,
                        'controlled_pnr_command',
                        true,
                        false,
                    );
                if (($f9lAssess['post_f9i_payload_digest_clean'] ?? false) === true) {
                    $basePayload['post_f9i_payload_digest_clean'] = true;
                }

                $finalReadiness = app(SabreControlledPnrFinalReadinessDiagnostics::class)->inspectBooking(
                    $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets']),
                );
                $basePayload['final_pnr_retry_ready'] = ($finalReadiness['final_pnr_retry_ready'] ?? false) === true;
                $basePayload['final_pnr_retry_blockers'] = is_array($finalReadiness['final_pnr_retry_blockers'] ?? null)
                    ? array_values($finalReadiness['final_pnr_retry_blockers'])
                    : [];

                $f9qAssess = app(SabreControlledFinalPnrRetryAllowanceGate::class)->assessAvailability(
                    $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
                    $controlledContext,
                    false,
                );
                $basePayload['controlled_final_pnr_retry_allowance_present'] = ($f9qAssess['present'] ?? false) === true;
                $basePayload['controlled_final_pnr_retry_allowance_valid'] = ($f9qAssess['valid'] ?? false) === true;
                $basePayload['controlled_final_pnr_retry_allowance_expires_at'] = $f9qAssess['expires_at'] ?? null;
                if (! $f9rContained) {
                    $basePayload['controlled_final_pnr_retry_allowance_used'] = ($finalReadiness['controlled_final_pnr_retry_allowance_used'] ?? false) === true;
                }

            }

            $this->emitPayload($basePayload);

            if ($f9rContained) {
                return self::FAILURE;
            }

            return ($evaluation['eligible'] ?? false) === true && $dryRun ? self::SUCCESS : self::FAILURE;
        }

        $confirmPhrase = 'CREATE-PNR-FOR-BOOKING-'.$booking->id;
        $controlledContext = [
            'controlled_pnr_create' => true,
            'controlled_manual_review_approved' => (bool) ($evaluation['controlled_pnr_manual_review_approved'] ?? false),
            'controlled_approval_source' => 'artisan',
            'controlled_approval_confirm_phrase' => $confirmPhrase,
            'readiness_snapshot' => $evaluation,
        ];
        $basePayload['controlled_manual_review_override_used'] = $deferOverrideGate->allowsDeferOverride(
            $booking,
            'controlled_pnr_command',
            true,
            $controlledContext,
        );

        $result = $sabreBookingService->createSupplierBooking(
            $booking->fresh(['passengers', 'contact', 'supplierBookings']),
            $this->systemActor(),
            false,
            true,
            false,
            'controlled_pnr_command',
            $controlledContext,
        );

        $success = $result->success === true;
        $overrideFromResult = ($result->safe_summary['controlled_manual_review_defer_override_used'] ?? false) === true;
        if ($overrideFromResult) {
            $basePayload['controlled_manual_review_override_used'] = true;
        }
        $retryAllowanceFromResult = ($result->safe_summary['controlled_supplier_retry_allowance_used'] ?? false) === true;
        if ($retryAllowanceFromResult) {
            $basePayload['controlled_supplier_retry_allowance_used'] = true;
            $basePayload['controlled_supplier_retry_allowance_reason'] = (string) (
                $result->safe_summary['controlled_supplier_retry_allowance_reason']
                ?? 'accepted_fare_change_retry'
            );
        }
        $retryAfterVcFixFromResult = ($result->safe_summary['controlled_supplier_retry_after_airprice_vc_fix_used'] ?? false) === true;
        if ($retryAfterVcFixFromResult) {
            $basePayload['controlled_supplier_retry_after_airprice_vc_fix_used'] = true;
            $basePayload['controlled_supplier_retry_after_airprice_vc_fix_reason'] = (string) (
                $result->safe_summary['controlled_supplier_retry_after_airprice_vc_fix_reason']
                ?? 'clean_airprice_validating_carrier_payload_after_no_fares_rbd_carrier'
            );
            $basePayload['post_f9i_payload_digest_clean'] = ($result->safe_summary['post_f9i_payload_digest_clean'] ?? false) === true;
            $basePayload['previous_no_fares_rbd_carrier_error_present'] = ($result->safe_summary['previous_no_fares_rbd_carrier_error_present'] ?? false) === true;
        }
        $schemaFixRecoveryFromResult = ($result->safe_summary['controlled_supplier_retry_after_airprice_vc_schema_fix_used'] ?? false) === true;
        if ($schemaFixRecoveryFromResult) {
            $basePayload['controlled_supplier_retry_after_airprice_vc_schema_fix_used'] = true;
            $basePayload['post_f9i_payload_digest_clean'] = ($result->safe_summary['post_f9i_payload_digest_clean'] ?? false) === true;
        }
        $finalRetryAllowanceFromResult = ($result->safe_summary['controlled_final_pnr_retry_allowance_used'] ?? false) === true;
        if ($finalRetryAllowanceFromResult) {
            $basePayload['controlled_final_pnr_retry_allowance_used'] = true;
        }
        $basePayload['live_supplier_call_attempted'] = true;
        $basePayload['pnr_create_attempted'] = true;
        $basePayload['booking_created_in_supplier'] = $success;
        $basePayload['classification'] = $success ? 'controlled_pnr_create_success' : 'controlled_pnr_create_failed';
        $basePayload['supplier_status'] = (string) ($result->status ?? '');
        $basePayload['error_code'] = (string) ($result->error_code ?? '');
        $basePayload['error_message'] = $result->error_message !== null && $result->error_message !== ''
            ? (string) $result->error_message
            : null;

        if (! $success) {
            $booking->refresh();
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $storedDigest = is_array($meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY] ?? null)
                ? $meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY]
                : null;
            if ($storedDigest === null || $storedDigest === []) {
                $safeSummary = is_array($result->safe_summary) ? $result->safe_summary : [];
                if (($safeSummary['application_error_digest_available'] ?? false) === true) {
                    $storedDigest = [
                        'application_status' => $safeSummary['passenger_records_application_status'] ?? null,
                        'error_count' => (int) ($safeSummary['sabre_application_error_count'] ?? 0),
                        'warning_count' => (int) ($safeSummary['sabre_application_warning_count'] ?? 0),
                        'message_count' => (int) ($safeSummary['sabre_application_message_count'] ?? 0),
                        'errors' => ($safeSummary['sabre_application_first_error_code'] ?? null) !== null
                            ? [[
                                'code' => $safeSummary['sabre_application_first_error_code'] ?? null,
                                'message' => $safeSummary['sabre_application_first_error_message'] ?? null,
                            ]]
                            : [],
                    ];
                }
            }
            $basePayload = array_merge(
                $basePayload,
                app(SabrePassengerRecordsApplicationResultDigest::class)->commandSummaryFromDigest($storedDigest),
            );

            $payloadDigest = null;
            $safeSummary = is_array($result->safe_summary) ? $result->safe_summary : [];
            if (is_array($safeSummary[SabrePassengerRecordsPayloadDigest::SLIM_DIGEST_KEY] ?? null)) {
                $payloadDigest = $safeSummary[SabrePassengerRecordsPayloadDigest::SLIM_DIGEST_KEY];
            } else {
                $rebuilt = $sabreBookingService->inspectControlledPnrPayloadDigestForBooking(
                    $booking->fresh(['passengers', 'contact', 'fareBreakdown']),
                );
                if (($rebuilt['digest_status'] ?? '') === 'ok') {
                    $payloadDigest = $rebuilt;
                }
            }
            $basePayload = array_merge(
                $basePayload,
                app(SabrePassengerRecordsPayloadDigest::class)->commandSummaryFromDigest($payloadDigest),
            );
        }

        $this->emitPayload($basePayload);

        return $success ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveBooking(): ?Booking
    {
        $bookingId = $this->option('booking');
        if ($bookingId !== null && $bookingId !== '' && is_numeric($bookingId)) {
            return Booking::query()->find((int) $bookingId);
        }

        $reference = trim((string) $this->option('reference'));
        if ($reference !== '') {
            return Booking::query()->where('reference_code', $reference)->first();
        }

        return null;
    }

    protected function wantsDryRun(): bool
    {
        if ($this->option('dry-run') === true) {
            return true;
        }

        $confirm = trim((string) $this->option('confirm'));

        return $confirm === '';
    }

    protected function confirmPhraseMatches(Booking $booking): bool
    {
        $expected = 'CREATE-PNR-FOR-BOOKING-'.$booking->id;

        return trim((string) $this->option('confirm')) === $expected;
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    protected function classificationFromEvaluation(array $evaluation, bool $dryRun, bool $confirmProvided): string
    {
        if ($dryRun) {
            return 'dry_run_readiness_only';
        }

        if (! $confirmProvided) {
            return 'blocked_missing_confirmation';
        }

        if (! ($evaluation['live_supplier_call_allowed'] ?? false)) {
            return 'blocked_readiness_gates';
        }

        return 'ready_for_controlled_create';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitPayload(array $payload): void
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        foreach ($payload as $key => $value) {
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } elseif ($value === null || $value === '') {
                $this->line($key.'=');
            } else {
                $this->line($key.'='.$value);
            }
        }
    }

    protected function systemActor(): User
    {
        $admin = User::query()->where('account_type', AccountType::PlatformAdmin)->orderBy('id')->first()
            ?? User::query()->where('email', 'admin@ota.demo')->orderBy('id')->first();

        if ($admin === null) {
            throw new InvalidArgumentException('No platform admin user found for controlled PNR command actor.');
        }

        return $admin;
    }
}
