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
     * @return array{mode: string, existing_token: string, token_expires_at: ?string, username: string, password: string}|null
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
        if (! in_array($mode, [AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL, AlHaiderSupplierConnectionNormalizer::AUTH_MODE_AUTO], true)) {
            $mode = AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL;
        }

        return [
            'mode' => $mode,
            'existing_token' => trim((string) ($credentials['existing_token'] ?? '')),
            'token_expires_at' => trim((string) ($credentials['token_expires_at'] ?? '')) ?: null,
            'username' => trim((string) ($credentials['username'] ?? '')),
            'password' => trim((string) ($credentials['password'] ?? '')),
            'base_url' => trim((string) ($connection->base_url ?? '')),
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
}
