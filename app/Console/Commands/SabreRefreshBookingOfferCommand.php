<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingOfferRefreshService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

/**
 * C3 local/testing-only: re-shop stored itinerary and optionally refresh offer snapshot (no PNR/payment).
 */
class SabreRefreshBookingOfferCommand extends Command
{
    protected $signature = 'sabre:refresh-booking-offer
                            {--booking= : Booking primary key}
                            {--apply : Persist refreshed offer snapshot when match is high-confidence}
                            {--json : Emit machine-readable line only}';

    protected $description = '[local/testing only] Re-shop booking itinerary and refresh offer snapshot (dry-run by default).';

    public function handle(SabreBookingOfferRefreshService $refreshService): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->emitPayload(['error' => 'environment_not_allowed', 'booking_id' => $this->resolveBookingId()]);

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

        $payload = $refreshService->refresh($booking, (bool) $this->option('apply'));
        $this->emitPayload($payload);

        if (isset($payload['error'])) {
            return self::FAILURE;
        }

        return self::SUCCESS;
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
        $this->line('refresh_offer_json='.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
