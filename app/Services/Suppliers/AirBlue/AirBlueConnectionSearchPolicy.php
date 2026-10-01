<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Support\Collection;

/**
 * Gates and dedupes AirBlue Zapways connections for normal/public flight search.
 *
 * Supplier health proves connectivity only — never certification. v3 requires explicit
 * certification before participating in public search.
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

        if ($airblue->isEmpty()) {
            return $connections;
        }

        $eligible = $airblue->filter(
            fn (SupplierConnection $connection): bool => $this->isEligibleForPublicSearch($connection),
        );

        if ($eligible->isEmpty()) {
            return $connections->reject(
                fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Airblue,
            )->values();
        }

        if ($eligible->count() === 1) {
            $winnerId = (int) $eligible->first()->id;

            return $connections->reject(
                fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Airblue
                    && (int) $connection->id !== $winnerId,
            )->values();
        }

        $preferredId = $this->selectPreferredConnectionId($eligible);
        if ($preferredId === null) {
            return $connections->reject(
                fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Airblue,
            )->values();
        }

        return $connections->reject(
            fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Airblue
                && (int) $connection->id !== $preferredId,
        )->values();
    }

    public function isEligibleForPublicSearch(SupplierConnection $connection): bool
    {
        if ($connection->provider !== SupplierProvider::Airblue) {
            return true;
        }

        if (! $connection->isEligibleForSupplierSearch()) {
            return false;
        }

        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $protocol = AirBlueZapwaysProtocolVersion::fromCredentials($credentials);

        return $this->isCertifiedForPublicSearch($protocol, $credentials);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function isCertifiedForPublicSearch(AirBlueZapwaysProtocolVersion $protocol, array $credentials): bool
    {
        if (array_key_exists('search_certified', $credentials)) {
            return filter_var($credentials['search_certified'], FILTER_VALIDATE_BOOLEAN);
        }

        $status = strtolower(trim((string) ($credentials['certification_status'] ?? '')));

        if ($protocol->isV3()) {
            return $status === 'certified';
        }

        if ($status === 'pending') {
            return false;
        }

        if ($status === 'certified') {
            return true;
        }

        return true;
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
        $explicitPriority = (int) ($credentials['search_priority'] ?? 0);

        return ($explicitPriority * 1000) + $protocol->searchPriority() + (int) $connection->id;
    }
}
