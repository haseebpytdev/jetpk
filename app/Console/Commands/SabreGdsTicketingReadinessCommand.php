<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use Illuminate\Console\Command;

class SabreGdsTicketingReadinessCommand extends Command
{
    protected $signature = 'sabre:gds-ticketing-readiness
                            {--booking= : Booking ID}
                            {--json : Emit JSON output}';

    protected $description = 'Sabre GDS ticketing readiness and blockers (no supplier HTTP)';

    public function handle(SabreGdsTicketingReadiness $readiness): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $report = $readiness->evaluate($booking, ['dry_run' => true]);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($report as $key => $value) {
            if (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
            } else {
                $this->line($key.'='.(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
            }
        }

        return self::SUCCESS;
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Booking::query()->with(['tickets', 'latestTicketingAttempt', 'passengers', 'fareBreakdown'])->find((int) $id);
    }
}
