<?php

namespace App\Support\Suppliers\Adapters;

use App\Models\Booking;
use App\Support\Suppliers\Contracts\SupplierCreateStrategyPort;
use App\Support\Suppliers\SupplierActionCode;
use App\Support\Suppliers\SupplierLifecycleCapabilities;

/**
 * Read-only stub adapter for suppliers without live create-strategy registry yet.
 */
final class SupplierStubCreateStrategyAdapter implements SupplierCreateStrategyPort
{
    private string $provider = 'unknown';

    private string $action = SupplierActionCode::CREATE_PNR;

    public function for(string $provider, string $action): self
    {
        $clone = clone $this;
        $clone->provider = strtolower(trim($provider));
        $clone->action = strtolower(trim($action));

        return $clone;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function distributionChannel(): string
    {
        return app(SupplierLifecycleCapabilities::class)->channelForProvider($this->provider);
    }

    public function supportedStrategyCodes(): array
    {
        return [];
    }

    public function strategyDefinitions(): array
    {
        return [];
    }

    public function selectForBooking(Booking $booking): array
    {
        unset($booking);

        return [
            'selected_strategy' => null,
            'selection_reason' => 'supplier_strategy_registry_not_implemented',
            'eligible_strategies' => [],
            'blocked_strategies' => [],
            'fallback_available' => false,
            'manual_review' => true,
            'reason_code' => 'supplier_no_eligible_create_strategy',
        ];
    }

    public function buildBookingSummary(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'provider' => $this->provider,
            'distribution_channel' => $this->distributionChannel(),
            'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 0) ?: null,
        ];
    }

    public function buildCandidateDigests(Booking $booking, ?array $selection = null): array
    {
        unset($booking, $selection);

        return [];
    }
}
