<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Sabre\SabreCommandSafetyOutput;
use App\Support\Sabre\SabreControlledPnrSellabilityDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * F9M: Read-only controlled Sabre CPNR sellability diagnostics (no live Sabre HTTP by default, no DB mutation).
 */
class SabreInspectControlledPnrSellabilityCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-SELLABILITY';

    public const PRODUCTION_FRESH_PROBE_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-SELLABILITY-FRESH-PROBE';

    protected $signature = 'sabre:inspect-controlled-pnr-sellability
                            {--booking= : Booking ID}
                            {--reference= : Booking reference (booking_reference)}
                            {--json : Emit diagnostic JSON only}
                            {--probe-fresh-revalidate : Optional live fresh shop probe (no DB mutation; stricter production confirm)}
                            {--confirm= : Production: READONLY-CONTROLLED-PNR-SELLABILITY or FRESH-PROBE phrase when probing}';

    protected $description = 'Controlled Sabre CPNR sellability diagnostics (read-only; production requires --confirm)';

    public function handle(SabreControlledPnrSellabilityDiagnostics $diagnostics): int
    {
        $freshProbe = (bool) $this->option('probe-fresh-revalidate');

        if ($this->resolveGate($freshProbe) === null) {
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

        $payload = $diagnostics->inspectBooking($booking, $freshProbe);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach (SabreCommandSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        if ($freshProbe) {
            $this->line('fresh_probe_requested=true');
            $this->line(SabreCommandSafetyOutput::liveSupplierCallAttempted(true));
        }
        $this->newLine();

        $scalarKeys = [
            'booking_id',
            'booking_reference',
            'supplier_connection_id',
            'endpoint_path',
            'payload_style',
            'current_application_error_code',
            'current_host_warning_code',
            'current_host_warning_message_summary',
            'pnr_present',
            'supplier_reference_present',
            'ticketing_attempted',
            'cancellation_attempted',
            'live_supplier_call_attempted',
            'pnr_create_attempted',
            'search_created_at',
            'selected_offer_created_at',
            'last_revalidated_at',
            'offer_refresh_status',
            'offer_refresh_reason',
            'minutes_since_revalidation',
            'minutes_since_offer_refresh',
            'revalidation_linkage_strength',
            'legacy_revalidation_signal_used',
            'safe_refresh_context_complete',
            'validated_offer_snapshot_present',
            'pricing_snapshot_present',
            'raw_payload_present',
            'freshness_status',
            'host_no_fares_rbd_carrier_status',
            'hard_payload_risk',
            'host_sellability_risk',
            'stale_context_risk',
            'weak_revalidation_risk',
            'rbd_sellability_unknown',
            'brand_qualifier_risk',
            'fare_basis_linkage_risk',
            'missing_price_quote_linkage',
            'recommended_lane',
            'recommended_next_action',
            'post_f9i_payload_digest_clean',
            'payload_digest_status',
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval',
        ];

        foreach ($scalarKeys as $key) {
            $this->printKeyValue($key, $payload[$key] ?? null);
        }

        $this->line('segment_sellability_matrix='.json_encode($payload['segment_sellability_matrix'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('fare_brand_matrix='.json_encode($payload['fare_brand_matrix'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($freshProbe && is_array($payload['fresh_probe'] ?? null)) {
            $this->line('fresh_probe='.json_encode($payload['fresh_probe'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

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
    protected function resolveGate(bool $freshProbe): ?array
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
        $required = $freshProbe
            ? self::PRODUCTION_FRESH_PROBE_CONFIRM_PHRASE
            : self::PRODUCTION_READONLY_CONFIRM_PHRASE;

        if ($confirm === $required) {
            return ['production_readonly_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.$required.' for read-only diagnostic.'
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
