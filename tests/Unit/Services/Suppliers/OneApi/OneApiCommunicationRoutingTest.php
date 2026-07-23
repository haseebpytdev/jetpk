<?php

namespace Tests\Unit\Services\Suppliers\OneApi;

use App\Services\Communication\BookingCommunicationService;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\SupplierBookingService;
use Tests\TestCase;

/**
 * Architectural evidence: One API must not send mail from adapter layer.
 */
class OneApiCommunicationRoutingTest extends TestCase
{
    public function test_one_api_booking_service_does_not_reference_communication_service(): void
    {
        $ref = new \ReflectionClass(OneApiBookingService::class);
        $this->assertFalse(
            str_contains((string) file_get_contents($ref->getFileName()), 'BookingCommunicationService'),
            'OneApiBookingService must not invoke BookingCommunicationService directly.',
        );
    }

    public function test_supplier_booking_service_invokes_communication_on_success_path(): void
    {
        $ref = new \ReflectionClass(SupplierBookingService::class);
        $source = (string) file_get_contents($ref->getFileName());
        $this->assertStringContainsString('sendSupplierBookingCreated', $source);
        $this->assertStringContainsString(BookingCommunicationService::class, $source);
    }
}
