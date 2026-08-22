<?php

namespace App\Support\Integrations;

use App\Enums\IntegrationCategory;
use App\Enums\SupplierProvider;
use App\Support\Suppliers\SupplierRegistry;

/**
 * Central registry of JetPakistan external integrations for the Admin Integrations hub.
 * Runtime adapters remain authoritative; this is an admin management facade.
 */
final class IntegrationRegistry
{
    /**
     * @return list<IntegrationDefinition>
     */
    public static function all(): array
    {
        return [
            ...self::flightProviders(),
            self::abhiPay(),
            ...self::draftPlaceholders(),
        ];
    }

    public static function find(string $code): ?IntegrationDefinition
    {
        foreach (self::all() as $definition) {
            if ($definition->code === $code) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return list<IntegrationCategory>
     */
    public static function activeCategories(): array
    {
        $seen = [];
        foreach (self::all() as $definition) {
            $seen[$definition->category->value] = $definition->category;
        }

        return array_values($seen);
    }

    /**
     * @return list<IntegrationDefinition>
     */
    private static function flightProviders(): array
    {
        /** @var array<string, array{0: string, 1: string, 2: list<string>, 3: ?string, 4: IntegrationCategory}> $map */
        $map = [
            SupplierProvider::Sabre->value => ['Sabre', 'SB', ['search', 'book', 'ticket'], 'https://developer.sabre.com/', IntegrationCategory::Flights],
            SupplierProvider::Iati->value => ['IATI', 'IA', ['search', 'book'], null, IntegrationCategory::Flights],
            SupplierProvider::PiaNdc->value => ['PIA NDC / Hitit', 'PK', ['search', 'book', 'ndc'], null, IntegrationCategory::Flights],
            SupplierProvider::OneApi->value => ['One API (FlyJinnah / Air Arabia)', '1A', ['search', 'book'], null, IntegrationCategory::Flights],
            SupplierProvider::Airblue->value => ['Airblue', 'AB', ['search', 'book'], null, IntegrationCategory::Flights],
            SupplierProvider::Duffel->value => ['Duffel', 'DF', ['search', 'book'], 'https://duffel.com/docs', IntegrationCategory::Flights],
            SupplierProvider::AlHaider->value => ['Al-Haider', 'AH', ['groups', 'umrah'], null, IntegrationCategory::Groups],
            SupplierProvider::AirlineDirect->value => ['Airline Direct', 'AD', ['direct'], null, IntegrationCategory::Flights],
            SupplierProvider::Amadeus->value => ['Amadeus', 'AM', ['gds'], null, IntegrationCategory::Flights],
            SupplierProvider::Travelport->value => ['Travelport', 'TP', ['gds'], null, IntegrationCategory::Flights],
        ];

        $definitions = [];
        foreach ($map as $code => [$name, $icon, $capabilities, $docs, $category]) {
            $provider = SupplierProvider::tryFrom($code);
            $installed = $provider instanceof SupplierProvider
                && SupplierRegistry::adapterInstalled($provider);

            $definitions[] = new IntegrationDefinition(
                code: $code,
                name: $name,
                category: $category,
                icon: $icon,
                capabilities: $capabilities,
                adapterInstalled: $installed,
                supportsConnectionTest: $installed,
                supportsTestTransaction: false,
                supportsEnableToggle: $installed,
                canActivateRuntime: $installed,
                docsUrl: $docs,
                manager: 'supplier',
            );
        }

        return $definitions;
    }

    private static function abhiPay(): IntegrationDefinition
    {
        return new IntegrationDefinition(
            code: 'abhipay',
            name: 'AbhiPay',
            category: IntegrationCategory::Payments,
            icon: 'AP',
            capabilities: ['checkout', 'callback_verify', 'test_connection', 'test_payment'],
            adapterInstalled: true,
            supportsConnectionTest: true,
            supportsTestTransaction: true,
            supportsEnableToggle: true,
            canActivateRuntime: true,
            docsUrl: '/docs/payments/abhipay-integration.md',
            manager: 'abhipay',
        );
    }

    /**
     * @return list<IntegrationDefinition>
     */
    private static function draftPlaceholders(): array
    {
        return [
            new IntegrationDefinition(
                code: 'hotelbeds',
                name: 'Hotelbeds',
                category: IntegrationCategory::Hotels,
                icon: 'HB',
                capabilities: ['search'],
                adapterInstalled: false,
                supportsConnectionTest: false,
                supportsTestTransaction: false,
                supportsEnableToggle: false,
                canActivateRuntime: false,
                docsUrl: null,
                manager: 'draft',
            ),
            new IntegrationDefinition(
                code: 'smtp_mail',
                name: 'Transactional Email (SMTP)',
                category: IntegrationCategory::Messaging,
                icon: 'EM',
                capabilities: ['notify'],
                adapterInstalled: false,
                supportsConnectionTest: false,
                supportsTestTransaction: false,
                supportsEnableToggle: false,
                canActivateRuntime: false,
                docsUrl: null,
                manager: 'draft',
            ),
        ];
    }
}
