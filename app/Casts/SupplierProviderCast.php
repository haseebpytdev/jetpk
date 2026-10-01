<?php

namespace App\Casts;

use App\Enums\SupplierProvider;
use App\Support\Suppliers\RetiredSupplierProviders;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts supplier_connections.provider to {@see SupplierProvider} for active providers.
 * Retired / unknown values hydrate as plain strings so legacy rows do not crash listing.
 *
 * @implements CastsAttributes<SupplierProvider|string|null, SupplierProvider|string|null>
 */
final class SupplierProviderCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): SupplierProvider|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === 'pia') {
            return SupplierProvider::PiaNdc;
        }

        $enum = SupplierProvider::tryFrom($raw);
        if ($enum !== null) {
            return $enum;
        }

        // Fail-closed hydration: preserve retired/unknown string; never remap to another supplier.
        return $raw;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof SupplierProvider) {
            return $value->value;
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === 'pia') {
            return SupplierProvider::PiaNdc->value;
        }

        if (SupplierProvider::tryFrom($raw) !== null) {
            return $raw;
        }

        if (RetiredSupplierProviders::isRetired($raw)) {
            // Allow persisting existing retired rows without remapping; block new writes at validation.
            return $raw;
        }

        throw new InvalidArgumentException('Unsupported supplier provider: '.$raw);
    }
}
