<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Models\SupplierConnection;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;

/**
 * Resolves EJMIRAL AEROST OTA API configuration from encrypted SupplierConnection credentials.
 * Uses Sub Travel Agent credentials only (PSA portal is administrative, not API booking).
 */
class EjmiralAerostConfigResolver
{
    /**
     * @return array{
     *     api_base_url: string,
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
        $apiBase = rtrim(trim((string) ($credentials['api_base_url'] ?? $connection->base_url ?? '')), '/');

        if ($username === '' || $password === '') {
            throw new EjmiralAerostValidationException(
                'configuration_error',
                422,
                'EJMIRAL AEROST Sub Travel Agent username and password are required.',
            );
        }

        if ($apiBase === '') {
            $apiBase = rtrim((string) config('suppliers.ejmiral_aerost.default_api_base_url', ''), '/');
        }

        if ($apiBase === '') {
            throw new EjmiralAerostValidationException(
                'configuration_error',
                422,
                'EJMIRAL AEROST api_base_url is required.',
            );
        }

        return [
            'api_base_url' => $apiBase,
            'username' => $username,
            'password' => $password,
            'connect_timeout_seconds' => (int) config('suppliers.ejmiral_aerost.connect_timeout_seconds', 10),
            'request_timeout_seconds' => (int) config('suppliers.ejmiral_aerost.request_timeout_seconds', 60),
            'token_ttl_seconds' => (int) config('suppliers.ejmiral_aerost.token_ttl_seconds', 7 * 24 * 3600),
            'token_expiry_margin_seconds' => (int) config('suppliers.ejmiral_aerost.token_expiry_margin_seconds', 300),
            'verify_tls' => (bool) ($credentials['verify_tls'] ?? true),
            'live_search_enabled' => (bool) config('suppliers.ejmiral_aerost.live_search_enabled', false),
            'live_booking_enabled' => (bool) config('suppliers.ejmiral_aerost.live_booking_enabled', false),
            'live_ticketing_enabled' => (bool) config('suppliers.ejmiral_aerost.live_ticketing_enabled', false),
        ];
    }

    public function otaPath(string $suffix): string
    {
        $base = (string) config('suppliers.ejmiral_aerost.ota_base_path', '/api/ota/v1');

        return rtrim($base, '/').'/'.ltrim($suffix, '/');
    }
}
