<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\IatiRetrieveService;
use Illuminate\Console\Command;

class IatiRetrieveCommand extends Command
{
    protected $signature = 'iati:retrieve
        {--booking= : OTA booking ID}
        {--order= : IATI order ID}
        {--connection= : Supplier connection ID}';

    protected $description = 'Retrieve and sync IATI booking by OTA booking ID';

    public function handle(IatiRetrieveService $retrieveService): int
    {
        $bookingId = $this->option('booking');
        if (! $bookingId) {
            $this->error('Provide --booking=ID');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('IATI connection not found.');

            return self::FAILURE;
        }

        $actor = User::query()->first();
        if ($actor === null) {
            $this->error('No user available.');

            return self::FAILURE;
        }

        $order = $this->option('order');
        if ($order) {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $meta['iati_context'] = array_merge(is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [], [
                'order_id' => (string) $order,
            ]);
            $booking->update(['meta' => $meta]);
        }

        $synced = $retrieveService->syncBooking($booking, $connection, $actor);
        $this->line(json_encode($synced, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::Iati)->first();
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];

        return SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0))
            ?? SupplierConnection::query()->where('provider', SupplierProvider::Iati)->orderByDesc('is_active')->first();
    }
}
