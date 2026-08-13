<?php

namespace App\Support\Dashboard;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SupplierRegistry;

/**
 * Single integration registry for dashboard status: adapter code + connection + credentials + activation.
 */
final class DashboardSupplierStatusPresenter
{
    /**
     * @return list<array<string, string>>
     */
    public function present(): array
    {
        $rows = [];
        $seen = [];

        $connections = SupplierConnection::query()
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        foreach ($connections as $connection) {
            $provider = $connection->provider instanceof SupplierProvider
                ? $connection->provider->value
                : (string) $connection->provider;
            $seen[$provider] = true;
            $state = SupplierRegistry::stateForConnection($connection);
            $rows[] = [
                'key' => 'connection-'.$connection->id,
                'label' => trim((string) ($connection->display_name ?: $connection->name ?: $provider)),
                'status' => $state,
                'detail' => SupplierRegistry::businessLabel($state).' · '.$provider,
            ];
        }

        foreach (SupplierProvider::cases() as $provider) {
            if (isset($seen[$provider->value])) {
                continue;
            }
            $state = SupplierRegistry::stateForUnprovisioned($provider);
            $rows[] = [
                'key' => $provider->value,
                'label' => $provider->name,
                'status' => $state,
                'detail' => $state === SupplierRegistry::ADAPTER_NOT_INSTALLED
                    ? 'Catalogue provider only — not a production failure'
                    : SupplierRegistry::businessLabel($state),
            ];
        }

        return $rows;
    }
}
