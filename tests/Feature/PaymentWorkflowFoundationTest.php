<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWorkflowFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_can_record_manual_payment(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'bank_transfer',
            'amount' => 5000,
            'payment_reference' => 'REF-1',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $booking->id,
            'status' => 'verified',
            'method' => 'bank_transfer',
        ]);
    }

    public function test_staff_can_record_manual_payment(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = $this->bookingWithFare($staff->current_agency_id, ['status' => BookingStatus::PaymentPending]);

        $this->actingAs($staff)->post(route('staff.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 2500,
            'payment_reference' => 'CASH-2',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_payments', ['booking_id' => $booking->id, 'method' => 'cash']);
    }

    public function test_agent_cannot_verify_payment(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $booking = $this->bookingWithFare($agent->current_agency_id, []);
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($agent)->patch(route('admin.bookings.payments.verify', $payment))->assertForbidden();
    }

    public function test_agent_can_submit_proof_for_own_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = $agentUser->agent();
        $booking = $this->bookingWithFare($agentUser->current_agency_id, [
            'agent_id' => $agent?->id,
            'status' => BookingStatus::PaymentPending,
            'source_channel' => 'agent_portal',
        ]);

        $this->actingAs($agentUser)->post(route('agent.bookings.payment-proof', $booking), [
            'method' => 'bank_transfer',
            'amount' => 3000,
            'payment_reference' => 'AGT-PROOF',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_payments', ['booking_id' => $booking->id, 'status' => 'submitted']);
    }

    public function test_agent_cannot_submit_proof_for_another_agents_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $otherAgentUser = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($otherAgentUser->id, ['role' => 'agent']);
        $otherAgent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $otherAgentUser->id,
        ]);
        $booking = $this->bookingWithFare($agency->id, [
            'agent_id' => $otherAgent->id,
            'status' => BookingStatus::PaymentPending,
            'source_channel' => 'agent_portal',
        ]);

        $this->actingAs($agentUser)->post(route('agent.bookings.payment-proof', $booking), [
            'method' => 'bank_transfer',
            'amount' => 3000,
        ])->assertForbidden();
    }

    public function test_cross_agency_payment_operation_denied(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $other = Agency::factory()->create();
        $foreign = $this->bookingWithFare($other->id, []);

        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $foreign), [
            'method' => 'cash',
            'amount' => 1000,
        ])->assertForbidden();
    }

    public function test_verified_payment_updates_amount_paid_and_balance_due(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 4000,
        ]);
        $fresh = $booking->fresh();
        $this->assertEquals(4000.0, (float) $fresh->amount_paid);
        $this->assertEquals(6000.0, (float) $fresh->balance_due);
    }

    public function test_partial_payment_sets_booking_payment_status_partial(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 1000,
        ]);

        $this->assertSame('partial', $booking->fresh()->payment_status);
    }

    public function test_full_payment_sets_booking_payment_status_paid(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 10000,
        ]);

        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_overpayment_is_blocked_without_admin_override(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 11000,
        ])->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('booking_payments', 0);
    }

    public function test_refund_cannot_exceed_verified_paid_amount(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 5000,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => 7000,
            'method' => 'bank_transfer',
            'reference' => 'RF-OVER',
        ])->assertSessionHasErrors('refund');
    }

    public function test_payment_verification_creates_audit_log(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.bookings.payments.verify', $payment))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'action' => 'booking.payment_verified',
        ]);
    }

    public function test_admin_booking_show_pending_proof_shows_review_hint(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 2500,
            'currency' => 'PKR',
            'proof_path' => 'booking-payments/proofs/test-proof.pdf',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.bookings.show', ['booking' => $booking, 'tab' => 'payments']))
            ->assertOk()
            ->assertSee('data-testid="admin-pending-payment-proof"', false)
            ->assertSee('data-testid="admin-booking-payment-summary"', false)
            ->assertSee('Verify payment', false);
    }

    public function test_payment_rejection_creates_audit_and_does_not_count_toward_amount_paid(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 5000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.bookings.payments.reject', $payment), ['reason' => 'Invalid transfer'])->assertRedirect();
        $this->assertSame(0.0, (float) $booking->fresh()->amount_paid);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'action' => 'booking.payment_rejected',
        ]);
    }

    public function test_paid_booking_does_not_become_ticketed(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 10000,
        ]);

        $this->assertNotSame(BookingStatus::Ticketed, $booking->fresh()->status);
    }

    public function test_payment_pending_fully_paid_moves_to_paid_or_ticketing_pending(): void
    {
        [$booking, $admin] = $this->bookingForAgencyAdmin(['status' => BookingStatus::PaymentPending]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $booking), [
            'method' => 'cash',
            'amount' => 10000,
        ]);
        $statusA = $booking->fresh()->status;

        $bookingB = $this->bookingWithFare($admin->current_agency_id, [
            'status' => BookingStatus::PaymentPending,
            'supplier_booking_status' => 'pending_ticketing',
        ]);
        $this->actingAs($admin)->post(route('admin.bookings.payments.store', $bookingB), [
            'method' => 'cash',
            'amount' => 10000,
        ]);
        $statusB = $bookingB->fresh()->status;

        $this->assertSame(BookingStatus::Paid, $statusA);
        $this->assertSame(BookingStatus::TicketingPending, $statusB);
    }

    public function test_dashboard_report_payment_breakdown_still_works(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $this->actingAs($admin)->get('/admin/reports')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Booking, 1: User}
     */
    protected function bookingForAgencyAdmin(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = $this->bookingWithFare($admin->current_agency_id, $overrides);

        return [$booking, $admin];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function bookingWithFare(int $agencyId, array $overrides): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agencyId,
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
