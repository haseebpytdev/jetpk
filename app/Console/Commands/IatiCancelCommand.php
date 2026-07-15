<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\IatiCancelService;
use Illuminate\Console\Command;

class IatiCancelCommand extends Command
{
    protected $signature = 'iati:cancel {booking : OTA booking ID} {--connection=}';

    protected $description = 'Cancel IATI booking according to booking state';

    public function handle(IatiCancelService $cancelService): int
    {
        $booking = Booking::query()->find((int) $this->argument('booking'));
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

        $result = $cancelService->cancelForBooking($booking, $connection, $actor);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
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
