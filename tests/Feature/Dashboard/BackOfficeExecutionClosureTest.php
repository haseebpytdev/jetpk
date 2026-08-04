<?php

namespace Tests\Feature\Dashboard;

use App\Data\TicketingResultData;
use App\Enums\AccountType;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\BookingTicket;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\TicketingAdapters\DuffelSupplierTicketingAdapter;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BackOfficeExecutionClosureTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_issue_ticket_json_issues_authoritative_tickets(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->eligibleDuffelBooking();

        $this->actingAs($admin)
            ->postJson(route('admin.bookings.issue-ticket', $booking))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('execution_state', 'success');

        $booking->refresh();
        $this->assertSame(BookingStatus::Ticketed, $booking->status);
        $this->assertDatabaseHas('booking_tickets', ['booking_id' => $booking->id]);
    }

    public function test_duplicate_issue_ticket_json_returns_conflict_without_second_supplier_call(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->eligibleDuffelBooking();

        $this->actingAs($admin)->postJson(route('admin.bookings.issue-ticket', $booking))->assertOk();
        $this->actingAs($admin)
            ->postJson(route('admin.bookings.issue-ticket', $booking))
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_ticketed');

        $this->assertSame(1, BookingTicket::query()->where('booking_id', $booking->id)->count());
    }

    public function test_ticketing_json_creates_commission_only_after_success(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->eligibleDuffelBooking(withAgent: true);

        $this->assertDatabaseMissing('agent_commission_entries', ['booking_id' => $booking->id]);

        $this->actingAs($admin)->postJson(route('admin.bookings.issue-ticket', $booking))->assertOk();

        $this->assertDatabaseHas('agent_commission_entries', [
            'booking_id' => $booking->id,
            'type' => 'earned',
        ]);
    }

    public function test_staff_without_cancellation_process_permission_denied(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => [StaffPermission::BookingsView]],
        ])->save();

        $booking = Booking::factory()->create(['agency_id' => $staff->current_agency_id]);
        $cancellation = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingCancellationStatus::Approved,
            'cancellation_type' => 'booking_cancel',
            'requested_by' => $admin->id,
            'request_source' => 'admin',
        ]);

        $this->actingAs($staff->fresh())
            ->patchJson(route('staff.bookings.cancellations.process', ['cancellationRequest' => $cancellation]))
            ->assertForbidden();
    }

    public function test_customer_cannot_execute_cancellation_process(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ]);
        $booking = Booking::factory()->create();
        $cancellation = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingCancellationStatus::Approved,
            'cancellation_type' => 'booking_cancel',
            'requested_by' => $customer->id,
            'request_source' => 'customer',
        ]);

        $this->actingAs($customer)
            ->patchJson(route('admin.bookings.cancellations.process', ['cancellationRequest' => $cancellation]))
            ->assertForbidden();
    }

    public function test_cancellation_process_duplicate_after_success_returns_conflict(): void
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
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('admin.bookings.cancellations.process', ['cancellationRequest' => $cancellation->fresh()]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_processed');
    }

    public function test_refund_reject_after_settle_conflict(): void
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
            ->patchJson(route('admin.bookings.refunds.reject', ['bookingRefund' => $refund->fresh()]), [
                'reason' => 'Too late',
            ])
            ->assertStatus(409);
    }

    public function test_blade_cancellation_process_redirect_still_works_without_json_accept(): void
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
            ->patch(route('admin.bookings.cancellations.process', ['cancellationRequest' => $cancellation]))
            ->assertRedirect();
    }

    public function test_blade_issue_ticket_redirect_still_works_without_json_accept(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->eligibleDuffelBooking();

        $this->actingAs($admin)
            ->post(route('admin.bookings.issue-ticket', $booking))
            ->assertRedirect();
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function eligibleDuffelBooking(bool $withAgent = false): array
    {
        $this->mock(DuffelSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->andReturnUsing(function (Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData {
                $tickets = [];
                foreach ($booking->passengers as $passenger) {
                    $tickets[] = [
                        'passenger_id' => $passenger->id,
                        'ticket_number' => 'TKT'.$passenger->id,
                        'pnr' => $booking->pnr,
                        'airline_code' => 'PK',
                        'issued_at' => now(),
                        'passenger_name' => trim((string) $passenger->first_name.' '.(string) $passenger->last_name),
                    ];
                }

                return new TicketingResultData(
                    success: true,
                    status: 'issued',
                    provider: is_string($supplierBooking->provider) ? $supplierBooking->provider : $supplierBooking->provider->value,
                    tickets: $tickets,
                    safe_summary: ['stub' => true],
                );
            });
        });

        $admin = $this->platformAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $agent = $withAgent ? Agent::query()->where('agency_id', $admin->current_agency_id)->firstOrFail() : null;

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'agent_id' => $agent?->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'pnr' => 'PNR123',
            'supplier_reference' => 'SUPP123',
            'supplier_booking_status' => 'pending_ticketing',
            'meta' => $withAgent ? [
                'pricing_snapshot' => [
                    'applied_rules' => [
                        [
                            'bucket' => 'agent_markup_or_commission',
                            'value' => 5,
                            'value_type' => 'percentage',
                        ],
                    ],
                ],
            ] : null,
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
        ]);
        if ($withAgent) {
            $booking->fareBreakdown()->create([
                'base_fare' => 10000,
                'taxes' => 2000,
                'fees' => 500,
                'markup' => 500,
                'discount' => 0,
                'total' => 13000,
                'currency' => 'PKR',
            ]);
        }
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Duffel->value,
            'supplier_reference' => 'SUPP123',
            'pnr' => 'PNR123',
            'status' => 'pending_ticketing',
            'raw_summary' => ['seeded' => true],
            'created_by' => $admin->id,
            'created_at_supplier' => now(),
        ]);

        return [$booking, $admin];
    }
}
