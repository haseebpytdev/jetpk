<?php

namespace Tests\Feature\Agent;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\TestCase;

class AgentPortalFinanceScenarioTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use RefreshDatabase;

    public function test_wallet_page_renders_without_transactions(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agent = Agent::factory()->create(['agency_id' => $agency->id, 'user_id' => $admin->id]);
        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $admin->id,
            'balance' => 0,
            'credit_limit' => null,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-wallet-kpis"', false);
    }

    public function test_wallet_page_renders_with_real_wallet_data(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('25,000.00', false)
            ->assertSee('Credit limit', false)
            ->assertSee('data-testid="agent-wallet-credit-notice"', false);
    }

    public function test_ledger_page_renders_empty_state_when_no_transactions(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agent = Agent::factory()->create(['agency_id' => $agency->id, 'user_id' => $admin->id]);
        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $admin->id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-ledger-table"', false);
    }

    public function test_ledger_page_renders_multiple_transactions_with_balances(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $tx = $scenario['recordsA']['transactions']['adminCredit'];

        $this->actingAs($scenario['adminA'])->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-ledger-row-'.$tx->id.'"', false)
            ->assertSee('ADMIN-CREDIT-A', false)
            ->assertSee('Manual adjustment without reference', false);
    }

    public function test_ledger_filters_by_type(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $hold = $scenario['recordsA']['transactions']['bookingHold'];

        $this->actingAs($scenario['adminA'])->get(route('agent.ledger.index', ['type' => 'booking_hold']))
            ->assertOk()
            ->assertSee('data-testid="agent-ledger-row-'.$hold->id.'"', false)
            ->assertDontSee('ADMIN-CREDIT-A', false);
    }

    public function test_available_balance_matches_wallet_service(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $summary = app(AgentWalletService::class)->summary($scenario['agentA']);

        $this->actingAs($scenario['adminA'])->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee(number_format($summary['available_balance'], 2), false);

        $this->assertSame(25000.0, $summary['balance']);
        $this->assertSame(50000.0, $summary['credit_limit']);
        $this->assertSame(75000.0, $summary['available_balance']);
        $this->assertSame(5000.0, $summary['pending_deposits']);
    }

    public function test_pending_deposits_display_on_deposits_index(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertSee('DEP-PENDING-A', false)
            ->assertSee('DEP-APPROVED-A', false);
    }

    public function test_deposit_create_visible_only_with_payments_upload(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['staff']['A5'])->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-deposits-create-link"', false);

        $this->actingAs($scenario['staff']['A3'])->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertDontSee('data-testid="agent-deposits-create-link"', false);
    }

    public function test_wallet_hides_admin_credit_mutation_actions(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertDontSee('Assign credit', false)
            ->assertDontSee('Admin credit', false)
            ->assertSee('display-only', false);
    }

    public function test_no_wallet_mutation_route_exposed_to_agent_users(): void
    {
        $scenario = $this->buildAgentPortalScenario();

        $this->actingAs($scenario['adminA'])->post('/agent/wallet/credit', [])->assertStatus(404);
        $response = $this->actingAs($scenario['staff']['A11'])->patch('/agent/wallet', []);
        $this->assertContains($response->status(), [404, 405]);
    }
}
