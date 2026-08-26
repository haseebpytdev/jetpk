<?php

namespace App\Services\Suppliers\Sabre;

use App\Data\FlightSearchRequestData;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierAdapterResolver;
use App\Support\Sabre\SabreSandboxQaConnectionPin;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Internal-only sandbox QA search: exactly one pinned Sabre sandbox connection.
 * Never fans out to live or other active connections.
 */
final class SabreSandboxQaSearchService
{
    public function __construct(
        protected SupplierAdapterResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{
     *     offers: list<array<string, mixed>>,
     *     warnings: list<string>,
     *     connection_id: int,
     *     connection_alias_safe: string,
     *     connection_count: int,
     *     live_connection_eligible: bool,
     *     source_channel: string
     * }
     */
    public function searchExact(
        int $sandboxConnectionId,
        array $criteria,
        ?Agency $agency = null,
        ?int $forbiddenLiveConnectionId = null,
    ): array {
        $connection = SabreSandboxQaConnectionPin::requireExact(
            $sandboxConnectionId,
            $forbiddenLiveConnectionId,
        );

        $agency ??= $connection->agency;
        $criteria['search_id'] = trim((string) ($criteria['search_id'] ?? '')) !== ''
            ? (string) $criteria['search_id']
            : (string) Str::uuid();

        $request = FlightSearchRequestData::fromArray(
            $criteria,
            $agency?->id,
            SabreSandboxQaConnectionPin::SOURCE_CHANNEL,
        );

        $adapter = $this->resolver->resolve($connection->provider);
        $result = $adapter->search($request, $connection);

        $offers = [];
        foreach ($result->offers as $offerData) {
            $row = $offerData->toArray();
            $row['supplier_connection_id'] = $connection->id;
            $offers[] = $row;
        }

        return [
            'offers' => $offers,
            'warnings' => $result->warnings,
            'connection_id' => $connection->id,
            'connection_alias_safe' => (string) $connection->name,
            'connection_count' => 1,
            'live_connection_eligible' => false,
            'source_channel' => SabreSandboxQaConnectionPin::SOURCE_CHANNEL,
        ];
    }

    public function assertPinnedConnection(SupplierConnection $connection, ?int $forbiddenLiveConnectionId = null): void
    {
        SabreSandboxQaConnectionPin::requireExact((int) $connection->id, $forbiddenLiveConnectionId);
    }
}
