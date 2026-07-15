<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\LedgerTransactionType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Ledger\LedgerQueryService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

/**
 * Regression: production agencies table has no `code` column; accounting ledger must still load.
 */
class AccountingLedgerAgencySchemaTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_agencies_table_has_no_code_column(): void
    {
        $this->assertFalse(Schema::hasColumn('agencies', 'code'));
    }

    public function test_ledger_query_service_paginates_without_agency_code_column(): void
    {
        $tx = $this->seedPostedDepositLedgerTransaction();

        $paginator = app(LedgerQueryService::class)->paginate(request(), null, 25);

        $this->assertGreaterThanOrEqual(1, $paginator->total());
        $row = collect($paginator->items())->firstWhere('id', $tx->id);
        $this->assertNotNull($row);
        $this->assertNotNull($row->agency?->name);
    }

    public function test_admin_accounting_ledger_index_and_show_render_without_agency_code_column(): void
    {
        $admin = $this->platformAdmin();
        $tx = $this->seedPostedDepositLedgerTransaction();

        $this->actingAs($admin)
            ->get(route('admin.accounting.ledger.index'))
            ->assertOk()
            ->assertSee('data-testid="accounting-ledger-table"', false)
            ->assertSee('data-testid="accounting-ledger-row-'.$tx->id.'"', false);

        $this->actingAs($admin)
            ->get(route('admin.accounting.ledger.show', $tx))
            ->assertOk()
            ->assertSee($tx->transaction_ref);
    }

    public function test_finance_dashboard_recent_ledger_link_does_not_500_without_agency_code_column(): void
    {
        $admin = $this->platformAdmin();
        $this->seedPostedDepositLedgerTransaction();

        $this->actingAs($admin)
            ->get(route('admin.finance.dashboard'))
            ->assertOk();
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin;
    }

    protected function seedPostedDepositLedgerTransaction(?int $agencyId = null): LedgerTransaction
    {
        $agencyId ??= Agency::query()->where('slug', 'asif-travels')->value('id')
            ?? Agency::factory()->create()->id;

        $agent = Agent::query()->where('agency_id', $agencyId)->first()
            ?? Agent::factory()->create(['agency_id' => $agencyId]);

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

        $admin = $this->platformAdmin();
        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agencyId,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 5000,
            'currency' => 'PKR',
            'reference' => 'DEP-SCHEMA-'.uniqid(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        return LedgerTransaction::query()
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->firstOrFail();
    }
}
