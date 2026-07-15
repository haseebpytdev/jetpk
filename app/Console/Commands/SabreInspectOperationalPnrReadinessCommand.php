<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use Illuminate\Console\Command;

/**
 * BF7-J-OPS: Read-only operational Sabre PNR readiness diagnostics (no live Sabre HTTP).
 */
class SabreInspectOperationalPnrReadinessCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-OPERATIONAL-PNR-READINESS';

    protected $signature = 'sabre:inspect-operational-pnr-readiness
                            {--booking= : Booking ID}
                            {--confirm= : Production only: READONLY-OPERATIONAL-PNR-READINESS}
                            {--json : Emit diagnostic JSON only}';

    protected $description = 'Operational Sabre PNR readiness diagnostics (read-only; production requires --confirm)';

    public function handle(SabreOperationalPnrReadiness $readiness): int
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

        $evaluation = $readiness->evaluate($booking);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($evaluation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('live_supplier_call_attempted=false');
        $this->newLine();

        $keys = [
            'would_attempt_pnr',
            'reason_code',
            'provider',
            'supplier_connection_id_present',
            'payment_mode',
            'ticketing_enabled',
            'same_carrier',
            'mixed_carrier',
            'pnr_present',
            'supplier_reference_present',
            'successful_supplier_booking_present',
            'passenger_required_fields_complete',
            'document_required_fields_complete',
            'sabre_booking_context_present',
            'safe_refresh_context_present',
            'public_checkout_pnr_enabled',
            'operational_auto_pnr_enabled',
        ];

        foreach ($keys as $key) {
            $this->printKeyValue($key, $evaluation[$key] ?? null);
        }

        $blocking = is_array($evaluation['blocking_conditions'] ?? null)
            ? array_values($evaluation['blocking_conditions'])
            : [];
        $this->line('blocking_conditions='.json_encode($blocking, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
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
