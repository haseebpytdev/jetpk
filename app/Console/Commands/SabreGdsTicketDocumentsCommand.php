<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketDocumentService;
use Illuminate\Console\Command;

class SabreGdsTicketDocumentsCommand extends Command
{
    protected $signature = 'sabre:gds-ticket-documents
                            {--booking= : Booking ID}
                            {--dry-run : Readiness/cached only (default)}
                            {--send : Live getBooking retrieve when env gate on}';

    protected $description = 'Sabre GDS ticket document lookup (safe metadata only)';

    public function handle(SabreGdsTicketDocumentService $documentService): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('Sabre supplier connection not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run') || ($this->option('send') === null && ! (bool) config('suppliers.sabre.ticket_documents_live_retrieve_enabled', false));
        if ($this->option('send') !== null) {
            $dryRun = (bool) $this->option('dry-run') || ! (bool) config('suppliers.sabre.ticket_documents_live_retrieve_enabled', false);
        }
        $result = $documentService->retrieve($booking, $connection, $dryRun);

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return ($result['blockers'] ?? []) === [] || $dryRun ? self::SUCCESS : self::FAILURE;
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

        return SupplierConnection::query()->where('provider', 'sabre')->where('is_active', true)->orderBy('id')->first();
    }
}
