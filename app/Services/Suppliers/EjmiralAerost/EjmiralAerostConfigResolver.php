<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;

/**
 * Resolves EJMIRAL AEROST OTA API configuration from encrypted SupplierConnection credentials.
 * Uses Sub Travel Agent credentials only (PSA portal is administrative, not API booking).
 * API base URL is system-managed via EJMIRAL_AEROST_API_BASE_URL — never operator free-text.
 */
class EjmiralAerostConfigResolver
{
    public const API_BASE_URL_STATUS_UNRESOLVED = 'UNRESOLVED';

    public const API_BASE_URL_STATUS_CONFIGURED = 'CONFIGURED';

    /**
     * @return array{
     *     api_base_url: string,
     *     api_base_url_status: string,
     *     username: string,
     *     password: string,
     *     connect_timeout_seconds: int,
     *     request_timeout_seconds: int,
     *     token_ttl_seconds: int,
     *     token_expiry_margin_seconds: int,
     *     verify_tls: bool,
     *     live_search_enabled: bool,
     *     live_booking_enabled: bool,
     *     live_ticketing_enabled: bool
     * }
     */
    public function resolve(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || trim($password) === '') {
            throw new EjmiralAerostValidationException(
                'configuration_error',
                422,
                'EJMIRAL AEROST Sub Travel Agent username and password are required.',
            );
        }

        $apiBase = $this->resolvedApiBaseUrl();
        if ($apiBase === '') {
            throw new EjmiralAerostValidationException(
                'configuration_error',
                422,
                'EJMIRAL AEROST API base URL is unresolved. Set EJMIRAL_AEROST_API_BASE_URL in system configuration.',
            );
        }

        return [
            'api_base_url' => $apiBase,
            'api_base_url_status' => self::API_BASE_URL_STATUS_CONFIGURED,
            'username' => $username,
            'password' => $password,
            'connect_timeout_seconds' => (int) config('suppliers.ejmiral_aerost.connect_timeout_seconds', 10),
            'request_timeout_seconds' => (int) config('suppliers.ejmiral_aerost.request_timeout_seconds', 60),
            'token_ttl_seconds' => (int) config('suppliers.ejmiral_aerost.token_ttl_seconds', 7 * 24 * 3600),
            'token_expiry_margin_seconds' => (int) config('suppliers.ejmiral_aerost.token_expiry_margin_seconds', 300),
            'verify_tls' => (bool) config('suppliers.ejmiral_aerost.verify_tls', true),
            'live_search_enabled' => (bool) config('suppliers.ejmiral_aerost.live_search_enabled', false),
            'live_booking_enabled' => (bool) config('suppliers.ejmiral_aerost.live_booking_enabled', false),
            'live_ticketing_enabled' => (bool) config('suppliers.ejmiral_aerost.live_ticketing_enabled', false),
        ];
    }

    public function apiBaseUrlStatus(): string
    {
        return $this->resolvedApiBaseUrl() !== ''
            ? self::API_BASE_URL_STATUS_CONFIGURED
            : self::API_BASE_URL_STATUS_UNRESOLVED;
    }

    public function resolvedApiBaseUrl(): string
    {
        return rtrim(trim((string) config('suppliers.ejmiral_aerost.default_api_base_url', '')), '/');
    }

    public function otaPath(string $suffix): string
    {
        $base = (string) config('suppliers.ejmiral_aerost.ota_base_path', '/api/ota/v1');

        return rtrim($base, '/').'/'.ltrim($suffix, '/');
    }
}
