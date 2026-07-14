<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class FinanceExportAuditPackTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_platform_admin_can_export_finance_dashboard_csv(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(88.25);

        $response = $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.dashboard.export'));

        $response->assertOk();
        $this->assertCsvResponse($response);
        $this->assertStringContainsString('wallet_balance_total', $response->streamedContent());
        $this->assertStringContainsString('88.25', $response->streamedContent());
    }

    public function test_platform_admin_can_export_reconciliation_csv(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 40);

        $content = $this->actingAs($this->platformAdmin())
            ->get(route('admin.accounting.reconciliation.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('matched', $content);
        $this->assertStringContainsString((string) $agency->id, $content);
    }

    public function test_platform_admin_can_export_wallet_audit_csv(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $content = $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.wallet-audit.export', ['agency_id' => $agency->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('canonical', $content);
        $this->assertStringContainsString('cleanup_candidate', $content);
        $this->assertStringContainsString((string) $canonical?->id, $content);
    }

    public function test_platform_admin_can_export_manual_adjustments_csv(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 10);

        $original = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->firstOrFail();
        $this->actingAs($this->platformAdmin())->post(
            route('admin.finance.adjustments.reverse', $original),
            [
                'reversal_reason' => 'error_correction',
                'confirmation' => '1',
            ],
        )->assertRedirect();

        $content = $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.adjustments.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('manual_credit', $content);
        $this->assertStringContainsString('yes', $content);
    }

    public function test_platform_admin_can_export_accounting_ledger_csv(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 15);

        $ledger = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::ManualWalletCredit)
            ->firstOrFail();

        $content = $this->actingAs($this->platformAdmin())
            ->get(route('admin.accounting.ledger.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertMatchesRegularExpression('/L[A-Z2-9]{9}/', $content);
        $this->assertStringContainsString($ledger->transaction_ref, $content);
    }

    public function test_existing_agent_statement_csv_export_still_works(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.finance.statements.export', $agency))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_staff_cannot_access_admin_finance_exports(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => array_merge($staff->meta ?? [], [
                'staff_permissions' => [StaffPermission::LedgerView, StaffPermission::ReportsView],
            ]),
        ])->save();

        $this->actingAs($staff->fresh())->get(route('admin.finance.dashboard.export'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.accounting.reconciliation.export'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.finance.wallet-audit.export'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.finance.adjustments.export'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.accounting.ledger.export'))->assertForbidden();
    }

    public function test_agent_cannot_access_admin_finance_exports(): void
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agentUser)->get(route('admin.finance.dashboard.export'))->assertForbidden();
        $this->actingAs($agentUser)->get(route('admin.finance.wallet-audit.export'))->assertForbidden();
        $this->actingAs($agentUser)->get(route('admin.finance.adjustments.export'))->assertForbidden();
    }

    public function test_customer_cannot_access_admin_finance_exports(): void
    {
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)->get(route('admin.finance.dashboard.export'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.accounting.ledger.export'))->assertForbidden();
    }

    public function test_exports_do_not_create_finance_rows(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $this->postAdjustment($agency, $wallet, 'manual_credit', 5);

        $before = $this->financeRowCounts();

        $this->actingAs($this->platformAdmin())->get(route('admin.finance.dashboard.export'))->assertOk();
        $this->actingAs($this->platformAdmin())->get(route('admin.accounting.reconciliation.export'))->assertOk();
        $this->actingAs($this->platformAdmin())->get(route('admin.finance.wallet-audit.export'))->assertOk();
        $this->actingAs($this->platformAdmin())->get(route('admin.finance.adjustments.export'))->assertOk();
        $this->actingAs($this->platformAdmin())->get(route('admin.accounting.ledger.export'))->assertOk();

        $this->assertSame($before, $this->financeRowCounts());
    }

    public function test_export_buttons_exist_on_admin_pages(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.finance.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="finance-dashboard-export-csv"', false);

        $this->actingAs($admin)->get(route('admin.accounting.reconciliation.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-reconciliation-export-csv"', false);

        $this->actingAs($admin)->get(route('admin.finance.wallet-audit.index'))
            ->assertOk()
            ->assertSee('data-testid="wallet-audit-export-csv"', false);

        $this->actingAs($admin)->get(route('admin.finance.adjustments.index'))
            ->assertOk()
            ->assertSee('data-testid="finance-adjustments-export-csv"', false);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-export"', false);
    }

    public function test_legacy_query_export_csv_on_ledger_index_still_works(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get(route('admin.accounting.ledger.index', ['export' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
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
            $agent = $this->createAgentForAgency($agency, $index === 0);
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

    protected function createAgentForAgency(Agency $agency, bool $first = true): Agent
    {
        $user = User::query()->create([
            'name' => $first ? 'Owner '.$agency->id : 'Agent '.$agency->id.'-'.uniqid(),
            'username' => 'export-'.$agency->id.'-'.uniqid(),
            'email' => 'export-'.$agency->id.'-'.uniqid().'@example.test',
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
            'adjustment_note' => 'Export pack test',
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

    /**
     * @return array<string, int>
     */
    protected function financeRowCounts(): array
    {
        return [
            'wallets' => AgentWallet::query()->count(),
            'wallet_transactions' => AgentWalletTransaction::query()->count(),
            'ledger_transactions' => LedgerTransaction::query()->count(),
        ];
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }

    protected function assertCsvResponse(TestResponse $response): void
    {
        $type = strtolower((string) $response->headers->get('content-type'));
        $this->assertTrue(
            str_contains($type, 'text/csv') || str_contains($type, 'application/octet-stream'),
            'Expected CSV content type, got: '.$type,
        );
    }
}
