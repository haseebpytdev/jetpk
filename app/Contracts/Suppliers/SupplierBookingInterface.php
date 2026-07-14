<?php

namespace App\Contracts\Suppliers;

use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;

interface SupplierBookingInterface
{
    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData;
}
