<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Controlled JP-DASH-03 temporary QA identities (Admin, Agent, Customer).
 * Staff uses {@see JetpkDash03QaStaffCommand}. Never prints passwords or OTP.
 */
class JetpkDash03QaIdentitiesCommand extends Command
{
  private const QA_AGENCY_SLUG = 'jp-dash-03-qa-agency';

  /** @var array<string, array{marker: string, name: string, email: string, account_type: AccountType, agency_role: string, env_password: string}> */
  private const ROLE_DEFINITIONS = [
    'admin' => [
      'marker' => 'jp-dash-03-qa-admin',
      'name' => 'JP-DASH-03 QA Admin',
      'email' => 'jp-dash-03-qa-admin@jetpakistan.pk',
      'account_type' => AccountType::PlatformAdmin,
      'agency_role' => 'agency_admin',
      'env_password' => 'JP_DASH_03_QA_ADMIN_PASSWORD',
    ],
    'agent' => [
      'marker' => 'jp-dash-03-qa-agent',
      'name' => 'JP-DASH-03 QA Agent',
      'email' => 'jp-dash-03-qa-agent@jetpakistan.pk',
      'account_type' => AccountType::Agent,
      'agency_role' => 'agent',
      'env_password' => 'JP_DASH_03_QA_AGENT_PASSWORD',
    ],
    'customer' => [
      'marker' => 'jp-dash-03-qa-customer',
      'name' => 'JP-DASH-03 QA Customer',
      'email' => 'jp-dash-03-qa-customer@jetpakistan.pk',
      'account_type' => AccountType::Customer,
      'agency_role' => 'customer',
      'env_password' => 'JP_DASH_03_QA_CUSTOMER_PASSWORD',
    ],
  ];

  protected $signature = 'jetpk:dash-03-qa-identities
        {role : admin|agent|customer|all}
        {action=status : create|deactivate|rotate-password|fix-emails|verify-email|status}';

  protected $description = 'JP-DASH-03 controlled temporary QA Admin/Agent/Customer identities';

  public function handle(AgentWalletService $walletService): int
  {
    $role = strtolower((string) $this->argument('role'));
    $action = (string) $this->argument('action');

    if ($role === 'all') {
      $exit = self::SUCCESS;
      foreach (array_keys(self::ROLE_DEFINITIONS) as $roleKey) {
        $result = $this->dispatchRoleAction($roleKey, $action, $walletService);
        if ($result !== self::SUCCESS) {
          $exit = $result;
        }
      }

      return $exit;
    }

    if (! isset(self::ROLE_DEFINITIONS[$role])) {
      $this->line('QA_IDENTITY_COMMAND=INVALID_ROLE');
      $this->error("Unknown role: {$role}");

      return self::FAILURE;
    }

    return $this->dispatchRoleAction($role, $action, $walletService);
  }

  private function dispatchRoleAction(string $role, string $action, AgentWalletService $walletService): int
  {
    return match ($action) {
      'create' => $this->createIdentity($role, $walletService),
      'deactivate' => $this->deactivateIdentity($role),
      'rotate-password' => $this->rotatePassword($role),
      'fix-emails' => $this->fixEmails($role),
      'verify-email' => $this->verifyEmail($role),
      'status' => $this->reportStatus($role),
      default => $this->invalidAction($action),
    };
  }

  private function invalidAction(string $action): int
  {
    $this->line('QA_IDENTITY_COMMAND=INVALID_ACTION');
    $this->error("Unknown action: {$action}");

    return self::FAILURE;
  }

  private function definition(string $role): array
  {
    return self::ROLE_DEFINITIONS[$role];
  }

  private function qaUserQuery(string $role)
  {
    $definition = $this->definition($role);

    return User::query()
      ->where('account_type', $definition['account_type'])
      ->where(function ($query) use ($definition): void {
        $query->where('username', $definition['marker'])
          ->orWhere('name', $definition['name']);
      });
  }

  private function resolvePassword(string $role): ?string
  {
    $envKey = $this->definition($role)['env_password'];
    $password = (string) env($envKey, '');

    return $password !== '' ? $password : null;
  }

  private function resolveAgency(string $role): ?Agency
  {
    if ($role === 'agent') {
      return Agency::query()->firstOrCreate(
        ['slug' => self::QA_AGENCY_SLUG],
        [
          'name' => 'JP-DASH-03 QA Agency',
          'timezone' => 'Asia/Karachi',
          'settings' => ['jp_dash_03_qa' => true],
        ],
      );
    }

    return Agency::query()->orderBy('id')->first();
  }

