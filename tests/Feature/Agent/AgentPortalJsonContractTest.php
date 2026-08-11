<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalJsonContractTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_agent_dashboard_json_requires_agent_portal_role(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $scenario['agencyA']->id,
        ]);

        $this->actingAs($customer)
            ->getJson(route('agent.dashboard', ['format' => 'json']))
            ->assertForbidden();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.dashboard', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['metrics' => ['total_bookings', 'pending_payment'], 'capabilities']);
    }

    public function test_agent_staff_without_wallet_permission_cannot_access_wallet_json(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $staff = $scenario['staff']['A1'];

        $this->actingAs($staff)
            ->getJson(route('agent.wallet.show', ['format' => 'json']))
            ->assertForbidden();

        $this->actingAs($scenario['staff']['A3'])
            ->getJson(route('agent.wallet.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['summary' => ['balance', 'available_balance', 'currency']]);
    }

    public function test_agent_bookings_json_pagination_and_ownership(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $booking = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.bookings.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['booking_reference' => $booking->booking_reference]);

        $this->actingAs($scenario['adminB'])
            ->getJson(route('agent.bookings.show', ['booking' => $booking->booking_reference, 'format' => 'json']))
            ->assertForbidden();
    }

    public function test_agent_deposits_create_json_and_blade_preserved(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A5'])
            ->getJson(route('agent.deposits.create', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['fields', 'submit_url']);

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.deposits.index'))
            ->assertOk();
    }

    public function test_agent_support_json_create_and_detail(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $booking = $scenario['recordsA']['bookings']['paid'];

        $this->actingAs($scenario['adminA'])
            ->postJson(route('agent.support.tickets.store'), [
                'subject' => 'Wallet help',
                'category' => 'payment',
                'body' => 'Please assist with wallet.',
                'booking_id' => $booking->id,
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $ticket = SupportTicket::query()->where('created_by_user_id', $scenario['adminA']->id)->firstOrFail();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.support.tickets.show', ['ticket' => $ticket->ticket_reference, 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ticket.reference', $ticket->ticket_reference);
    }

    public function test_agent_notifications_json_returns_available_inbox(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.notifications.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('available', true)
            ->assertJsonPath('transport', 'EVENT_POLLING')
            ->assertJsonPath('unread_count', 0);
    }

    public function test_agent_profile_json_and_blade_routes_preserved(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])
            ->getJson(route('agent.profile.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.email', $scenario['adminA']->email);

        $this->actingAs($scenario['adminA'])
            ->get(route('agent.dashboard'))
            ->assertOk();
    }
}
