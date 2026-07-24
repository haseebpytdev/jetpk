<?php

namespace Tests\Unit;

use App\Services\Booking\BookingProviderRouter;
use App\Services\Suppliers\SupplierBookingService;
use ReflectionClass;
use Tests\TestCase;

class SupplierBookingServiceCompatibilityPhase17DTest extends TestCase
{
    public function test_supplier_booking_service_is_concrete_orchestrator_not_interface_stub(): void
    {
        $ref = new ReflectionClass(SupplierBookingService::class);
        $this->assertTrue($ref->hasMethod('createSupplierBooking'));
        $this->assertTrue($ref->hasMethod('isBookingEligible'));
        $this->assertTrue($ref->hasMethod('markManualPnr'));
        $this->assertFalse($ref->isAbstract());
    }

    public function test_booking_provider_router_depends_on_supplier_booking_service_without_reverse_injection(): void
    {
        $routerRef = new ReflectionClass(BookingProviderRouter::class);
        $serviceRef = new ReflectionClass(SupplierBookingService::class);

        $routerCtor = $routerRef->getConstructor();
        $paramTypes = collect($routerCtor?->getParameters() ?? [])
            ->map(fn ($p) => $p->getType()?->getName())
            ->filter()
            ->all();

        $this->assertContains(SupplierBookingService::class, $paramTypes);

        $serviceCtor = $serviceRef->getConstructor();
        $serviceDeps = collect($serviceCtor?->getParameters() ?? [])
            ->map(fn ($p) => $p->getType()?->getName())
            ->filter()
            ->all();

        $this->assertNotContains(BookingProviderRouter::class, $serviceDeps);
    }
}
