<?php

namespace Tests\Feature\Booking;

use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Staff\BookingController;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BookingProviderRouterControllerIntegrationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Http::fake();
    }

    public function test_admin_controller_injects_booking_provider_router_not_supplier_service(): void
    {
        $params = collect((new \ReflectionClass(BookingManagementController::class))
            ->getConstructor()?->getParameters() ?? [])
            ->map(fn (\ReflectionParameter $p) => $p->getName())
            ->all();

        $this->assertContains('bookingProviderRouter', $params);
        $this->assertNotContains('supplierBookingService', $params);
    }

    public function test_staff_controller_injects_booking_provider_router_not_supplier_service(): void
    {
        $params = collect((new \ReflectionClass(BookingController::class))
            ->getConstructor()?->getParameters() ?? [])
            ->map(fn (\ReflectionParameter $p) => $p->getName())
            ->all();

        $this->assertContains('bookingProviderRouter', $params);
        $this->assertNotContains('supplierBookingService', $params);
    }

    public function test_admin_create_supplier_booking_post_delegates_to_router_without_outbound_http(): void
    {
        $booking = $this->eligibleBooking();
        $admin = $this->platformAdmin();

        $bookingId = $booking->id;
        $adminId = $admin->id;

        $this->mock(BookingProviderRouter::class, function ($mock) use ($bookingId, $adminId): void {
            $mock->shouldReceive('isBookingEligible')
                ->once()
                ->with(\Mockery::on(fn ($b) => (int) $b->id === (int) $bookingId))
                ->andReturn(true);
            $mock->shouldReceive('createSupplierBooking')
                ->once()
                ->with(
                    \Mockery::on(fn ($b) => (int) $b->id === (int) $bookingId),
                    \Mockery::on(fn ($u) => (int) $u->id === (int) $adminId),
                    false,
                    true,
                )
                ->andReturn(new SupplierBookingResultData(
                    success: true,
                    status: 'created',
                    provider: SupplierProvider::Duffel->value,
                    supplier_reference: 'router-delegate',
                    pnr: 'RTR001',
                ));
        });

        $this->actingAs($admin)
            ->post(route('admin.bookings.supplier-booking', $booking))
            ->assertRedirect()
            ->assertSessionHas('status', 'supplier-booking-created');

        Http::assertNothingSent();
    }

    public function test_admin_mark_manual_pnr_post_delegates_to_router_without_outbound_http(): void
    {
        $booking = $this->eligibleBooking();
        $admin = $this->platformAdmin();

        $bookingId = $booking->id;
        $adminId = $admin->id;

        $this->mock(BookingProviderRouter::class, function ($mock) use ($bookingId, $adminId): void {
            $mock->shouldReceive('markManualPnr')
                ->once()
                ->with(
                    \Mockery::on(fn ($b) => (int) $b->id === (int) $bookingId),
                    \Mockery::on(fn ($u) => (int) $u->id === (int) $adminId),
                    'ADMNP1',
                    'REF-ADM',
                    'ops note',
                );
        });

        $this->actingAs($admin)
            ->post(route('admin.bookings.manual-pnr', $booking), [
                'pnr' => 'ADMNP1',
                'supplier_reference' => 'REF-ADM',
                'note' => 'ops note',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'manual-pnr-marked');

        Http::assertNothingSent();
    }

    public function test_staff_create_supplier_booking_post_delegates_to_router_without_outbound_http(): void
    {
        $booking = $this->eligibleBooking();
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView, StaffPermission::BookingsUpdateStatus]);

        $bookingId = $booking->id;
        $staffId = $staff->id;

        $this->mock(BookingProviderRouter::class, function ($mock) use ($bookingId, $staffId): void {
            $mock->shouldReceive('isBookingEligible')
                ->once()
                ->with(\Mockery::on(fn ($b) => (int) $b->id === (int) $bookingId))
                ->andReturn(true);
            $mock->shouldReceive('createSupplierBooking')
                ->once()
                ->with(
                    \Mockery::on(fn ($b) => (int) $b->id === (int) $bookingId),
                    \Mockery::on(fn ($u) => (int) $u->id === (int) $staffId),
                    false,
                    true,
                )
                ->andReturn(new SupplierBookingResultData(
                    success: true,
                    status: 'created',
                    provider: SupplierProvider::Duffel->value,
                    supplier_reference: 'staff-router',
                    pnr: 'STF001',
                ));
        });

        $this->actingAs($staff)
            ->post(route('staff.bookings.supplier-booking', $booking))
            ->assertRedirect()
            ->assertSessionHas('status', 'supplier-booking-created');

        Http::assertNothingSent();
    }

    public function test_staff_mark_manual_pnr_post_delegates_to_router_without_outbound_http(): void
    {
        $booking = $this->eligibleBooking();
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView, StaffPermission::BookingsUpdateStatus]);

        $bookingId = $booking->id;
        $staffId = $staff->id;

        $this->mock(BookingProviderRouter::class, function ($mock) use ($bookingId, $staffId): void {
            $mock->shouldReceive('markManualPnr')
                ->once()
                ->with(
                    \Mockery::on(fn ($b) => (int) $b->id === (int) $bookingId),
                    \Mockery::on(fn ($u) => (int) $u->id === (int) $staffId),
                    'STFPNR',
                    'REF-STF',
                    'staff note',
                );
        });

        $this->actingAs($staff)
            ->post(route('staff.bookings.manual-pnr', $booking), [
                'pnr' => 'STFPNR',
                'supplier_reference' => 'REF-STF',
                'note' => 'staff note',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'manual-pnr-marked');

        Http::assertNothingSent();
    }

    public function test_booking_provider_router_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.bookings.supplier-booking'));
        $this->assertTrue(Route::has('admin.bookings.manual-pnr'));
        $this->assertTrue(Route::has('staff.bookings.supplier-booking'));
        $this->assertTrue(Route::has('staff.bookings.manual-pnr'));
    }

    public function test_controllers_resolve_from_container(): void
    {
        $this->assertInstanceOf(BookingManagementController::class, app(BookingManagementController::class));
        $this->assertInstanceOf(BookingController::class, app(BookingController::class));
    }

    protected function eligibleBooking(): Booking
    {
        $admin = $this->platformAdmin();
        $agencyId = (int) $admin->current_agency_id;
        $connection = SupplierConnection::query()
            ->where('agency_id', $agencyId)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        return Booking::factory()->create([
            'agency_id' => $agencyId,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => ['offer_id' => 'router-ctrl-test'],
            ],
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffWithPermissions(array $permissions): User
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['meta' => ['staff_permissions' => $permissions]])->save();

        return $staff->fresh();
    }
}
