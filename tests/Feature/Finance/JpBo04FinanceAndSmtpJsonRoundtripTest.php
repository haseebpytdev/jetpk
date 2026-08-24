<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class JpBo04FinanceAndSmtpJsonRoundtripTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_json_ledger_credit_debit_and_reversal_roundtrip(): void
    {
        [$agency, $wallet] = $this->seedAgencyWallet(100);
        $admin = $this->platformAdmin();

        $index = $this->actingAs($admin)->getJson(route('admin.finance.adjustments.index', ['format' => 'json']));
        $index->assertOk()->assertJsonPath('ok', true);

        $create = $this->actingAs($admin)->getJson(route('admin.finance.adjustments.create', [
            'format' => 'json',
            'agency_id' => $agency->id,
        ]));
        $create->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('canonical_summary.wallet_id', $wallet->id);

        $creditKey = (string) Str::uuid();
        $credit = $this->actingAs($admin)->postJson(route('admin.finance.adjustments.store', ['format' => 'json']), [
            'agency_id' => $agency->id,
            'wallet_id' => $wallet->id,
            'adjustment_type' => 'manual_credit',
            'amount' => 25,
            'adjustment_reason' => 'bank_correction',
            'adjustment_note' => 'jp-bo-04d credit',
            'idempotency_key' => $creditKey,
            'confirmation' => true,
        ]);
        $credit->assertOk()->assertJsonPath('ok', true);
        $creditId = (string) $credit->json('wallet_transaction.id');
        $this->assertNotSame('', $creditId);
        $this->assertSame(125.0, (float) $wallet->fresh()->balance);

        $debitKey = (string) Str::uuid();
        $debit = $this->actingAs($admin)->postJson(route('admin.finance.adjustments.store', ['format' => 'json']), [
            'agency_id' => $agency->id,
            'wallet_id' => $wallet->id,
            'adjustment_type' => 'manual_debit',
            'amount' => 10,
            'adjustment_reason' => 'other',
            'adjustment_note' => 'jp-bo-04d debit',
            'idempotency_key' => $debitKey,
            'confirmation' => true,
        ]);
        $debit->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(115.0, (float) $wallet->fresh()->balance);

        $show = $this->actingAs($admin)->getJson(route('admin.finance.adjustments.show', [
            'walletTransaction' => $creditId,
            'format' => 'json',
        ]));
        $show->assertOk()->assertJsonPath('can_reverse', true);

        $reverse = $this->actingAs($admin)->postJson(route('admin.finance.adjustments.reverse', [
            'walletTransaction' => $creditId,
            'format' => 'json',
        ]), [
            'reversal_reason' => 'jp-bo-04d reverse credit',
            'confirmation' => true,
        ]);
        $reverse->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(90.0, (float) $wallet->fresh()->balance);

        $this->assertDatabaseHas('agent_wallet_transactions', [
            'id' => (int) $creditId,
            'type' => 'manual_credit',
        ]);
        $this->assertNotNull(
            AgentWalletTransaction::query()
                ->where('meta->reversal_of_wallet_transaction_id', (int) $creditId)
                ->first()
        );
    }

    public function test_json_smtp_settings_save_masks_secret_and_test_requires_confirmation(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();

        $load = $this->actingAs($admin)->getJson(route('admin.settings.communications.index', ['format' => 'json']));
        $load->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('settings.smtp_password');

        $save = $this->actingAs($admin)->patchJson(route('admin.settings.communications.update', ['format' => 'json']), [
            'email_enabled' => true,
            'smtp_enabled' => true,
            'smtp_host' => 'smtp.jp-bo-04.test',
            'smtp_port' => 587,
            'smtp_username' => 'ops-user',
            'smtp_password' => 'jp-bo-04-secret-password',
            'smtp_encryption' => 'tls',
            'mail_from_name' => 'JetPakistan Ops',
            'mail_from_email' => 'ops@jetpakistan.test',
        ]);
        $save->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('settings.smtp_host', 'smtp.jp-bo-04.test')
            ->assertJsonPath('settings.smtp_password_masked', '********')
            ->assertJsonMissingPath('settings.smtp_password');

        $denied = $this->actingAs($admin)->postJson(route('admin.settings.communications.test-email', ['format' => 'json']), [
            'recipient_email' => 'ops-recipient@example.test',
        ]);
        $denied->assertStatus(422);

        $test = $this->actingAs($admin)->postJson(route('admin.settings.communications.test-email', ['format' => 'json']), [
            'recipient_email' => 'ops-recipient@example.test',
            'confirmation' => true,
        ]);
        $test->assertOk()->assertJsonPath('ok', true);
        Mail::assertSentCount(1);
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
            'username' => 'jpbo04-agent-'.$agency->id.'-'.uniqid(),
            'email' => 'jpbo04-'.$agency->id.'-'.uniqid().'@example.test',
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
