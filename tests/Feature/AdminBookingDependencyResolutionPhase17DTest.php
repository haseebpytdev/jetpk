<?php

namespace Tests\Feature;

use App\Http\Controllers\Staff\BookingController;
use App\Services\Suppliers\AirBlue\AirBlueBookingRouterService;
use App\Services\Suppliers\Duffel\DuffelBookingService;
use App\Services\Suppliers\Iati\IatiBookingRouterService;
use App\Services\Suppliers\OneApi\OneApiBookingRouterService;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingRouterService;
use App\Services\Suppliers\SupplierBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBookingDependencyResolutionPhase17DTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_staff_booking_controller_resolves_from_container(): void
    {
        $this->assertInstanceOf(BookingController::class, app(BookingController::class));
    }

    public function test_supplier_router_services_resolve(): void
    {
        $this->assertInstanceOf(SupplierBookingService::class, app(SupplierBookingService::class));
        $this->assertInstanceOf(DuffelBookingService::class, app(DuffelBookingService::class));
        $this->assertInstanceOf(PiaNdcBookingRouterService::class, app(PiaNdcBookingRouterService::class));
        $this->assertInstanceOf(AirBlueBookingRouterService::class, app(AirBlueBookingRouterService::class));
        $this->assertInstanceOf(IatiBookingRouterService::class, app(IatiBookingRouterService::class));
        $this->assertInstanceOf(OneApiBookingRouterService::class, app(OneApiBookingRouterService::class));
    }
}
