<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;

/**
 * Authoritative supplier integration catalog for Dashboard API Connections.
 * installed / createable truth is explicit — never inferred from enum membership alone.
 */
final class SupplierIntegrationCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => SupplierProvider::Sabre->value,
                'label' => 'Sabre',
                'kind' => 'supplier',
                'channel' => 'GDS / NDC',
                'description' => 'Sabre GDS and NDC channels with CERT/LIVE environments.',
                'icon' => 'SB',
                'capabilities' => ['GDS', 'NDC', 'PNR'],
                'installed' => true,
                'implementation_state' => 'operational',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'configuration_validation',
                'readiness' => 'Implemented',
                'baseUrlOverridable' => false,
            ],
            [
                'key' => SupplierProvider::PiaNdc->value,
                'label' => 'PIA NDC',
                'kind' => 'supplier',
                'channel' => 'Hitit / Crane NDC',
                'description' => 'Pakistan International Airlines NDC direct connect (Hitit Crane 20.1).',
                'icon' => 'PK',
                'capabilities' => ['NDC', 'Direct'],
                'installed' => true,
                'implementation_state' => 'operational',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'configuration_validation',
                'readiness' => 'Implemented',
                'baseUrlOverridable' => true,
            ],
            [
                'key' => SupplierProvider::Airblue->value,
                'label' => 'AirBlue / Zapways',
                'kind' => 'supplier',
                'channel' => 'Zapways OTA',
                'description' => 'AirBlue Zapways OTA inventory channel (v2 mTLS).',
                'icon' => 'AB',
                'capabilities' => ['API', 'LCC', 'Zapways'],
                'installed' => true,
                'implementation_state' => 'certification_pending',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'configuration_validation',
                'readiness' => 'Certification pending',
                'baseUrlOverridable' => false,
            ],
            [
                'key' => SupplierProvider::Iati->value,
                'label' => 'IATI',
                'kind' => 'supplier',
                'channel' => 'API',
                'description' => 'IATI consolidated inventory and booking API.',
                'icon' => 'IA',
                'capabilities' => ['API', 'Search'],
                'installed' => true,
                'implementation_state' => 'operational',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'configuration_validation',
                'readiness' => 'Implemented',
                'baseUrlOverridable' => false,
            ],
            [
                'key' => SupplierProvider::Duffel->value,
                'label' => 'Duffel',
                'kind' => 'supplier',
                'channel' => 'API',
                'description' => 'Duffel NDC aggregator for global content.',
                'icon' => 'DF',
                'capabilities' => ['NDC', 'Global'],
                'installed' => true,
                'implementation_state' => 'operational',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'configuration_validation',
                'readiness' => 'Implemented',
                'baseUrlOverridable' => false,
            ],
            [
                'key' => SupplierProvider::OneApi->value,
                'label' => 'One API',
                'kind' => 'supplier',
                'channel' => 'API',
                'description' => 'One API consolidated channel (Air Arabia / FlyJinnah family where configured).',
                'icon' => 'OA',
                'capabilities' => ['API', 'LCC'],
                'installed' => true,
                'implementation_state' => 'operational',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'configuration_validation',
                'readiness' => 'Implemented',
                'baseUrlOverridable' => false,
            ],
            [
                'key' => SupplierProvider::AlHaider->value,
                'label' => 'Al-Haider',
                'kind' => 'group_supplier',
                'channel' => 'Group',
                'description' => 'Al-Haider Umrah group ticketing and package inventory.',
                'icon' => 'AH',
                'capabilities' => ['Group', 'Umrah'],
                'installed' => true,
                'implementation_state' => 'operational',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'configuration_validation',
                'readiness' => 'Group integration',
                'baseUrlOverridable' => true,
            ],
            [
                'key' => SupplierProvider::AmeerEMillat->value,
                'label' => 'Ameer-e-Millat',
                'kind' => 'group_supplier',
                'channel' => 'Group',
                'description' => 'Ameer-e-Millat group flight inventory and post-payment booking.',
                'icon' => 'AM',
                'capabilities' => ['Group', 'Live Inventory', 'Booking'],
                'installed' => true,
                'implementation_state' => 'operational',
                'configuration_source' => 'supplier_connection',
                'supports_create' => true,
                'supports_update' => true,
                'supports_delete' => true,
                'supports_enable_disable' => true,
                'check_type' => 'connectivity_probe',
                'readiness' => 'Group integration',
                'baseUrlOverridable' => false,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function createableKeys(): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['key'],
            array_filter(self::definitions(), static fn (array $row): bool => (bool) ($row['supports_create'] ?? false)),
        ));
    }

    public static function definitionFor(string $key): ?array
    {
        foreach (self::definitions() as $row) {
            if (($row['key'] ?? '') === $key) {
                return $row;
            }
        }

        return null;
    }

    public static function checkTypeFor(string $key): string
    {
        return (string) (self::definitionFor($key)['check_type'] ?? 'configuration_validation');
    }

    public static function baseUrlOverridable(string $key): bool
    {
        return (bool) (self::definitionFor($key)['baseUrlOverridable'] ?? false);
    }
}
