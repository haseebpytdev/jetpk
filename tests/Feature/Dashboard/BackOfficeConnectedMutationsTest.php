<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\AgentCommissionEntry;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingRefund;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-OPS-07 behavioural JSON proofs for routes moved to CONNECTED (beyond CoreOps/OperationalClosure).
 */
class BackOfficeConnectedMutationsTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_staff_cancellation_and_refund_review_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => [
                'staff_permissions' => [
                    StaffPermission::CancellationsApprove,
                    StaffPermission::RefundsApprove,
                    StaffPermission::RefundsReject,
                ],
            ],
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
            ->patchJson(route('staff.bookings.cancellations.reject', ['cancellationRequest' => $cancellation]), [
                'reason' => 'Not eligible',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $refund = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingRefundStatus::Pending,
            'amount' => 500,
            'currency' => 'PKR',
            'method' => 'cash',
        ]);

        $this->actingAs($staff->fresh())
            ->patchJson(route('staff.bookings.refunds.approve', ['bookingRefund' => $refund]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $refund->refresh();
        $this->assertSame(BookingRefundStatus::Approved, $refund->status);

        $refund2 = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingRefundStatus::Pending,
            'amount' => 300,
            'currency' => 'PKR',
            'method' => 'cash',
        ]);

        $this->actingAs($staff->fresh())
            ->patchJson(route('staff.bookings.refunds.reject', ['bookingRefund' => $refund2]), [
                'reason' => 'Denied',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_staff_booking_note_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create(['agency_id' => $staff->current_agency_id]);

        $this->actingAs($staff)
            ->postJson(route('staff.bookings.notes', ['booking' => $booking]), [
                'note' => 'Staff internal note',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_agency_prefix_and_rbac_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $user = $agent->user;
        $agentStaff = User::query()->create([
            'name' => 'Agency Staff Ops07',
            'username' => 'agency-staff-ops07',
            'email' => 'agency-staff-ops07@agency.test',
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => [AgentPermission::BookingsView],
            ],
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.agencies.prefix.update', ['agency' => $agency]), [
                'code_prefix' => 'JPO7',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->patchJson(route('admin.agencies.users.agency-role.update', ['agency' => $agency, 'user' => $user]), [
                'agency_role' => 'manager',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->patchJson(route('admin.agencies.users.agent-permissions.update', ['agency' => $agency, 'user' => $agentStaff]), [
                'permissions' => [AgentPermission::BookingsCreate, AgentPermission::BookingsView],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->postJson(route('admin.agencies.users.agent-permissions.apply-template', ['agency' => $agency, 'user' => $agentStaff]), [
                'confirm_template_apply' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_agent_application_review_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $approve = AgentApplication::query()->create([
            'first_name' => 'Approve',
            'last_name' => 'Candidate',
            'email' => 'approve-'.Str::random(6).'@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Approve Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Office',
            'expected_booking_volume' => '10',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.agent-applications.approve', $approve), [
                'internal_note' => 'Approved via JSON',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $needs = AgentApplication::query()->create([
            'first_name' => 'Needs',
            'last_name' => 'Info',
            'email' => 'needs-'.Str::random(6).'@example.test',
            'mobile' => '+923001112234',
            'company_name' => 'Needs Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Office',
            'expected_booking_volume' => '10',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.agent-applications.needs-more-info', $needs), [
                'internal_note' => 'Upload NTN',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $reject = AgentApplication::query()->create([
            'first_name' => 'Reject',
            'last_name' => 'Candidate',
            'email' => 'reject-'.Str::random(6).'@example.test',
            'mobile' => '+923001112235',
            'company_name' => 'Reject Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Office',
            'expected_booking_volume' => '10',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.agent-applications.reject', $reject), [
                'internal_note' => 'Incomplete',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_support_ticket_lifecycle_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agency = Agency::query()->firstOrFail();

        $ticket = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $admin->id,
            'subject' => 'JP-OPS-07 support',
            'category' => SupportTicketCategory::Other,
            'priority' => 'normal',
            'status' => SupportTicketStatus::Open,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.support.tickets.assign', ['ticket' => $ticket]), [
                'assigned_to_user_id' => $staff->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->patchJson(route('admin.support.tickets.forward', ['ticket' => $ticket]), [
                'forward_to_user_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->postJson(route('admin.support.tickets.reply', ['ticket' => $ticket]), [
                'body' => 'Admin reply body',
                'is_internal' => false,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->patchJson(route('admin.support.tickets.status', ['ticket' => $ticket]), [
                'status' => 'resolved',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $staffTicket = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $staff->id,
            'subject' => 'Staff ticket',
            'category' => SupportTicketCategory::Other,
            'priority' => 'normal',
            'status' => SupportTicketStatus::Open,
        ]);

        $this->actingAs($staff)
            ->postJson(route('staff.support.tickets.reply', ['ticket' => $staffTicket]), [
                'body' => 'Staff reply',
                'is_internal' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($staff)
            ->patchJson(route('staff.support.tickets.status', ['ticket' => $staffTicket]), [
                'status' => 'resolved',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_commission_and_finance_adjustment_json(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $booking = Booking::factory()->create(['agency_id' => $agency->id, 'agent_id' => $agent->id]);

        $entry = AgentCommissionEntry::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'booking_id' => $booking->id,
            'type' => AgentCommissionEntryType::Earned,
            'status' => AgentCommissionEntryStatus::Pending,
            'commission_amount' => 500,
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.commissions.entries.approve', ['entry' => $entry]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $rejectEntry = AgentCommissionEntry::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'booking_id' => $booking->id,
            'type' => AgentCommissionEntryType::Earned,
            'status' => AgentCommissionEntryStatus::Pending,
            'commission_amount' => 300,
            'currency' => 'PKR',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.commissions.entries.reject', ['entry' => $rejectEntry]), [
                'reason' => 'Incorrect rate',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agency->id,
                'user_id' => $agent->user_id,
                'balance' => 1000,
                'currency' => 'PKR',
            ],
        );

        $this->actingAs($admin)
            ->postJson(route('admin.finance.adjustments.store'), [
                'agency_id' => $agency->id,
                'wallet_id' => $wallet->id,
                'adjustment_type' => 'manual_credit',
                'amount' => 100,
                'adjustment_reason' => 'other',
                'adjustment_note' => 'JP-OPS-07 test credit',
                'idempotency_key' => (string) Str::uuid(),
                'confirmation' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $walletTransaction = AgentWalletTransaction::query()
            ->where('agent_wallet_id', $wallet->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->postJson(route('admin.finance.adjustments.reverse', ['walletTransaction' => $walletTransaction]), [
                'reversal_reason' => 'Correction reversal',
                'confirmation' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
