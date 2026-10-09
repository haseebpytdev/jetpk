<?php

namespace Tests\Feature\Console;

use App\Console\Commands\JetpkDashboardFinal11QaCommand;
use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agencies\AgencyRolePermissionMatrix;
use App\Support\Qa\JetpkDashboardFinal11QaScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class JetpkDashboardFinal11QaCommandTestHarness extends JetpkDashboardFinal11QaCommand
{
    public string $stdinPassword = '';

    protected function readSyncPasswordStdin(): string
    {
        return $this->stdinPassword;
    }
}

class JetpkDashboardFinal11QaCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PASSWORD = 'Final11QaPass!567';

    private const FINAL11_PASSWORD_ENV_KEYS = [
        'JP_FINAL_11_QA_CUSTOMER_B_PASSWORD',
        'JP_FINAL_11_QA_AGENT_B_PASSWORD',
        'JP_FINAL_11_QA_STAFF_MANAGER_PASSWORD',
        'JP_FINAL_11_QA_STAFF_ACCOUNTANT_PASSWORD',
        'JP_FINAL_11_QA_STAFF_SALES_PASSWORD',
        'JP_FINAL_11_QA_STAFF_SUPPORT_PASSWORD',
        'JP_FINAL_11_QA_STAFF_TICKETING_PASSWORD',
        'JP_FINAL_11_QA_STAFF_VIEWER_PASSWORD',
        'JP_FINAL_11_QA_LEGACY_AGENCY_ADMIN_PASSWORD',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDash03PasswordEnv();
        $this->unsetAllFinal11PasswordEnv();
        $this->bootstrapQaAgencyContext();
    }

    private function seedDash03PasswordEnv(): void
    {
        putenv('JP_DASH_03_QA_ADMIN_PASSWORD='.self::TEST_PASSWORD);
        putenv('JP_DASH_03_QA_STAFF_PASSWORD='.self::TEST_PASSWORD);
        putenv('JP_DASH_03_QA_AGENT_PASSWORD='.self::TEST_PASSWORD);
        putenv('JP_DASH_03_QA_CUSTOMER_PASSWORD='.self::TEST_PASSWORD);
        putenv('JP_DASH_03_QA_AGENT_STAFF_PASSWORD='.self::TEST_PASSWORD);
    }

    private function unsetAllFinal11PasswordEnv(): void
    {
        foreach (self::FINAL11_PASSWORD_ENV_KEYS as $envKey) {
            putenv($envKey.'=');
            unset($_ENV[$envKey], $_SERVER[$envKey]);
        }
    }

    private function bootstrapQaAgencyContext(): void
    {
        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'all', 'action' => 'create'])->assertSuccessful();
        $this->artisan('jetpk:dash-03-qa-staff', ['action' => 'create'])->assertSuccessful();
        $this->artisan('jetpk:dashboard-prod-cert-qa', ['action' => 'reconcile'])->assertSuccessful();

        Agency::query()->firstOrCreate(
            ['slug' => JetpkDashboardFinal11QaScope::LEGACY_AGENCY_SLUG],
            [
                'name' => 'JP-DASH-03 QA Legacy Agency',
                'timezone' => 'Asia/Karachi',
                'settings' => [
                    'qa_only' => true,
                    'jp_dash_03_qa' => true,
                    'purpose' => 'jp_dash_03_qa_agency',
                ],
            ],
        );
    }

    public function test_status_is_read_only(): void
    {
        $beforeUsers = User::query()->count();

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'status'])
            ->expectsOutputToContain('FINAL11_QA_RUN_ID='.JetpkDashboardFinal11QaCommand::QA_RUN_ID)
            ->assertSuccessful();

        $this->assertSame($beforeUsers, User::query()->count());
    }

    public function test_reconcile_requires_execute_flag(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile'])
            ->expectsOutputToContain('FINAL11_RECONCILE=EXECUTE_REQUIRED')
            ->assertFailed();
    }

    public function test_reconcile_creates_missing_owned_qa_identities(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->expectsOutputToContain('FINAL11_QA_RECONCILE=PASS')
            ->assertSuccessful();

        $customerB = User::query()->where('username', 'jp-final-11-qa-customer-b')->first();
        $this->assertNotNull($customerB);
        $this->assertSame(AccountType::Customer, $customerB->account_type);

        $agentB = User::query()->where('username', 'jp-final-11-qa-agent-b')->first();
        $this->assertNotNull($agentB);
        $agencyB = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_B_SLUG)->first();
        $this->assertNotNull($agencyB);
        $this->assertSame($agencyB->id, $agentB->current_agency_id);

        $legacy = User::query()->where('username', 'jp-final-11-qa-legacy-agency-admin')->first();
        $this->assertNotNull($legacy);
        $this->assertSame(AccountType::AgencyAdmin, $legacy->account_type);
    }

    public function test_reconcile_is_idempotent(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $countAfterFirst = User::query()
            ->where('username', 'like', JetpkDashboardFinal11QaScope::USERNAME_PREFIX.'%')
            ->count();

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->expectsOutputToContain('FINAL11_QA_RECONCILE=PASS')
            ->assertSuccessful();

        $countAfterSecond = User::query()
            ->where('username', 'like', JetpkDashboardFinal11QaScope::USERNAME_PREFIX.'%')
            ->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(9, $countAfterSecond);
    }

    public function test_non_qa_user_is_never_overwritten(): void
    {
        $agency = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)->firstOrFail();
        $victim = User::factory()->create([
            'username' => 'production-real-customer',
            'email' => 'real.customer@example.com',
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'name' => 'Real Customer',
            'meta' => [],
        ]);
        $originalName = $victim->name;

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $victim->refresh();
        $this->assertSame($originalName, $victim->name);
        $this->assertFalse(JetpkDashboardFinal11QaScope::isOwnedUser($victim));
    }

    public function test_agent_a_and_agent_b_stay_isolated(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $agentA = User::query()->where('username', 'jp-dash-03-qa-agent')->firstOrFail();
        $agentB = User::query()->where('username', 'jp-final-11-qa-agent-b')->firstOrFail();
        $agencyA = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)->firstOrFail();
        $agencyB = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_B_SLUG)->firstOrFail();

        $this->assertSame($agencyA->id, $agentA->current_agency_id);
        $this->assertSame($agencyB->id, $agentB->current_agency_id);
        $this->assertNotSame($agentA->current_agency_id, $agentB->current_agency_id);

        $this->assertTrue(
            Agent::query()->where('agency_id', $agencyA->id)->where('user_id', $agentA->id)->exists()
        );
        $this->assertTrue(
            Agent::query()->where('agency_id', $agencyB->id)->where('user_id', $agentB->id)->exists()
        );
    }

    public function test_all_six_agent_staff_role_templates_are_created(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $expectedRoles = [
            'agent_staff_manager' => AgencyRole::Manager,
            'agent_staff_accountant' => AgencyRole::Accountant,
            'agent_staff_sales' => AgencyRole::SalesAgent,
            'agent_staff_support' => AgencyRole::SupportStaff,
            'agent_staff_ticketing' => AgencyRole::TicketingStaff,
            'agent_staff_viewer' => AgencyRole::Viewer,
        ];

        foreach ($expectedRoles as $roleKey => $agencyRole) {
            $definition = JetpkDashboardFinal11QaCommand::ROLE_DEFINITIONS[$roleKey];
            $user = User::query()->where('username', $definition['marker'])->first();
            $this->assertNotNull($user, "Missing {$roleKey}");
            $this->assertSame(AccountType::AgentStaff, $user->account_type);
            $membership = AgencyUser::query()
                ->where('user_id', $user->id)
                ->where('agency_id', $user->current_agency_id)
                ->first();
            $this->assertNotNull($membership);
            $storedRole = $membership->agency_role;
            $storedValue = $storedRole instanceof AgencyRole ? $storedRole->value : (string) $storedRole;
            $this->assertSame($agencyRole->value, $storedValue);
        }
    }

    public function test_agent_staff_permissions_use_authoritative_templates(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $user = User::query()->where('username', 'jp-final-11-qa-staff-manager')->firstOrFail();
        $expected = AgencyRolePermissionMatrix::templatePermissionsForAgentStaff(AgencyRole::Manager);
        $actual = is_array($user->meta['agent_permissions'] ?? null) ? $user->meta['agent_permissions'] : [];
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_customer_a_and_customer_b_remain_distinct(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $customerA = User::query()->where('username', 'jp-dash-03-qa-customer')->firstOrFail();
        $customerB = User::query()->where('username', 'jp-final-11-qa-customer-b')->firstOrFail();

        $this->assertNotSame($customerA->id, $customerB->id);
        $this->assertNotSame($customerA->email, $customerB->email);
    }

    public function test_password_values_never_appear_in_command_output(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->doesntExpectOutputToContain(self::TEST_PASSWORD)
            ->assertSuccessful();
    }

    public function test_reconcile_passes_with_all_final11_password_env_unset(): void
    {
        $this->unsetAllFinal11PasswordEnv();

        $final11UserIds = User::query()
            ->where('username', 'like', JetpkDashboardFinal11QaScope::USERNAME_PREFIX.'%')
            ->pluck('id');
        AgencyUser::query()->whereIn('user_id', $final11UserIds)->delete();
        Agent::query()->whereIn('user_id', $final11UserIds)->delete();
        User::query()->whereIn('id', $final11UserIds)->delete();

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->expectsOutputToContain('FINAL11_QA_RECONCILE=PASS')
            ->doesntExpectOutputToContain('FINAL11_MISSING_ENV=')
            ->assertSuccessful();

        $this->assertSame(
            9,
            User::query()->where('username', 'like', JetpkDashboardFinal11QaScope::USERNAME_PREFIX.'%')->count(),
        );
    }

    public function test_newly_created_user_has_bootstrap_password_and_must_change_flag(): void
    {
        $this->unsetAllFinal11PasswordEnv();

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $user = User::query()->where('username', 'jp-final-11-qa-customer-b')->firstOrFail();
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertFalse(Hash::check(self::TEST_PASSWORD, (string) $user->password));
        $this->assertFalse(Hash::check('password', (string) $user->password));
    }

    public function test_sync_password_via_stdin_sets_password_and_clears_must_change(): void
    {
        $this->unsetAllFinal11PasswordEnv();

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $syncPassword = 'Final11StdinSync!890';
        $output = $this->runFinal11SyncPasswordHarness('customer_b', $syncPassword);
        $this->assertStringContainsString('FINAL11_PASSWORD_SYNC=PASS', $output);
        $this->assertStringNotContainsString($syncPassword, $output);

        $user = User::query()->where('username', 'jp-final-11-qa-customer-b')->firstOrFail();
        $this->assertTrue(Hash::check($syncPassword, (string) $user->password));
        $this->assertFalse((bool) $user->must_change_password);
    }

    public function test_repeated_reconcile_does_not_reset_synced_owned_user_password(): void
    {
        $this->unsetAllFinal11PasswordEnv();

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->assertSuccessful();

        $syncPassword = 'Final11PreserveSync!901';
        $this->runFinal11SyncPasswordHarness('customer_b', $syncPassword);

        $user = User::query()->where('username', 'jp-final-11-qa-customer-b')->firstOrFail();
        $hashBefore = (string) $user->password;

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->expectsOutputToContain('FINAL11_QA_RECONCILE=PASS')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame($hashBefore, (string) $user->password);
        $this->assertTrue(Hash::check($syncPassword, (string) $user->password));
        $this->assertFalse((bool) $user->must_change_password);
    }

    private function runFinal11SyncPasswordHarness(string $roleKey, string $password): string
    {
        $command = new JetpkDashboardFinal11QaCommandTestHarness;
        $command->stdinPassword = $password;
        $command->setLaravel($this->app);
        $input = new ArrayInput([
            'action' => 'sync-password',
            '--role' => $roleKey,
            '--execute' => true,
        ]);
        $output = new BufferedOutput;
        $exit = $command->run($input, $output);
        $buffer = $output->fetch();
        $this->assertSame(0, $exit, $buffer);

        return $buffer;
    }

    public function test_sync_password_requires_execute_and_stdin(): void
    {
        $this->artisan('jetpk:dashboard-final-11-qa', [
            'action' => 'sync-password',
            '--role' => 'customer_b',
        ])->expectsOutputToContain('FINAL11_PASSWORD_SYNC=EXECUTE_REQUIRED')
            ->assertFailed();
    }

    public function test_ambiguous_existing_user_blocks_reconcile(): void
    {
        Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)->firstOrFail();
        User::factory()->create([
            'username' => 'jp-final-11-qa-customer-b',
            'email' => 'wrong@jetpakistan.pk',
            'account_type' => AccountType::Agent,
            'meta' => [],
        ]);

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->expectsOutputToContain('FINAL11_customer_b_RECONCILE=ambiguous_ownership')
            ->assertFailed();
    }

    public function test_reserved_username_without_qa_metadata_blocks_reconcile_and_leaves_user_unchanged(): void
    {
        $agency = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)->firstOrFail();
        $collision = User::factory()->create([
            'username' => 'jp-final-11-qa-customer-b',
            'email' => 'collision.customer@jetpakistan.pk',
            'name' => 'Collision Customer',
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'meta' => [],
        ]);

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->expectsOutputToContain('FINAL11_customer_b_RECONCILE=ambiguous_ownership')
            ->assertFailed();

        $collision->refresh();
        $this->assertSame('Collision Customer', $collision->name);
        $this->assertSame('collision.customer@jetpakistan.pk', $collision->email);
        $this->assertFalse(JetpkDashboardFinal11QaScope::hasPositiveOwnershipMeta($collision));
    }

    public function test_reserved_username_with_final11_qa_metadata_reconcile_passes(): void
    {
        $agency = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)->firstOrFail();
        User::factory()->create([
            'username' => 'jp-final-11-qa-customer-b',
            'email' => 'jp-final-11-qa-customer-b@jetpakistan.pk',
            'name' => 'JP-FINAL-11 QA Customer B',
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'status' => UserAccountStatus::Active,
            'password' => Hash::make(self::TEST_PASSWORD),
            'meta' => [
                'jp_final_11_qa' => true,
                'qa_run_id' => JetpkDashboardFinal11QaScope::QA_RUN_ID,
                'qa_only' => true,
            ],
        ]);

        $this->artisan('jetpk:dashboard-final-11-qa', ['action' => 'reconcile', '--execute' => true])
            ->expectsOutputToContain('FINAL11_customer_b_RECONCILE=PASS')
            ->assertSuccessful();
    }

    public function test_sync_password_on_ambiguous_reserved_user_fails_and_password_unchanged(): void
    {
        $agency = Agency::query()->where('slug', JetpkDashboardFinal11QaScope::QA_AGENCY_A_SLUG)->firstOrFail();
        $user = User::factory()->create([
            'username' => 'jp-final-11-qa-customer-b',
            'email' => 'collision.sync@jetpakistan.pk',
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'meta' => [],
        ]);
        $user->forceFill(['password' => Hash::make('OriginalPass!999')])->save();

        $this->artisan('jetpk:dashboard-final-11-qa', [
            'action' => 'sync-password',
            '--role' => 'customer_b',
            '--execute' => true,
        ])->expectsOutputToContain('FINAL11_PASSWORD_SYNC=USER_MISSING_OR_AMBIGUOUS')
            ->assertFailed();

        $user->refresh();
        $this->assertTrue(Hash::check('OriginalPass!999', (string) $user->password));
    }

    public function test_setup_script_has_no_direct_runtime_scp_or_upload(): void
    {
        $path = base_path('dashboard/scripts/jp-dash-final-11/setup-production-qa.mjs');
        $source = (string) file_get_contents($path);

        $this->assertStringNotContainsString('uploadCommandIfNeeded', $source);
        $this->assertStringNotContainsString('spawnSync("scp"', $source);
        $this->assertStringNotContainsString('JP_SCP_HOST', $source);
        $this->assertStringContainsString('FINAL11_DIRECT_RUNTIME_SCP=NO', $source);
        $this->assertStringContainsString('FINAL11_PROTECTED_DEPLOYMENT_REQUIRED=YES', $source);
    }

    public function test_setup_script_dry_run_exits_before_production_ssh_calls(): void
    {
        $path = base_path('dashboard/scripts/jp-dash-final-11/setup-production-qa.mjs');
        $source = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression(
            '/if \(!execute\) \{[\s\S]*?FINAL11_SETUP_DRY_RUN=PASS[\s\S]*?process\.exit\(0\);[\s\S]*?\}[\s\S]*verifyProductionRuntimeSha\(\)[\s\S]*verifyRemoteCommandSupport\(\)/',
            $source,
        );
    }

    public function test_setup_script_execute_requires_expected_runtime_sha(): void
    {
        $path = base_path('dashboard/scripts/jp-dash-final-11/setup-production-qa.mjs');
        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('FINAL11_RUNTIME_SHA_CHECK=EXPECTED_REQUIRED', $source);
        $this->assertStringContainsString('PASSWORD_TRANSPORT=STDIN_ONLY', $source);
        $this->assertStringContainsString('SERVER_ENV_PASSWORDS_REQUIRED=NO', $source);
    }

    public function test_setup_execute_without_expected_runtime_sha_fails_closed(): void
    {
        $script = base_path('dashboard/scripts/jp-dash-final-11/setup-production-qa.mjs');
        $env = array_merge($_ENV, [
            'JP_FINAL11_EXECUTE' => '1',
            'JP_EXPECTED_RUNTIME_SHA' => '',
            'JP_PRODUCTION_SHA' => '',
        ]);
        foreach (self::FINAL11_PASSWORD_ENV_KEYS as $envKey) {
            $env[$envKey] = 'local-only-secret-value-'.substr($envKey, -8);
        }

        $process = new Process(['node', $script], base_path(), $env);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $combined = $process->getOutput().$process->getErrorOutput();
        $this->assertStringContainsString('FINAL11_RUNTIME_SHA_CHECK=EXPECTED_REQUIRED', $combined);
        $this->assertStringNotContainsString('local-only-secret-value-', $combined);
    }

    public function test_setup_dry_run_does_not_require_runtime_sha(): void
    {
        $script = base_path('dashboard/scripts/jp-dash-final-11/setup-production-qa.mjs');
        $env = array_merge($_ENV, [
            'JP_FINAL11_EXECUTE' => '0',
            'JP_EXPECTED_RUNTIME_SHA' => '',
        ]);
        foreach (self::FINAL11_PASSWORD_ENV_KEYS as $envKey) {
            $env[$envKey] = 'dry-run-local-secret-'.substr($envKey, -6);
        }

        $process = new Process(['node', $script], base_path(), $env);
        $process->run();

        $this->assertTrue($process->isSuccessful());
        $combined = $process->getOutput().$process->getErrorOutput();
        $this->assertStringContainsString('FINAL11_SETUP_DRY_RUN=PASS', $combined);
        $this->assertStringNotContainsString('dry-run-local-secret-', $combined);
    }
}
