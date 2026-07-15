<?php

namespace App\Contracts\Suppliers;

use App\Data\TicketingResultData;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\User;

interface SupplierTicketingInterface
{
    public function issueTickets(Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData;
}
