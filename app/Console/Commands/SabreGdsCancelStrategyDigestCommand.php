<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Sabre\Cancel\SabreGdsCancelStrategyDigest;
use Illuminate\Console\Command;

/**
 * Read-only Sabre GDS unticketed cancel strategy digest.
 */
class SabreGdsCancelStrategyDigestCommand extends Command
{
    public const CONFIRM_PHRASE = 'READONLY-SABRE-GDS-CANCEL-STRATEGY-DIGEST';

    protected $signature = 'sabre:gds-cancel-strategy-digest
                            {--booking= : Booking ID}
                            {--confirm= : Production: READONLY-SABRE-GDS-CANCEL-STRATEGY-DIGEST}';

    protected $description = '[read-only] Safe Sabre GDS unticketed cancel strategy digest';

    public function handle(SabreGdsCancelStrategyDigest $digest): int
    {
        if (! SabreInspectGate::allowed() && (string) config('app.env', 'production') === 'production') {
            $confirm = trim((string) $this->option('confirm'));
            if ($confirm !== self::CONFIRM_PHRASE) {
                $this->components->error('--confirm='.self::CONFIRM_PHRASE.' required on production.');

                return self::FAILURE;
            }
        }

        $bookingId = $this->option('booking');
        if ($bookingId === null || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id}.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        foreach ($digest->buildBookingSummary($booking) as $key => $value) {
            $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
        }
        $this->newLine();

        foreach ($digest->buildCandidateDigests($booking) as $index => $candidate) {
            $this->line('candidate['.$index.']');
            foreach ($candidate as $key => $value) {
                $this->line('  '.$key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
            }
        }

        return self::SUCCESS;
    }
}
