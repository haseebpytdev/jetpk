<?php

namespace App\Services\Suppliers\BookingAdapters;

use App\Contracts\Suppliers\SupplierBookingInterface;
use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class AirBlueSupplierBookingAdapter implements SupplierBookingInterface
{
    public function __construct(
        private readonly AirBlueBookingService $bookingService,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        if ($connection->provider !== SupplierProvider::Airblue) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'failed',
                safeMessage: 'Supplier provider mismatch for AirBlue booking.',
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $connection->provider->value,
                error_code: 'supplier_provider_mismatch',
                error_message: 'Supplier provider mismatch for AirBlue booking.',
            );
        }

        return $this->bookingService->createSupplierBooking($booking, $connection, $actor);
    }
}
