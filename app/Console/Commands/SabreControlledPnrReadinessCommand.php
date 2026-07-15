<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledPnrReadiness;
use Illuminate\Console\Command;

/**
 * F9: Read-only controlled Sabre PNR readiness diagnostics (no live Sabre HTTP).
 */
class SabreControlledPnrReadinessCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-READINESS';

    protected $signature = 'sabre:controlled-pnr-readiness
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--json : Emit diagnostic JSON only}
                            {--confirm= : Production only: READONLY-CONTROLLED-PNR-READINESS}';

    protected $description = 'Controlled Sabre PNR readiness diagnostics (read-only; production requires --confirm)';

    public function handle(SabreControlledPnrReadiness $readiness): int
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

        $evaluation = $readiness->evaluate($booking, [
            'context' => 'readiness_command',
        ]);
        $evaluation['live_supplier_call_attempted'] = false;

        if ((bool) $this->option('json')) {
            $this->line(json_encode($evaluation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('live_supplier_call_attempted=false');
        $this->newLine();

        $keys = [
            'eligible',
            'can_attempt_supplier_pnr',
            'live_supplier_call_allowed',
            'reason_code',
            'human_message',
            'booking_reference',
            'booking_id',
            'supplier_connection_present',
            'supplier_connection_id',
            'is_sabre_booking',
            'is_ticketed',
            'is_cancelled',
            'has_existing_pnr',
            'has_required_passengers',
            'has_required_contact',
            'has_pricing_context',
            'has_revalidation_context',
            'has_payment_gate',
            'has_usable_controlled_pnr_context',
            'controlled_pnr_manual_review_approved',
            'admin_pnr_live_action_allowed',
            'pricing_context_ready',
            'controlled_pnr_certification_status',
            'environment_label',
            'payload_preview_available',
            'retrieve_after_create_available',
            'ticketing_disabled',
            'cancellation_disabled',
            'recommended_next_action',
        ];

        foreach ($keys as $key) {
            $this->printKeyValue($key, $evaluation[$key] ?? null);
        }

        $blockers = is_array($evaluation['blockers'] ?? null) ? array_values($evaluation['blockers']) : [];
        $warnings = is_array($evaluation['warnings'] ?? null) ? array_values($evaluation['warnings']) : [];
        $this->line('blockers='.json_encode($blockers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('warnings='.json_encode($warnings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $multiSegmentBlockers = is_array($evaluation['multi_segment_blocker_reasons'] ?? null)
            ? array_values($evaluation['multi_segment_blocker_reasons'])
            : [];
        $this->line('multi_segment_blocker_reasons='.json_encode($multiSegmentBlockers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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
