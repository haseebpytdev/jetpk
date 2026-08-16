<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Http\Controllers\Staff\BookingController as StaffBookingController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class StaffPortalDashboardTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_staff_dashboard_shows_kpis_and_queues(): void
    {
        [$staff, $assigned] = $this->staffWithAssignedBooking();

        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();

        $html = $this->staffDashboardHtml($staff);
        $this->assertStringContainsString('data-testid="staff-dashboard-kpis"', $html);
        $this->assertStringContainsString('Assigned to me', $html);
        $this->assertStringContainsString('Payment review', $html);
        $this->assertStringContainsString('Manual review', $html);
        $this->assertStringContainsString('data-testid="staff-dashboard-queues"', $html);
        $this->assertStringContainsString('data-testid="staff-recent-assigned"', $html);
        $this->assertStringContainsString($assigned->booking_reference, $html);
    }

    public function test_staff_assigned_to_me_bookings_filter(): void
    {
        [$staff, $assigned] = $this->staffWithAssignedBooking();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $other = Booking::factory()->for($agency)->create([
            'assigned_staff_id' => null,
            'booking_reference' => 'OTA-STAFF-UNASSIGNED',
        ]);

        $this->actingAs($staff)->get(route('staff.bookings.index', ['assigned_to_me' => 1]))
            ->assertRedirect();
        $location = (string) $this->actingAs($staff)
            ->get(route('staff.bookings.index', ['assigned_to_me' => 1]))
            ->headers
            ->get('Location');
        $this->assertStringContainsString('/staff/dashboard/bookings', $location);

        $html = $this->staffBookingsIndexHtml($staff, ['assigned_to_me' => 1]);
        $this->assertStringContainsString('data-testid="staff-bookings-queues"', $html);
        $this->assertStringContainsString($assigned->booking_reference, $html);
        $this->assertStringNotContainsString($other->booking_reference, $html);
    }

    public function test_staff_bookings_index_supports_payment_review_queue(): void
    {
        [$staff] = $this->staffWithAssignedBooking();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $unpaid = Booking::factory()->for($agency)->create([
            'payment_status' => 'unpaid',
            'booking_reference' => 'OTA-STAFF-PAY-QUEUE',
        ]);
        $paid = Booking::factory()->for($agency)->create([
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'OTA-STAFF-PAID-QUEUE',
        ]);

        $this->actingAs($staff)->get(route('staff.bookings.index', ['queue' => 'payment_review']))
            ->assertRedirect();

        $html = $this->staffBookingsIndexHtml($staff, ['queue' => 'payment_review']);
        $this->assertStringContainsString('ota-bstat', $html);
        $this->assertStringContainsString($unpaid->booking_reference, $html);
        $this->assertStringNotContainsString($paid->booking_reference, $html);
    }

    public function test_staff_cannot_assign_staff_or_see_admin_sidebar_links(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->for(
            Agency::query()->where('slug', 'asif-travels')->firstOrFail()
        )->create(['status' => BookingStatus::Pending]);

        $html = $this->staffDashboardHtml($staff);
        $this->assertStringNotContainsString('Agency settings', $html);
        $this->assertStringNotContainsString('Assign staff', $html);
        $this->assertStringNotContainsString('href="/admin/api-settings"', $html);

        $this->actingAs($staff)->patch(route('admin.bookings.assign-staff', $booking), [
            'staff_user_id' => $staff->id,
        ])->assertForbidden();

        $this->actingAs($staff)->get(route('staff.bookings.show', $booking))
            ->assertRedirect();

        $showHtml = $this->staffBookingShowHtml($staff, $booking);
        $this->assertStringNotContainsString('id="assign-staff-panel"', $showHtml);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    protected function staffDashboardHtml(User $staff): string
    {
        $this->actingAs($staff);
        ViewFacade::share('errors', new ViewErrorBag);

        return app(StaffDashboardController::class)->index()->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function staffBookingsIndexHtml(User $staff, array $query = []): string
    {
        $this->actingAs($staff);
        ViewFacade::share('errors', new ViewErrorBag);
        $request = Request::create('/staff/bookings', 'GET', $query);
        $request->setUserResolver(fn () => $staff);

        return app(StaffBookingController::class)->index($request)->render();
    }

    protected function staffBookingShowHtml(User $staff, Booking $booking): string
    {
        $this->actingAs($staff);
        ViewFacade::share('errors', new ViewErrorBag);
        $request = Request::create('/staff/bookings/'.$booking->id, 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffBookingController::class)->show($booking)->render();
    }

    /**
     * @return array{0: User, 1: Booking}
     */
    protected function staffWithAssignedBooking(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'assigned_staff_id' => $staff->id,
            'assigned_at' => now(),
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'LHE → DXB',
            'booking_reference' => 'OTA-STAFF-E4-'.uniqid(),
        ]);

        return [$staff, $booking];
    }
}
