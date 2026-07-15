<?php

namespace App\Support\Platform;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Platform\PlatformModuleSettingsService;

/**
 * Platform module gate — Sprint 8I: nav visibility; Sprint 8J: routeEnabled(); Sprint 8L: enforcer delegation; 8P: Dev CP enforcement labels.
 */
final class PlatformModuleGate
{
    /** @var list<string> */
    private const ROUTE_MIDDLEWARE_KEYS = [
        'public_flight_search',
        'customer_checkout',
        'customer_registration',
        'customer_booking_lookup',
        'customer_portal',
        'agent_deposits',
        'agent_staff',
        'agent_reports',
        'agent_applications',
        'agent_support',
        'saved_travelers',
        'api_settings',
        'markup_settings',
        'finance_reports',
        'branding_settings',
        'notifications',
        'support_system',
    ];

    /** @var list<string> */
    private const SERVICE_ENFORCED_KEYS = [
        'payment_proofs',
        'agent_deposits',
        'agent_wallet',
        'supplier_search',
        'sabre_gds',
        'sabre_ndc',
        'duffel_supplier',
        'iati_supplier',
        'pia_ndc_supplier',
        'airblue_supplier',
        'supplier_booking',
        'ticketing',
    ];

    /** @var list<string> */
    private const PROVIDER_MODULE_KEYS = [
        'sabre_gds',
        'sabre_ndc',
        'duffel_supplier',
        'iati_supplier',
        'pia_ndc_supplier',
        'airblue_supplier',
    ];

    public static function allows(string $key): bool
    {
        return self::findModule($key) !== null;
    }

    public static function routeEnabled(string $key): bool
    {
        return app(PlatformModuleEnforcer::class)->routeEnabled($key);
    }

    public static function visible(string $key): bool
    {
        return app(PlatformModuleEnforcer::class)->effectiveModuleEnabled($key);
    }

    /**
     * @return array{
     *     registry_default: bool,
     *     db_override: bool|null,
     *     db_row_exists: bool,
     *     planned_enabled: bool,
     *     visible: bool,
     *     nav_hidden: bool,
     *     gate_allows: bool,
     *     enforced: bool,
     *     display: string,
     *     env_snapshot: list<array{label: string, value: string}>
     * }
     */
    public static function effectiveStatus(string $key, ?int $agencyId = null): array
    {
        $module = self::findModule($key);

        if ($module === null) {
            return [
                'registry_default' => false,
                'db_override' => null,
                'db_row_exists' => false,
                'planned_enabled' => false,
                'visible' => false,
                'nav_hidden' => true,
                'gate_allows' => false,
                'enforced' => false,
                'display' => 'Unknown module',
                'env_snapshot' => [],
            ];
        }

        $dbState = app(PlatformModuleSettingsService::class)->effectiveStateFor($key);
        $enforcer = app(PlatformModuleEnforcer::class);
        $plannedEnabled = $agencyId !== null
            ? $enforcer->effectiveModuleEnabledForAgency($key, $agencyId)
            : $dbState['effective_enabled'];
        $visible = $agencyId !== null
            ? $plannedEnabled
            : self::visible($key);
        $routeEnabled = $agencyId !== null
            ? $plannedEnabled
            : self::routeEnabled($key);

        return [
            'registry_default' => $module->defaultEnabled,
            'db_override' => $dbState['db_override'],
            'db_row_exists' => $dbState['db_row_exists'],
            'planned_enabled' => $plannedEnabled,
            'visible' => $visible,
            'nav_hidden' => ! $visible,
            'gate_allows' => self::allows($key),
            'enforced' => ! $routeEnabled,
            'display' => self::previewDisplayLabel($plannedEnabled, $dbState['db_row_exists'], ! $visible, ! $routeEnabled),
            'env_snapshot' => self::envSnapshotFor($module, $agencyId),
            'route_middleware' => self::hasRouteMiddleware($key),
            'backend_service' => self::hasBackendServiceEnforcement($key),
            'enforcement_summary' => self::enforcementSummary($key),
            'provider_scope' => self::providerScopeNote($key),
        ];
    }

    public static function hasRouteMiddleware(string $key): bool
    {
        return in_array($key, self::ROUTE_MIDDLEWARE_KEYS, true);
    }

    public static function hasBackendServiceEnforcement(string $key): bool
    {
        return in_array($key, self::SERVICE_ENFORCED_KEYS, true);
    }

    public static function enforcementSummary(string $key): string
    {
        $route = self::hasRouteMiddleware($key);
        $service = self::hasBackendServiceEnforcement($key);

        return match (true) {
            $route && $service => 'Routes + backend services',
            $route => 'Route middleware',
            $service => 'Backend services',
            default => 'Navigation visibility',
        };
    }

    public static function providerScopeNote(string $key): ?string
    {
        if (! in_array($key, self::PROVIDER_MODULE_KEYS, true)) {
            return null;
        }

        return match ($key) {
            'sabre_gds' => 'Filters Sabre GDS shop, validation, booking, and ticketing (NDC uses sabre_ndc).',
            'sabre_ndc' => 'Filters Sabre NDC offers only; GDS remains when sabre_gds is on.',
            'duffel_supplier' => 'Filters Duffel search, validation, booking, and ticketing.',
            'iati_supplier' => 'Filters IATI search, fare confirmation, booking, and ticketing.',
            'pia_ndc_supplier' => 'Filters PIA NDC search, option PNR, ticketing, cancel, and void.',
            'airblue_supplier' => 'Filters AirBlue Crane NDC and Zapways OTA search, booking, ticketing, and cancel.',
            default => null,
        };
    }

