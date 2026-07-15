<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;

/**
 * Read-only supplier search capability checks for public flight search filters.
 */
final class SupplierFlightSearchCapability
{
    public function supportsSupplierDirectOnlyRequest(SupplierProvider|string $provider): bool
    {
        $value = $provider instanceof SupplierProvider ? $provider->value : strtolower(trim((string) $provider));

        return in_array($value, [
            SupplierProvider::Sabre->value,
            SupplierProvider::Duffel->value,
            SupplierProvider::Airblue->value,
        ], true);
    }
}
