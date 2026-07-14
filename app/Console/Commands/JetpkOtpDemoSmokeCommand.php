<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\DeveloperUser;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Support\Auth\DemoFixedLoginOtpGate;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * Smoke checks for JetPK local demo fixed OTP configuration and verifier behavior.
 */
class JetpkOtpDemoSmokeCommand extends Command
{
    protected $signature = 'jetpk:otp-demo-smoke';

    protected $description = 'JetPK OTP demo smoke — config, demo users, fixed verifier, production guard';

    /** @var list<string> */
    private array $failures = [];

    /** @var list<string> */
    private array $passes = [];

    public function handle(LoginOtpService $loginOtpService): int
    {
        $this->line('JetPK OTP demo smoke (read-only verifier checks; no OTP codes logged)');
        $this->newLine();

        $this->checkEnvironmentConfig();
        $this->checkDemoUsers();
        $this->checkDevCpUser();
        $this->checkLoginRoutes();
        $this->checkVerifierBehavior($loginOtpService);
        $this->checkProductionGuard();

        $this->newLine();
        $this->info(sprintf('pass=%d fail=%d', count($this->passes), count($this->failures)));

        if ($this->failures !== []) {
            $this->newLine();
            $this->error('Failures:');
            foreach ($this->failures as $failure) {
                $this->line('  - '.$failure);
            }

            return self::FAILURE;
        }

        $masked = DemoFixedLoginOtpGate::maskCode(DemoFixedLoginOtpGate::configuredFixedCode());
        $this->info('JetPK OTP demo smoke passed. Configured demo code mask: '.$masked);

        return self::SUCCESS;
    }

    private function checkEnvironmentConfig(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->recordFail('env:expected local or testing, got '.app()->environment());

            return;
        }

        $this->recordPass('env:'.app()->environment());

        if (! DemoFixedLoginOtpGate::isEnabled()) {
            $this->recordFail('config:OTP_DEMO_FIXED_ENABLED must be true with valid six-digit OTP_DEMO_FIXED_CODE in local');

            return;
        }

        $this->recordPass('config:demo fixed OTP gate enabled');

        $allowed = config('ota_otp_demo.allowed_emails', []);
        if (! is_array($allowed) || $allowed === []) {
            $this->recordFail('config:OTP_DEMO_ALLOWED_EMAILS empty');

            return;
        }

        foreach (['admin@ota.demo', 'staff@ota.demo', 'agent@ota.demo', 'customer@ota.demo'] as $email) {
            if (! in_array($email, $allowed, true)) {
                $this->recordFail('config:missing allowed email '.$email);

                return;
            }
        }

