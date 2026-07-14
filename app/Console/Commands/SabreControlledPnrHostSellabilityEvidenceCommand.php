<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Sabre\SabreCommandSafetyOutput;
use App\Support\Sabre\SabreControlledPnrHostSellabilityEvidenceDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * F9R: Read-only controlled Sabre PNR host-sellability evidence after post-final-retry failure (no live Sabre HTTP, no DB mutation).
 */
class SabreControlledPnrHostSellabilityEvidenceCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-HOST-SELLABILITY-EVIDENCE';

    protected $signature = 'sabre:controlled-pnr-host-sellability-evidence
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--json : Emit diagnostic JSON only}
                            {--confirm= : Production only: READONLY-CONTROLLED-PNR-HOST-SELLABILITY-EVIDENCE}';

    protected $description = 'Controlled Sabre PNR host-sellability evidence after post-final-retry failure (read-only; production requires --confirm)';

    public function handle(SabreControlledPnrHostSellabilityEvidenceDiagnostics $diagnostics): int
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

        $payload = $diagnostics->inspectBooking($booking);

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
            'local_payload_clean',
            'host_rejected_sellability',
            'controlled_final_pnr_retry_allowance_used',
            'final_controlled_create_attempted',
            'final_controlled_create_failed',
            'post_final_retry_host_failure',
            'post_final_retry_host_failure_code',
            'no_safe_retry_without_remediation',
            'digest_status',
            'endpoint_path',
            'payload_style',
            'segment_count',
            'validating_carrier',
            'brand_code',
            'airprice_validating_carrier_present',
            'airprice_validating_carrier',
            'validating_carrier_match',
            'selected_context_brand_code',
            'payload_airprice_brand_code',
            'brand_match',
            'airbook_segment_count',
            'airprice_present',
            'airbook_rbd_complete',
            'airbook_carrier_complete',
            'cpnr_schema_validation_status',
            'cpnr_schema_validation_failed',
            'post_f9i_payload_digest_clean',
            'application_error_digest_available',
            'sabre_last_create_status',
            'sabre_last_create_error_code',
            'sabre_last_create_error_message',
            'sabre_application_status',
            'sabre_application_error_count',
            'sabre_application_warning_count',
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
        $this->line('rbd_by_segment='.json_encode($payload['rbd_by_segment'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('fare_basis_by_segment='.json_encode($payload['fare_basis_by_segment'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('context_comparison='.json_encode($payload['context_comparison'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('safe_errors='.json_encode($payload['safe_errors'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('safe_warnings='.json_encode($payload['safe_warnings'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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
            $booking = Booking::query()->where('booking_reference', $reference)->first();
            if ($booking !== null) {
                return $booking;
            }

            if (Schema::hasColumn('bookings', 'reference_code')) {
                return Booking::query()->where('reference_code', $reference)->first();
            }
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
