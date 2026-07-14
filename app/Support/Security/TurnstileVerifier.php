<?php

namespace App\Support\Security;

use App\Rules\Turnstile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side Cloudflare Turnstile verification (siteverify API).
 */
class TurnstileVerifier
{
    public const RESPONSE_FIELD = 'cf-turnstile-response';

    public const FAILURE_MESSAGE = 'Security check failed. Please refresh and try again.';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function isEnabled(): bool
    {
        if (! (bool) config('services.turnstile.enabled', false)) {
            return false;
        }

        $siteKey = trim((string) config('services.turnstile.site_key', ''));
        $secretKey = trim((string) config('services.turnstile.secret_key', ''));

        return $siteKey !== '' && $secretKey !== '';
    }

    /**
     * @return array<string, list<string|Turnstile>>
     */
    public static function validationRules(): array
    {
        if (! self::isEnabled()) {
            return [];
        }

        return [
            self::RESPONSE_FIELD => ['required', new Turnstile],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            self::RESPONSE_FIELD.'.required' => self::FAILURE_MESSAGE,
        ];
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! self::isEnabled()) {
            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            if (! $response->successful()) {
                Log::warning('turnstile.verify_http_failed', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return false;
            }

            if (! (bool) ($payload['success'] ?? false)) {
                $codes = $payload['error-codes'] ?? [];
                Log::info('turnstile.verify_rejected', [
                    'error_codes' => is_array($codes) ? $codes : [],
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('turnstile.verify_exception', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
