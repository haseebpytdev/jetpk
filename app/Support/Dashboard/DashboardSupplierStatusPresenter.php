<?php

namespace App\Support\Dashboard;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AlHaider\AlHaiderClient;

/**
 * Sanitized supplier operational labels for dashboard read-only panels.
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
        $piaActive = SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->where('is_active', true)
            ->exists();

        $oneApiConfigured = SupplierConnection::query()
            ->where('provider', SupplierProvider::OneApi)
            ->where('is_active', true)
            ->exists();

        $alHaiderConfigured = $this->alHaiderClient->isConfigured();

        return [
            [
                'key' => 'sabre',
                'label' => 'Sabre GDS',
                'status' => 'operational',
                'detail' => 'Live search operational',
            ],
            [
                'key' => 'pia_ndc',
                'label' => 'PIA NDC',
                'status' => $piaActive ? 'configured' : 'inactive',
                'detail' => $piaActive ? 'Connection configured' : 'No active connection',
            ],
            [
                'key' => 'al_haider',
                'label' => 'Al Haider',
                'status' => $alHaiderConfigured ? 'pending_activation' : 'not_configured',
                'detail' => $alHaiderConfigured
                    ? 'Integration prepared; initial token issuance deferred'
                    : 'Not configured',
            ],
            [
                'key' => 'iati',
                'label' => 'IATI',
                'status' => 'inactive',
                'detail' => 'Intentionally inactive',
            ],
            [
                'key' => 'one_api',
                'label' => 'One API',
                'status' => $oneApiConfigured ? 'deferred' : 'inactive',
                'detail' => $oneApiConfigured
                    ? 'Credentials available; dedicated production test deferred'
                    : 'Not active',
            ],
        ];
    }
}
