<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueTicketingException;
use Illuminate\Console\Command;

class AirBlueTestTicketingCommand extends Command
{
    protected $signature = 'airblue:test-ticketing
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}';

    protected $description = 'Issue AirBlue tickets (DoOrderChange) for a booking';

    public function handle(AirBlueTicketingService $ticketingService): int
    {
        $booking = Booking::query()->with('latestSupplierBooking.supplierConnection')->find((int) $this->argument('booking'));
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
            $this->error('No user available.');

            return self::FAILURE;
        }

        try {
            $result = $ticketingService->issueTickets($booking, $connection, $actor);
            $this->line('success='.($result->success ? 'true' : 'false'));
            $this->line('status='.$result->status);
            $this->line('tickets='.json_encode($result->tickets));

            return $result->success ? self::SUCCESS : self::FAILURE;
        } catch (AirBlueTicketingException $exception) {
            $this->error('ticketing_failed='.$exception->safeMessage);

            return self::FAILURE;
        }
    }

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::Airblue)->first();
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $supplierBooking = $booking->latestSupplierBooking;
        if ($supplierBooking?->supplier_connection_id) {
            return SupplierConnection::query()
                ->where('id', $supplierBooking->supplier_connection_id)
                ->where('provider', SupplierProvider::Airblue)
                ->first();
        }

        return SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0))
            ?? SupplierConnection::query()->where('provider', SupplierProvider::Airblue)->orderByDesc('is_active')->first();
    }
}
