<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use Illuminate\Console\Command;

class AirBlueTestBookingCommand extends Command
{
    protected $signature = 'airblue:test-booking
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}
        {--dry-run : Build payloads only without live call}';

    protected $description = 'Execute or dry-run AirBlue DoOrderCreate for an OTA booking';

    public function handle(AirBlueBookingService $bookingService): int
    {
        if ($this->option('dry-run')) {
            $this->warn('Dry-run: payload validation only — live booking not implemented in CLI dry-run.');

            return self::SUCCESS;
        }

        $booking = Booking::query()->find((int) $this->argument('booking'));
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('AirBlue connection not found.');

            return self::FAILURE;
        }

        $actor = User::query()->first();
        if ($actor === null) {
            $this->error('No user available for booking actor.');

            return self::FAILURE;
        }

        $result = $bookingService->createSupplierBooking($booking, $connection, $actor);
        $this->line('success='.($result->success ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('pnr='.($result->pnr ?? ''));
        $this->line('reference='.($result->supplier_reference ?? ''));

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::Airblue)->first();
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = (int) ($meta['supplier_connection_id'] ?? 0);

        return $cid > 0
            ? SupplierConnection::query()->where('id', $cid)->where('provider', SupplierProvider::Airblue)->first()
            : SupplierConnection::query()->where('provider', SupplierProvider::Airblue)->orderByDesc('is_active')->first();
    }
}