  private function createIdentity(string $role, AgentWalletService $walletService): int
  {
    if ($this->qaUserQuery($role)->exists()) {
      $this->line('QA_'.$role.'_CREATED=already_exists');
      $this->reportStatus($role);

      return self::SUCCESS;
    }

    $password = $this->resolvePassword($role);
    if ($password === null) {
      $this->line('QA_'.$role.'_PASSWORD_REQUIRED=YES');
      $this->error('Set '.$this->definition($role)['env_password'].' in the process environment (never commit).');

      return self::FAILURE;
    }

    $agency = $this->resolveAgency($role);
    if ($agency === null) {
      $this->line('QA_'.$role.'_CREATED=no');
      $this->error('No agency available for QA identity.');

      return self::FAILURE;
    }

    $definition = $this->definition($role);

    DB::transaction(function () use ($role, $definition, $password, $agency, $walletService): void {
      $user = User::query()->create([
        'name' => $definition['name'],
        'email' => $definition['email'],
        'username' => $definition['marker'],
        'password' => Hash::make($password),
        'account_type' => $definition['account_type'],
        'current_agency_id' => $agency->id,
        'status' => UserAccountStatus::Active,
        'email_verified_at' => now(),
        'meta' => ['jp_dash_03_qa' => true],
      ]);

      AgencyUser::query()->create([
        'agency_id' => $agency->id,
        'user_id' => $user->id,
        'role' => $definition['agency_role'],
      ]);

      if ($role === 'agent') {
        $agent = Agent::query()->create([
          'agency_id' => $agency->id,
          'user_id' => $user->id,
          'code' => 'QA-JP-DASH-03',
          'commission_percent' => 0,
          'is_active' => true,
          'meta' => ['jp_dash_03_qa' => true, 'agency_name' => $agency->name],
        ]);

        $wallet = $walletService->walletFor($agent);
        $wallet->forceFill([
          'balance' => 0,
          'credit_limit' => 0,
        ])->save();
      }
    });

    $this->line('QA_'.$role.'_CREATED=yes');
    $this->line('QA_'.$role.'_ACCOUNT_TYPE='.$definition['account_type']->value);
    $this->line('QA_'.$role.'_STATUS=Active');
    if ($role === 'agent') {
      $this->line('QA_AGENT_AGENCY_TEST_ENTITY=yes');
      $this->line('QA_AGENT_WALLET_BALANCE=0');
      $this->line('QA_AGENT_CREDIT_LIMIT=0');
    }

    return self::SUCCESS;
  }

  private function deactivateIdentity(string $role): int
  {
    $user = $this->qaUserQuery($role)->first();
    if ($user === null) {
      $this->line('QA_'.$role.'_STATUS=missing');

      return self::SUCCESS;
    }

    $user->forceFill([
      'status' => UserAccountStatus::Suspended,
      'remember_token' => null,
    ])->save();

    DB::table('sessions')->where('user_id', $user->id)->delete();

    if ($role === 'agent') {
      Agent::query()->where('user_id', $user->id)->update(['is_active' => false]);
    }

    $this->line('QA_'.$role.'_STATUS=Inactive');
    $this->line('QA_'.$role.'_SESSIONS_INVALIDATED=yes');
    $this->line('QA_'.$role.'_REMEMBER_TOKEN_INVALIDATED=yes');

    return self::SUCCESS;
  }

  private function fixEmails(string $role): int
  {
    $user = $this->qaUserQuery($role)->first();
    if ($user === null) {
      $this->line('QA_'.$role.'_STATUS=missing');

      return self::FAILURE;
    }

    $definition = $this->definition($role);
    $user->forceFill(['email' => $definition['email']])->save();
    $this->line('QA_'.$role.'_EMAIL_FIXED=yes');

    return self::SUCCESS;
  }

  private function verifyEmail(string $role): int
  {
    $user = $this->qaUserQuery($role)->first();
    if ($user === null) {
      $this->line('QA_'.$role.'_STATUS=missing');

      return self::FAILURE;
    }

    if ($user->email_verified_at === null) {
      $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->line('QA_'.$role.'_EMAIL_VERIFIED=yes');

    return self::SUCCESS;
  }

  private function rotatePassword(string $role): int
  {
    $user = $this->qaUserQuery($role)->first();
    if ($user === null) {
      $this->line('QA_'.$role.'_STATUS=missing');

      return self::FAILURE;
    }

    $password = $this->resolvePassword($role);
    if ($password === null) {
      $this->line('QA_'.$role.'_PASSWORD_REQUIRED=YES');

      return self::FAILURE;
    }

    $user->forceFill(['password' => Hash::make($password)])->save();
    $this->line('QA_'.$role.'_PASSWORD_ROTATED=yes');

    return self::SUCCESS;
  }

  private function reportStatus(string $role): int
  {
    $user = $this->qaUserQuery($role)->first();
    if ($user === null) {
      $this->line('QA_'.$role.'_CREATED=no');
      $this->line('QA_'.$role.'_STATUS=missing');

      return self::SUCCESS;
    }

    $definition = $this->definition($role);
    $this->line('QA_'.$role.'_CREATED=yes');
    $this->line('QA_'.$role.'_ACCOUNT_TYPE='.$definition['account_type']->value);
    $this->line('QA_'.$role.'_STATUS='.$user->status->value);
    $this->line('QA_'.$role.'_USER_ID='.$user->id);
    if ($role === 'agent') {
      $this->line('QA_AGENT_AGENCY_TEST_ENTITY=yes');
    }

    return self::SUCCESS;
  }
}
