<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\User;
use App\Services\Suppliers\SupplierBookingService;

class PiaNdcBookingRouterService
{
    public function __construct(
        private readonly SupplierBookingService $supplierBookingService,
    ) {}

    public function createSupplierBooking(
        Booking $booking,
        User $actor,
        bool $adminOverride = false,
        bool $explicitRetry = false,
        string $attemptSource = 'system',
    ): SupplierBookingResultData {
        return $this->supplierBookingService->createSupplierBooking(
            $booking,
            $actor,
            $adminOverride,
            $explicitRetry,
            $attemptSource,
        );
    }
}
