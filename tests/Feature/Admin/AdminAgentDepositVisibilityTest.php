<?php

namespace Tests\Feature\Admin;

use App\Enums\AgentDepositRequestStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminAgentDepositVisibilityTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_platform_admin_dashboard_shows_pending_deposits_alert(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 3000,
            'reference' => 'VIS-DASH-1',
        ]);

        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => null])->save();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="ota-command-banner-pending-deposits"', false)
            ->assertSee('1 pending deposit', false)
            ->assertSee(route('admin.agent-deposits.index', ['status' => 'submitted']), false);
    }

    public function test_platform_admin_deposit_index_lists_all_agencies_when_no_agency_context(): void
    {
        $primaryAgency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $secondaryAgency = Agency::query()->create([
            'name' => 'Beta Travel',
            'slug' => 'beta-travel',
            'timezone' => 'Asia/Karachi',
            'settings' => ['code_prefix' => 'BETA'],
        ]);

        [$primaryUser, $primaryAgent] = $this->seededAgent();
        $secondaryUser = User::factory()->agent()->create(['current_agency_id' => $secondaryAgency->id]);
        $secondaryAgency->users()->attach($secondaryUser->id, ['role' => 'agent']);
        $secondaryAgent = Agent::factory()->create([
            'agency_id' => $secondaryAgency->id,
            'user_id' => $secondaryUser->id,
        ]);

        $walletService = app(AgentWalletService::class);
        $walletService->submitDepositRequest($primaryAgent, $primaryUser, [
            'amount' => 1000,
            'reference' => 'PRIMARY-DEP',
        ]);
        $walletService->submitDepositRequest($secondaryAgent, $secondaryUser, [
            'amount' => 2000,
            'reference' => 'BETA-DEP',
        ]);

        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => null])->save();

        $this->actingAs($admin)
            ->get(route('admin.agent-deposits.index', ['status' => 'submitted']))
            ->assertOk()
            ->assertSee('Asif Travels', false)
            ->assertSee('Beta Travel', false)
            ->assertSee('PRIMARY-DEP', false)
            ->assertSee('BETA-DEP', false)
            ->assertSee('Agency code: BETA', false);
    }

    public function test_submitted_status_filter_matches_pending_deposits(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $wallet = app(AgentWalletService::class)->walletFor($agent);

        AgentDepositRequest::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agentUser->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 500,
            'currency' => 'PKR',
            'status' => AgentDepositRequestStatus::Approved,
            'reference' => 'OLD-APPROVED',
        ]);

        app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 750,
            'reference' => 'NEW-PENDING',
        ]);

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.agent-deposits.index', ['status' => 'submitted']))
            ->assertOk()
            ->assertSee('NEW-PENDING', false)
            ->assertDontSee('OLD-APPROVED', false);
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
}
