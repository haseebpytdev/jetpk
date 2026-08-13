<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierAdapterResolver;

/**
 * Authoritative supplier/API registry states for dashboard and API Connections.
 */
final class SupplierRegistry
{
    public const ADAPTER_INSTALLED = 'ADAPTER_INSTALLED';

    public const CONNECTION_NOT_CONFIGURED = 'CONNECTION_NOT_CONFIGURED';

    public const CONFIGURED_DISABLED = 'CONFIGURED_DISABLED';

    public const CONFIGURED_ENABLED = 'CONFIGURED_ENABLED';

    public const PENDING_ACTIVATION = 'PENDING_ACTIVATION';

    public const ADAPTER_NOT_INSTALLED = 'ADAPTER_NOT_INSTALLED';

    public static function adapterInstalled(SupplierProvider $provider): bool
    {
        try {
            app(SupplierAdapterResolver::class)->resolve($provider);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function stateForConnection(SupplierConnection $connection): string
    {
        $provider = $connection->provider instanceof SupplierProvider
            ? $connection->provider
            : SupplierProvider::tryFrom((string) $connection->provider);

        if (! $provider instanceof SupplierProvider || ! self::adapterInstalled($provider)) {
            return self::ADAPTER_NOT_INSTALLED;
        }

        $hasCredentials = is_array($connection->credentials) && $connection->credentials !== [];
        $status = $connection->status instanceof SupplierConnectionStatus
            ? $connection->status
            : SupplierConnectionStatus::tryFrom((string) $connection->status);

        if (! $hasCredentials) {
            return self::CONNECTION_NOT_CONFIGURED;
        }

        if ($status === SupplierConnectionStatus::Testing) {
            return self::PENDING_ACTIVATION;
        }

        if ($connection->isActive()) {
            return self::CONFIGURED_ENABLED;
        }

        return self::CONFIGURED_DISABLED;
    }

    public static function stateForUnprovisioned(SupplierProvider $provider): string
    {
        return self::adapterInstalled($provider)
            ? self::ADAPTER_INSTALLED
            : self::ADAPTER_NOT_INSTALLED;
    }

    public static function businessLabel(string $state): string
    {
        return match ($state) {
            self::ADAPTER_INSTALLED => 'Adapter installed — no connection yet',
            self::CONNECTION_NOT_CONFIGURED => 'Connection exists — credentials not configured',
            self::CONFIGURED_DISABLED => 'Configured and disabled',
            self::CONFIGURED_ENABLED => 'Configured and enabled',
            self::PENDING_ACTIVATION => 'Pending activation',
            default => 'Adapter not installed',
        };
    }
}
