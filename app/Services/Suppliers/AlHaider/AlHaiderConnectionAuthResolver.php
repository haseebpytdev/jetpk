<?php

namespace App\Services\Suppliers\AlHaider;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;

/**
 * Resolves Al-Haider authentication from active SupplierConnection rows before global config fallback.
 */
final class AlHaiderConnectionAuthResolver
{
    public function resolveActiveConnection(): ?SupplierConnection
    {
        return SupplierConnection::query()
            ->where('provider', SupplierProvider::AlHaider->value)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *     mode: string,
     *     existing_token: string,
     *     token_expires_at: ?string,
     *     username: string,
     *     password: string,
     *     auto_renew: bool,
     *     base_url: string,
     *     connection_id: int
     * }|null
     */
    public function resolveConnectionAuth(): ?array
    {
        $connection = $this->resolveActiveConnection();
        if ($connection === null) {
            return null;
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        if ($credentials === []) {
            return null;
        }

        $mode = strtolower(trim((string) ($credentials['auth_mode'] ?? AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL)));
        if (! in_array($mode, AlHaiderSupplierConnectionNormalizer::supportedAuthModes(), true)) {
            $mode = AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL;
        }

        $autoRenewRaw = $credentials['auto_renew'] ?? $credentials['auto_renew_enabled'] ?? false;
        $autoRenew = is_bool($autoRenewRaw)
            ? $autoRenewRaw
            : in_array(strtolower(trim((string) $autoRenewRaw)), ['1', 'true', 'yes', 'enabled', 'on'], true);

        return [
            'mode' => $mode,
            'existing_token' => trim((string) ($credentials['existing_token'] ?? '')),
            'token_expires_at' => trim((string) ($credentials['token_expires_at'] ?? '')) ?: null,
            'username' => trim((string) ($credentials['username'] ?? '')),
            'password' => trim((string) ($credentials['password'] ?? '')),
            'auto_renew' => $autoRenew,
            'base_url' => trim((string) ($connection->base_url ?? '')),
            'connection_id' => (int) $connection->id,
        ];
    }

    public function manualTokenExpired(?string $tokenExpiresAt): bool
    {
        if ($tokenExpiresAt === null || trim($tokenExpiresAt) === '') {
            return false;
        }

        $timestamp = strtotime($tokenExpiresAt);
        if ($timestamp === false) {
            return false;
        }

        return $timestamp <= time();
    }

    /**
     * Whether an active DB connection presents a usable auth strategy (no ENV secrets required).
     *
     * @param  array{
     *     mode: string,
     *     existing_token: string,
     *     token_expires_at: ?string,
     *     username: string,
     *     password: string,
     *     auto_renew: bool
     * }  $connectionAuth
     */
    public function connectionAuthIsConfigured(array $connectionAuth): bool
    {
        $mode = $connectionAuth['mode'];

        if ($mode === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_AUTO) {
            return $connectionAuth['username'] !== '' && $connectionAuth['password'] !== '';
        }

        if ($connectionAuth['existing_token'] === '') {
            return false;
        }

        if ($this->manualTokenExpired($connectionAuth['token_expires_at'])) {
            // Managed + auto-renew may still recover after genuine expiry.
            return $mode === AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANAGED
                && ($connectionAuth['auto_renew'] ?? false)
                && $connectionAuth['username'] !== ''
                && $connectionAuth['password'] !== '';
        }

        return true;
    }
}
