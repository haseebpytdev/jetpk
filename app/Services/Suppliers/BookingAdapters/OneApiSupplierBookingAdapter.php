<?php

namespace App\Services\Suppliers\BookingAdapters;

use App\Contracts\Suppliers\SupplierBookingInterface;
use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;

class OneApiSupplierBookingAdapter implements SupplierBookingInterface
{
    public function __construct(
        private readonly OneApiBookingService $bookingService,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        if ($connection->provider !== SupplierProvider::OneApi) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $connection->provider->value,
                error_code: 'supplier_provider_mismatch',
                error_message: 'Supplier provider mismatch for One API booking.',
            );
        }

        $diagnostic = [];
        if (app()->runningUnitTests()) {
            $fixtures = data_get($booking->meta, 'one_api_test_fixtures');
            if (is_array($fixtures)) {
                $diagnostic = $fixtures;
            }
        }

        return $this->bookingService->createSupplierBooking($booking, $connection, $actor, $diagnostic);
    }
}
