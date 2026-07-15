<?php

namespace Tests\Feature\Platform;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Exceptions\PlatformModuleDisabledException;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\TicketingAttempt;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use App\Services\Suppliers\TicketingAdapters\PiaNdcSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModuleSupplierTicketingHardStopTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
        Config::set('ota-developer.enabled', true);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_supplier_booking_off_blocks_booking_provider_router_without_adapter_call(): void
    {
        Http::fake();
        $this->planModuleOff('supplier_booking');
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();
        $attemptsBefore = SupplierBookingAttempt::query()->count();

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
        $this->assertSame(PlatformModuleDisabledException::PUBLIC_MESSAGE, $result->error_message);
        $this->assertSame($attemptsBefore + 1, SupplierBookingAttempt::query()->count());
        $this->assertDatabaseMissing('supplier_bookings', ['booking_id' => $booking->id]);
        Http::assertNothingSent();
    }

    public function test_duffel_supplier_off_blocks_supplier_booking_for_duffel_provider(): void
    {
        $this->planModuleOff('duffel_supplier');
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
        $this->assertDatabaseMissing('supplier_bookings', ['booking_id' => $booking->id]);
    }

    public function test_supplier_booking_off_blocks_admin_create_supplier_booking_post(): void
    {
        $this->planModuleOff('supplier_booking');
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->eligibleDuffelBooking();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.bookings.supplier-booking', $booking))
            ->assertForbidden();

        $this->assertDatabaseMissing('supplier_bookings', ['booking_id' => $booking->id]);
    }

    public function test_ticketing_off_blocks_issue_ticket_service_call(): void
    {
        $this->planModuleOff('ticketing');
        $booking = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $admin = $this->platformAdmin();

        $this->mock(PiaNdcSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->never();
        });

        $result = app(TicketingService::class)->issueTickets($booking->fresh(['latestSupplierBooking']), $admin);

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
        $this->assertSame('Ticketing is disabled for this deployment.', $result->error_message);
        $this->assertSame(0, TicketingAttempt::query()->where('booking_id', $booking->id)->count());
    }

    public function test_supplier_booking_off_also_prevents_ticketing(): void
    {
        $this->planModuleOff('supplier_booking');
        $booking = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $admin = $this->platformAdmin();

        $result = app(TicketingService::class)->issueTickets($booking->fresh(['latestSupplierBooking']), $admin);

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
    }

    public function test_sabre_ticketing_env_false_blocks_even_when_ticketing_module_enabled(): void
    {
        Config::set('suppliers.sabre.ticketing_enabled', false);
        $booking = $this->eligibleSabreBooking(withSupplierBooking: true);
        $admin = $this->platformAdmin();

        $result = app(TicketingService::class)->issueTickets($booking->fresh(['latestSupplierBooking']), $admin);

        $this->assertFalse($result->success);
        $this->assertSame('ticketing_disabled_by_config', $result->error_code);
    }

    public function test_read_only_admin_booking_show_still_works_when_supplier_modules_off(): void
    {
        foreach (['supplier_booking', 'ticketing', 'duffel_supplier'] as $key) {
            $this->planModuleOff($key);
        }

        $booking = $this->eligibleDuffelBooking(withSupplierBooking: true);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk();
    }

    public function test_developer_cp_remains_accessible_when_supplier_ticketing_modules_off(): void
    {
        foreach (['supplier_booking', 'ticketing', 'sabre_gds', 'duffel_supplier'] as $key) {
            $this->planModuleOff($key);
        }

        $developer = DeveloperUser::query()->create([
            'name' => 'Dev 8O',
            'email' => 'dev-8o@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk();
    }

    public function test_admin_platform_modules_route_remains_404(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/admin/platform/modules')
            ->assertNotFound();
    }

    public function test_staff_with_bookings_view_only_cannot_create_supplier_booking(): void
    {
        $booking = $this->eligibleDuffelBooking();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::BookingsView]],
        ])->save();

        $this->actingAs($staff->fresh())
            ->post(route('staff.bookings.supplier-booking', $booking))
            ->assertForbidden();
    }

    public function test_staff_with_bookings_update_status_can_pass_authorization_for_supplier_booking(): void
    {
        $this->planModuleOff('supplier_booking');

        $booking = $this->eligibleDuffelBooking();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [
                StaffPermission::BookingsView,
                StaffPermission::BookingsUpdateStatus,
            ]],
        ])->save();

        $this->actingAs($staff->fresh())
            ->post(route('staff.bookings.supplier-booking', $booking))
            ->assertForbidden();
    }

    public function test_flight_search_service_has_no_supplier_booking_hard_stop(): void
    {
        $contents = (string) file_get_contents(base_path('app/Services/FlightSearch/FlightSearchService.php'));

        $this->assertStringNotContainsString('ensureSupplierBookingEnabled', $contents);
    }

    protected function eligibleDuffelBooking(bool $withSupplierBooking = false): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => ['offer_id' => 'duffel-offer-8o'],
            ],
        ]);

        if ($withSupplierBooking) {
            $booking->update([
                'pnr' => 'PNR8O',
                'supplier_reference' => 'REF8O',
                'supplier_booking_status' => 'pending_ticketing',
            ]);
            SupplierBooking::query()->create([
                'agency_id' => $agency->id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::Duffel->value,
                'supplier_reference' => 'REF8O',
                'pnr' => 'PNR8O',
                'status' => 'pending_ticketing',
            ]);
        }

        return $booking->fresh();
    }

    protected function eligibleSabreBooking(bool $withSupplierBooking = false): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $connection->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => ['offer_id' => 'sabre-offer-8o'],
            ],
        ]);

        if ($withSupplierBooking) {
            $booking->update([
                'pnr' => 'SBR8O',
                'supplier_reference' => 'SREF8O',
                'supplier_booking_status' => 'pending_ticketing',
            ]);
            SupplierBooking::query()->create([
                'agency_id' => $agency->id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::Sabre->value,
                'supplier_reference' => 'SREF8O',
                'pnr' => 'SBR8O',
                'status' => 'pending_ticketing',
            ]);
        }

        return $booking->fresh();
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }
}
