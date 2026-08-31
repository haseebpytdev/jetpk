<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;

/**
 * Canonical provider endpoint defaults by environment. Never invents URLs —
 * values come from existing normalizers / suppliers config only.
 */
final class ProviderEndpointDefaults
{
    /**
     * @return array{base_url: ?string, overridable: bool, source: string}
     */
    public static function for(string $provider, string $environment): array
    {
        $env = strtolower(trim($environment));
        $live = $env === 'live';

        return match ($provider) {
            SupplierProvider::Sabre->value => [
                'base_url' => SabreSupplierConnectionNormalizer::baseUrlForEnvironment($live ? 'live' : 'sandbox'),
                'overridable' => true,
                'source' => 'SabreSupplierConnectionNormalizer',
            ],
            SupplierProvider::Iati->value => [
                'base_url' => IatiSupplierConnectionNormalizer::flightBaseUrlForEnvironment($live ? 'live' : 'sandbox'),
                'overridable' => false,
                'source' => 'IatiSupplierConnectionNormalizer',
            ],
            SupplierProvider::AlHaider->value => [
                'base_url' => rtrim((string) config('suppliers.al_haider.default_base_url', 'https://alhaidertravel.pk'), '/'),
                'overridable' => true,
                'source' => 'config:suppliers.al_haider.default_base_url',
            ],
            SupplierProvider::Duffel->value => [
                'base_url' => (string) config('suppliers.duffel.default_base_url', 'https://api.duffel.com'),
                'overridable' => false,
                'source' => 'config:suppliers.duffel.default_base_url',
            ],
            SupplierProvider::Smtp->value => [
                'base_url' => null,
                'overridable' => false,
                'source' => 'n/a',
            ],
            SupplierProvider::GoogleOauth->value => [
                'base_url' => null,
                'overridable' => false,
                'source' => 'n/a',
            ],
            default => [
                'base_url' => null,
                'overridable' => in_array($provider, [
                    SupplierProvider::PiaNdc->value,
                    SupplierProvider::Airblue->value,
                ], true),
                'source' => 'provider-specific',
            ],
        };
    }
}
