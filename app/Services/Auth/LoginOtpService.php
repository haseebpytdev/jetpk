<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\LoginOtpDeliveryException;
use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Support\Auth\ClientLoginOtpGate;
use App\Support\Auth\DemoFixedLoginOtpGate;
use App\Support\Auth\LoginOtpMailDiagnostics;
use App\Support\Branding\ClientMailBrandingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session-backed login OTP challenge (hashed code, expiry, resend cooldown).
 */
final class LoginOtpService
{
    public const SESSION_KEY = 'pending_login_otp';

    public function initiate(Request $request, User $user, bool $remember): void
    {
        $this->sendChallenge($request, $user, $remember, force: true);
    }

    public function resend(Request $request): void
    {
        $pending = $this->pending($request);
        if ($pending === null) {
            throw ValidationException::withMessages([
                'otp' => 'Your login session expired. Please sign in again.',
            ]);
        }

        $user = User::query()->find($pending['user_id']);
        if ($user === null) {
            $this->clear($request);

            throw ValidationException::withMessages([
                'otp' => 'Your login session expired. Please sign in again.',
            ]);
        }

        $this->sendChallenge($request, $user, (bool) ($pending['remember'] ?? false), force: false);
    }

    /**
     * @return array{user: User, remember: bool}
     */
    public function verify(Request $request, string $otp): array
    {
        $pending = $this->pending($request);
        if ($pending === null) {
            throw ValidationException::withMessages([
                'otp' => 'Your login session expired. Please sign in again.',
            ]);
        }

        if (($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $this->clear($request);

            throw ValidationException::withMessages([
                'otp' => 'This code has expired. Please sign in again to receive a new code.',
            ]);
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= ClientLoginOtpGate::maxAttempts()) {
            $this->clear($request);

            throw ValidationException::withMessages([
                'otp' => 'Too many incorrect attempts. Please sign in again.',
            ]);
        }

        $user = User::query()->find($pending['user_id']);
        if ($user === null) {
            $this->clear($request);

            throw ValidationException::withMessages([
                'otp' => 'Your login session expired. Please sign in again.',
            ]);
        }

        $otpValid = Hash::check($otp, (string) ($pending['otp_hash'] ?? ''));
        if (! $otpValid) {
            $email = strtolower(trim((string) ($pending['email'] ?? (string) $user->email)));
            if (DemoFixedLoginOtpGate::acceptsSubmittedCode($email, $otp)) {
                DemoFixedLoginOtpGate::logAccepted($email, (int) $user->id);
                $otpValid = true;
            }
        }

        if (! $otpValid) {
            $pending['attempts'] = $attempts + 1;
            $request->session()->put(self::SESSION_KEY, $pending);

            throw ValidationException::withMessages([
                'otp' => 'The verification code is incorrect.',
            ]);
        }

        $remember = (bool) ($pending['remember'] ?? false);
        $this->clear($request);

        return ['user' => $user, 'remember' => $remember];
    }

    public function hasPending(Request $request): bool
    {
        return $this->pending($request) !== null;
    }

    public function maskedEmail(Request $request): ?string
    {
        $pending = $this->pending($request);
        if ($pending === null) {
            return null;
        }

        $email = trim((string) ($pending['email'] ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = (string) $local;
        if (strlen($local) <= 2) {
            return substr($local, 0, 1).'*@'.$domain;
        }

        return substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 2)).substr($local, -1).'@'.$domain;
    }

    public function resendAvailableIn(Request $request): int
    {
        $pending = $this->pending($request);
        if ($pending === null) {
            return 0;
        }

        $sentAt = (int) ($pending['sent_at'] ?? 0);
        $cooldown = ClientLoginOtpGate::resendCooldownSeconds();
        $remaining = ($sentAt + $cooldown) - now()->getTimestamp();

        return max(0, $remaining);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function sendChallenge(Request $request, User $user, bool $remember, bool $force): void
    {
        $pending = $this->pending($request);
        if (! $force && $pending !== null) {
            $remaining = $this->resendAvailableIn($request);
            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'otp' => 'Please wait '.$remaining.' seconds before requesting a new code.',
                ]);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $clientSlug = ClientLoginOtpGate::resolvedClientSlug($request);
        $brandName = $clientSlug === 'jetpk'
            ? ClientMailBrandingResolver::resolve('jetpk')->companyName
            : (is_client_preview() ? client_branding()->companyName() : (string) config('app.name', 'OTA'));
        $email = trim((string) $user->email);
        $maskedEmail = $this->maskEmailForLogs($email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new LoginOtpDeliveryException(
                'We cannot send a verification code because this account has no valid email on file. Contact your administrator to add an email address.',
            );
        }

        $skipMailForDemo = DemoFixedLoginOtpGate::isEmailAllowed($email);

        if (! $skipMailForDemo) {
            $fromAddress = trim((string) config('mail.from.address', ''));
            if ($fromAddress === '' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
                Log::warning('Login OTP email blocked — MAIL_FROM_ADDRESS missing or invalid.', [
                    'user_id' => $user->id,
                    'client_slug' => $clientSlug,
                    'masked_email' => $maskedEmail,
                    'mail_config' => LoginOtpMailDiagnostics::mailConfigSnapshot(),
                ]);

                throw new LoginOtpDeliveryException(
                    LoginOtpMailDiagnostics::userFacingMessage($clientSlug),
                );
            }

            try {
                Mail::to($email)->send(new LoginOtpMail(
                    user: $user,
                    brandName: $brandName,
                    otpCode: $code,
                    expiryMinutes: ClientLoginOtpGate::expiryMinutes(),
                    clientSlug: $clientSlug,
                ));
            } catch (\Throwable $e) {
                LoginOtpMailDiagnostics::logFailure($e, $user->id, $clientSlug, $maskedEmail);

                throw new LoginOtpDeliveryException(
                    LoginOtpMailDiagnostics::userFacingMessage($clientSlug),
                );
            }
        } else {
            Log::info('Login OTP challenge started for demo allowlisted user (email delivery skipped in local demo mode).', [
                'user_id' => $user->id,
                'client_slug' => $clientSlug,
                'masked_email' => $maskedEmail,
            ]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'email' => (string) $user->email,
            'remember' => $remember,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(ClientLoginOtpGate::expiryMinutes())->getTimestamp(),
            'attempts' => 0,
            'sent_at' => now()->getTimestamp(),
            'client_slug' => ClientLoginOtpGate::resolvedClientSlug($request),
            'challenge' => Str::random(40),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending)) {
            return null;
        }

        $slug = ClientLoginOtpGate::resolvedClientSlug($request);
        $pendingSlug = $pending['client_slug'] ?? null;
        if ($slug !== null && $pendingSlug !== null && $slug !== $pendingSlug) {
            return null;
        }

        return $pending;
    }

    private function maskEmailForLogs(string $email): string
    {
        if ($email === '' || ! str_contains($email, '@')) {
            return '—';
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = (string) $local;
        if (strlen($local) <= 2) {
            return substr($local, 0, 1).'*@'.$domain;
        }

        return substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 2)).substr($local, -1).'@'.$domain;
    }
}
