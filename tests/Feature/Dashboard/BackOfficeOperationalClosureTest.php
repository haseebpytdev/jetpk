<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficeOperationalClosureTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_dashboard_session_includes_capabilities_and_navigation(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk()
            ->assertJsonPath('data.sessionUsable', true)
            ->assertJsonPath('data.platformRole', 'platform_admin')
            ->assertJsonStructure(['data' => ['navigation', 'capabilities']]);
    }

    public function test_customer_is_denied_dashboard_session(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.session'))
            ->assertForbidden();
    }

    public function test_staff_without_payment_permission_cannot_verify_payment_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::BookingsView]],
        ])->save();

        $booking = Booking::factory()->create([
            'agency_id' => $staff->current_agency_id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
        ]);
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($staff->fresh())
            ->patchJson(route('staff.bookings.payments.verify', ['bookingPayment' => $payment]))
            ->assertForbidden();
    }

    public function test_admin_payment_verify_json_is_idempotent_on_duplicate(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create();
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.payments.verify', ['bookingPayment' => $payment]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $payment->refresh();
        $this->assertSame('verified', $payment->status->value);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.payments.verify', ['bookingPayment' => $payment]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_processed');
    }

    public function test_admin_deposit_approve_credits_wallet_once(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agency->id,
                'user_id' => $agent->user_id,
                'balance' => 1000,
                'currency' => 'PKR',
            ],
        );
        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 500,
            'currency' => 'PKR',
            'reference' => 'DEP-OPS05-'.uniqid(),
            'status' => AgentDepositRequestStatus::Submitted,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.agent-deposits.approve', ['deposit' => $deposit]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $wallet->refresh();
        $this->assertSame(1500.0, (float) $wallet->balance);

        $this->actingAs($admin)
            ->patchJson(route('admin.agent-deposits.approve', ['deposit' => $deposit]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_processed');

        $wallet->refresh();
        $this->assertSame(1500.0, (float) $wallet->balance);
    }

    public function test_cancellation_process_json_executes_for_approved_unticketed_booking(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
        $cancellation = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingCancellationStatus::Approved,
            'cancellation_type' => 'booking_cancel',
            'requested_by' => $admin->id,
            'request_source' => 'admin',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.cancellations.process', ['cancellationRequest' => $cancellation]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('execution_state', 'success');

        $booking->refresh();
        $cancellation->refresh();
        $this->assertSame('cancelled', $booking->status->value);
        $this->assertSame(BookingCancellationStatus::Processed, $cancellation->status);
    }

    public function test_refund_mark_paid_json_records_settlement(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create();
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'verified',
            'amount' => 1000,
            'currency' => 'PKR',
            'verified_at' => now(),
        ]);
        $refund = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingRefundStatus::Approved,
            'amount' => 500,
            'currency' => 'PKR',
            'method' => 'cash',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.refunds.mark-paid', ['bookingRefund' => $refund]), [
                'reference' => 'SETTLE-OPS06',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('execution_state', 'success');

        $refund->refresh();
        $this->assertSame(BookingRefundStatus::Paid, $refund->status);
        $this->assertSame('SETTLE-OPS06', $refund->reference);
    }

    public function test_refund_mark_paid_json_is_idempotent_on_duplicate(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create();
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'verified',
            'amount' => 1000,
            'currency' => 'PKR',
            'verified_at' => now(),
        ]);
        $refund = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingRefundStatus::Approved,
            'amount' => 500,
            'currency' => 'PKR',
            'method' => 'cash',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.refunds.mark-paid', ['bookingRefund' => $refund]))
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.refunds.mark-paid', ['bookingRefund' => $refund]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_processed');
    }

    public function test_admin_payment_reject_requires_reason_and_conflicts_after_verify(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create();
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.payments.verify', ['bookingPayment' => $payment]))
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.payments.reject', ['bookingPayment' => $payment]), [
                'reason' => 'Too late',
            ])
            ->assertStatus(409);
    }

    public function test_cancellation_review_approve_json_does_not_cancel_booking(): void
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
            ->patchJson(route('admin.bookings.cancellations.approve', ['cancellationRequest' => $cancellation]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $booking->refresh();
        $cancellation->refresh();
        $this->assertSame(BookingCancellationStatus::Approved, $cancellation->status);
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotSame('cancelled', $booking->status->value);
    }

    public function test_refund_review_approve_json_remains_approved_not_paid(): void
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
            ->patchJson(route('admin.bookings.refunds.approve', ['bookingRefund' => $refund]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $refund->refresh();
        $this->assertSame(BookingRefundStatus::Approved, $refund->status);
        $this->assertNotSame(BookingRefundStatus::Paid, $refund->status);
    }

    public function test_deposit_approve_posts_single_wallet_transaction(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agency->id,
                'user_id' => $agent->user_id,
                'balance' => 1000,
                'currency' => 'PKR',
            ],
        );
        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 500,
            'currency' => 'PKR',
            'reference' => 'DEP-LEDGER-'.uniqid(),
            'status' => AgentDepositRequestStatus::Submitted,
        ]);

        $beforeCount = \App\Models\AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->count();

        $this->actingAs($admin)
            ->patchJson(route('admin.agent-deposits.approve', ['deposit' => $deposit]))
            ->assertOk();

        $afterCount = \App\Models\AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->count();
        $this->assertSame($beforeCount + 1, $afterCount);

        $this->actingAs($admin)
            ->patchJson(route('admin.agent-deposits.approve', ['deposit' => $deposit->fresh()]))
            ->assertStatus(409);

        $finalCount = \App\Models\AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->count();
        $this->assertSame($afterCount, $finalCount);
    }

    public function test_blade_payment_verify_redirect_still_works_without_json_accept(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create();
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.bookings.payments.verify', ['bookingPayment' => $payment]))
            ->assertRedirect();
    }
}
