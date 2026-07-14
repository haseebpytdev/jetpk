<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Support\Bookings\SupplierLifecycleContextResolver;

/**
 * Declares lifecycle capabilities per supplier (stubs for future NDC/direct APIs).
 */
final class SupplierLifecycleCapabilities
{
    /**
     * @return array<string, mixed>
     */
    public function forProvider(string $provider): array
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            SupplierProvider::Sabre->value => [
                'search' => true,
                'fare_options' => true,
                'revalidation' => true,
                'create_pnr' => true,
                'create_order' => false,
                'retrieve' => true,
                'cancel_unticketed' => true,
                'ticketing' => true,
            ],
            SupplierProvider::PiaNdc->value => [
                'search' => true,
                'fare_options' => true,
                'revalidation' => true,
                'create_pnr' => false,
                'create_order' => true,
                'retrieve' => true,
                'cancel_unticketed' => true,
                'ticketing' => true,
            ],
            SupplierProvider::Duffel->value => [
                'search' => true,
                'fare_options' => true,
                'revalidation' => true,
                'create_pnr' => false,
                'create_order' => true,
                'retrieve' => true,
                'cancel_unticketed' => true,
                'ticketing' => true,
            ],
            'airblue', 'airsial' => [
                'search' => true,
                'fare_options' => true,
                'revalidation' => true,
                'create_pnr' => true,
                'create_order' => false,
                'retrieve' => true,
                'cancel_unticketed' => true,
                'ticketing' => true,
            ],
            default => [
                'search' => false,
                'fare_options' => false,
                'revalidation' => false,
                'create_pnr' => false,
                'create_order' => false,
                'retrieve' => false,
                'cancel_unticketed' => false,
                'ticketing' => false,
            ],
        };
    }

    public function declaresCreateStrategy(string $provider, string $action): bool
    {
        $caps = $this->forProvider($provider);

        return match ($action) {
            SupplierActionCode::CREATE_PNR => (bool) ($caps['create_pnr'] ?? false),
            SupplierActionCode::CREATE_ORDER => (bool) ($caps['create_order'] ?? false),
            default => false,
        };
    }

    public function channelForProvider(string $provider): string
    {
        return match (strtolower(trim($provider))) {
            SupplierProvider::Sabre->value => SupplierLifecycleContextResolver::CHANNEL_GDS,
            SupplierProvider::PiaNdc->value => SupplierLifecycleContextResolver::CHANNEL_NDC,
            SupplierProvider::Duffel->value => SupplierLifecycleContextResolver::CHANNEL_DIRECT,
            'airblue', 'airsial' => SupplierLifecycleContextResolver::CHANNEL_DIRECT,
            default => SupplierLifecycleContextResolver::CHANNEL_OTHER,
        };
    }
}
