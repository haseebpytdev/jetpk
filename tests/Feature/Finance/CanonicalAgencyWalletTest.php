<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentWalletTransactionType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Dashboard\AdminFinanceDashboardService;
use App\Services\Finance\Ledger\LedgerBalanceService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class CanonicalAgencyWalletTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_canonical_wallet_for_agency_prefers_non_zero_wallet(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);

        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $this->assertNotNull($canonical);
        $this->assertSame((int) $wallets[2]->id, (int) $canonical->id);
    }

    public function test_canonical_wallet_prefers_latest_transaction_when_multiple_non_zero(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([50, 80]);
        $service = app(AgentWalletService::class);

        AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $wallets[0]->agent_id,
            'user_id' => $wallets[0]->user_id,
            'agent_wallet_id' => $wallets[0]->id,
            'type' => AgentWalletTransactionType::ManualCredit,
            'amount' => 1,
            'balance_before' => 49,
            'balance_after' => 50,
            'status' => 'posted',
            'created_at' => now()->subDay(),
        ]);

        AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $wallets[1]->agent_id,
            'user_id' => $wallets[1]->user_id,
            'agent_wallet_id' => $wallets[1]->id,
            'type' => AgentWalletTransactionType::ManualCredit,
            'amount' => 1,
            'balance_before' => 79,
            'balance_after' => 80,
            'status' => 'posted',
            'created_at' => now(),
        ]);

        $canonical = $service->canonicalWalletForAgency($agency);

        $this->assertSame((int) $wallets[1]->id, (int) $canonical?->id);
    }

    public function test_canonical_wallet_falls_back_to_owner_wallet(): void
    {
        $agency = Agency::factory()->create();
        $owner = $this->createAgentForAgency($agency, AccountType::Agent, true);
        $staffAgent = $this->createAgentForAgency($agency, AccountType::Agent, false);

        $ownerWallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $owner->id,
            'user_id' => $owner->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => 'active',
        ]);
        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $staffAgent->id,
            'user_id' => $staffAgent->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $this->assertSame((int) $ownerWallet->id, (int) $canonical?->id);
    }

    public function test_canonical_wallet_falls_back_to_oldest_active_wallet(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0]);

        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $this->assertSame((int) $wallets[0]->id, (int) $canonical?->id);
    }

    public function test_get_or_create_canonical_wallet_creates_when_none_exist(): void
    {
        $agency = Agency::factory()->create();
        $this->createAgentForAgency($agency, AccountType::Agent, true);

        $this->assertSame(0, AgentWallet::query()->where('agency_id', $agency->id)->count());

        $wallet = app(AgentWalletService::class)->getOrCreateCanonicalWalletForAgency($agency);

        $this->assertSame(1, AgentWallet::query()->where('agency_id', $agency->id)->count());
        $this->assertSame((int) $agency->id, (int) $wallet->agency_id);
    }

    public function test_get_or_create_canonical_wallet_does_not_create_duplicates(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $service = app(AgentWalletService::class);
        $before = AgentWallet::query()->where('agency_id', $agency->id)->count();

        $first = $service->getOrCreateCanonicalWalletForAgency($agency);
        $second = $service->getOrCreateCanonicalWalletForAgency($agency);

        $this->assertSame($before, AgentWallet::query()->where('agency_id', $agency->id)->count());
        $this->assertSame((int) $first->id, (int) $second->id);
    }

    public function test_manual_adjustment_create_shows_canonical_wallet_without_selector(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.adjustments.create', ['agency_id' => $agency->id]))
            ->assertOk()
            ->assertSee('data-testid="finance-adjustment-canonical-wallet"', false)
            ->assertSee('data-testid="finance-adjustment-duplicate-wallet-warning"', false)
            ->assertSee('Wallet #'.$canonical?->id, false)
            ->assertDontSee('Select wallet', false);
    }

    public function test_manual_credit_posts_to_canonical_wallet(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        $zeroWallet = $wallets[0];

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), [
            'agency_id' => $agency->id,
            'adjustment_type' => 'manual_credit',
            'amount' => 10,
            'adjustment_reason' => 'bank_correction',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation' => '1',
        ])->assertRedirect();

        $this->assertSame(110.0, (float) $canonical?->fresh()->balance);
        $this->assertSame(0.0, (float) $zeroWallet->fresh()->balance);
        $this->assertDatabaseHas('agent_wallet_transactions', [
            'agent_wallet_id' => $canonical?->id,
            'type' => AgentWalletTransactionType::ManualCredit->value,
            'amount' => 10,
        ]);
    }

    public function test_manual_debit_posts_to_canonical_wallet(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), [
            'agency_id' => $agency->id,
            'adjustment_type' => 'manual_debit',
            'amount' => 15,
            'adjustment_reason' => 'bank_correction',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation' => '1',
        ])->assertRedirect();

        $this->assertSame(85.0, (float) $canonical?->fresh()->balance);
        $this->assertSame(0.0, (float) $wallets[0]->fresh()->balance);
    }

    public function test_deposit_request_uses_canonical_wallet(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        $staffAgent = Agent::query()->whereKey($wallets[1]->agent_id)->firstOrFail();
        $staffUser = User::query()->findOrFail($staffAgent->user_id);

        app(AgentWalletService::class)->submitDepositRequest($staffAgent, $staffUser, [
            'amount' => 25,
            'payment_method' => 'Bank',
            'reference' => 'DEP-CANON',
        ]);

        $deposit = AgentDepositRequest::query()->where('reference', 'DEP-CANON')->firstOrFail();
        $this->assertSame((int) $canonical?->id, (int) $deposit->agent_wallet_id);
    }

    public function test_agent_staff_wallet_for_uses_agency_canonical_wallet(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        $staffAgent = Agent::query()->whereKey($wallets[1]->agent_id)->firstOrFail();

        $wallet = app(AgentWalletService::class)->walletFor($staffAgent);

        $this->assertSame((int) $canonical?->id, (int) $wallet->id);
        $this->assertSame(3, AgentWallet::query()->where('agency_id', $agency->id)->count());
    }

    public function test_agency_list_and_profile_still_show_summed_total(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('PKR 100.00', false);

        $this->actingAs($admin)->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'wallet']))
            ->assertOk()
            ->assertSee('PKR 100.00', false)
            ->assertSee('Canonical wallet', false);
    }

    public function test_finance_dashboard_remains_matched(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $compare = app(LedgerBalanceService::class)->compareWalletToLedger($agency->id);
        $dashboard = app(AdminFinanceDashboardService::class)->build();
        $exposure = collect($dashboard['agency_exposure'] ?? [])->firstWhere('agency_id', $agency->id);

        $this->assertSame(100.0, $compare['wallet_balance']);
        if ($exposure !== null) {
            $this->assertSame(100.0, (float) $exposure['wallet_balance']);
        }
    }

    public function test_statement_remains_correct_after_canonical_adjustment(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), [
            'agency_id' => $agency->id,
            'adjustment_type' => 'manual_credit',
            'amount' => 5,
            'adjustment_reason' => 'bank_correction',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation' => '1',
        ])->assertRedirect();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.statements.show', [
                'agency' => $agency,
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Manual wallet credit', false);
    }

    public function test_viewing_agency_pages_does_not_mutate_wallets(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $before = [
            'wallets' => AgentWallet::query()->count(),
            'transactions' => AgentWalletTransaction::query()->count(),
        ];

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->get(route('admin.agencies.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.agencies.show', $agency))->assertOk();
        $this->actingAs($admin)->get(route('admin.agencies.show', ['agency' => $agency, 'tab' => 'wallet']))->assertOk();
        $this->actingAs($admin)->get(route('admin.finance.adjustments.create', ['agency_id' => $agency->id]))->assertOk();

        $this->assertSame($before['wallets'], AgentWallet::query()->count());
        $this->assertSame($before['transactions'], AgentWalletTransaction::query()->count());
        $this->assertSame(100.0, app(AgentWalletService::class)->agencyWalletSummary($agency->id)['balance']);
    }

    /**
     * @param  list<float>  $balances
     * @return array{0: Agency, 1: list<AgentWallet>}
     */
    protected function seedMultiWallets(array $balances): array
    {
        $agency = Agency::factory()->create();
        $wallets = [];

        foreach ($balances as $index => $balance) {
            $agent = $this->createAgentForAgency($agency, AccountType::Agent, $index === 0);
            $wallets[] = AgentWallet::query()->create([
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
                'user_id' => $agent->user_id,
                'balance' => $balance,
                'currency' => 'PKR',
                'status' => 'active',
            ]);
        }

        return [$agency, $wallets];
    }

    protected function createAgentForAgency(Agency $agency, AccountType $type, bool $first): Agent
    {
        $user = User::query()->create([
            'name' => $first ? 'Owner '.$agency->id : 'Agent '.$agency->id.'-'.uniqid(),
            'username' => 'canon-'.$agency->id.'-'.uniqid(),
            'email' => 'canon-'.$agency->id.'-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'account_type' => $type,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);

        return Agent::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
