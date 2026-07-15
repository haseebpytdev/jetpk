<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcRetrieveService;
use Illuminate\Console\Command;

class PiaNdcRetrieveCommand extends Command
{
    protected $signature = 'pia-ndc:retrieve
        {--booking= : OTA booking ID}
        {--order= : PIA NDC order ID}
        {--owner= : Owner code (e.g. PK)}
        {--connection= : Supplier connection ID}';

    protected $description = 'Retrieve and sync PIA NDC order by OTA booking ID';

    public function handle(PiaNdcRetrieveService $retrieveService): int
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
            $this->error('PIA NDC connection not found.');

            return self::FAILURE;
        }

        $order = $this->option('order');
        $owner = $this->option('owner');
        if ($order || $owner) {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
            if ($order) {
                $context['order_id'] = (string) $order;
            }
            if ($owner) {
                $context['owner_code'] = (string) $owner;
            }
            $meta['pia_ndc_context'] = $context;
            $booking->update(['meta' => $meta]);
        }

        $synced = $retrieveService->retrieveAndSync($booking, $connection);
        $this->line(json_encode($synced, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($synced['synced'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::PiaNdc)->first();
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];

        return SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0))
            ?? SupplierConnection::query()->where('provider', SupplierProvider::PiaNdc)->orderByDesc('is_active')->first();
    }
}
