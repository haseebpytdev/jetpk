<?php

namespace Tests\Feature\Agent;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_admin_can_view_wallet_and_ledger(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $this->seedWalletTransaction($agent);

        $this->actingAs($agentUser)->get(route('agent.wallet.show'))->assertOk();
        $this->actingAs($agentUser)->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-ledger-table"', false);
    }

    public function test_agent_staff_without_ledger_permission_cannot_access_ledger(): void
    {
        [, $agent] = $this->seedAgent();
        $staff = $this->createStaffForAgent($agent, 'nowallet-ledger@test', [AgentPermission::WalletView]);

        $this->actingAs($staff)->get(route('agent.ledger.index'))->assertForbidden();
    }

    public function test_agent_staff_with_ledger_view_can_access_ledger(): void
    {
        [, $agent] = $this->seedAgent();
        $this->seedWalletTransaction($agent);
        $staff = $this->createStaffForAgent($agent, 'ledger@test', [AgentPermission::LedgerView, AgentPermission::WalletView]);

        $this->actingAs($staff)->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-ledger-table"', false);
    }

    public function test_agent_staff_cannot_see_another_agents_ledger_entries(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $agentA = Agent::query()->whereHas('user', fn ($q) => $q->where('email', 'agent@ota.demo'))->firstOrFail();
        $agentBUser = User::query()->where('email', 'agent2@ota.demo')->first();
        if ($agentBUser === null) {
            $this->markTestSkipped('Second demo agent not seeded.');
        }
        $agentB = Agent::query()->where('user_id', $agentBUser->id)->firstOrFail();

        $txA = $this->seedWalletTransaction($agentA);
        $txB = $this->seedWalletTransaction($agentB);

        $staffB = $this->createStaffForAgent($agentB, 'staff-b@test', [AgentPermission::LedgerView]);

        $this->actingAs($staffB)->get(route('agent.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-ledger-row-'.$txB->id.'"', false)
            ->assertDontSee('data-testid="agent-ledger-row-'.$txA->id.'"', false);
    }

    protected function seedWalletTransaction(Agent $agent): AgentWalletTransaction
    {
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agent->agency_id,
                'user_id' => $agent->user_id,
                'balance' => 100,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );

        return AgentWalletTransaction::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'type' => 'deposit_approved',
            'amount' => 50,
            'balance_before' => 50,
            'balance_after' => 100,
            'status' => 'posted',
            'reference' => 'TEST-REF-'.$agent->id,
            'description' => 'Test deposit',
        ]);
    }

    protected function createStaffForAgent(Agent $agent, string $email, array $permissions = []): User
    {
        return User::query()->create([
            'name' => 'Staff User',
            'username' => str_replace('@', '-', $email),
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agent->agency_id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => $permissions,
            ],
        ]);
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seedAgent(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }
}
