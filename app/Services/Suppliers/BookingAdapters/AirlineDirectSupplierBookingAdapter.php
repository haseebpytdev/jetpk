<?php

namespace App\Services\Suppliers\BookingAdapters;

use App\Contracts\Suppliers\SupplierBookingInterface;
use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;

class AirlineDirectSupplierBookingAdapter implements SupplierBookingInterface
{
    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        return new SupplierBookingResultData(
            success: false,
            status: 'not_supported',
            provider: $connection->provider->value,
            warnings: ['PNR creation for this provider requires API documentation review.'],
        );
    }
}
