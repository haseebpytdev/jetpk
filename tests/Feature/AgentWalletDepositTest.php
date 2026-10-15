<?php

namespace Tests\Feature;

use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Communication\NotificationRecipientResolver;
use App\Services\Communication\OtaNotificationService;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AgentWalletDepositTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_approved_agent_sees_wallet_summary_on_dashboard(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $wallet = app(AgentWalletService::class)->walletFor($agent);
        $wallet->update(['balance' => 25000, 'available_balance' => 24000]);

        $this->actingAs($agentUser)->get(route('agent.dashboard'))
            ->assertOk()
            ->assertSee('Wallet', false);

        $this->actingAs($agentUser)->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-wallet-kpis"', false)
            ->assertSee('25,000.00', false);
    }

    public function test_wallet_is_created_lazily_when_missing(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $this->assertDatabaseMissing('agent_wallets', ['agent_id' => $agent->id]);

        $this->actingAs($agentUser)->get(route('agent.wallet.show'))->assertOk();

        $this->assertDatabaseHas('agent_wallets', [
            'agent_id' => $agent->id,
            'balance' => 0,
        ]);
    }

    public function test_agent_can_submit_deposit_request(): void
    {
        Storage::fake('local');
        [$agentUser, $agent] = $this->seededAgent();

        $this->actingAs($agentUser)->post(route('agent.deposits.store'), [
            'amount' => 5000,
            'payment_method' => 'Bank transfer',
            'reference' => 'TXN-123',
            'agent_note' => 'Paid to agency account',
            'proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('agent.deposits.index'))
            ->assertSessionHas('status', 'deposit-submitted');

        $this->assertDatabaseHas('agent_deposit_requests', [
            'agent_id' => $agent->id,
            'amount' => 5000,
            'status' => 'submitted',
            'reference' => 'TXN-123',
        ]);
    }

    public function test_agent_sees_only_own_deposits(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $other = $this->otherAgent();

        AgentDepositRequest::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => app(AgentWalletService::class)->walletFor($agent)->id,
            'amount' => 1000,
            'currency' => 'PKR',
            'status' => 'submitted',
        ]);

        AgentDepositRequest::query()->create([
            'agency_id' => $other->agency_id,
            'agent_id' => $other->id,
            'user_id' => $other->user_id,
            'agent_wallet_id' => app(AgentWalletService::class)->walletFor($other)->id,
            'amount' => 9999,
            'currency' => 'PKR',
            'status' => 'submitted',
            'reference' => 'OTHER-SECRET',
        ]);

        $this->actingAs($agentUser)->get(route('agent.deposits.index'))
            ->assertOk()
            ->assertSee('1,000.00', false)
            ->assertDontSee('OTHER-SECRET', false);
    }

    public function test_agent_cannot_view_other_agent_admin_deposit(): void
    {
        [$agentUser] = $this->seededAgent();
        $other = $this->otherAgent();
        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $other->agency_id,
            'agent_id' => $other->id,
            'user_id' => $other->user_id,
            'agent_wallet_id' => app(AgentWalletService::class)->walletFor($other)->id,
            'amount' => 2000,
            'currency' => 'PKR',
            'status' => 'submitted',
        ]);

        $admin = $this->platformAdmin();

        $this->actingAs($agentUser)->get(route('admin.agent-deposits.show', $deposit))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.agent-deposits.show', $deposit))->assertOk();
    }

    public function test_admin_approve_increases_wallet_balance_once(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $admin = $this->platformAdmin();
        $wallet = app(AgentWalletService::class)->walletFor($agent);

        $deposit = app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 2500,
            'payment_method' => 'Bank',
            'reference' => 'DEP-1',
        ]);

        $this->actingAs($admin)->patch(route('admin.agent-deposits.approve', $deposit))
            ->assertRedirect();

        $wallet->refresh();
        $this->assertSame(2500.0, (float) $wallet->balance);

        $this->assertSame(1, AgentWalletTransaction::query()
            ->where('agent_deposit_request_id', $deposit->id)
            ->where('type', AgentWalletTransactionType::DepositApproved)
            ->count());
        $this->assertSame(1, LedgerTransaction::query()
            ->where('source_type', $deposit->getMorphClass())
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->count());

        $this->actingAs($admin)->patch(route('admin.agent-deposits.approve', $deposit->fresh()))
            ->assertRedirect()
            ->assertSessionHasErrors('deposit');

        $wallet->refresh();
        $this->assertSame(2500.0, (float) $wallet->balance);
        $this->assertSame(1, AgentWalletTransaction::query()
            ->where('agent_deposit_request_id', $deposit->id)
            ->where('type', AgentWalletTransactionType::DepositApproved)
            ->count());
        $this->assertSame(1, LedgerTransaction::query()
            ->where('source_type', $deposit->getMorphClass())
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->count());
    }

    public function test_approve_deposit_is_fully_atomic_with_ledger(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $admin = $this->platformAdmin();
        $wallet = app(AgentWalletService::class)->walletFor($agent);
        $wallet->update(['balance' => 1000]);

        $deposit = app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 500,
            'payment_method' => 'Bank',
            'reference' => 'DEP-ATOMIC',
        ]);

        $approved = app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $this->assertSame(AgentDepositRequestStatus::Approved, $approved->status);
        $this->assertSame($admin->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);

        $wallet->refresh();
        $this->assertSame(1500.0, (float) $wallet->balance);

        $requestTx = AgentWalletTransaction::query()
            ->where('agent_deposit_request_id', $deposit->id)
            ->where('type', AgentWalletTransactionType::DepositRequest)
            ->firstOrFail();
        $this->assertSame(AgentWalletTransactionStatus::Approved, $requestTx->status);

        $this->assertSame(1, AgentWalletTransaction::query()
            ->where('agent_deposit_request_id', $deposit->id)
            ->where('type', AgentWalletTransactionType::DepositApproved)
            ->count());

        $ledger = LedgerTransaction::query()
            ->where('source_type', $deposit->getMorphClass())
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->firstOrFail();
        $this->assertSame(LedgerTransactionStatus::Posted, $ledger->status);
        $this->assertSame(500.0, (float) $ledger->amount_total);

        $debit = round((float) $ledger->entries->sum('debit'), 2);
        $credit = round((float) $ledger->entries->sum('credit'), 2);
        $this->assertSame($debit, $credit);
        $this->assertSame(500.0, $debit);
    }

    public function test_ledger_failure_rolls_back_wallet_deposit_and_blocks_notification(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $admin = $this->platformAdmin();
        $wallet = app(AgentWalletService::class)->walletFor($agent);
        $wallet->update(['balance' => 2000]);

        $deposit = app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 750,
            'payment_method' => 'Bank',
            'reference' => 'DEP-FAIL',
        ]);

        $notifications = Mockery::mock(OtaNotificationService::class);
        $notifications->shouldNotReceive('send');
        $this->app->instance(OtaNotificationService::class, $notifications);

        $ledger = Mockery::mock(LedgerEventRecorder::class);
        $ledger->shouldReceive('recordAgencyDepositApprovedOrFail')
            ->once()
            ->andThrow(new RuntimeException('forced ledger failure'));
        $this->app->instance(LedgerEventRecorder::class, $ledger);

        try {
            app(AgentWalletService::class)->approveDeposit($deposit, $admin);
            $this->fail('Expected ledger failure to abort approval.');
        } catch (RuntimeException $e) {
            $this->assertSame('forced ledger failure', $e->getMessage());
        }

        $deposit->refresh();
        $wallet->refresh();

        $this->assertSame(AgentDepositRequestStatus::Submitted, $deposit->status);
        $this->assertNull($deposit->reviewed_by);
        $this->assertNull($deposit->reviewed_at);
        $this->assertSame(2000.0, (float) $wallet->balance);

        $requestTx = AgentWalletTransaction::query()
            ->where('agent_deposit_request_id', $deposit->id)
            ->where('type', AgentWalletTransactionType::DepositRequest)
            ->firstOrFail();
        $this->assertSame(AgentWalletTransactionStatus::Pending, $requestTx->status);

        $this->assertSame(0, AgentWalletTransaction::query()
            ->where('agent_deposit_request_id', $deposit->id)
            ->where('type', AgentWalletTransactionType::DepositApproved)
            ->count());

        $this->assertSame(0, LedgerTransaction::query()
            ->where('source_type', $deposit->getMorphClass())
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->count());
    }

    public function test_admin_reject_does_not_change_balance(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $admin = $this->platformAdmin();
        $wallet = app(AgentWalletService::class)->walletFor($agent);

        $deposit = app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 1500,
        ]);

        $this->actingAs($admin)->patch(route('admin.agent-deposits.reject', $deposit), [
            'admin_note' => 'Proof unclear',
        ])->assertRedirect();

        $wallet->refresh();
        $this->assertSame(0.0, (float) $wallet->balance);
        $this->assertSame('rejected', $deposit->fresh()->status->value);
    }

    public function test_deposit_submitted_notification_routes_to_finance_and_admin(): void
    {
        $platformAdmin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true, 'meta' => ['finance_email' => 'finance@example.test']],
        );

        [$agentUser] = $this->seededAgent();

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $agency,
            OtaNotificationEvent::AgentDepositSubmitted->value,
            null,
            $agentUser,
            ['applicant_email' => $agentUser->email],
        );

        $this->assertContains('finance@example.test', $resolved['to']);
        $this->assertContains(strtolower($platformAdmin->email), $resolved['to']);
    }

    public function test_legacy_agency_admin_cannot_approve_deposit(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $legacy = $this->legacyAgencyAdminFromSeed();
        $deposit = app(AgentWalletService::class)->submitDepositRequest($agent, $agentUser, [
            'amount' => 500,
        ]);

        $this->actingAs($legacy)->patch(route('admin.agent-deposits.approve', $deposit))->assertForbidden();
    }

    public function test_deposit_approved_notification_routes_to_agent(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        [$agentUser] = $this->seededAgent();

        $resolved = app(NotificationRecipientResolver::class)->resolve(
            $agency,
            OtaNotificationEvent::AgentDepositApproved->value,
            null,
            null,
            ['applicant_email' => $agentUser->email],
        );

        $this->assertContains(strtolower($agentUser->email), $resolved['to']);
    }

    public function test_sidebar_shows_wallet_balance(): void
    {
        [$agentUser, $agent] = $this->seededAgent();
        $wallet = app(AgentWalletService::class)->walletFor($agent);
        $wallet->update(['balance' => 1200]);

        $this->actingAs($agentUser)->get(route('agent.wallet.show'))
            ->assertOk()
            ->assertSee('data-testid="agent-wallet-kpis"', false)
            ->assertSee('1,200.00', false);
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

    protected function otherAgent(): Agent
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $otherUser = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($otherUser->id, ['role' => 'agent']);

        return Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $otherUser->id,
        ]);
    }
}
