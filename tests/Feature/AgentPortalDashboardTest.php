<?php

namespace Tests\Feature;

use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentPortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_dashboard_shows_kpis_and_recent_bookings(): void
    {
        [$agentUser, $booking] = $this->agentBooking();

        $this->actingAs($agentUser)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="agent-dashboard-kpis"', false)
            ->assertSee('My bookings', false)
            ->assertSee('Pending payment', false)
            ->assertSee('PNR created / confirmed', false)
            ->assertSee('Commission earned', false)
            ->assertSee('data-testid="agent-finance-summary"', false)
            ->assertSee('data-testid="agent-dashboard-wallet-balance"', false)
            ->assertSee('Booking credit enforcement is not enabled yet', false)
            ->assertSee($booking->booking_reference, false);
    }

    public function test_agent_bookings_index_supports_pending_payment_filter(): void
    {
        [$agentUser, $unpaid] = $this->agentBooking(['payment_status' => 'unpaid']);
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();
        $paid = Booking::factory()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'OTA-AGENT-PAID',
        ]);

        $this->actingAs($agentUser)->get(route('agent.bookings.index', ['filter' => 'pending_payment']))
            ->assertOk()
            ->assertSee('data-testid="agent-bookings-filters"', false)
            ->assertSee('ota-bstat', false)
            ->assertSee($unpaid->booking_reference, false)
            ->assertDontSee($paid->booking_reference, false);
    }

    public function test_agent_cannot_view_another_agents_booking(): void
    {
        [$agentUser] = $this->agentBooking();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $otherAgentUser = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($otherAgentUser->id, ['role' => 'agent']);
        $otherAgent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $otherAgentUser->id,
        ]);
        $otherBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $otherAgent->id,
            'source_channel' => 'agent_portal',
            'booking_reference' => 'OTA-OTHER-AGENT-E3',
        ]);

        $this->actingAs($agentUser)->get(route('agent.bookings.show', $otherBooking))->assertForbidden();
    }

    public function test_agent_booking_show_labels_itinerary_without_admin_diagnostics(): void
    {
        [$agentUser, $booking] = $this->agentBooking([
            'pnr' => 'AGT123',
            'meta' => [
                'pnr_itinerary_snapshot' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'DXB',
                            'departure_at' => now()->addDays(10)->toIso8601String(),
                            'arrival_at' => now()->addDays(10)->addHours(3)->toIso8601String(),
                            'marketing_carrier' => 'EK',
                            'flight_number' => '602',
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($agentUser)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="agent-itinerary-source"', false)
            ->assertSee('data-testid="agent-booking-commission"', false)
            ->assertDontSee('supplier_booking_attempts', false)
            ->assertDontSee('safe_summary', false)
            ->assertDontSee('Authorization', false);
    }

    public function test_agent_booking_show_displays_commission_entry_when_present(): void
    {
        [$agentUser, $booking] = $this->agentBooking();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        AgentCommissionEntry::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'booking_id' => $booking->id,
            'type' => AgentCommissionEntryType::Earned,
            'status' => AgentCommissionEntryStatus::Pending,
            'calculation_basis' => 'fixed',
            'base_amount' => 1000,
            'commission_amount' => 150,
            'currency' => 'PKR',
            'description' => 'Commission for booking',
        ]);

        $this->actingAs($agentUser)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Rs 150.00', false)
            ->assertSee('pending', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    protected function agentBooking(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'LHE → DXB',
            'booking_reference' => 'OTA-AGENT-E3-'.uniqid(),
        ], $overrides));

        return [$agentUser, $booking];
    }
}
