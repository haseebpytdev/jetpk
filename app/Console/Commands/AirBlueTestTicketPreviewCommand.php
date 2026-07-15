<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueTicketPreviewService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueTicketingException;
use Illuminate\Console\Command;

class AirBlueTestTicketPreviewCommand extends Command
{
    protected $signature = 'airblue:test-ticket-preview
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}';

    protected $description = 'Run AirBlue DoTicketPreview for an OTA booking';

    public function handle(AirBlueTicketPreviewService $ticketPreviewService): int
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
            $preview = $ticketPreviewService->preview($booking, $connection);
            $this->line('amount='.($preview['amount'] ?? ''));
            $this->line('currency='.($preview['currency'] ?? ''));

            return self::SUCCESS;
        } catch (AirBlueTicketingException $exception) {
            $this->error('preview_failed='.$exception->safeMessage);

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
