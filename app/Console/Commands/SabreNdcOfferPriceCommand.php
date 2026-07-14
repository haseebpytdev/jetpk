<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferPriceService;
use Illuminate\Console\Command;

class SabreNdcOfferPriceCommand extends Command
{
    protected $signature = 'sabre:ndc-offer-price
                            {--booking= : Booking ID}
                            {--offer= : Offer ID override}
                            {--dry-run : Preview only}';

    protected $description = 'Sabre NDC offer price readiness (dry-run default)';

    public function handle(SabreNdcOfferPriceService $service): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $offer = $this->option('offer');
        $context = is_string($offer) && $offer !== '' ? ['offer_id' => $offer] : [];
        $result = $service->preview($booking, $this->resolveConnection($booking), $context, true);

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Booking::query()->find((int) $id);
    }

    private function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId > 0) {
            return SupplierConnection::query()->find($connectionId);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->orderByDesc('is_active')->first();
    }
}
