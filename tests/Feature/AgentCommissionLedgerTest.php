<?php

namespace Tests\Feature;

use App\Data\TicketingResultData;
use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionStatement;
use App\Models\Booking;
use App\Models\MarkupRule;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Agents\AgentCommissionService;
use App\Services\Suppliers\TicketingAdapters\PiaNdcSupplierTicketingAdapter;
use App\Support\Staff\StaffPermission;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AgentCommissionLedgerTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_ticketing_an_agent_booking_creates_commission_entry(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->ticketingReadyBooking(withAgent: true);

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();
        $this->assertDatabaseHas('agent_commission_entries', [
            'booking_id' => $booking->id,
            'type' => 'earned',
        ]);
    }

    public function test_direct_customer_booking_does_not_create_commission_entry(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->ticketingReadyBooking(withAgent: false);

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();
        $this->assertDatabaseMissing('agent_commission_entries', ['booking_id' => $booking->id]);
    }

    public function test_duplicate_ticketing_duplicate_call_does_not_create_duplicate_commission(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->ticketingReadyBooking(withAgent: true);

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertSessionHasErrors();

        $this->assertSame(1, AgentCommissionEntry::query()->where('booking_id', $booking->id)->where('type', 'earned')->count());
    }

    public function test_platform_admin_can_view_commissions(): void
    {
        [$agent, $admin] = $this->agentForAgency();

        $this->actingAs($admin)->get(route('admin.commissions.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.commissions.show', $agent))->assertOk();
    }

    public function test_legacy_agency_admin_cannot_view_commissions(): void
    {
        [$agent] = $this->agentForAgency();
        $legacy = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacy)->get(route('admin.commissions.index'))->assertForbidden();
        $this->actingAs($legacy)->get(route('admin.commissions.show', $agent))->assertForbidden();
    }

    public function test_agent_can_view_own_commissions(): void
    {
        [$agent, $admin] = $this->agentForAgency();
        AgentCommissionEntry::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'type' => 'adjustment',
            'status' => 'approved',
            'calculation_basis' => 'manual',
            'base_amount' => 0,
            'commission_amount' => 500,
            'currency' => 'PKR',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($agent->user)->get(route('agent.commissions.index'))->assertOk();
    }

    public function test_agent_cannot_view_another_agent_commissions(): void
    {
        [$agentA] = $this->agentForAgency();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $otherUser = User::factory()->create(['account_type' => AccountType::Agent, 'current_agency_id' => $agency->id]);
        $agency->users()->attach($otherUser->id, ['role' => 'agent']);
        $agentB = Agent::factory()->create(['agency_id' => $agency->id, 'user_id' => $otherUser->id]);
        $statement = AgentCommissionStatement::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agentA->id,
            'status' => 'issued',
            'closing_balance' => 100,
        ]);

        $this->actingAs($agentB->user)->get(route('agent.commissions.statements.show', $statement))->assertForbidden();
    }

    public function test_platform_admin_can_approve_commission_entry(): void
    {
        [$agent, $admin] = $this->agentForAgency();
        $entry = $this->pendingEntry($agent);

        $this->actingAs($admin)->post(route('admin.commissions.entries.approve', $entry))->assertRedirect();
        $this->assertSame('approved', $entry->fresh()->status->value);
    }

    public function test_staff_with_ticketing_permission_can_issue_ticket(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking] = $this->ticketingReadyBooking(withAgent: true);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => array_merge($staff->meta ?? [], [
                'staff_permissions' => [StaffPermission::TicketingIssue],
            ]),
        ])->save();

        $this->actingAs($staff->fresh())->post(route('staff.bookings.issue-ticket', $booking))->assertRedirect();
    }

    public function test_staff_without_ticketing_permission_cannot_issue_ticket(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking] = $this->ticketingReadyBooking(withAgent: true);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => array_merge($staff->meta ?? [], ['staff_permissions' => []]),
        ])->save();

        $this->actingAs($staff->fresh())->post(route('staff.bookings.issue-ticket', $booking))->assertForbidden();
    }

    public function test_agent_cannot_issue_ticket_via_admin_route(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking] = $this->ticketingReadyBooking(withAgent: true);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agentUser)->post(route('admin.bookings.issue-ticket', $booking))->assertForbidden();
    }

    public function test_staff_cannot_approve_or_payout(): void
    {
        [$agent] = $this->agentForAgency();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $entry = $this->pendingEntry($agent);

        $this->actingAs($staff)->post(route('admin.commissions.entries.approve', $entry))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.commissions.payouts.store', $agent), ['amount' => 100])->assertForbidden();
    }

    public function test_platform_admin_can_record_adjustment(): void
    {
        [$agent, $admin] = $this->agentForAgency();
        $this->actingAs($admin)->post(route('admin.commissions.adjustments.store', $agent), ['amount' => 250, 'description' => 'Manual add'])->assertRedirect();

        $this->assertDatabaseHas('agent_commission_entries', ['agent_id' => $agent->id, 'type' => 'adjustment', 'commission_amount' => 250]);
    }

    public function test_platform_admin_can_record_payout(): void
    {
        [$agent, $admin] = $this->agentForAgency();
        $this->actingAs($admin)->post(route('admin.commissions.payouts.store', $agent), ['amount' => 250, 'description' => 'Payout'])->assertRedirect();

        $this->assertDatabaseHas('agent_commission_entries', ['agent_id' => $agent->id, 'type' => 'payout', 'status' => 'paid']);
    }

    public function test_payout_reduces_offsets_balance(): void
    {
        [$agent, $admin] = $this->agentForAgency();
        app(AgentCommissionService::class)->recordAdjustment($agent, $admin, ['amount' => 500]);
        app(AgentCommissionService::class)->recordPayout($agent, $admin, ['amount' => 200]);

        $balance = app(AgentCommissionService::class)->calculateBalance($agent);
        $this->assertSame(300.0, $balance);
    }

    public function test_statement_can_be_generated(): void
    {
        [$agent, $admin] = $this->agentForAgency();
        app(AgentCommissionService::class)->recordAdjustment($agent, $admin, ['amount' => 500]);

        $this->actingAs($admin)->post(route('admin.commissions.statements.store', $agent))->assertRedirect();
        $this->assertDatabaseHas('agent_commission_statements', ['agent_id' => $agent->id]);
    }

    public function test_statement_links_entries(): void
    {
        [$agent, $admin] = $this->agentForAgency();
        $entry = app(AgentCommissionService::class)->recordAdjustment($agent, $admin, ['amount' => 500]);

        $this->actingAs($admin)->post(route('admin.commissions.statements.store', $agent))->assertRedirect();
        $statement = AgentCommissionStatement::query()->where('agent_id', $agent->id)->latest('id')->firstOrFail();
        $this->assertTrue($statement->entries()->where('entry_id', $entry->id)->exists());
    }

    public function test_commission_entry_stores_calculation_snapshot(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->ticketingReadyBooking(withAgent: true, withAgentRuleMeta: true);
        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();
        $entry = AgentCommissionEntry::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertNotEmpty($entry->meta);
        $this->assertArrayHasKey('source', $entry->meta);
    }

    public function test_changing_rules_later_does_not_alter_old_commission_entry(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->ticketingReadyBooking(withAgent: true);
        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();
        $entry = AgentCommissionEntry::query()->where('booking_id', $booking->id)->firstOrFail();
        $oldAmount = (float) $entry->commission_amount;

        MarkupRule::query()->where('agency_id', $booking->agency_id)->update(['value' => 50]);
        $this->assertSame($oldAmount, (float) $entry->fresh()->commission_amount);
    }

    /**
     * @return array{0: Agent, 1: User}
     */
    protected function agentForAgency(): array
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();

        return [$agent, $admin];
    }

    protected function pendingEntry(Agent $agent): AgentCommissionEntry
    {
        return AgentCommissionEntry::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'type' => 'earned',
            'status' => 'pending',
            'calculation_basis' => 'percentage',
            'rate' => 5,
            'base_amount' => 10000,
            'commission_amount' => 500,
            'currency' => 'PKR',
        ]);
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function ticketingReadyBooking(bool $withAgent, bool $withAgentRuleMeta = false): array
    {
        $this->mock(PiaNdcSupplierTicketingAdapter::class, function ($mock): void {
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
        $connection = SupplierConnection::query()->where('agency_id', $admin->current_agency_id)->where('provider', SupplierProvider::Duffel)->firstOrFail();
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
            'meta' => $withAgentRuleMeta ? [
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
        $booking->fareBreakdown()->create([
            'base_fare' => 10000,
            'taxes' => 2000,
            'fees' => 500,
            'markup' => 500,
            'discount' => 0,
            'total' => 13000,
            'currency' => 'PKR',
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
        ]);
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