    private static function previewDisplayLabel(bool $plannedEnabled, bool $hasDbOverride, bool $navHidden, bool $routesBlocked): string
    {
        $navSuffix = $navHidden ? ', nav hidden' : '';
        $routeSuffix = $routesBlocked ? ' — guarded routes blocked' : ' — routes not blocked';

        if ($hasDbOverride) {
            return $plannedEnabled
                ? "Planned on (preview{$navSuffix}{$routeSuffix})"
                : "Planned off (preview{$navSuffix}{$routeSuffix})";
        }

        return $plannedEnabled
            ? "Enabled (registry default{$navSuffix}{$routeSuffix})"
            : "Disabled (registry default{$navSuffix}{$routeSuffix})";
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function envSnapshotFor(PlatformModule $module, ?int $agencyId = null): array
    {
        $rows = [];

        foreach ($module->configHints as $hint) {
            $resolved = self::resolveConfigHint($hint, $agencyId);
            if ($resolved !== null) {
                $rows[] = $resolved;
            }
        }

        $rows = array_merge($rows, self::supplierSnapshotFor($module->key, $agencyId));

        $providerScope = self::providerScopeNote($module->key);
        if ($providerScope !== null) {
            $rows[] = [
                'label' => 'Provider enforcement',
                'value' => $providerScope,
            ];
        }

        if (self::hasBackendServiceEnforcement($module->key) || self::hasRouteMiddleware($module->key)) {
            $rows[] = [
                'label' => 'Deployment enforcement',
                'value' => self::enforcementSummary($module->key),
            ];
        }

        return $rows;
    }

    private static function findModule(string $key): ?PlatformModule
    {
        return PlatformModuleRegistry::find($key);
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private static function resolveConfigHint(string $hint, ?int $agencyId): ?array
    {
        return match ($hint) {
            'SABRE_BOOKING_ENABLED' => self::boolRow($hint, (bool) config('suppliers.sabre.booking_enabled')),
            'SABRE_BOOKING_LIVE_CALL_ENABLED' => self::boolRow($hint, (bool) config('suppliers.sabre.booking_live_call_enabled')),
            'SABRE_TICKETING_ENABLED' => self::boolRow($hint, (bool) config('suppliers.sabre.ticketing_enabled')),
            'OTA_SUPPLIER_DEFAULT_PROVIDER' => [
                'label' => $hint,
                'value' => (string) config('ota.supplier_default_provider', '—'),
            ],
            'OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS' => [
                'label' => $hint,
                'value' => implode(', ', (array) config('ota.public_flight_results_suppliers', [])) ?: '—',
            ],
            'DUFFEL_DEFAULT_BASE_URL' => [
                'label' => $hint,
                'value' => self::configuredLabel(filled(config('suppliers.duffel.default_base_url'))),
            ],
            default => null,
        };
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private static function supplierSnapshotFor(string $moduleKey, ?int $agencyId): array
    {
        if (! in_array($moduleKey, ['sabre_gds', 'sabre_ndc', 'duffel_supplier', 'iati_supplier', 'pia_ndc_supplier', 'airblue_supplier', 'supplier_search', 'supplier_booking', 'ticketing'], true)) {
            return [];
        }

        if ($agencyId === null) {
            return [
                ['label' => 'supplier_connections', 'value' => 'Agency context required'],
            ];
        }

        $rows = [];
        $providers = match ($moduleKey) {
            'duffel_supplier' => [SupplierProvider::Duffel],
            'iati_supplier' => [SupplierProvider::Iati],
            'pia_ndc_supplier' => [SupplierProvider::PiaNdc],
            'airblue_supplier' => [SupplierProvider::Airblue],
            'sabre_gds', 'sabre_ndc' => [SupplierProvider::Sabre],
            default => [SupplierProvider::Sabre, SupplierProvider::Duffel, SupplierProvider::Iati],
        };

        foreach ($providers as $provider) {
            $connections = SupplierConnection::query()
                ->where('agency_id', $agencyId)
                ->where('provider', $provider)
                ->orderBy('name')
                ->get(['provider', 'name', 'is_active', 'status', 'environment']);

            $activeCount = $connections->filter(fn (SupplierConnection $c): bool => $c->is_active
                || ($c->status?->value ?? (string) $c->status) === 'active')->count();

            $rows[] = [
                'label' => $provider->value.' connections (active/total)',
                'value' => "{$activeCount}/{$connections->count()}",
            ];

            foreach ($connections->take(5) as $connection) {
                $status = $connection->status?->value ?? (string) $connection->status;
                $env = $connection->environment?->value ?? (string) $connection->environment;
                $active = $connection->is_active ? 'active' : 'inactive';
                $rows[] = [
                    'label' => $provider->value.': '.$connection->name,
                    'value' => "{$active}, status={$status}, env={$env}",
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{label: string, value: string}
     */
    private static function boolRow(string $label, bool $value): array
    {
        return [
            'label' => $label,
            'value' => $value ? 'true (enabled)' : 'false (disabled)',
        ];
    }

    private static function configuredLabel(bool $configured): string
    {
        return $configured ? 'configured' : 'not configured';
    }
}
