<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingRefund;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficeCoreOpsClosureTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_can_activate_and_suspend_user_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $user = User::factory()->create([
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.users.suspend', ['user' => $user]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $user->refresh();
        $this->assertSame(UserAccountStatus::Suspended, $user->status);

        $this->actingAs($admin)
            ->patchJson(route('admin.users.activate', ['user' => $user]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $user->refresh();
        $this->assertSame(UserAccountStatus::Active, $user->status);
    }

    public function test_admin_can_store_booking_note_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.bookings.notes', ['booking' => $booking]), [
                'note' => 'JP-OPS-07 internal note',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_cancellation_review_reject_json_happy_path(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
        $cancellation = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingCancellationStatus::Requested,
            'cancellation_type' => 'booking_cancel',
            'requested_by' => $admin->id,
            'request_source' => 'admin',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.cancellations.reject', ['cancellationRequest' => $cancellation]), [
                'reason' => 'Not eligible',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $cancellation->refresh();
        $this->assertSame(BookingCancellationStatus::Rejected, $cancellation->status);
    }

    public function test_cancellation_review_approve_after_reject_conflicts(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
        $cancellation = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingCancellationStatus::Requested,
            'cancellation_type' => 'booking_cancel',
            'requested_by' => $admin->id,
            'request_source' => 'admin',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.cancellations.reject', ['cancellationRequest' => $cancellation]), [
                'reason' => 'Denied',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.cancellations.approve', ['cancellationRequest' => $cancellation->fresh()]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_processed');
    }

    public function test_refund_review_reject_json_happy_path(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create();
        $refund = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingRefundStatus::Pending,
            'amount' => 500,
            'currency' => 'PKR',
            'method' => 'cash',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.refunds.reject', ['bookingRefund' => $refund]), [
                'reason' => 'Not approved',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $refund->refresh();
        $this->assertSame(BookingRefundStatus::Rejected, $refund->status);
    }

    public function test_staff_with_cancellation_permission_can_approve_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::CancellationsApprove]],
        ])->save();

        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::Confirmed,
        ]);
        $cancellation = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingCancellationStatus::Requested,
            'cancellation_type' => 'booking_cancel',
            'requested_by' => $staff->id,
            'request_source' => 'staff',
        ]);

        $this->actingAs($staff->fresh())
            ->patchJson(route('staff.bookings.cancellations.approve', ['cancellationRequest' => $cancellation]))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
