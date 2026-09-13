<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Support\Collection;

/**
 * Prevents duplicate AirBlue PA offers when v2 and v3 Zapways connections are both active.
 */
class AirBlueConnectionSearchPolicy
{
    /**
     * @param  Collection<int, SupplierConnection>  $connections
     * @return Collection<int, SupplierConnection>
     */
    public function dedupeForSearch(Collection $connections): Collection
    {
        $airblue = $connections->filter(
            fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Airblue,
        );

        if ($airblue->count() <= 1) {
            return $connections;
        }

        $preferredId = $this->selectPreferredConnectionId($airblue);
        if ($preferredId === null) {
            return $connections;
        }

        return $connections->reject(
            fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Airblue
                && (int) $connection->id !== $preferredId,
        )->values();
    }

    /**
     * @param  Collection<int, SupplierConnection>  $airblueConnections
     */
    private function selectPreferredConnectionId(Collection $airblueConnections): ?int
    {
        $ranked = $airblueConnections
            ->sortByDesc(fn (SupplierConnection $connection): int => $this->connectionSearchRank($connection))
            ->values();

        $winner = $ranked->first();
        if ($winner === null) {
            return null;
        }

        return (int) $winner->id;
    }

    private function connectionSearchRank(SupplierConnection $connection): int
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $protocol = AirBlueZapwaysProtocolVersion::fromCredentials($credentials);
        $certified = $this->isSearchCertified($connection, $credentials);

        if (! $certified) {
            return (int) $connection->id;
        }

        $explicitPriority = (int) ($credentials['search_priority'] ?? 0);

        return 10_000 + ($explicitPriority * 1000) + $protocol->searchPriority() + (int) $connection->id;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function isSearchCertified(SupplierConnection $connection, array $credentials): bool
    {
        if (array_key_exists('search_certified', $credentials)) {
            return filter_var($credentials['search_certified'], FILTER_VALIDATE_BOOLEAN);
        }

        $status = strtolower(trim((string) ($credentials['certification_status'] ?? '')));
        if ($status === 'certified') {
            return true;
        }
        if ($status === 'pending') {
            return false;
        }

        return $connection->supplierHealthHealthy();
    }
}
