<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Auth\ClientLoginOtpGate;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Read-only JetPK login user audit — OTP readiness for a username without exposing secrets.
 */
class OtaJetpkLoginUserAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-login-user-audit
                            {--username= : Username to inspect}
                            {--client=jetpk : Client slug for OTP gate simulation}';

    protected $description = 'Read-only audit — JetPK login OTP readiness for a user (masked email, roles, no secrets)';

    public function handle(): int
    {
        $username = trim((string) $this->option('username'));
        if ($username === '') {
            $this->error('Option --username is required.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('db_write_attempted=false');
        $this->newLine();

        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->first();

        if ($user === null) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($username)])
                ->first();
        }

        $clientSlug = trim((string) $this->option('client'));
        $otpRequired = $this->simulateJetpkOtpRequired($clientSlug);
        $email = trim((string) ($user?->email ?? ''));
        $emailValid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        $roles = [];
        if ($user !== null) {
            $roles[] = $user->account_type?->value ?? 'unknown';
            if ($user->isPlatformAdmin()) {
                $roles[] = 'platform_admin';
            }
            foreach ($user->agencies as $agency) {
                $pivotRole = (string) ($agency->pivot->role ?? '');
                if ($pivotRole !== '') {
                    $roles[] = $pivotRole;
                }
            }
            $roles = array_values(array_unique($roles));
        }

        $rows = [
            ['username found', $user !== null ? 'yes' : 'no'],
            ['user id', $user !== null ? (string) $user->id : '—'],
            ['account type', $user?->account_type?->value ?? '—'],
            ['email present', $email !== '' ? 'yes' : 'no'],
            ['email (masked)', $this->maskEmail($email)],
            ['email verified', $user !== null ? ($user->hasVerifiedEmail() ? 'yes' : 'no') : '—'],
            ['active', $user !== null ? ($user->isSuspended() || $user->status?->value === 'inactive' ? 'no' : 'yes') : '—'],
            ['can password login', $user !== null ? ($user->isSuspended() || $user->status?->value === 'inactive' ? 'no' : 'yes') : '—'],
            ['roles', $roles !== [] ? implode(', ', $roles) : '—'],
            ['jetpk_otp_required', $otpRequired ? 'yes' : 'no'],
            ['otp_destination_ready', $otpRequired && $emailValid ? 'yes' : ($otpRequired ? 'no' : 'n/a')],
            ['google oauth configured', \App\Http\Controllers\Auth\SocialAuthController::providerIsConfigured('google') ? 'yes' : 'no'],
            ['client.parity.social.redirect', Route::has('client.parity.social.redirect') ? 'yes' : 'no'],
            ['social.callback (shared)', Route::has('social.callback') ? 'yes' : 'no'],
        ];

        $this->table(['check', 'value'], $rows);

        if ($user === null) {
            $this->warn('User not found.');

            return self::FAILURE;
        }

        if ($otpRequired && ! $emailValid) {
            $this->warn('OTP is required for JetPK but this user has no deliverable email.');

            return self::FAILURE;
        }

        $this->info('Login user audit complete.');

        return self::SUCCESS;
    }

    private function simulateJetpkOtpRequired(string $clientSlug): bool
    {
        $request = Request::create('/'.$clientSlug.'/login', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put(\App\Http\Middleware\PersistClientPreviewContext::SESSION_KEY, $clientSlug);
        app()->instance('request', $request);

        return ClientLoginOtpGate::isRequired($request);
    }

    private function maskEmail(string $email): string
    {
        if ($email === '' || ! str_contains($email, '@')) {
            return '—';
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = (string) $local;
        if (strlen($local) <= 2) {
            return substr($local, 0, 1).'*@'.$domain;
        }

        return substr($local, 0, 2).str_repeat('*', max(1, strlen($local) - 3)).substr($local, -1).'@'.$domain;
    }
}
