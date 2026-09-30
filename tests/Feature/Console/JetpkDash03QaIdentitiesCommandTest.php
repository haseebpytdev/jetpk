<?php

namespace Tests\Feature\Console;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agent;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JetpkDash03QaIdentitiesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('JP_DASH_03_QA_ADMIN_PASSWORD=QaAdminPass!234');
        putenv('JP_DASH_03_QA_AGENT_PASSWORD=QaAgentPass!234');
        putenv('JP_DASH_03_QA_CUSTOMER_PASSWORD=QaCustomerPass!234');
        $_ENV['JP_DASH_03_QA_ADMIN_PASSWORD'] = 'QaAdminPass!234';
        $_ENV['JP_DASH_03_QA_AGENT_PASSWORD'] = 'QaAgentPass!234';
        $_ENV['JP_DASH_03_QA_CUSTOMER_PASSWORD'] = 'QaCustomerPass!234';
    }

    protected function tearDown(): void
    {
        putenv('JP_DASH_03_QA_ADMIN_PASSWORD');
        putenv('JP_DASH_03_QA_AGENT_PASSWORD');
        putenv('JP_DASH_03_QA_CUSTOMER_PASSWORD');
        unset(
            $_ENV['JP_DASH_03_QA_ADMIN_PASSWORD'],
            $_ENV['JP_DASH_03_QA_AGENT_PASSWORD'],
            $_ENV['JP_DASH_03_QA_CUSTOMER_PASSWORD'],
        );
        parent::tearDown();
    }

    public function test_create_all_identities_with_zero_agent_wallet_and_no_password_leak(): void
    {
        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'all', 'action' => 'create'])
            ->expectsOutputToContain('QA_admin_CREATED=yes')
            ->expectsOutputToContain('QA_agent_CREATED=yes')
            ->expectsOutputToContain('QA_customer_CREATED=yes')
            ->expectsOutputToContain('QA_AGENT_WALLET_BALANCE=0')
            ->expectsOutputToContain('QA_AGENT_CREDIT_LIMIT=0')
            ->assertSuccessful();

        $admin = User::query()->where('username', 'jp-dash-03-qa-admin')->first();
        $agentUser = User::query()->where('username', 'jp-dash-03-qa-agent')->first();
        $customer = User::query()->where('username', 'jp-dash-03-qa-customer')->first();

        $this->assertNotNull($admin);
        $this->assertSame(AccountType::PlatformAdmin, $admin->account_type);
        $this->assertTrue((bool) ($admin->meta['jp_dash_03_qa'] ?? false));
        $this->assertTrue(Hash::check('QaAdminPass!234', $admin->password));

        $this->assertNotNull($agentUser);
        $agent = Agent::query()->where('user_id', $agentUser->id)->first();
        $this->assertNotNull($agent);
        $wallet = app(AgentWalletService::class)->walletFor($agent);
        $this->assertSame(0.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) ($wallet->credit_limit ?? 0));

        $this->assertNotNull($customer);
        $this->assertSame(AccountType::Customer, $customer->account_type);

        $output = $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'admin', 'action' => 'status'])
            ->run();
        $this->assertSame(0, $output);
    }

    public function test_deactivate_invalidates_sessions_and_remember_token(): void
    {
        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'admin', 'action' => 'create'])->assertSuccessful();
        $user = User::query()->where('username', 'jp-dash-03-qa-admin')->firstOrFail();
        $user->forceFill(['remember_token' => 'remember-token-value'])->save();
        DB::table('sessions')->insert([
            'id' => 'qa-session-1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'admin', 'action' => 'deactivate'])
            ->expectsOutputToContain('QA_admin_STATUS=Inactive')
            ->expectsOutputToContain('QA_admin_SESSIONS_INVALIDATED=yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(UserAccountStatus::Suspended, $user->status);
        $this->assertNull($user->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_password_required_without_env(): void
    {
        putenv('JP_DASH_03_QA_ADMIN_PASSWORD');
        unset($_ENV['JP_DASH_03_QA_ADMIN_PASSWORD']);

        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'admin', 'action' => 'create'])
            ->expectsOutputToContain('QA_admin_PASSWORD_REQUIRED=YES')
            ->assertFailed();
    }

    public function test_command_output_never_contains_password_material(): void
    {
        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'agent', 'action' => 'create'])->assertSuccessful();
        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'agent', 'action' => 'status'])->assertSuccessful();
        $text = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringNotContainsString('QaAgentPass!234', $text);
        $this->assertStringNotContainsString('password=', strtolower($text));
    }
}
