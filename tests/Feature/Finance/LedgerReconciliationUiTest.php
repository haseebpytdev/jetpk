<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\TestCase;

class LedgerReconciliationUiTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_platform_admin_can_access_reconciliation_dashboard(): void
    {
        $admin = $this->platformAdmin();

        $this->assertLegacyAccountingRedirect($admin, route('admin.accounting.reconciliation.index'));
        $html = $this->adminAccountingReconciliationIndexHtml($admin);
        $this->assertStringContainsString('data-testid="accounting-reconciliation-cards"', $html);
        $this->assertStringContainsString('data-testid="accounting-reconciliation-title"', $html);
    }

    public function test_staff_with_ledger_permission_can_access_reconciliation_dashboard(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::LedgerView]);

        $this->assertLegacyStaffAccountingRedirect($staff, route('staff.accounting.reconciliation.index'));
        $html = $this->staffAccountingReconciliationIndexHtml($staff);
        $this->assertStringContainsString('data-testid="accounting-reconciliation-cards"', $html);
    }

    public function test_staff_without_ledger_permission_gets_403_on_reconciliation(): void
    {
        $staff = $this->staffWithPermissions([StaffPermission::BookingsView]);

        $this->actingAs($staff);
        $request = Request::create('/staff/accounting/reconciliation', 'GET');
        $request->setUserResolver(fn () => $staff);

        try {
            app(\App\Http\Controllers\Staff\AccountingReconciliationController::class)->index($request);
            $this->fail('Expected staff reconciliation index to deny access.');
        } catch (AuthorizationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_reconciliation_cards_calculate_wallet_vs_ledger_difference(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();

        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agency->id,
                'user_id' => $agent->user_id,
                'balance' => 10000,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );
        $wallet->update(['balance' => 10000]);

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 5000,
            'currency' => 'PKR',
            'reference' => 'DEP-RECON-'.uniqid(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $this->assertLegacyAccountingRedirect($admin, route('admin.accounting.reconciliation.index'));
        $html = $this->adminAccountingReconciliationIndexHtml($admin);
        $this->assertStringContainsString('Wallet balance (source of truth)', $html);
        $this->assertStringContainsString('Agency wallet liability (ledger)', $html);
        $this->assertStringContainsString('data-testid="reconciliation-agency-'.$agency->id.'"', $html);
    }

    public function test_empty_production_reconciliation_shows_zero_safely(): void
    {
        $admin = $this->platformAdmin();

        $this->assertLegacyAccountingRedirect($admin, route('admin.accounting.reconciliation.index'));
        $html = $this->adminAccountingReconciliationIndexHtml($admin);
        $this->assertStringContainsString('data-testid="accounting-reconciliation-cards"', $html);
        $this->assertStringContainsString('None yet', $html);
    }

    public function test_agent_accounting_ledger_shows_agency_summary(): void
    {
        [$agentUser, $agent] = $this->seedAgent();
        $this->seedDepositForAgency($agent->agency_id, $agent);

        $this->actingAs($agentUser)->get(route('agent.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="agent-accounting-summary"', false)
            ->assertSee('Ledger liability', false);
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin;
    }

    protected function staffWithPermissions(array $permissions): User
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => array_merge($staff->meta ?? [], ['staff_permissions' => $permissions]),
        ])->save();

        return $staff->fresh();
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seedAgent(): array
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }

    protected function seedDepositForAgency(int $agencyId, Agent $agent): void
    {
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agencyId,
                'user_id' => $agent->user_id,
                'balance' => 0,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agencyId,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 2500,
            'currency' => 'PKR',
            'reference' => 'DEP-AGENT-'.uniqid(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        app(AgentWalletService::class)->approveDeposit($deposit, $this->platformAdmin());
    }
}
