<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Sabre\SabreCommandSafetyOutput;
use App\Support\Sabre\SabreControlledPnrStrongRevalidationLinkageDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * F9O: Read-only controlled Sabre BFM strong revalidation linkage diagnostics (no DB mutation by default).
 */
class SabreInspectControlledPnrStrongRevalidationLinkageCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-LINKAGE';

    public const PRODUCTION_PROBE_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-PROBE';

    protected $signature = 'sabre:inspect-controlled-pnr-strong-revalidation-linkage
                            {--booking= : Booking ID}
                            {--reference= : Booking reference (booking_reference)}
                            {--json : Emit diagnostic JSON only}
                            {--probe-revalidate : Optional live shop refresh probe (no DB mutation; stricter production confirm)}
                            {--confirm= : Production: READONLY-CONTROLLED-PNR-STRONG-REVALIDATION-LINKAGE or PROBE phrase when probing}';

    protected $description = 'Controlled Sabre BFM strong revalidation linkage diagnostics (read-only; production requires --confirm)';

    public function handle(SabreControlledPnrStrongRevalidationLinkageDiagnostics $diagnostics): int
    {
        $probe = (bool) $this->option('probe-revalidate');

        if ($this->resolveGate($probe) === null) {
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

        $payload = $diagnostics->inspectBooking($booking, $probe);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach (SabreCommandSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        if ($probe) {
            $this->line('probe_revalidate_requested=true');
            $this->line(SabreCommandSafetyOutput::liveSupplierCallAttempted(true));
        }
        $this->newLine();

        $scalarKeys = [
            'booking_id',
            'booking_reference',
            'pnr_present',
            'supplier_reference_present',
            'ticketing_attempted',
            'cancellation_attempted',
            'live_supplier_call_attempted',
            'pnr_create_attempted',
            'current_revalidation_linkage_strength',
            'legacy_revalidation_signal_used',
            'weak_revalidation_risk',
            'stale_context_risk',
            'controlled_fresh_context_apply_present',
            'controlled_fresh_context_apply_applied_at',
            'controlled_strong_revalidation_linkage_apply_present',
            'selected_offer_created_at',
            'last_revalidated_at',
            'minutes_since_revalidation',
            'safe_refresh_context_complete',
            'validated_offer_snapshot_present',
            'pricing_snapshot_present',
            'raw_payload_present',
            'selected_offer_not_strongly_revalidated',
            'strong_bfm_linkage_missing',
            'revalidation_payload_unusable',
            'revalidation_total_fare_missing',
            'revalidation_validating_carrier_missing',
            'revalidation_pricing_info_missing',
            'revalidation_segment_refs_missing',
            'recommended_lane',
            'recommended_next_action',
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval',
        ];

        foreach ($scalarKeys as $key) {
            $this->printKeyValue($key, $payload[$key] ?? null);
        }

        $this->line('strong_linkage_matrix='.json_encode($payload['strong_linkage_matrix'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($probe && is_array($payload['revalidation_probe'] ?? null)) {
            $this->line('revalidation_probe='.json_encode($payload['revalidation_probe'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
    protected function resolveGate(bool $probe): ?array
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
        $required = $probe
            ? self::PRODUCTION_PROBE_CONFIRM_PHRASE
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
