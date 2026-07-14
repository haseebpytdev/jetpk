<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

/**
 * Read-only GDS PNR payload integrity + branded fare context summary (no live HTTP, no DB mutation).
 * Local/testing: runs without confirm. Production: requires --confirm=READONLY-GDS-PNR-PAYLOAD-INTEGRITY.
 */
class SabreInspectGdsPnrPayloadIntegrityCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-GDS-PNR-PAYLOAD-INTEGRITY';

    protected $signature = 'sabre:inspect-gds-pnr-payload-integrity
                            {--booking= : Booking ID}
                            {--confirm= : Production only: READONLY-GDS-PNR-PAYLOAD-INTEGRITY}';

    protected $description = '[read-only] Safe GDS PNR payload integrity + branded fare context (local/testing; production requires --confirm)';

    public function handle(SabreBookingService $sabreBooking): int
    {
        $gate = $this->resolveGate();
        if ($gate === null) {
            return self::FAILURE;
        }

        $bookingId = $this->option('booking');
        if ($bookingId === null || $bookingId === '' || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $this->printReadonlySafetyLines($gate['production_readonly_confirmed']);

        $summary = $sabreBooking->inspectGdsPnrPayloadIntegrityForCommand($booking);
        foreach ($summary as $key => $value) {
            if (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
            } elseif (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif ($value === null) {
                continue;
            } else {
                $this->line($key.'='.$value);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{production_readonly_confirmed: bool}|null
     */
    protected function resolveGate(): ?array
    {
        if (SabreInspectGate::allowed()) {
            return ['production_readonly_confirmed' => false];
        }

        $env = (string) config('app.env', 'production');
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
                'Production requires --confirm='.self::PRODUCTION_READONLY_CONFIRM_PHRASE.' for read-only inspect.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production read-only inspect.');
        }

        return null;
    }

    protected function printReadonlySafetyLines(bool $productionReadonlyConfirmed): void
    {
        $this->line('production_readonly_confirmed='.($productionReadonlyConfirmed ? 'true' : 'false'));
        $this->line('live_supplier_call_attempted=false');
        $this->line('booking_status_updated=false');
        $this->newLine();
    }
}
