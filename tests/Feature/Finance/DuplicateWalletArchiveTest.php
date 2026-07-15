<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserAccountStatus;
use App\Enums\WalletAuditClassification;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Wallets\DuplicateWalletArchiveService;
use App\Services\Finance\Wallets\WalletAuditService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class DuplicateWalletArchiveTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_platform_admin_can_view_archive_preview(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.finance.wallet-audit.archive-preview', ['agency_id' => $agency->id]))
            ->assertOk()
            ->assertSee('data-testid="wallet-archive-warning"', false)
            ->assertSee('data-testid="wallet-archive-eligible-table"', false);
    }

    public function test_staff_cannot_view_archive_preview(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('admin.finance.wallet-audit.archive-preview', ['agency_id' => $agency->id]))
            ->assertForbidden();
    }

    public function test_agent_cannot_view_archive_preview(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agentUser)
            ->get(route('admin.finance.wallet-audit.archive-preview', ['agency_id' => $agency->id]))
            ->assertForbidden();
    }

    public function test_customer_cannot_view_archive_preview(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($customer)
            ->get(route('admin.finance.wallet-audit.archive-preview', ['agency_id' => $agency->id]))
            ->assertForbidden();
    }

    public function test_preview_lists_eligible_cleanup_candidates(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonicalId = (int) app(AgentWalletService::class)->canonicalWalletForAgency($agency)?->id;

        $preview = app(DuplicateWalletArchiveService::class)->preview($agency->id);
        $eligibleIds = collect($preview['eligible'])->pluck('wallet_id')->map(fn ($id): int => (int) $id)->all();

        $this->assertCount(2, $eligibleIds);
        $this->assertNotContains($canonicalId, $eligibleIds);
    }

    public function test_preview_blocks_canonical_wallet(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $result = app(DuplicateWalletArchiveService::class)->canArchive($canonical);
        $this->assertFalse($result->eligible);
        $this->assertStringContainsString('Canonical', $result->reason());
    }

    public function test_preview_blocks_wallet_with_non_zero_balance(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([25, 0, 100]);
        $nonCanonical = collect($wallets)->first(fn (AgentWallet $w): bool => (float) $w->balance > 0 && ! $this->isCanonical($agency, $w));
        $this->assertNotNull($nonCanonical);

        $result = app(DuplicateWalletArchiveService::class)->canArchive($nonCanonical);
        $this->assertFalse($result->eligible);
    }

    public function test_preview_blocks_wallet_with_wallet_transactions(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $duplicate = collect($wallets)->first(fn (AgentWallet $w): bool => ! $this->isCanonical($agency, $w));

        AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $duplicate->agent_id,
            'user_id' => $duplicate->user_id,
            'agent_wallet_id' => $duplicate->id,
            'type' => AgentWalletTransactionType::ManualCredit,
            'amount' => 1,
            'balance_before' => 0,
            'balance_after' => 0,
            'status' => 'posted',
        ]);

        $result = app(DuplicateWalletArchiveService::class)->canArchive($duplicate->fresh());
        $this->assertFalse($result->eligible);
    }

    public function test_preview_blocks_wallet_with_deposit_requests(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $duplicate = collect($wallets)->first(fn (AgentWallet $w): bool => ! $this->isCanonical($agency, $w));

        AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $duplicate->agent_id,
            'user_id' => $duplicate->user_id,
            'agent_wallet_id' => $duplicate->id,
            'amount' => 10,
            'currency' => 'PKR',
            'status' => AgentDepositRequestStatus::Approved,
        ]);

        $result = app(DuplicateWalletArchiveService::class)->canArchive($duplicate->fresh());
        $this->assertFalse($result->eligible);
    }

    public function test_preview_blocks_wallet_with_ledger_refs(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $duplicate = collect($wallets)->first(fn (AgentWallet $w): bool => ! $this->isCanonical($agency, $w));

        $tx = AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $duplicate->agent_id,
            'user_id' => $duplicate->user_id,
            'agent_wallet_id' => $duplicate->id,
            'type' => AgentWalletTransactionType::ManualCredit,
            'amount' => 5,
            'balance_before' => 0,
            'balance_after' => 5,
            'status' => 'posted',
        ]);

        DB::table('ledger_transactions')->insert([
            'transaction_ref' => 'LT-ARCH-TEST-'.$tx->id,
            'transaction_type' => LedgerTransactionType::WalletAdjustment->value,
            'status' => LedgerTransactionStatus::Posted->value,
            'agency_id' => $agency->id,
            'source_type' => (new AgentWalletTransaction)->getMorphClass(),
            'source_id' => $tx->id,
            'currency' => 'PKR',
            'amount_total' => 5,
            'description' => 'test',
            'posted_at' => now(),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $duplicate->update(['balance' => 0]);

        $result = app(DuplicateWalletArchiveService::class)->canArchive($duplicate->fresh());
        $this->assertFalse($result->eligible);
    }

    public function test_post_requires_confirmation_archive(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.wallet-audit.archive'), [
            'agency_id' => $agency->id,
            'confirmation' => 'WRONG',
            'reason' => 'Test archive reason for validation',
        ])->assertSessionHasErrors('confirmation');
    }

    public function test_post_requires_reason(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.wallet-audit.archive'), [
            'agency_id' => $agency->id,
            'confirmation' => 'ARCHIVE',
            'reason' => 'short',
        ])->assertSessionHasErrors('reason');
    }

    public function test_post_archives_only_eligible_cleanup_candidates(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.wallet-audit.archive'), [
            'agency_id' => $agency->id,
            'confirmation' => 'ARCHIVE',
            'reason' => 'Archive zero-balance duplicate wallets after audit verification',
        ])->assertRedirect();

        foreach ($wallets as $wallet) {
            $fresh = $wallet->fresh();
            if ((int) $fresh->id === (int) $canonical?->id) {
                $this->assertSame(AgentWalletStatus::Active, $fresh->status);
            } else {
                $this->assertSame(AgentWalletStatus::Archived, $fresh->status);
            }
        }
    }

    public function test_post_does_not_delete_wallets(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $countBefore = AgentWallet::query()->where('agency_id', $agency->id)->count();

        $this->archiveViaPost($agency);

        $this->assertSame($countBefore, AgentWallet::query()->where('agency_id', $agency->id)->count());
    }

    public function test_post_does_not_change_balances(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $balancesBefore = AgentWallet::query()->where('agency_id', $agency->id)->pluck('balance', 'id')->all();

        $this->archiveViaPost($agency);

        foreach ($balancesBefore as $id => $balance) {
            $this->assertSame((float) $balance, (float) AgentWallet::query()->find($id)?->balance);
        }
    }

    public function test_post_does_not_create_or_delete_ledger_transactions(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $ledgerBefore = DB::table('ledger_transactions')->count();

        $this->archiveViaPost($agency);

        $this->assertSame($ledgerBefore, DB::table('ledger_transactions')->count());
    }

    public function test_post_does_not_mutate_wallet_transactions_or_deposit_requests(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $txBefore = AgentWalletTransaction::query()->count();
        $depBefore = AgentDepositRequest::query()->count();

        $this->archiveViaPost($agency);

        $this->assertSame($txBefore, AgentWalletTransaction::query()->count());
        $this->assertSame($depBefore, AgentDepositRequest::query()->count());
    }

    public function test_archived_wallets_are_not_selected_as_canonical(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $this->archiveViaPost($agency);

        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        $this->assertSame(AgentWalletStatus::Active, $canonical?->status);
        $this->assertSame(100.0, (float) $canonical?->balance);
    }

    public function test_manual_adjustment_still_uses_active_canonical_wallet(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        $this->archiveViaPost($agency);

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), [
            'agency_id' => $agency->id,
            'adjustment_type' => 'manual_credit',
            'amount' => 5,
            'adjustment_reason' => 'bank_correction',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation' => '1',
        ])->assertRedirect();

        $this->assertSame(105.0, (float) $canonical?->fresh()->balance);
        $this->assertDatabaseHas('agent_wallet_transactions', [
            'agent_wallet_id' => $canonical?->id,
            'type' => AgentWalletTransactionType::ManualCredit->value,
        ]);
    }

    public function test_deposit_flow_still_uses_active_canonical_wallet(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $canonical = app(AgentWalletService::class)->canonicalWalletForAgency($agency);
        $this->archiveViaPost($agency);

        $staffAgent = Agent::query()->whereKey($wallets[1]->agent_id)->firstOrFail();
        $staffUser = User::query()->findOrFail($staffAgent->user_id);

        app(AgentWalletService::class)->submitDepositRequest($staffAgent, $staffUser, [
            'amount' => 20,
            'payment_method' => 'Bank',
            'reference' => 'DEP-ARCH-TEST',
        ]);

        $deposit = AgentDepositRequest::query()->where('reference', 'DEP-ARCH-TEST')->firstOrFail();
        $this->assertSame((int) $canonical?->id, (int) $deposit->agent_wallet_id);
    }

    public function test_audit_page_shows_archived_status_after_archive(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $duplicate = collect($wallets)->first(fn (AgentWallet $w): bool => ! $this->isCanonical($agency, $w));
        $this->archiveViaPost($agency);

        $row = $this->walletRowFor($agency->id, (int) $duplicate->id);

        $this->assertSame('archived', $row['status']);
        $this->assertSame(WalletAuditClassification::ArchivedDuplicate->value, $row['classification']);
    }

    public function test_command_dry_run_creates_no_changes(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $this->platformAdmin();
        $statusesBefore = AgentWallet::query()->where('agency_id', $agency->id)->pluck('status', 'id')->all();

        Artisan::call('agent-wallets:archive-candidates', [
            '--agency' => $agency->id,
            '--dry-run' => true,
        ]);

        foreach ($statusesBefore as $id => $status) {
            $this->assertSame(
                $status instanceof AgentWalletStatus ? $status->value : (string) $status,
                AgentWallet::query()->find($id)?->status?->value,
            );
        }
    }

    public function test_command_apply_archives_only_eligible_candidates(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $this->platformAdmin();
        $canonicalId = (int) app(AgentWalletService::class)->canonicalWalletForAgency($agency)?->id;

        Artisan::call('agent-wallets:archive-candidates', [
            '--agency' => $agency->id,
            '--apply' => true,
            '--reason' => 'Archive zero-balance duplicate wallets after FR14 audit verification',
        ]);

        foreach ($wallets as $wallet) {
            $fresh = $wallet->fresh();
            if ((int) $fresh->id === $canonicalId) {
                $this->assertSame(AgentWalletStatus::Active, $fresh->status);
            } else {
                $this->assertSame(AgentWalletStatus::Archived, $fresh->status);
            }
        }
    }

    public function test_command_apply_requires_reason(): void
    {
        [$agency] = $this->seedMultiWallets([0, 0, 100]);
        $this->platformAdmin();

        $exitCode = Artisan::call('agent-wallets:archive-candidates', [
            '--agency' => $agency->id,
            '--apply' => true,
            '--reason' => 'short',
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function test_archive_writes_audit_log(): void
    {
        [$agency, $wallets] = $this->seedMultiWallets([0, 0, 100]);
        $duplicate = collect($wallets)->first(fn (AgentWallet $w): bool => ! $this->isCanonical($agency, $w));

        $this->archiveViaPost($agency);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => AgentWallet::class,
            'auditable_id' => $duplicate->id,
            'action' => DuplicateWalletArchiveService::AUDIT_ACTION,
        ]);
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
                'status' => AgentWalletStatus::Active,
            ]);
        }

        return [$agency, $wallets];
    }

    protected function createAgentForAgency(Agency $agency, AccountType $type, bool $first): Agent
    {
        $user = User::query()->create([
            'name' => $first ? 'Owner '.$agency->id : 'Agent '.$agency->id.'-'.uniqid(),
            'username' => 'arch-'.$agency->id.'-'.uniqid(),
            'email' => 'arch-'.$agency->id.'-'.uniqid().'@example.test',
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

    protected function archiveViaPost(Agency $agency): void
    {
        $this->actingAs($this->platformAdmin())->post(route('admin.finance.wallet-audit.archive'), [
            'agency_id' => $agency->id,
            'confirmation' => 'ARCHIVE',
            'reason' => 'Archive zero-balance duplicate wallets after audit verification',
        ]);
    }

    protected function isCanonical(Agency $agency, AgentWallet $wallet): bool
    {
        $canonicalId = (int) app(AgentWalletService::class)->canonicalWalletForAgency($agency)?->id;

        return (int) $wallet->id === $canonicalId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function walletRowFor(int $agencyId, int $walletId): array
    {
        $report = app(WalletAuditService::class)->build(agencyId: $agencyId);
        $row = collect($report['wallets'])->firstWhere('wallet_id', $walletId);
        $this->assertNotNull($row);

        return $row;
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
