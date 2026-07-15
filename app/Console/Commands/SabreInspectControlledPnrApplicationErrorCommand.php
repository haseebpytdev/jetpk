<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Sabre\SabreCommandSafetyOutput;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Illuminate\Console\Command;

/**
 * F9G: Read-only controlled Sabre PNR application-error diagnostics (no live Sabre HTTP, no DB mutation).
 */
class SabreInspectControlledPnrApplicationErrorCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-APPLICATION-ERROR';

    protected $signature = 'sabre:inspect-controlled-pnr-application-error
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--attempt= : Supplier booking attempt ID (optional; defaults to latest meaningful create)}
                            {--json : Emit diagnostic JSON only}
                            {--confirm= : Production only: READONLY-CONTROLLED-PNR-APPLICATION-ERROR}';

    protected $description = 'Controlled Sabre PNR application-error digest (read-only; production requires --confirm)';

    public function handle(SabrePassengerRecordsApplicationResultDigest $digest): int
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

        $attempt = $this->resolveAttempt($booking);
        if ($attempt !== null) {
            $booking->setRelation('supplierBookingAttempts', collect([$attempt]));
        }

        $payload = $digest->inspectBooking($booking);
        if ($attempt !== null) {
            $payload['attempt_id'] = $attempt->id;
            $payload['attempt_status'] = $attempt->status;
            $payload['http_status'] = $attempt->http_status;
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
            'attempt_id',
            'attempt_status',
            'http_status',
            'pnr_present',
            'supplier_reference_present',
            'digest_source',
            'digest_status',
            'sabre_last_create_status',
            'sabre_last_create_error_code',
            'sabre_last_create_error_message',
            'safe_application_status',
            'sabre_application_status',
            'sabre_application_error_count',
            'sabre_application_warning_count',
            'sabre_application_message_count',
            'sabre_application_first_error_code',
            'sabre_application_first_error_message',
            'application_error_digest_available',
            'safe_reason_code',
            'safe_host_error_family',
            'host_error_family',
            'retry_policy',
            'recommended_admin_action',
            'manual_review_required',
            'host_classification_reclassified_from_digest',
            'retroactive_enrichment_available',
            'retroactive_enrichment_source',
            'retroactive_enrichment_note',
            'payload_preflight_status',
            'mixed_mapping_comparison_result',
            'command_pricing_schema_valid',
            'command_pricing_allowed_shape',
            'command_pricing_rejected_keys',
            'mixed_fare_carrier_mapping_complete',
            'no_fares_rbd_carrier_preflight_risk',
            'segment_marketing_carriers',
            'command_pricing_carriers',
            'command_pricing_segmentselect_pairing_complete',
            'segment_select_rph_values',
            'command_pricing_rph_values',
            'brand_present',
            'brand_code',
            'brand_rph_present',
            'brand_rph_type',
            'brand_rph_values',
            'brand_rph_values_raw',
            'brand_rph_values_normalized',
            'brand_rph_schema_valid',
            'brand_segmentselect_pairing_required',
            'brand_segmentselect_pairing_complete',
            'brand_segmentselect_pairing_values_match_normalized',
            'brand_segmentselect_missing_rph',
            'brand_schema_valid',
            'brand_schema_rejected_pointer',
            'brand_schema_rejected_message',
            'brand_wire_shape',
            'brand_omitted_for_mixed_v24_segmentselect',
            'brand_omission_reason',
            'selected_payload_style',
            'recommended_next_action',
            'live_supplier_call_attempted',
            'pnr_create_attempted',
            'ticketing_attempted',
            'cancellation_attempted',
        ];

        foreach ($keys as $key) {
            $this->printKeyValue($key, $payload[$key] ?? null);
        }

        $this->line('safe_application_errors='.json_encode($payload['safe_application_errors'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('safe_application_warnings='.json_encode($payload['safe_application_warnings'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('safe_errors='.json_encode($payload['safe_errors'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('safe_warnings='.json_encode($payload['safe_warnings'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('safe_messages='.json_encode($payload['safe_messages'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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

    protected function resolveAttempt(Booking $booking): ?SupplierBookingAttempt
    {
        $attemptId = $this->option('attempt');
        if ($attemptId !== null && $attemptId !== '' && is_numeric($attemptId)) {
            return SupplierBookingAttempt::query()
                ->where('booking_id', $booking->id)
                ->whereKey((int) $attemptId)
                ->first();
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
