<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\UserAccountStatus;
use App\Exceptions\Finance\NonCanonicalWalletDepositException;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Finance\Wallets\DuplicateWalletArchiveService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

/**
 * OPS-DEF-0007: DB canonical marker uniqueness, approve fail-closed, migration refuse, race safety.
 */
class OpsDef0007CanonicalWalletInvariantTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_unique_null_semantics_allow_historical_rows_block_second_canonical(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $agentA1 = $this->createAgent($agencyA);
        $agentA2 = $this->createAgent($agencyA);
        $agentA3 = $this->createAgent($agencyA);
        $agentB1 = $this->createAgent($agencyB);

        $canonicalA = AgentWallet::query()->create([
            'agency_id' => $agencyA->id,
            'agent_id' => $agentA1->id,
            'user_id' => $agentA1->user_id,
            'balance' => 100,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);

        AgentWallet::query()->create([
            'agency_id' => $agencyA->id,
            'agent_id' => $agentA2->id,
            'user_id' => $agentA2->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => null,
        ]);

        AgentWallet::query()->create([
            'agency_id' => $agencyA->id,
            'agent_id' => $agentA3->id,
            'user_id' => $agentA3->user_id,
            'balance' => 5,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => null,
        ]);

        AgentWallet::query()->create([
            'agency_id' => $agencyB->id,
            'agent_id' => $agentB1->id,
            'user_id' => $agentB1->user_id,
            'balance' => 50,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);

        $this->assertSame(3, AgentWallet::query()->where('agency_id', $agencyA->id)->count());
        $this->assertTrue($canonicalA->fresh()->isCanonicalForAgency());

        $blocked = false;
        try {
            $extra = $this->createAgent($agencyA);
            AgentWallet::query()->create([
                'agency_id' => $agencyA->id,
                'agent_id' => $extra->id,
                'user_id' => $extra->user_id,
                'balance' => 0,
                'currency' => 'PKR',
                'status' => AgentWalletStatus::Active,
                'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
            ]);
        } catch (UniqueConstraintViolationException|QueryException) {
            $blocked = true;
        }

        $this->assertTrue($blocked, 'Second canonical_for_agency=1 for same agency must be DB-rejected');
        $this->assertSame(
            1,
            AgentWallet::query()
                ->where('agency_id', $agencyA->id)
                ->where('canonical_for_agency', AgentWallet::CANONICAL_MARKER)
                ->count()
        );
    }

    public function test_false_or_zero_must_not_be_used_as_noncanonical_marker(): void
    {
        $agency = Agency::factory()->create();
        $agent1 = $this->createAgent($agency);
        $agent2 = $this->createAgent($agency);

        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent1->id,
            'user_id' => $agent1->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);

        // Using 0 as "non-canonical" would incorrectly collide under UNIQUE(agency_id, canonical_for_agency).
        $blocked = false;
        try {
            AgentWallet::query()->create([
                'agency_id' => $agency->id,
                'agent_id' => $agent2->id,
                'user_id' => $agent2->user_id,
                'balance' => 0,
                'currency' => 'PKR',
                'status' => AgentWalletStatus::Active,
                'canonical_for_agency' => 0,
            ]);
            // If driver accepted 0, a second 0 must still be rejected — proving 0 is unsafe as historical marker.
            AgentWallet::query()->create([
                'agency_id' => $agency->id,
                'agent_id' => $this->createAgent($agency)->id,
                'user_id' => $agent2->user_id,
                'balance' => 0,
                'currency' => 'PKR',
                'status' => AgentWalletStatus::Active,
                'canonical_for_agency' => 0,
            ]);
        } catch (UniqueConstraintViolationException|QueryException) {
            $blocked = true;
        }

        $this->assertTrue($blocked, 'canonical_for_agency=0 is unsafe under composite unique; use NULL for historical rows');
    }

    public function test_get_or_create_repeated_calls_return_same_marked_wallet(): void
    {
        $agency = Agency::factory()->create();
        $this->createAgent($agency, owner: true);
        $service = app(AgentWalletService::class);

        $first = $service->getOrCreateCanonicalWalletForAgency($agency);
        $second = $service->getOrCreateCanonicalWalletForAgency($agency);
        $third = $service->getOrCreateCanonicalWalletForAgency($agency);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame((int) $first->id, (int) $third->id);
        $this->assertTrue($first->fresh()->isCanonicalForAgency());
        $this->assertSame(1, AgentWallet::query()->where('agency_id', $agency->id)->count());
    }

    public function test_approve_deposit_on_noncanonical_wallet_fails_closed(): void
    {
        $agency = Agency::factory()->create();
        $owner = $this->createAgent($agency, owner: true);
        $staff = $this->createAgent($agency);

        $canonical = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $owner->id,
            'user_id' => $owner->user_id,
            'balance' => 100,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);
        $historical = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $staff->id,
            'user_id' => $staff->user_id,
            'balance' => 25,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => null,
        ]);

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $staff->id,
            'user_id' => $staff->user_id,
            'agent_wallet_id' => $historical->id,
            'amount' => 40,
            'currency' => 'PKR',
            'reference' => 'OPS7-NONCANON',
            'status' => AgentDepositRequestStatus::Submitted,
        ]);

        AgentWalletTransaction::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $staff->id,
            'user_id' => $staff->user_id,
            'agent_wallet_id' => $historical->id,
            'agent_deposit_request_id' => $deposit->id,
            'type' => AgentWalletTransactionType::DepositRequest,
            'amount' => 40,
            'balance_before' => 25,
            'balance_after' => 25,
            'status' => 'pending',
            'reference' => 'OPS7-NONCANON',
            'description' => 'Deposit request submitted',
            'created_by' => $staff->user_id,
        ]);

        $reviewer = $this->platformAdmin();
        $ledgerBefore = LedgerTransaction::query()->count();
        $txBefore = AgentWalletTransaction::query()
            ->where('type', AgentWalletTransactionType::DepositApproved)
            ->count();

        try {
            app(AgentWalletService::class)->approveDeposit($deposit, $reviewer);
            $this->fail('Expected NonCanonicalWalletDepositException');
        } catch (NonCanonicalWalletDepositException $e) {
            $this->assertSame((int) $deposit->id, $e->depositId);
            $this->assertSame((int) $historical->id, $e->depositWalletId);
            $this->assertSame((int) $canonical->id, $e->canonicalWalletId);
        }

        $this->assertSame(100.0, (float) $canonical->fresh()->balance);
        $this->assertSame(25.0, (float) $historical->fresh()->balance);
        $this->assertSame(AgentDepositRequestStatus::Submitted, $deposit->fresh()->status);
        $this->assertSame($txBefore, AgentWalletTransaction::query()->where('type', AgentWalletTransactionType::DepositApproved)->count());
        $this->assertSame($ledgerBefore, LedgerTransaction::query()->count());
    }

    public function test_submit_deposit_always_binds_canonical_wallet(): void
    {
        $agency = Agency::factory()->create();
        $owner = $this->createAgent($agency, owner: true);
        $staff = $this->createAgent($agency);

        $canonical = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $owner->id,
            'user_id' => $owner->user_id,
            'balance' => 10,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);
        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $staff->id,
            'user_id' => $staff->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => null,
        ]);

        $staffUser = User::query()->findOrFail($staff->user_id);
        app(AgentWalletService::class)->submitDepositRequest($staff, $staffUser, [
            'amount' => 12,
            'payment_method' => 'Bank',
            'reference' => 'OPS7-STAFF-DEP',
        ]);

        $deposit = AgentDepositRequest::query()->where('reference', 'OPS7-STAFF-DEP')->firstOrFail();
        $this->assertSame((int) $canonical->id, (int) $deposit->agent_wallet_id);
    }

    public function test_marked_canonical_cannot_be_archived(): void
    {
        $agency = Agency::factory()->create();
        $owner = $this->createAgent($agency, owner: true);
        $staff = $this->createAgent($agency);

        $canonical = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $owner->id,
            'user_id' => $owner->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);
        $historical = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $staff->id,
            'user_id' => $staff->user_id,
            'balance' => 0,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => null,
        ]);

        $archive = app(DuplicateWalletArchiveService::class);
        $this->assertFalse($archive->canArchive($canonical)->eligible);
        $this->assertTrue($archive->canArchive($historical)->eligible);
    }

    public function test_migration_refuses_ambiguous_duplicate_agency_groups(): void
    {
        Schema::table('agent_wallets', function ($table): void {
            $table->dropUnique('agent_wallets_agency_canonical_unique');
        });
        Schema::table('agent_wallets', function ($table): void {
            $table->dropColumn('canonical_for_agency');
        });

        $agency = Agency::factory()->create();
        $a1 = $this->createAgent($agency);
        $a2 = $this->createAgent($agency);

        DB::table('agent_wallets')->insert([
            [
                'agency_id' => $agency->id,
                'agent_id' => $a1->id,
                'user_id' => $a1->user_id,
                'balance' => 10,
                'currency' => 'PKR',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'agency_id' => $agency->id,
                'agent_id' => $a2->id,
                'user_id' => $a2->user_id,
                'balance' => 20,
                'currency' => 'PKR',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_10_15_120000_add_canonical_for_agency_to_agent_wallets.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OPS-DEF-0007 migration refused');
        $migration->up();
    }

    public function test_agency_wallet_summary_still_sums_historical_balances(): void
    {
        $agency = Agency::factory()->create();
        $owner = $this->createAgent($agency, owner: true);
        $staff = $this->createAgent($agency);

        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $owner->id,
            'user_id' => $owner->user_id,
            'balance' => 100,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => AgentWallet::CANONICAL_MARKER,
        ]);
        AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $staff->id,
            'user_id' => $staff->user_id,
            'balance' => 40,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
            'canonical_for_agency' => null,
        ]);

        $summary = app(AgentWalletService::class)->agencyWalletSummary((int) $agency->id);
        $this->assertSame(140.0, $summary['balance']);
        $this->assertSame(2, $summary['wallet_count']);
    }

    protected function createAgent(Agency $agency, bool $owner = false): Agent
    {
        $user = User::query()->create([
            'name' => ($owner ? 'Owner ' : 'Agent ').$agency->id.'-'.uniqid(),
            'username' => 'ops7-'.$agency->id.'-'.uniqid(),
            'email' => 'ops7-'.$agency->id.'-'.uniqid().'@example.test',
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

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
