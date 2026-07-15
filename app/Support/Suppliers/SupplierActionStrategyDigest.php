<?php

namespace App\Support\Suppliers;

use App\Models\Booking;

/**
 * Universal supplier strategy digest (read-only; no raw payload / PII).
 */
final class SupplierActionStrategyDigest
{
    public function __construct(
        protected SupplierActionStrategyRegistry $registry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildBookingSummary(Booking $booking, string $provider, string $action): array
    {
        return $this->registry->adapterFor($provider, $action)->buildBookingSummary($booking);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildCandidateDigests(Booking $booking, string $provider, string $action, ?array $selection = null): array
    {
        return $this->registry->adapterFor($provider, $action)->buildCandidateDigests($booking, $selection);
    }
}
