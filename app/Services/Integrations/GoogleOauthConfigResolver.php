<?php

namespace App\Services\Integrations;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\GoogleOauthSupplierConnectionNormalizer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * DB-first Google OAuth resolution with ENV fallback. Disabling DB never deletes ENV.
 * Active but incomplete/invalid DB rows also fall back safely to ENV.
 * Never overrides env with empty DB values.
 */
final class GoogleOauthConfigResolver
{
    public function applyRuntimeConfig(): string
    {
        $connection = $this->activeGoogleOauthConnection();
        if ($connection === null) {
            return 'env_fallback';
        }

        if (! $this->isUsable($connection)) {
            Log::warning('google_oauth.db_connection_invalid_fallback', [
                'connection_id' => $connection->id,
                'reason' => $this->usabilityFailureReason($connection),
            ]);

            return 'env_fallback';
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        $clientSecret = trim((string) ($credentials['client_secret'] ?? ''));
        $redirect = GoogleOauthSupplierConnectionNormalizer::resolvedRedirectUri($connection);

        // Never Config::set empty overlays over env.
        if ($clientId === '' || $clientSecret === '' || $redirect === '') {
            return 'env_fallback';
        }

        Config::set('services.google.client_id', $clientId);
        Config::set('services.google.client_secret', $clientSecret);
        Config::set('services.google.redirect', $redirect);

        return 'db_managed';
    }

    public function currentSource(): string
    {
        $connection = $this->activeGoogleOauthConnection();
        if ($connection === null) {
            return 'env_fallback';
        }

        return $this->isUsable($connection) ? 'db_managed' : 'env_fallback';
    }

    public function isUsable(SupplierConnection $connection): bool
    {
        return $this->usabilityFailureReason($connection) === null;
    }

    public function usabilityFailureReason(SupplierConnection $connection): ?string
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        if ($clientId === '') {
            return 'missing_client_id';
        }

        $clientSecret = trim((string) ($credentials['client_secret'] ?? ''));
        if ($clientSecret === '') {
            return 'missing_client_secret';
        }

        $redirect = GoogleOauthSupplierConnectionNormalizer::resolvedRedirectUri($connection);
        if ($redirect === '') {
            return 'missing_redirect_uri';
        }

        if (! filter_var($redirect, FILTER_VALIDATE_URL)) {
            return 'invalid_redirect_uri';
        }

        $parts = parse_url($redirect);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '');

        if ($host === '' || $path === '') {
            return 'invalid_redirect_uri';
        }

        $isProduction = app()->environment('production');
        if ($isProduction && $scheme !== 'https') {
            return 'redirect_must_be_https';
        }

        if (! in_array($scheme, ['https', 'http'], true)) {
            return 'invalid_redirect_uri';
        }

        return null;
    }

    public function activeGoogleOauthConnection(): ?SupplierConnection
    {
        try {
            return SupplierConnection::query()
                ->where('provider', SupplierProvider::GoogleOauth->value)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('google_oauth.resolver_unavailable', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
