<?php

namespace Tests\Feature\Api\Dashboard;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Dashboard\Api\DashboardDepositsReadService;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * OPS-DEF-0008 — dashboard deposits index must isolate by agent_id for agent portal users.
 */
class DashboardDepositsIsolationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_platform_admin_sees_deposits_across_agents(): void
    {
        [$agentA, $depositA, $depositB] = $this->sameAgencyTwoAgentDeposits();

        $admin = $this->platformAdmin();
        $this->actingAs($admin);
        $result = app(DashboardDepositsReadService::class)->paginate(
            $admin,
            Request::create('/api/dashboard/deposits', 'GET'),
        );

        $ids = collect($result['items'])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertContains((string) $depositA->id, $ids);
        $this->assertContains((string) $depositB->id, $ids);
        $this->assertGreaterThanOrEqual(2, $result['pagination']['total']);
        $this->assertSame($agentA->id, $depositA->agent_id);
    }

    public function test_agent_a_index_sees_own_deposit_not_peer_same_agency(): void
    {
        [$agentAUser, $depositA, $depositB] = $this->sameAgencyTwoAgentDepositsWithUsers();

        $this->actingAs($agentAUser);
        $result = app(DashboardDepositsReadService::class)->paginate(
            $agentAUser,
            Request::create('/api/dashboard/deposits', 'GET'),
        );

        $ids = collect($result['items'])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertContains((string) $depositA->id, $ids);
        $this->assertNotContains((string) $depositB->id, $ids);
        $this->assertSame(1, $result['pagination']['total']);
        $this->assertCount(1, $result['items']);
    }

    public function test_agent_a_does_not_see_other_agency_deposit(): void
    {
        [$agentAUser, $depositA] = $this->agentAWithOwnDeposit();
        $otherAgencyDeposit = $this->depositInOtherAgency();

        $this->actingAs($agentAUser);
        $result = app(DashboardDepositsReadService::class)->paginate(
            $agentAUser,
            Request::create('/api/dashboard/deposits', 'GET'),
        );

        $ids = collect($result['items'])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertContains((string) $depositA->id, $ids);
        $this->assertNotContains((string) $otherAgencyDeposit->id, $ids);
    }

    public function test_agent_a_detail_for_agent_b_deposit_is_denied(): void
    {
        [$agentAUser, , $depositB] = $this->sameAgencyTwoAgentDepositsWithUsers();

        $this->actingAs($agentAUser);
        $detail = app(DashboardDepositsReadService::class)->detail($agentAUser, (string) $depositB->id);

        $this->assertNull($detail);
    }

    public function test_agent_a_detail_for_own_deposit_allowed(): void
    {
        [$agentAUser, $depositA] = $this->agentAWithOwnDeposit();

        $this->actingAs($agentAUser);
        $detail = app(DashboardDepositsReadService::class)->detail($agentAUser, (string) $depositA->id);

        $this->assertNotNull($detail);
        $this->assertSame((string) $depositA->id, (string) ($detail['id'] ?? ''));
    }

    public function test_wallet_view_staff_without_permission_denied_on_view_any(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $staff = $this->createStaffForAgent($agent, 'no-wallet-view@example.test', [AgentPermission::BookingsView]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(DashboardDepositsReadService::class)->paginate(
            $staff,
            Request::create('/api/dashboard/deposits', 'GET'),
        );
    }

    public function test_agent_portal_user_without_agent_relation_fails_closed(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $orphan = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
            'email' => 'orphan-agent-no-row@example.test',
        ]);
        $agency->users()->attach($orphan->id, ['role' => 'agent']);
        $this->assertNull($orphan->fresh()->agent());

        $this->depositForSeededAgent();

        // Policy viewAny requires agent() !== null; scopedQuery also fail-closes.
        $this->actingAs($orphan->fresh());
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(DashboardDepositsReadService::class)->paginate(
            $orphan->fresh(),
            Request::create('/api/dashboard/deposits', 'GET'),
        );
    }

    public function test_http_agents_view_middleware_blocks_agent_portal_deposits_index(): void
    {
        [$agentUser] = $this->seededAgent();

        // Route is gated by dashboard.permission:agents.view (platform-admin only today).
        $this->actingAs($agentUser)
            ->getJson(route('api.dashboard.deposits.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Agent, 1: AgentDepositRequest, 2: AgentDepositRequest}
     */
    protected function sameAgencyTwoAgentDeposits(): array
    {
        [$agentAUser, $agentA] = $this->seededAgent();
        $agentB = $this->otherAgentSameAgency();

        $depositA = $this->createDeposit($agentA, $agentAUser, 1000, 'OWN-A');
        $depositB = $this->createDeposit($agentB, $agentB->user, 9999, 'PEER-B');

        return [$agentA, $depositA, $depositB];
    }

    /**
     * @return array{0: User, 1: AgentDepositRequest, 2: AgentDepositRequest}
     */
    protected function sameAgencyTwoAgentDepositsWithUsers(): array
    {
        [$agentAUser, $agentA] = $this->seededAgent();
        $agentB = $this->otherAgentSameAgency();

        $depositA = $this->createDeposit($agentA, $agentAUser, 1000, 'OWN-A');
        $depositB = $this->createDeposit($agentB, $agentB->user, 9999, 'PEER-B');

        return [$agentAUser, $depositA, $depositB];
    }

    /**
     * @return array{0: User, 1: AgentDepositRequest}
     */
    protected function agentAWithOwnDeposit(): array
    {
        [$agentAUser, $agentA] = $this->seededAgent();
        $depositA = $this->createDeposit($agentA, $agentAUser, 1000, 'OWN-A');

        return [$agentAUser, $depositA];
    }

    protected function depositForSeededAgent(): AgentDepositRequest
    {
        [$agentUser, $agent] = $this->seededAgent();

        return $this->createDeposit($agent, $agentUser, 500, 'SEEDED');
    }

    protected function depositInOtherAgency(): AgentDepositRequest
    {
        $agency = Agency::factory()->create(['slug' => 'other-agency-iso-'.uniqid()]);
        $user = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($user->id, ['role' => 'agent']);
        $agent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
        ]);

        return $this->createDeposit($agent, $user, 7777, 'OTHER-AGENCY');
    }

    protected function createDeposit(Agent $agent, User $user, float $amount, string $reference): AgentDepositRequest
    {
        $wallet = app(AgentWalletService::class)->walletFor($agent);

        return AgentDepositRequest::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => $amount,
            'currency' => 'PKR',
            'status' => 'submitted',
            'reference' => $reference,
        ]);
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seededAgent(): array
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }

    protected function otherAgentSameAgency(): Agent
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $otherUser = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($otherUser->id, ['role' => 'agent']);

        return Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $otherUser->id,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function createStaffForAgent(Agent $agent, string $email, array $permissions): User
    {
        $agency = Agency::query()->findOrFail($agent->agency_id);
        $staff = User::factory()->create([
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
            'email' => $email,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => $permissions,
            ],
        ]);
        $agency->users()->attach($staff->id, ['role' => 'agent_staff']);

        return $staff;
    }
}
