<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueVoidTicketService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;
use Illuminate\Console\Command;

class AirBlueVoidTicketCommand extends Command
{
    protected $signature = 'airblue:void-ticket
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}';

    protected $description = 'Run AirBlue DoVoidTicket for an OTA booking';

    public function handle(AirBlueVoidTicketService $voidTicketService): int
    {
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

        try {
            $result = $voidTicketService->voidTicket($booking, $connection);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($result['void_status'] ?? '') === 'voided' ? self::SUCCESS : self::FAILURE;
        } catch (AirBlueCancellationException $exception) {
            $this->error('void_ticket_failed='.$exception->safeMessage);

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

        return SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0))
            ?? SupplierConnection::query()->where('provider', SupplierProvider::Airblue)->orderByDesc('is_active')->first();
    }
}
