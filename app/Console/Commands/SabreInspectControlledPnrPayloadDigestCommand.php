<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Sabre\SabreCommandSafetyOutput;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;
use Illuminate\Console\Command;

/**
 * F9H: Read-only controlled Sabre Passenger Records payload digest (no live Sabre HTTP, no DB mutation).
 */
class SabreInspectControlledPnrPayloadDigestCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST';

    protected $signature = 'sabre:inspect-controlled-pnr-payload-digest
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--json : Emit diagnostic JSON only}
                            {--confirm= : Production only: READONLY-CONTROLLED-PNR-PAYLOAD-DIGEST}';

    protected $description = 'Controlled Sabre Passenger Records payload digest (read-only; production requires --confirm)';

    public function handle(SabreBookingService $sabreBookingService): int
    {
        if ($this->resolveGate() === null) {
            return self::FAILURE;
        }

        $booking = $this->resolveBooking();
        if ($booking === null) {
            if ($this->option('booking') === null && $this->option('reference') === null) {
                $this->components->error('Pass --booking={id} or --reference={code}.');
            } else {
                $this->components->error('Booking not found.');
            }

            return self::FAILURE;
        }

        $payload = $sabreBookingService->inspectControlledPnrPayloadDigestForBooking($booking);

        $digestSummary = ($payload['digest_status'] ?? '') === 'ok'
            ? app(SabrePassengerRecordsPayloadDigest::class)->commandSummaryFromDigest($payload)
            : null;
        if ($digestSummary !== null) {
            $payload = array_merge($payload, $digestSummary);
        }
        $f9jAssess = app(SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::class)->assessAvailability(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
            $digestSummary,
        );
        $payload['post_f9i_payload_digest_clean'] = ($f9jAssess['post_f9i_payload_digest_clean'] ?? false) === true;
        $payload['controlled_retry_after_airprice_vc_fix_available'] = ($f9jAssess['available'] ?? false) === true;
        $payload['controlled_retry_after_airprice_vc_fix_blockers'] = is_array($f9jAssess['blockers'] ?? null)
            ? array_values($f9jAssess['blockers'])
            : [];

        $f9lDiagnostics = app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)
            ->buildF9jAccountingDiagnostics(
                $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
                null,
                $digestSummary,
                ['controlled_pnr_create' => true],
                false,
            );
        $payload = array_merge($payload, $f9lDiagnostics);
        if (($f9lDiagnostics['post_f9i_payload_digest_clean'] ?? null) !== true) {
            $f9lAssess = app(SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::class)
                ->assessSchemaRecoveryAvailability(
                    $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings', 'tickets', 'supplierBookingAttempts']),
                    $digestSummary,
                    ['controlled_pnr_create' => true],
                    null,
                    'controlled_pnr_command',
                    true,
                    false,
                );
            if (($f9lAssess['post_f9i_payload_digest_clean'] ?? false) === true) {
                $payload['post_f9i_payload_digest_clean'] = true;
            }
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach (SabreCommandSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->newLine();

        $keys = [
            'booking_id',
            'booking_reference',
            'pnr_present',
            'supplier_reference_present',
            'digest_status',
            'endpoint_path',
            'payload_schema',
            'payload_style',
            'version',
            'passenger_count',
            'segment_count',
            'validating_carrier',
            'brand_code',
            'brand_name',
            'has_create_passenger_name_record_rq',
            'has_enhanced_air_book',
            'has_air_book',
            'has_air_price',
            'has_travel_itinerary_add_info',
            'has_special_req_details',
            'has_post_processing',
            'number_in_party',
            'payload_digest_available',
            'no_fares_rbd_carrier_risk',
            'no_fares_rbd_carrier_risk_reasons',
            'hard_no_fares_rbd_carrier_risk',
            'hard_no_fares_rbd_carrier_risk_reasons',
            'warning_reasons',
            'airprice_validating_carrier_present',
            'airprice_validating_carrier',
            'validating_carrier_match',
            'selected_context_brand_code',
            'payload_airprice_brand_code',
            'brand_match',
            'brand_mismatch_reason',
            'airbook_segment_count',
            'airprice_present',
            'airbook_rbd_complete',
            'airbook_carrier_complete',
            'cpnr_schema_validation_status',
            'cpnr_schema_validation_failed',
            'cpnr_schema_validation_pointer',
            'cpnr_schema_validation_message_summary',
            'post_f9i_payload_digest_clean',
            'controlled_retry_after_airprice_vc_fix_available',
            'controlled_retry_after_airprice_vc_fix_blockers',
            'controlled_retry_after_airprice_vc_schema_fix_available',
            'f9j_accounting_state',
            'f9j_used',
            'f9j_used_at',
            'f9j_used_for',
            'f9j_previous_error_code',
            'f9j_previous_host_message_present',
            'f9j_previous_no_fares_rbd_carrier_present',
            'f9j_schema_validation_failed',
            'f9j_schema_validation_stage',
            'f9j_host_application_results_received',
            'f9k_schema_recovery_available',
            'f9k_schema_recovery_blockers',
            'retry_recovery_reason',
            'f9l_schema_recovery_already_used',
            'recommended_next_action',
            'live_supplier_call_attempted',
            'pnr_create_attempted',
            'ticketing_attempted',
            'cancellation_attempted',
        ];

        foreach ($keys as $key) {
            $this->printKeyValue($key, $payload[$key] ?? null);
        }

        $this->line('selected_context_summary='.json_encode($payload['selected_context_summary'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('airbook_segment_digest='.json_encode($payload['airbook_segment_digest'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('airprice_digest='.json_encode($payload['airprice_digest'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('context_comparison='.json_encode($payload['context_comparison'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('mismatch_reasons='.json_encode($payload['mismatch_reasons'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
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

    /**
     * @return array{production_readonly_confirmed: bool}|null
     */
    protected function resolveGate(): ?array
    {
        $env = (string) config('app.env', 'production');
        if (in_array($env, ['local', 'testing'], true)) {
            return ['production_readonly_confirmed' => false];
        }

        if ($env !== 'production') {
            $this->components->error('This command only runs when APP_ENV is local, testing, or production.');

            return null;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm === self::PRODUCTION_READONLY_CONFIRM_PHRASE) {
            return ['production_readonly_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.self::PRODUCTION_READONLY_CONFIRM_PHRASE.' for read-only diagnostic.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production read-only diagnostic.');
        }

        return null;
    }

    protected function printKeyValue(string $key, mixed $value): void
    {
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
