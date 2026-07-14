<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use Illuminate\Console\Command;

/**
 * P3 local/testing: Accept a refreshed Sabre offer after price change (before PNR).
 */
class SabreAcceptRefreshedOfferCommand extends Command
{
    protected $signature = 'sabre:accept-refreshed-offer
                            {--booking= : Booking primary key}
                            {--json : Emit machine-readable line only}';

    protected $description = '[local/testing only] Accept refreshed Sabre offer snapshot after price change.';

    public function handle(): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->emitPayload(['error' => 'environment_not_allowed']);

            return self::FAILURE;
        }

        $bookingId = $this->resolveBookingId();
        if ($bookingId === null) {
            $this->emitPayload(['error' => 'missing_booking_id']);

            return self::FAILURE;
        }

        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            $this->emitPayload(['error' => 'booking_not_found', 'booking_id' => $bookingId]);

            return self::FAILURE;
        }

        $result = SabreOfferRefreshAcceptance::accept($booking, 'cli');
        $this->emitPayload(array_merge(['booking_id' => $booking->id], $result));

        return ($result['success'] ?? false) === true ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveBookingId(): ?int
    {
        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitPayload(array $payload): void
    {
        $this->line('accept_refreshed_offer_json='.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
