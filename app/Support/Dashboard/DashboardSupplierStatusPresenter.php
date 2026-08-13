<?php

namespace App\Support\Dashboard;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AlHaider\AlHaiderClient;

/**
 * Single integration registry for dashboard status: DB connections plus unprovisioned adapters.
 */
final class DashboardSupplierStatusPresenter
{
    public function __construct(
        protected AlHaiderClient $alHaiderClient,
    ) {}

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
            $active = (bool) $connection->is_active;
            $rows[] = [
                'key' => 'connection-'.$connection->id,
                'label' => trim((string) ($connection->display_name ?: $connection->name ?: $provider)),
                'status' => $active ? 'configured' : 'inactive',
                'detail' => sprintf(
                    'API connection · %s · %s',
                    $provider,
                    $active ? 'enabled' : 'disabled',
                ),
            ];
        }

        foreach (SupplierProvider::cases() as $provider) {
            if (isset($seen[$provider->value])) {
                continue;
            }
            if (in_array($provider, [SupplierProvider::Amadeus, SupplierProvider::Travelport, SupplierProvider::AirlineDirect, SupplierProvider::Duffel], true)) {
                $rows[] = [
                    'key' => $provider->value,
                    'label' => $provider->name,
                    'status' => 'not_integrated',
                    'detail' => 'Provider adapter not installed / engineering integration required',
                ];

                continue;
            }
            $rows[] = [
                'key' => $provider->value,
                'label' => $provider->name,
                'status' => 'not_provisioned',
                'detail' => 'No API connection record',
            ];
        }

        $rows[] = [
            'key' => 'al_haider',
            'label' => 'Al Haider',
            'status' => $this->alHaiderClient->isConfigured() ? 'pending_activation' : 'not_integrated',
            'detail' => $this->alHaiderClient->isConfigured()
                ? 'Adapter prepared; not an API Connections record; token issuance deferred'
                : 'Provider adapter not provisioned as SupplierConnection',
        ];

        return $rows;
    }
}
