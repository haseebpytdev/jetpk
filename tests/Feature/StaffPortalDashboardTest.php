<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_shows_kpis_and_queues(): void
    {
        [$staff, $assigned] = $this->staffWithAssignedBooking();

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="staff-dashboard-kpis"', false)
            ->assertSee('Assigned to me', false)
            ->assertSee('Payment review', false)
            ->assertSee('Manual review', false)
            ->assertSee('data-testid="staff-dashboard-queues"', false)
            ->assertSee('data-testid="staff-recent-assigned"', false)
            ->assertSee($assigned->booking_reference, false);
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
            ->assertOk()
            ->assertSee('data-testid="staff-bookings-queues"', false)
            ->assertSee($assigned->booking_reference, false)
            ->assertDontSee($other->booking_reference, false);
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
            ->assertOk()
            ->assertSee('ota-bstat', false)
            ->assertSee($unpaid->booking_reference, false)
            ->assertDontSee($paid->booking_reference, false);
    }

    public function test_staff_cannot_assign_staff_or_see_admin_sidebar_links(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->for(
            Agency::query()->where('slug', 'asif-travels')->firstOrFail()
        )->create(['status' => BookingStatus::Pending]);

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('Supplier connections', false)
            ->assertDontSee('Agency settings', false)
            ->assertDontSee('Assign staff', false);

        $this->actingAs($staff)->patch(route('admin.bookings.assign-staff', $booking), [
            'staff_user_id' => $staff->id,
        ])->assertForbidden();

        $this->actingAs($staff)->get(route('staff.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('id="assign-staff-panel"', false);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
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
