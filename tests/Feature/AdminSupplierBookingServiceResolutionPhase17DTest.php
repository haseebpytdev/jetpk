<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Frontend\MobileViewController;
use App\Models\Agency;
use App\Models\Booking;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Suppliers\SupplierBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminSupplierBookingServiceResolutionPhase17DTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_supplier_booking_service_resolves_from_container(): void
    {
        $this->assertInstanceOf(SupplierBookingService::class, app(SupplierBookingService::class));
        $this->assertInstanceOf(BookingProviderRouter::class, app(BookingProviderRouter::class));
    }

    public function test_admin_booking_management_controller_resolves_without_binding_failure(): void
    {
        $this->assertInstanceOf(BookingManagementController::class, app(BookingManagementController::class));
    }

    public function test_platform_admin_get_admin_bookings_returns_200(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings'))
            ->assertOk()
            ->assertSee('Bookings', false);
    }

    public function test_platform_admin_booking_show_returns_200(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create(['agency_id' => $agency->id]);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk();
    }

    public function test_admin_booking_controller_does_not_inject_supplier_booking_service_directly(): void
    {
        $ref = new \ReflectionClass(BookingManagementController::class);
        $params = collect($ref->getConstructor()?->getParameters() ?? [])
            ->map(fn (\ReflectionParameter $p) => $p->getName())
            ->all();

        $this->assertNotContains('supplierBookingService', $params);
        $this->assertContains('bookingProviderRouter', $params);
    }
}
