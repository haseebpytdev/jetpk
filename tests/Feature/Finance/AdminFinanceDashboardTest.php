<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\BookingPayment;
use App\Models\LedgerTransaction;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class AdminFinanceDashboardTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_platform_admin_can_view_dashboard(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-summary-cards"', false)
            ->assertSee('data-testid="finance-dashboard-readonly-notice"', false);
    }

    public function test_staff_cannot_view_dashboard(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.finance.dashboard'))->assertForbidden();
    }

    public function test_agent_cannot_view_dashboard(): void
    {
        $agency = Agency::factory()->create();
        $agent = $this->createAgentForAgency($agency);

        $this->actingAs(User::query()->findOrFail($agent->user_id))
            ->get(route('admin.finance.dashboard'))
            ->assertForbidden();
    }

    public function test_customer_cannot_view_dashboard(): void
    {
        $customer = User::query()->create([
            'name' => 'Cust Dash',
            'username' => 'cust-dash-'.uniqid(),
            'email' => 'cust-dash-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
        ]);

        $this->actingAs($customer)->get(route('admin.finance.dashboard'))->assertForbidden();
    }

    public function test_dashboard_shows_wallet_total(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(125.50);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-wallet-total"', false)
            ->assertSee('125.50', false);
    }

    public function test_dashboard_shows_ledger_liability_total(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 80);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-ledger-total"', false)
            ->assertSee('80.00', false);
    }

    public function test_dashboard_shows_reconciliation_matched_when_values_match(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 50);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-reconciliation-status"', false)
            ->assertSee('Matched', false);
    }

    public function test_dashboard_shows_mismatch_alert_when_values_differ(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(100);
        $wallet->update(['balance' => 200]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-reconciliation-status"', false)
            ->assertSee('Mismatch', false)
            ->assertSee('data-testid="finance-dashboard-mismatch-count"', false);
    }

    public function test_dashboard_shows_recent_ledger_transaction(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 30);

        $ledger = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::ManualWalletCredit)
            ->firstOrFail();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-recent-ledger"', false)
            ->assertSee($ledger->transaction_ref, false);
    }

    public function test_dashboard_shows_recent_manual_adjustment(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 22);

        $tx = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->firstOrFail();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-recent-adjustments"', false)
            ->assertSee($tx->reference ?? '', false);
    }

    public function test_dashboard_shows_recent_deposit(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $agent = Agent::query()->findOrFail($wallet->agent_id);

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 1500,
            'currency' => 'PKR',
            'reference' => 'DEP-DASH-'.uniqid(),
            'status' => AgentDepositRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-recent-deposits"', false)
            ->assertSee($deposit->reference, false);
    }

    public function test_dashboard_shows_agency_exposure_row(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(75);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-agency-exposure"', false)
            ->assertSee($agency->name, false)
            ->assertSee('data-testid="finance-dashboard-agency-row"', false);
    }

    public function test_dashboard_page_is_read_only_and_creates_no_finance_rows(): void
    {
        $countsBefore = $this->financeRowCounts();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard'))
            ->assertOk();

        $this->assertSame($countsBefore, $this->financeRowCounts());
    }

    public function test_existing_finance_statements_still_work(): void
    {
        $agency = Agency::factory()->create();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.statements.index'))
            ->assertOk();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.statements.show', $agency))
            ->assertOk();
    }

    public function test_existing_manual_adjustments_pages_still_work(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.adjustments.index'))
            ->assertOk();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.adjustments.create'))
            ->assertOk();
    }

    public function test_existing_accounting_ledger_and_reconciliation_pages_still_work(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.accounting.ledger.index'))
            ->assertOk();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.accounting.reconciliation.index'))
            ->assertOk();
    }

    /**
     * @return array<string, int>
     */
    protected function financeRowCounts(): array
    {
        return [
            'wallets' => AgentWallet::query()->count(),
            'wallet_transactions' => AgentWalletTransaction::query()->count(),
            'ledger_transactions' => LedgerTransaction::query()->count(),
            'deposits' => AgentDepositRequest::query()->count(),
            'booking_payments' => BookingPayment::query()->count(),
        ];
    }

    /**
     * @return array{0: Agency, 1: AgentWallet}
     */
    protected function seedAgencyWallet(float $balance): array
    {
        $agency = Agency::factory()->create();
        $agent = $this->createAgentForAgency($agency);
        $wallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'balance' => $balance,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        return [$agency, $wallet];
    }

    protected function createAgentForAgency(Agency $agency): Agent
    {
        $user = User::query()->create([
            'name' => 'Agent '.$agency->id,
            'username' => 'dash-agent-'.$agency->id.'-'.uniqid(),
            'email' => 'dash-'.$agency->id.'-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);

        return Agent::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(Agency $agency, AgentWallet $wallet, string $type, float $amount): array
    {
        return [
            'agency_id' => $agency->id,
            'wallet_id' => $wallet->id,
            'adjustment_type' => $type,
            'amount' => $amount,
            'adjustment_reason' => 'bank_correction',
            'adjustment_note' => 'Dashboard test',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation' => '1',
        ];
    }

    protected function postAdjustment(Agency $agency, AgentWallet $wallet, string $type, float $amount): void
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, $type, $amount))
            ->assertRedirect();
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
