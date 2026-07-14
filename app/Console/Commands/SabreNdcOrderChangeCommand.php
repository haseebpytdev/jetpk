<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOrderChangeService;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;

class SabreNdcOrderChangeCommand extends Command
{
    protected $signature = 'sabre:ndc-order-change
                            {--booking= : Booking ID}
                            {--dry-run : Preview only (default)}
                            {--send : Live order change HTTP}
                            {--confirm= : CHANGE-NDC-ORDER-FOR-BOOKING-{id}}';

    protected $description = 'Sabre NDC order change — dry-run default; live requires --send + env + --confirm';

    public function handle(SabreNdcOrderChangeService $service): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        $expected = 'CHANGE-NDC-ORDER-FOR-BOOKING-'.$booking->id;
        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            $expected,
            ['suppliers.sabre.ndc.enabled', 'suppliers.sabre.ndc.order_change_enabled'],
        );

        $result = $service->changeOrder($booking, $connection, [], ! $gate['live_allowed']);
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
