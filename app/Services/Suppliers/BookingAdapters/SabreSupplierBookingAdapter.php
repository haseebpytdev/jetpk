<?php

namespace App\Services\Suppliers\BookingAdapters;

use App\Contracts\Suppliers\SupplierBookingInterface;
use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;

class SabreSupplierBookingAdapter implements SupplierBookingInterface
{
    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        return new SupplierBookingResultData(
            success: false,
            status: 'not_supported',
            provider: $connection->provider->value,
            safe_summary: ['reason' => 'api_docs_required'],
            warnings: ['Sabre PNR creation is not implemented until supplier booking API documentation is reviewed.'],
        );
    }
}
