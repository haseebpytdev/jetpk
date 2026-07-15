<?php

namespace App\Services\Suppliers\Duffel;

use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Suppliers\SupplierBookingService;

/**
 * Thin adapter so {@see BookingProviderRouter} delegates Duffel PNR creation
 * explicitly to the existing Duffel implementation in {@see SupplierBookingService}.
 */
class DuffelBookingService
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
