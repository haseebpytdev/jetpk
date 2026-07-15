<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcRepriceOrderService;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;

class SabreNdcRepriceOrderCommand extends Command
{
    protected $signature = 'sabre:ndc-reprice-order
                            {--booking= : Booking ID}
                            {--dry-run : Preview only (default)}
                            {--send : Live repriceOrder HTTP}
                            {--confirm= : REPRICE-NDC-ORDER-FOR-BOOKING-{id}}';

    protected $description = 'Sabre NDC repriceOrder — dry-run default; live requires --send + env + --confirm';

    public function handle(SabreNdcRepriceOrderService $service): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('Sabre connection not found.');

            return self::FAILURE;
        }

        $expected = 'REPRICE-NDC-ORDER-FOR-BOOKING-'.$booking->id;
        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            $expected,
            ['suppliers.sabre.ndc.enabled', 'suppliers.sabre.ndc.reprice_order_enabled'],
        );

        $result = $service->repriceOrder($booking, $connection, ! $gate['live_allowed']);
        $result['gate'] = $gate;
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($result['success'] ?? false) || ($gate['dry_run'] ?? true) ? self::SUCCESS : self::FAILURE;
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');

        return is_numeric($id) ? Booking::query()->find((int) $id) : null;
    }

    private function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $id = (int) ($meta['supplier_connection_id'] ?? 0);

        return $id > 0
            ? SupplierConnection::query()->find($id)
            : SupplierConnection::query()->where('provider', 'sabre')->where('is_active', true)->orderBy('id')->first();
    }
}
