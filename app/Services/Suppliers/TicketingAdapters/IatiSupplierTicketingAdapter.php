<?php

namespace App\Services\Suppliers\TicketingAdapters;

use App\Contracts\Suppliers\SupplierTicketingInterface;
use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;
use App\Services\Suppliers\Iati\IatiTicketingService;

class IatiSupplierTicketingAdapter implements SupplierTicketingInterface
{
    public function __construct(
        private readonly IatiTicketingService $ticketingService,
    ) {}

    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData
    {
        if ($supplierBooking->provider !== SupplierProvider::Iati->value) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: $supplierBooking->provider,
                error_code: 'supplier_provider_mismatch',
                error_message: 'Supplier provider mismatch for IATI ticketing.',
            );
        }

        return $this->ticketingService->issueTickets($booking, $supplierBooking, $actor);
    }
}
