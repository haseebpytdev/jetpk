<?php

namespace App\Support\Suppliers;

/**
 * @deprecated Use {@see SabreNdcEntitlementEvidenceStore}
 */
final class SabreNdcMarketMatrixResultStore
{
    public function __construct(
        private readonly SabreNdcEntitlementEvidenceStore $evidenceStore,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $rows
     */
    public function store(int $connectionId, array $summary, array $rows): void
    {
        $this->evidenceStore->storeMatrix($connectionId, $summary, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastForConnection(int $connectionId): ?array
    {
        return $this->evidenceStore->lastMatrix($connectionId);
    }
}
