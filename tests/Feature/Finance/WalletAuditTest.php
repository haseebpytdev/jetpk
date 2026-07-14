<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\UserAccountStatus;
use App\Enums\WalletAuditClassification;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Wallets\WalletAuditService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class WalletAuditTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_platform_admin_can_view_wallet_audit_page(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.wallet-audit.index'))
            ->assertOk()
            ->assertSee('data-testid="wallet-audit-summary-cards"', false)
            ->assertSee('data-testid="wallet-audit-readonly-notice"', false);
    }

    public function test_staff_cannot_view_wallet_audit_page(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.finance.wallet-audit.index'))->assertForbidden();
    }

    public function test_agent_cannot_view_wallet_audit_page(): void
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agentUser)->get(route('admin.finance.wallet-audit.index'))->assertForbidden();
    }

    public function test_customer_cannot_view_wallet_audit_page(): void
    {
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)->get(route('admin.finance.wallet-audit.index'))->assertForbidden();
    }

    public function test_audit_classifies_canonical_wallet_correctly(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $row = $this->walletRowFor($agency->id, (int) $canonical?->id);

        $this->assertSame(WalletAuditClassification::Canonical->value, $row['classification']);
        $this->assertTrue($row['is_canonical']);
    }

    public function test_audit_classifies_zero_balance_duplicate_with_no_references_as_cleanup_candidate(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonicalId = (int) app(AgentWalletService::class)->canonicalWalletForAgency($agency)?->id;

        $duplicate = collect($wallets)->first(fn (AgentWallet $w): bool => (int) $w->id !== $canonicalId);
        $this->assertNotNull($duplicate);

        $row = $this->walletRowFor($agency->id, (int) $duplicate->id);

        $this->assertSame(WalletAuditClassification::CleanupCandidate->value, $row['classification']);
        $this->assertSame(0, $row['transaction_count']);
        $this->assertSame(0, $row['deposit_request_count']);
    }

    public function test_audit_classifies_duplicate_with_transactions_as_historical_active(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 25]);
        $canonicalId = (int) app(AgentWalletService::class)->canonicalWalletForAgency($agency)?->id;
        $nonCanonical = collect($wallets)->first(fn (AgentWallet $w): bool => (int) $w->id !== $canonicalId);
        $this->assertNotNull($nonCanonical);

        AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $nonCanonical->agent_id,
            'user_id' => $nonCanonical->user_id,
            'agent_wallet_id' => $nonCanonical->id,
            'type' => AgentWalletTransactionType::ManualCredit,
            'amount' => 1,
            'balance_before' => 24,
            'balance_after' => 25,
            'status' => 'posted',
        ]);

        $row = $this->walletRowFor($agency->id, (int) $nonCanonical->id);

        $this->assertSame(WalletAuditClassification::HistoricalActive->value, $row['classification']);
        $this->assertGreaterThan(0, $row['transaction_count']);
    }

    public function test_audit_classifies_duplicate_with_deposit_requests_as_historical_active_or_review(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 50]);
        $canonicalId = (int) app(AgentWalletService::class)->canonicalWalletForAgency($agency)?->id;
        $duplicate = collect($wallets)->first(fn (AgentWallet $w): bool => (int) $w->id !== $canonicalId);
        $this->assertNotNull($duplicate);

        AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $duplicate->agent_id,
            'user_id' => $duplicate->user_id,
            'agent_wallet_id' => $duplicate->id,
            'amount' => 10,
            'currency' => 'PKR',
            'status' => AgentDepositRequestStatus::Approved,
        ]);

        $row = $this->walletRowFor($agency->id, (int) $duplicate->id);

        $this->assertContains($row['classification'], [
            WalletAuditClassification::HistoricalActive->value,
            WalletAuditClassification::ReviewRequired->value,
        ]);
        $this->assertGreaterThan(0, $row['deposit_request_count']);
    }

    public function test_audit_summary_counts_agencies_with_multiple_wallets(): void
    {
        $this->seedMultiWallets([0, 0, 100]);
        $this->seedMultiWallets([10]);

        $summary = app(WalletAuditService::class)->build()['summary'];

        $this->assertGreaterThanOrEqual(2, $summary['total_agencies']);
        $this->assertSame(1, $summary['agencies_with_multiple_wallets']);
        $this->assertSame(1, $summary['agencies_with_one_wallet']);
        $this->assertGreaterThanOrEqual(2, $summary['total_duplicate_wallets']);
    }

    public function test_audit_works_when_agencies_table_has_no_legal_name_column(): void
    {
        $this->assertFalse(Schema::hasColumn('agencies', 'legal_name'));

        [$agency] = $this->seedMultiWallets([0, 0, 55]);
        $agency->forceFill(['name' => 'Schema Safe Agency'])->save();

        $report = app(WalletAuditService::class)->build(agencyId: $agency->id);

        $this->assertSame('Schema Safe Agency', $report['agencies'][0]['agency_name'] ?? null);

        Artisan::call('agent-wallets:audit', ['--agency' => $agency->id]);
        $this->assertStringContainsString('Schema Safe Agency', Artisan::output());
    }

    public function test_audit_command_outputs_canonical_wallet_id(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 75]);
        $canonicalId = (int) app(AgentWalletService::class)->canonicalWalletForAgency($agency)?->id;

        Artisan::call('agent-wallets:audit', ['--agency' => $agency->id]);
        $output = Artisan::output();

        $this->assertStringContainsString((string) $canonicalId, $output);
        $this->assertStringContainsString('Agencies with multiple wallets: 1', $output);
    }

    public function test_audit_command_supports_agency_filter(): void
    {
        [$agencyA] = $this->seedMultiWallets([100]);
        [$agencyB] = $this->seedMultiWallets([0, 0, 50]);

        Artisan::call('agent-wallets:audit', ['--agency' => $agencyA->id]);
        $output = Artisan::output();

        $this->assertStringContainsString('Agencies with 1 wallet: 1', $output);
        $this->assertStringNotContainsString('Agencies with multiple wallets: 1', $output);
    }

    public function test_audit_command_supports_only_candidates(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 80]);

        Artisan::call('agent-wallets:audit', [
            '--agency' => $agency->id,
            '--only-candidates' => true,
            '--format' => 'json',
        ]);

        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded['wallets']);
        foreach ($decoded['wallets'] as $row) {
            $this->assertSame(WalletAuditClassification::CleanupCandidate->value, $row['classification']);
        }
    }

    public function test_audit_command_supports_json_format(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 40]);

        Artisan::call('agent-wallets:audit', [
            '--agency' => $agency->id,
            '--format' => 'json',
        ]);

        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('wallets', $decoded);
        $this->assertArrayHasKey('agencies', $decoded);
        $this->assertSame(1, $decoded['summary']['agencies_with_multiple_wallets']);
    }

    public function test_wallet_audit_page_creates_no_wallet_ledger_deposit_or_transaction_rows(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 60]);

        $countsBefore = $this->financeTableCounts();

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.wallet-audit.index'))
            ->assertOk();

        $this->assertSame($countsBefore, $this->financeTableCounts());
    }

    /**
     * @return array<string, int>
     */
    protected function financeTableCounts(): array
    {
        return [
            'agent_wallets' => (int) DB::table('agent_wallets')->count(),
            'agent_wallet_transactions' => (int) DB::table('agent_wallet_transactions')->count(),
            'agent_deposit_requests' => (int) DB::table('agent_deposit_requests')->count(),
            'ledger_transactions' => (int) DB::table('ledger_transactions')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function walletRowFor(int $agencyId, int $walletId): array
    {
        $report = app(WalletAuditService::class)->build(agencyId: $agencyId);
        $row = collect($report['wallets'])->firstWhere('wallet_id', $walletId);
        $this->assertNotNull($row, 'Wallet row not found in audit report.');

        return $row;
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
            'username' => 'audit-'.$agency->id.'-'.uniqid(),
            'email' => 'audit-'.$agency->id.'-'.uniqid().'@example.test',
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
