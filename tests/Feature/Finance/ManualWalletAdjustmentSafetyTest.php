<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentWalletTransactionType;
use App\Enums\LedgerTransactionType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Finance\Adjustments\ManualWalletAdjustmentService;
use App\Services\Finance\Ledger\LedgerBalanceService;
use App\Support\Agents\AgentPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class ManualWalletAdjustmentSafetyTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_duplicate_submit_creates_one_wallet_transaction(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $key = (string) Str::uuid();
        $payload = $this->validPayload($agency, $wallet, 'manual_credit', 10, $key);

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $payload);
        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $payload);

        $this->assertSame(1, AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->count());
    }

    public function test_adjustment_uses_compact_wallet_reference_and_meta_idempotency_key(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $key = (string) Str::uuid();
        $payload = $this->validPayload($agency, $wallet, 'manual_credit', 10, $key);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), $payload);

        $tx = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^W[A-Z2-9]{8}$/', (string) $tx->reference);
        $this->assertSame($key, is_array($tx->meta) ? ($tx->meta['idempotency_key'] ?? null) : null);
    }

    public function test_duplicate_submit_creates_one_ledger_transaction(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $key = (string) Str::uuid();
        $payload = $this->validPayload($agency, $wallet, 'manual_credit', 10, $key);

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $payload);
        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $payload);

        $tx = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->firstOrFail();
        $this->assertSame(1, LedgerTransaction::query()->where('source_id', $tx->id)->count());
    }

    public function test_duplicate_submit_redirects_with_existing_status(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $key = (string) Str::uuid();
        $payload = $this->validPayload($agency, $wallet, 'manual_credit', 10, $key);

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $payload)->assertSessionHas('status', 'adjustment-created');
        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $payload)->assertSessionHas('status', 'adjustment-existing');
    }

    public function test_different_idempotency_key_creates_separate_adjustment(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(100);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, 'manual_credit', 5, (string) Str::uuid()));
        $this->actingAs($admin)->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, 'manual_credit', 5, (string) Str::uuid()));

        $this->assertSame(2, AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->where('type', 'manual_credit')->count());
        $this->assertSame(110.0, (float) $wallet->fresh()->balance);
    }

    public function test_manual_credit_can_be_reversed_by_platform_admin(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $original = $this->postCredit($agency, $wallet, 25);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.reverse', $original), $this->reversePayload())
            ->assertRedirect()
            ->assertSessionHas('status', 'adjustment-reversed');
    }

    public function test_manual_debit_can_be_reversed_by_platform_admin(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(100);
        $original = $this->postDebit($agency, $wallet, 30);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.reverse', $original), $this->reversePayload())
            ->assertRedirect()
            ->assertSessionHas('status', 'adjustment-reversed');
    }

    public function test_credit_reversal_restores_wallet_balance(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $original = $this->postCredit($agency, $wallet, 25);
        $this->assertSame(75.0, (float) $wallet->fresh()->balance);

        $this->reverseAsAdmin($original);

        $this->assertSame(50.0, (float) $wallet->fresh()->balance);
    }

    public function test_debit_reversal_restores_wallet_balance(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(100);
        $original = $this->postDebit($agency, $wallet, 30);
        $this->assertSame(70.0, (float) $wallet->fresh()->balance);

        $this->reverseAsAdmin($original);

        $this->assertSame(100.0, (float) $wallet->fresh()->balance);
    }

    public function test_reversal_creates_wallet_transaction_with_reversal_meta(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(20);
        $original = $this->postCredit($agency, $wallet, 10);
        $this->reverseAsAdmin($original, 'Entered in error');

        $reversal = AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->where('id', '!=', $original->id)->firstOrFail();
        $meta = is_array($reversal->meta) ? $reversal->meta : [];
        $this->assertSame($original->id, (int) ($meta['reversal_of_wallet_transaction_id'] ?? 0));
        $this->assertSame('Entered in error', $meta['reversal_reason'] ?? null);
        $this->assertSame('manual_credit', $meta['original_type'] ?? null);
        $this->assertTrue($meta['is_reversal'] ?? false);
    }

    public function test_reversal_creates_balanced_ledger_transaction(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(40);
        $original = $this->postCredit($agency, $wallet, 15);
        $this->reverseAsAdmin($original);

        $reversal = app(ManualWalletAdjustmentService::class)->findReversalFor($original);
        $this->assertNotNull($reversal);

        $ledger = LedgerTransaction::query()
            ->where('source_id', $reversal->id)
            ->where('transaction_type', LedgerTransactionType::ManualWalletDebitReversal)
            ->firstOrFail();

        $ledger->load('entries');
        $debit = round((float) $ledger->entries->sum('debit'), 2);
        $credit = round((float) $ledger->entries->sum('credit'), 2);
        $this->assertEqualsWithDelta($debit, $credit, 0.01);
    }

    public function test_reversal_appears_in_admin_statement(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $original = $this->postCredit($agency, $wallet, 12);
        $this->reverseAsAdmin($original);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.statements.show', [
                'agency' => $agency,
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Manual wallet credit reversal', false);
    }

    public function test_reversal_appears_in_agent_statement(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $agentUser = User::query()->findOrFail($wallet->user_id);
        $agentUser->forceFill([
            'meta' => array_merge($agentUser->meta ?? [], [
                'agent_permissions' => [AgentPermission::ReportsView],
            ]),
        ])->save();

        $original = $this->postCredit($agency, $wallet, 8);
        $this->reverseAsAdmin($original);

        $this->actingAs($agentUser->fresh())
            ->get(route('agent.finance.statement.show', [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Manual wallet credit reversal', false);
    }

    public function test_reconciliation_remains_matched_after_reversal(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(0);
        $original = $this->postCredit($agency, $wallet, 50);
        $this->reverseAsAdmin($original);

        $compare = app(LedgerBalanceService::class)->compareWalletToLedger($agency->id);
        $this->assertTrue($compare['matches']);
        $this->assertSame(0.0, $compare['wallet_balance']);
        $this->assertSame(0.0, $compare['ledger_balance']);
    }

    public function test_already_reversed_transaction_cannot_be_reversed_again(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $original = $this->postCredit($agency, $wallet, 10);
        $this->reverseAsAdmin($original);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.reverse', $original), $this->reversePayload())
            ->assertForbidden();
    }

    public function test_non_manual_transaction_cannot_be_reversed(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $deposit = AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $wallet->agent_id,
            'user_id' => $wallet->user_id,
            'agent_wallet_id' => $wallet->id,
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 10,
            'balance_before' => 50,
            'balance_after' => 60,
            'status' => 'posted',
            'reference' => 'DEP-TEST',
            'description' => 'Deposit',
            'created_by' => $this->platformAdmin()->id,
            'approved_by' => $this->platformAdmin()->id,
        ]);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.reverse', $deposit), $this->reversePayload())
            ->assertForbidden();
    }

    public function test_non_admin_cannot_reverse(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $original = $this->postCredit($agency, $wallet, 10);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('admin.finance.adjustments.reverse', $original), $this->reversePayload())
            ->assertForbidden();
    }

    public function test_reversal_requires_reason(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $original = $this->postCredit($agency, $wallet, 10);

        $payload = $this->reversePayload();
        unset($payload['reversal_reason']);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.reverse', $original), $payload)
            ->assertSessionHasErrors('reversal_reason');
    }

    public function test_reversal_requires_confirmation(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $original = $this->postCredit($agency, $wallet, 10);

        $payload = $this->reversePayload();
        unset($payload['confirmation']);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.reverse', $original), $payload)
            ->assertSessionHasErrors('confirmation');
    }

    public function test_reversal_blocked_when_wallet_would_go_negative(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(10);
        $original = $this->postCredit($agency, $wallet, 10);
        $this->postDebit($agency, $wallet->fresh(), 15);

        $this->actingAs($this->platformAdmin())
            ->from(route('admin.finance.adjustments.reverse.confirm', $original))
            ->post(route('admin.finance.adjustments.reverse', $original), $this->reversePayload())
            ->assertRedirect()
            ->assertSessionHasErrors('reversal');

        $this->assertNull(app(ManualWalletAdjustmentService::class)->findReversalFor($original));
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
            'username' => 'adj-safe-'.$agency->id.'-'.uniqid(),
            'email' => 'adj-safe-'.$agency->id.'-'.uniqid().'@example.test',
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
    protected function validPayload(Agency $agency, AgentWallet $wallet, string $type, float $amount, ?string $idempotencyKey = null): array
    {
        return [
            'agency_id' => $agency->id,
            'wallet_id' => $wallet->id,
            'adjustment_type' => $type,
            'amount' => $amount,
            'adjustment_reason' => 'bank_correction',
            'adjustment_note' => 'Test',
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            'confirmation' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reversePayload(): array
    {
        return [
            'reversal_reason' => 'Correction required',
            'confirmation' => '1',
        ];
    }

    protected function postCredit(Agency $agency, AgentWallet $wallet, float $amount): AgentWalletTransaction
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, 'manual_credit', $amount))
            ->assertRedirect();

        return AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->latest('id')->firstOrFail();
    }

    protected function postDebit(Agency $agency, AgentWallet $wallet, float $amount): AgentWalletTransaction
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.store'), $this->validPayload($agency, $wallet, 'manual_debit', $amount))
            ->assertRedirect();

        return AgentWalletTransaction::query()->where('agent_wallet_id', $wallet->id)->latest('id')->firstOrFail();
    }

    protected function reverseAsAdmin(AgentWalletTransaction $original, string $reason = 'Correction required'): void
    {
        $payload = $this->reversePayload();
        $payload['reversal_reason'] = $reason;

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.finance.adjustments.reverse', $original), $payload)
            ->assertRedirect();
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
