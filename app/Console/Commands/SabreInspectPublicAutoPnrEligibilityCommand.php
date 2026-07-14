<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreBrandedFarePublicAutoPnrEligibility;
use Illuminate\Console\Command;

/**
 * BF7-H/I: Read-only branded-fare public Auto-PNR eligibility diagnostics (no live Sabre HTTP).
 */
class SabreInspectPublicAutoPnrEligibilityCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-PUBLIC-AUTO-PNR-ELIGIBILITY';

    protected $signature = 'sabre:inspect-public-auto-pnr-eligibility
                            {--booking= : Booking ID}
                            {--confirm= : Production only: READONLY-PUBLIC-AUTO-PNR-ELIGIBILITY}
                            {--reevaluate : Re-run eligibility from current booking data (no supplier calls)}
                            {--json : Emit diagnostic JSON only}';

    protected $description = 'Branded-fare public Auto-PNR eligibility diagnostics (read-only; production requires --confirm)';

    public function handle(SabreBrandedFarePublicAutoPnrEligibility $eligibility): int
    {
        if ($this->resolveGate() === null) {
            return self::FAILURE;
        }

        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $raw);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $stored = is_array($meta[SabreBrandedFarePublicAutoPnrEligibility::META_KEY] ?? null)
            ? $meta[SabreBrandedFarePublicAutoPnrEligibility::META_KEY]
            : null;

        $shouldReevaluate = (bool) $this->option('reevaluate') || $stored === null;
        $liveEvaluation = $shouldReevaluate ? $eligibility->evaluate($booking) : null;

        if ((bool) $this->option('json')) {
            $payload = [];
            if ($stored !== null) {
                $payload['stored_eligibility'] = $stored;
            }
            if ($liveEvaluation !== null) {
                $payload['live_evaluation'] = $liveEvaluation;
            }
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('live_supplier_call_attempted=false');
        $this->newLine();

        if ($stored !== null) {
            $this->line('[stored_eligibility]');
            $this->printSummarySection($stored);
            $this->newLine();
        }

        if ($liveEvaluation !== null) {
            $this->line('[live_evaluation]');
            $this->printSummarySection($liveEvaluation, includeBookingId: true);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function printSummarySection(array $summary, bool $includeBookingId = false): void
    {
        $keys = [
            'eligible',
            'reason_code',
            'selected_brand_code',
            'brand_shape',
            'carrier_chain',
            'payment_mode',
            'ticketing_enabled',
            'public_flag_enabled',
            'auto_pnr_flag_enabled',
        ];
        if ($includeBookingId) {
            $keys[] = 'booking_id';
        }
        $keys[] = 'evaluated_at';

        foreach ($keys as $key) {
            if ($key === 'evaluated_at' && ! array_key_exists($key, $summary)) {
                continue;
            }
            $this->printKeyValue($key, $summary[$key] ?? null);
        }

        $failed = is_array($summary['failed_conditions'] ?? null) ? $summary['failed_conditions'] : [];
        $this->line('failed_conditions='.json_encode(array_values($failed), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
        } elseif ($value === null || $value === '') {
            $this->line($key.'=');
        } else {
            $this->line($key.'='.$value);
        }
    }
}
