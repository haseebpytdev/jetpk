<?php

namespace App\Services\Suppliers\Iati;

use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\User;
use App\Services\Suppliers\SupplierBookingService;

/**
 * Router wrapper so BookingProviderRouter delegates IATI explicitly.
 */
class IatiBookingRouterService
{
    public function __construct(
        protected SupplierBookingService $supplierBookingService,
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
