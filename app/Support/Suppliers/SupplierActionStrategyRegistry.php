<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Support\Suppliers\Adapters\SabreGdsCreatePnrStrategyAdapter;
use App\Support\Suppliers\Adapters\SupplierStubCreateStrategyAdapter;
use App\Support\Suppliers\Contracts\SupplierCreateStrategyPort;

/**
 * Resolves provider + action to the correct create-strategy adapter (no Sabre-only assumptions in callers).
 */
final class SupplierActionStrategyRegistry
{
    public function __construct(
        protected SabreGdsCreatePnrStrategyAdapter $sabreGdsCreatePnr,
        protected SupplierStubCreateStrategyAdapter $stubAdapter,
    ) {}

    public function supports(string $provider, string $action): bool
    {
        return match (SupplierActionCode::key($provider, $action)) {
            SupplierActionCode::key(SupplierProvider::Sabre->value, SupplierActionCode::CREATE_PNR) => true,
            default => app(SupplierLifecycleCapabilities::class)->declaresCreateStrategy($provider, $action),
        };
    }

    public function adapterFor(string $provider, string $action): SupplierCreateStrategyPort
    {
        return match (SupplierActionCode::key($provider, $action)) {
            SupplierActionCode::key(SupplierProvider::Sabre->value, SupplierActionCode::CREATE_PNR) => $this->sabreGdsCreatePnr,
            default => $this->stubAdapter->for($provider, $action),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function strategyDefinitions(string $provider, string $action): array
    {
        return $this->adapterFor($provider, $action)->strategyDefinitions();
    }
}
