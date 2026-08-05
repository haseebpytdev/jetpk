<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\GroupBookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-OPS-07 Laravel JSON behavioural proofs for booking intake and group-payment routes.
 */
class BackOfficeBookingIntakeMutationsTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_assign_staff_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.assign-staff', ['booking' => $booking]), [
                'staff_user_id' => $staff->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $booking->refresh();
        $this->assertSame($staff->id, $booking->assigned_staff_id);
        $this->assertNotNull($booking->assigned_at);
    }

    public function test_admin_booking_intake_store_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $bookingCancel = Booking::factory()->create(['status' => BookingStatus::Pending]);
        $bookingRefund = Booking::factory()->create(['status' => BookingStatus::Cancelled]);
        BookingPayment::query()->create([
            'agency_id' => $bookingRefund->agency_id,
            'booking_id' => $bookingRefund->id,
            'method' => 'cash',
            'status' => BookingPaymentStatus::Verified,
            'amount' => 5000,
            'currency' => 'PKR',
        ]);
        $bookingPayment = $this->bookingWithFare($bookingCancel->agency_id, ['status' => BookingStatus::PaymentPending]);

        $this->actingAs($admin)
            ->postJson(route('admin.bookings.cancellations.store', ['booking' => $bookingCancel]), [
                'cancellation_type' => 'booking_cancel',
                'reason' => 'Customer request',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('booking_cancellation_requests', [
            'booking_id' => $bookingCancel->id,
            'status' => BookingCancellationStatus::Requested->value,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.bookings.refunds.store', ['booking' => $bookingRefund]), [
                'amount' => 100,
                'currency' => 'PKR',
                'method' => 'cash',
                'reason' => 'Partial refund',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('booking_refunds', [
            'booking_id' => $bookingRefund->id,
            'amount' => 100,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.bookings.payments.store', ['booking' => $bookingPayment]), [
                'method' => 'bank_transfer',
                'amount' => 1000,
                'currency' => 'PKR',
                'payment_reference' => 'REF-OPS07',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $bookingPayment->id,
            'method' => 'bank_transfer',
        ]);
    }

    public function test_admin_booking_intake_validation_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);

        $this->actingAs($admin)
            ->postJson(route('admin.bookings.cancellations.store', ['booking' => $booking]), [])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_staff_booking_intake_store_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => [
                'staff_permissions' => [
                    StaffPermission::CancellationsCreate,
                    StaffPermission::RefundsCreate,
                    StaffPermission::PaymentsRecord,
                ],
            ],
        ])->save();

        $bookingCancel = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::Pending,
        ]);
        $bookingRefund = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::Cancelled,
        ]);
        BookingPayment::query()->create([
            'agency_id' => $bookingRefund->agency_id,
            'booking_id' => $bookingRefund->id,
            'method' => 'cash',
            'status' => BookingPaymentStatus::Verified,
            'amount' => 5000,
            'currency' => 'PKR',
        ]);
        $bookingPayment = $this->bookingWithFare($staff->current_agency_id, ['status' => BookingStatus::PaymentPending]);

        $this->actingAs($staff->fresh())
            ->postJson(route('staff.bookings.cancellations.store', ['booking' => $bookingCancel]), [
                'cancellation_type' => 'booking_cancel',
                'reason' => 'Staff initiated',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($staff->fresh())
            ->postJson(route('staff.bookings.refunds.store', ['booking' => $bookingRefund]), [
                'amount' => 200,
                'currency' => 'PKR',
                'method' => 'cash',
                'reason' => 'Refund request',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($staff->fresh())
            ->postJson(route('staff.bookings.payments.store', ['booking' => $bookingPayment]), [
                'method' => 'cash',
                'amount' => 500,
                'currency' => 'PKR',
                'payment_reference' => 'STAFF-PAY',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_staff_without_permission_cannot_store_cancellation_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['meta' => ['staff_permissions' => [StaffPermission::BookingsView]]])->save();
        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($staff->fresh())
            ->postJson(route('staff.bookings.cancellations.store', ['booking' => $booking]), [
                'cancellation_type' => 'booking_cancel',
                'reason' => 'Denied',
            ])
            ->assertForbidden();
    }

    public function test_group_booking_payment_verify_and_reject_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'ops07-json-1',
            'public_id' => 'ALH-OPS07-JSON',
            'title' => 'Ops07 JSON Group',
            'total_seats' => 5,
            'held_seats' => 1,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);

        $verifyBooking = GroupBooking::query()->create([
            'reference' => 'GRP-OPS07-VERIFY',
            'user_id' => $customer->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::ManualPaymentPendingReview,
            'seat_count' => 1,
            'total_amount' => 50000,
            'currency' => 'PKR',
            'payment_submitted_at' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.group-bookings.verify-payment', ['groupBooking' => $verifyBooking->reference]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('group_booking.status', GroupBookingStatus::Confirmed->value);

        $verifyBooking->refresh();
        $this->assertSame(GroupBookingStatus::Confirmed, $verifyBooking->status);

        $rejectBooking = GroupBooking::query()->create([
            'reference' => 'GRP-OPS07-REJECT',
            'user_id' => $customer->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::ManualPaymentPendingReview,
            'seat_count' => 1,
            'total_amount' => 40000,
            'currency' => 'PKR',
            'payment_submitted_at' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.group-bookings.reject-payment', ['groupBooking' => $rejectBooking->id]), [
                'rejection_note' => 'Invalid proof',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $rejectBooking->refresh();
        $this->assertSame(GroupBookingStatus::Released, $rejectBooking->status);
    }

    public function test_group_booking_verify_duplicate_is_safe_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'ops07-dup',
            'public_id' => 'ALH-OPS07-DUP',
            'title' => 'Ops07 Dup Group',
            'total_seats' => 5,
            'held_seats' => 1,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-OPS07-DUP01',
            'user_id' => $customer->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::ManualPaymentPendingReview,
            'seat_count' => 1,
            'total_amount' => 50000,
            'currency' => 'PKR',
            'payment_submitted_at' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.group-bookings.verify-payment', ['groupBooking' => $booking]))
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('admin.group-bookings.verify-payment', ['groupBooking' => $booking->fresh()]))
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_group_booking_invalid_binding_returns_404_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->postJson(route('admin.group-bookings.verify-payment', ['groupBooking' => 'NONEXISTENT-REF']))
            ->assertNotFound();
    }

    public function test_non_admin_cannot_verify_group_booking_payment_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $customer = User::factory()->create(['account_type' => AccountType::Customer, 'status' => UserAccountStatus::Active]);
        $inventory = GroupInventory::query()->create([
            'supplier' => 'alhaider',
            'supplier_package_id' => 'ops07-forbid',
            'public_id' => 'ALH-OPS07-FORBID',
            'title' => 'Ops07 Forbid Group',
            'total_seats' => 5,
            'held_seats' => 1,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
        ]);
        $booking = GroupBooking::query()->create([
            'reference' => 'GRP-OPS07-FORBID',
            'user_id' => $customer->id,
            'group_inventory_id' => $inventory->id,
            'status' => GroupBookingStatus::ManualPaymentPendingReview,
            'seat_count' => 1,
            'total_amount' => 50000,
            'currency' => 'PKR',
            'payment_submitted_at' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($customer)
            ->postJson(route('admin.group-bookings.verify-payment', ['groupBooking' => $booking]))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function bookingWithFare(int $agencyId, array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agencyId,
            'booking_reference' => 'BKG-'.fake()->unique()->numerify('######'),
            'status' => BookingStatus::Confirmed,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'balance_due' => 10000,
            'currency' => 'PKR',
        ], $overrides));
        $booking->fareBreakdown()->create([
            'base_fare' => 7000,
            'taxes' => 2000,
            'fees' => 500,
            'markup' => 500,
            'discount' => 0,
            'total' => 10000,
            'currency' => 'PKR',
            'breakdown' => null,
        ]);

        return $booking->fresh();
    }
}
