<?php

namespace App\Services\Suppliers\TicketingAdapters;

use App\Contracts\Suppliers\SupplierTicketingInterface;
use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\PiaNdcTicketingService;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class PiaNdcSupplierTicketingAdapter implements SupplierTicketingInterface
{
    public function __construct(
        private readonly PiaNdcTicketingService $ticketingService,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData
    {
        if ($supplierBooking->provider !== SupplierProvider::PiaNdc->value) {
            $this->diagnosticLogger->log(
                connection: $supplierBooking->supplierConnection,
                action: 'ticketing',
                status: 'failed',
                safeMessage: 'Supplier provider mismatch for PIA NDC ticketing.',
            );

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: $supplierBooking->provider,
                error_code: 'supplier_provider_mismatch',
                error_message: 'Ticketing failed, admin review required.',
            );
        }

        $connection = $supplierBooking->supplierConnection;
        if ($connection === null) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::PiaNdc->value,
                error_code: 'missing_connection',
                error_message: 'Ticketing failed, admin review required.',
            );
        }

        return $this->ticketingService->issueTickets($booking, $connection, $actor);
    }
}
