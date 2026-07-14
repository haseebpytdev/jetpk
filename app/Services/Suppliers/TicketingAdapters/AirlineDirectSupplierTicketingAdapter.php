<?php

namespace App\Services\Suppliers\TicketingAdapters;

use App\Contracts\Suppliers\SupplierTicketingInterface;
use App\Data\TicketingResultData;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;

class AirlineDirectSupplierTicketingAdapter implements SupplierTicketingInterface
{
    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData
    {
        return new TicketingResultData(
            success: false,
            status: 'not_supported',
            provider: $supplierBooking->provider,
            warnings: ['Airline direct ticketing is not implemented until ticketing API documentation is reviewed.'],
        );
    }
}
