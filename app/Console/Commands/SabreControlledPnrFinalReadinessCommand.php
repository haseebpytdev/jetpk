<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Sabre\SabreControlledPnrFinalReadinessDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * F9P: Read-only final controlled Sabre PNR retry readiness after F9N + F9O (no live Sabre HTTP).
 */
class SabreControlledPnrFinalReadinessCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-FINAL-READINESS';

    protected $signature = 'sabre:controlled-pnr-final-readiness
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--json : Emit diagnostic JSON only}
                            {--confirm= : Production only: READONLY-CONTROLLED-PNR-FINAL-READINESS}';

    protected $description = 'Final controlled Sabre PNR retry readiness after strong linkage (read-only; production requires --confirm)';

    public function handle(SabreControlledPnrFinalReadinessDiagnostics $diagnostics): int
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

        $evaluation = $diagnostics->inspectBooking($booking);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($evaluation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('live_supplier_call_attempted=false');
        $this->newLine();

        $keys = [
            'booking_id',
            'booking_reference',
            'pnr_present',
            'supplier_reference_present',
            'ticketing_attempted',
            'cancellation_attempted',
            'pnr_create_attempted',
            'controlled_fresh_context_apply_present',
            'controlled_strong_revalidation_linkage_apply_present',
            'strong_revalidation_linkage_ready',
            'strong_linkage_recheck_required',
            'weak_revalidation_risk',
            'stale_context_risk',
            'minutes_since_revalidation',
            'final_freshness_ready',
            'payload_digest_status',
            'cpnr_schema_validation_status',
            'post_f9i_payload_digest_clean',
            'hard_payload_risk',
            'brand_match',
            'fare_basis_match',
            'rbd_match',
            'route_match',
            'date_match',
            'existing_retry_allowances_consumed',
            'new_explicit_retry_approval_required',
            'controlled_final_pnr_retry_allowance_used',
            'final_controlled_create_attempted',
            'final_controlled_create_failed',
            'post_final_retry_host_failure',
            'post_final_retry_host_failure_code',
            'no_safe_retry_without_remediation',
            'final_pnr_retry_ready',
            'recommended_next_action',
        ];

        foreach ($keys as $key) {
            $this->printKeyValue($key, $evaluation[$key] ?? null);
        }

        $finalFreshnessBlockers = is_array($evaluation['final_freshness_blockers'] ?? null)
            ? array_values($evaluation['final_freshness_blockers'])
            : [];
        $finalPnrRetryBlockers = is_array($evaluation['final_pnr_retry_blockers'] ?? null)
            ? array_values($evaluation['final_pnr_retry_blockers'])
            : [];

        $this->line('final_freshness_blockers='.json_encode($finalFreshnessBlockers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('final_pnr_retry_blockers='.json_encode($finalPnrRetryBlockers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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
