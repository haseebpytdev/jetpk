<?php

namespace App\Console\Commands;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Support\Auth\ClientLoginOtpGate;
use App\Support\Auth\LoginOtpMailDiagnostics;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Branding\ClientMailBrandingResolver;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Read-only JetPK OTP mail probe — build path + optional test send (never prints OTP or secrets).
 */
class OtaJetpkOtpMailProbeCommand extends Command
{
    protected $signature = 'ota:jetpk-otp-mail-probe
                            {--username= : Username or email to inspect}
                            {--client=jetpk : Client slug for branding simulation}
                            {--send : Send a test OTP email using the live mailer (does not print the code)}';

    protected $description = 'Probe JetPK login OTP mail build/send path (read-only unless --send)';

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

        $clientSlug = trim((string) $this->option('client'));
        $this->primeClientContext($clientSlug);

        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->first();

        if ($user === null) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($username)])
                ->first();
        }

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $email = trim((string) $user->email);
        $emailValid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $branding = ClientMailBrandingResolver::resolve($clientSlug);
        $mailConfig = LoginOtpMailDiagnostics::mailConfigSnapshot();

        $rows = [
            ['user id', (string) $user->id],
            ['account type', $user->account_type?->value ?? '—'],
            ['email present', $email !== '' ? 'yes' : 'no'],
            ['email (masked)', $this->maskEmail($email)],
            ['email valid', $emailValid ? 'yes' : 'no'],
            ['jetpk_otp_required', ClientLoginOtpGate::isRequired() ? 'yes' : 'no'],
            ['mailer', $mailConfig['mailer'] !== '' ? $mailConfig['mailer'] : '—'],
            ['from_address_configured', ($mailConfig['from_address_configured'] ?? false) ? 'yes' : 'no'],
            ['from_name (config)', (string) ($mailConfig['from_name'] ?? '')],
            ['client mail from name', $branding->mailFromName],
            ['client reply-to', $branding->replyToEmail ?? '—'],
            ['queue_connection', (string) ($mailConfig['queue_connection'] ?? '')],
        ];

        $this->table(['check', 'value'], $rows);

        if (! $emailValid) {
            $this->error('User has no valid email destination.');

            return self::FAILURE;
        }

        if (! ($mailConfig['from_address_configured'] ?? false)) {
            $this->error('MAIL_FROM_ADDRESS is not configured — OTP mail cannot send.');

            return self::FAILURE;
        }

        try {
            $mailable = new LoginOtpMail(
                user: $user,
                brandName: $branding->companyName,
                otpCode: '000000',
                expiryMinutes: ClientLoginOtpGate::expiryMinutes(),
                clientSlug: $clientSlug,
            );
            $envelope = $mailable->envelope();
            $mailable->render();
            $this->info('Mailable build: OK');
            $this->line('  subject: '.$envelope->subject);
            $from = $envelope->from;
            if ($from instanceof Address) {
                $this->line('  from: '.$this->maskEmail((string) $from->address).' ('.($from->name ?? '').')');
                $this->line('  from_name resolved: '.($from->name ?? '—'));
            }
        } catch (\Throwable $e) {
            $sanitized = \App\Support\Security\SensitiveDataRedactor::sanitizeErrorMessage($e->getMessage());
            $this->error('Mailable build failed: '.$e::class.' — '.($sanitized ?? 'unknown'));
            LoginOtpMailDiagnostics::logFailure($e, $user->id, $clientSlug, $this->maskEmail($email), 'probe_build');

            return self::FAILURE;
        }

        if (! $this->option('send')) {
            $this->comment('Dry run complete. Pass --send to deliver a test OTP email (code is not printed).');

            return self::SUCCESS;
        }

        try {
            Mail::to($email)->send(new LoginOtpMail(
                user: $user,
                brandName: $branding->companyName,
                otpCode: str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                expiryMinutes: ClientLoginOtpGate::expiryMinutes(),
                clientSlug: $clientSlug,
            ));
            $this->info('Test OTP email dispatched via '.(string) config('mail.default').'.');
        } catch (\Throwable $e) {
            $this->error('Send failed: '.$e::class);
            LoginOtpMailDiagnostics::logFailure($e, $user->id, $clientSlug, $this->maskEmail($email), 'probe_send');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function primeClientContext(string $clientSlug): void
    {
        $request = Request::create('/'.$clientSlug.'/login', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put(\App\Http\Middleware\PersistClientPreviewContext::SESSION_KEY, $clientSlug);
        app()->instance('request', $request);
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
