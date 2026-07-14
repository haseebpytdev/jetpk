<?php

namespace App\Services\Suppliers\TicketingAdapters;

use App\Contracts\Suppliers\SupplierTicketingInterface;
use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;
use App\Services\Suppliers\Duffel\DuffelTicketingService;

class DuffelSupplierTicketingAdapter implements SupplierTicketingInterface
{
    public function __construct(
        private readonly DuffelTicketingService $ticketingService,
    ) {}

    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData
    {
        if ($supplierBooking->provider !== SupplierProvider::Duffel->value) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: $supplierBooking->provider,
                error_code: 'supplier_provider_mismatch',
                error_message: 'Supplier provider mismatch for Duffel ticketing.',
            );
        }

        $connection = $supplierBooking->supplierConnection;
        if ($connection === null) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Duffel->value,
                error_code: 'missing_connection',
                error_message: 'Supplier connection is required for Duffel ticketing.',
            );
        }

        return $this->ticketingService->issueTickets($booking, $connection, $actor);
    }
}
