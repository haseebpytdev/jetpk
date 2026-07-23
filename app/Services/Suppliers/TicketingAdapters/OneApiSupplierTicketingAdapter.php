<?php

namespace App\Services\Suppliers\TicketingAdapters;

use App\Contracts\Suppliers\SupplierTicketingInterface;
use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;

/**
 * One API issues e-tickets on paid book; standalone ticketing is not used.
 */
class OneApiSupplierTicketingAdapter implements SupplierTicketingInterface
{
    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData
    {
        unset($actor);

        if ($supplierBooking->provider !== SupplierProvider::OneApi->value) {
            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::OneApi->value,
                error_message: 'Provider mismatch for One API ticketing.',
            );
        }

        if ($booking->tickets()->exists()) {
            return new TicketingResultData(
                success: true,
                status: 'already_ticketed',
                provider: SupplierProvider::OneApi->value,
            );
        }

        return new TicketingResultData(
            success: true,
            status: 'not_required',
            provider: SupplierProvider::OneApi->value,
            warnings: ['One API ticketing is fulfilled at booking time when e-tickets are returned.'],
        );
    }
}