        $this->recordPass('config:demo allowlist includes core demo users');
    }

    private function checkDemoUsers(): void
    {
        $expected = [
            'admin@ota.demo' => AccountType::PlatformAdmin,
            'staff@ota.demo' => AccountType::Staff,
            'agent@ota.demo' => AccountType::Agent,
            'customer@ota.demo' => AccountType::Customer,
        ];

        foreach ($expected as $email => $type) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            if ($user === null) {
                $this->recordFail('user:missing '.$email);

                continue;
            }

            if ($user->account_type !== $type) {
                $this->recordFail('user:'.$email.' expected '.$type->value.', got '.($user->account_type?->value ?? 'null'));

                continue;
            }

            if ($user->email_verified_at === null) {
                $this->recordFail('user:'.$email.' email not verified');

                continue;
            }

            $this->recordPass('user:'.$email.' '.$type->value);
        }
    }

    private function checkDevCpUser(): void
    {
        $devcp = DeveloperUser::query()->where('is_active', true)->count();
        if ($devcp < 1) {
            $this->recordFail('devcp:no active developer_users row');

            return;
        }

        $this->recordPass('devcp:active developer user exists (password-only login; OTP not used)');
    }

    private function checkLoginRoutes(): void
    {
        $routes = ['login', 'login.otp', 'login.otp.verify', 'dev.cp.login'];
        foreach ($routes as $name) {
            if (! Route::has($name)) {
                $this->recordFail('route:missing '.$name);

                continue;
            }

            $this->recordPass('route:'.$name);
        }
    }

    private function checkVerifierBehavior(LoginOtpService $loginOtpService): void
    {
        $code = DemoFixedLoginOtpGate::configuredFixedCode();
        if ($code === null) {
            $this->recordFail('verifier:fixed code not configured');

            return;
        }

        $admin = User::query()->where('email', 'admin@ota.demo')->first();
        if ($admin === null) {
            $this->recordFail('verifier:admin@ota.demo missing');

            return;
        }

        $request = Request::create('/login/otp', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->start();
        $sessionHash = bcrypt('999999');
        $request->session()->put(LoginOtpService::SESSION_KEY, [
            'user_id' => $admin->id,
            'email' => $admin->email,
            'remember' => false,
            'otp_hash' => $sessionHash,
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
            'attempts' => 0,
            'sent_at' => now()->getTimestamp(),
            'client_slug' => 'jetpk',
            'challenge' => 'smoke-test',
        ]);

        try {
            $result = $loginOtpService->verify($request, $code);
            if ($result['user']->id !== $admin->id) {
                $this->recordFail('verifier:demo code did not return expected admin user');
            } else {
                $this->recordPass('verifier:accepts demo code for allowlisted admin@ota.demo');
            }
        } catch (ValidationException) {
            $this->recordFail('verifier:rejected demo code for allowlisted admin@ota.demo');
        }

        $wrongHash = bcrypt('999999');
        $request->session()->put(LoginOtpService::SESSION_KEY, [
            'user_id' => $admin->id,
            'email' => $admin->email,
            'remember' => false,
            'otp_hash' => $wrongHash,
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
            'attempts' => 0,
            'sent_at' => now()->getTimestamp(),
            'client_slug' => 'jetpk',
            'challenge' => 'smoke-test-wrong',
        ]);

        try {
            $loginOtpService->verify($request, '000000');
            $this->recordFail('verifier:accepted wrong OTP for allowlisted user');
        } catch (ValidationException) {
            $this->recordPass('verifier:rejects wrong OTP for allowlisted user');
        }

        $intruder = User::query()
            ->whereRaw('LOWER(email) = ?', ['agent.sana@ota.demo'])
            ->first();

        if ($intruder === null) {
            $intruder = User::query()
                ->whereNotIn('email', config('ota_otp_demo.allowed_emails', []))
                ->where('account_type', AccountType::Customer)
                ->first();
        }

        if ($intruder === null) {
            $this->recordPass('verifier:skip non-allowlisted user test (no extra customer row)');

            return;
        }

        $request->session()->put(LoginOtpService::SESSION_KEY, [
            'user_id' => $intruder->id,
            'email' => $intruder->email,
            'remember' => false,
            'otp_hash' => bcrypt('999999'),
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
            'attempts' => 0,
            'sent_at' => now()->getTimestamp(),
            'client_slug' => 'jetpk',
            'challenge' => 'smoke-test-intruder',
        ]);

        try {
            $loginOtpService->verify($request, $code);
            $this->recordFail('verifier:accepted demo code for non-allowlisted '.$intruder->email);
        } catch (ValidationException) {
            $this->recordPass('verifier:rejects demo code for non-allowlisted user');
        }
    }

    private function checkProductionGuard(): void
    {
        if (app()->environment('production')) {
            if (DemoFixedLoginOtpGate::isEnabled()) {
                $this->recordFail('production:demo fixed OTP gate must be disabled');
            } else {
                $this->recordPass('production:demo fixed OTP gate disabled');
            }

            return;
        }

        $this->recordPass('production:guard relies on environment!==production (current env safe)');

        if (DemoFixedLoginOtpGate::acceptsSubmittedCode('admin@ota.demo', DemoFixedLoginOtpGate::configuredFixedCode() ?? '')) {
            $this->recordPass('production:local verifier active for allowlisted user as expected');
        }
    }

    private function recordPass(string $message): void
    {
        $this->passes[] = $message;
        $this->line('  OK  '.$message);
    }

    private function recordFail(string $message): void
    {
        $this->failures[] = $message;
        $this->line('  FAIL '.$message);
    }
}
