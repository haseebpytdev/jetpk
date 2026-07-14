<?php

namespace App\Support\Suppliers\Adapters;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Bookings\SupplierLifecycleContextResolver;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyDigest;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategySelector;
use App\Support\Suppliers\Contracts\SupplierCreateStrategyPort;
use App\Support\Suppliers\SupplierActionCertificationMatrix;
use App\Support\Suppliers\SupplierActionCode;

/**
 * Sabre GDS PNR create strategy adapter (wraps Phase-1 registry; universal lifecycle entry).
 */
final class SabreGdsCreatePnrStrategyAdapter implements SupplierCreateStrategyPort
{
    public function __construct(
        protected SabreGdsPnrCreateStrategyRegistry $registry,
        protected SabreGdsPnrCreateStrategySelector $selector,
        protected SabreGdsPnrCreateStrategyDigest $digest,
        protected SupplierActionCertificationMatrix $certificationMatrix,
    ) {}

    public function provider(): string
    {
        return SupplierProvider::Sabre->value;
    }

    public function action(): string
    {
        return SupplierActionCode::CREATE_PNR;
    }

    public function distributionChannel(): string
    {
        return SupplierLifecycleContextResolver::CHANNEL_GDS;
    }

    public function supportedStrategyCodes(): array
    {
        return $this->registry->supportedCodes();
    }

    public function strategyDefinitions(): array
    {
        return $this->registry->all();
    }

    public function selectForBooking(Booking $booking): array
    {
        return $this->selector->selectForBooking($booking);
    }

    public function buildBookingSummary(Booking $booking): array
    {
        $summary = $this->digest->buildBookingSummary($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return array_merge($summary, [
            'provider' => $this->provider(),
            'distribution_channel' => $this->distributionChannel(),
            'supplier_connection_id' => (int) ($meta['supplier_connection_id'] ?? 0) ?: null,
        ]);
    }

    public function buildCandidateDigests(Booking $booking, ?array $selection = null): array
    {
        $candidates = $this->digest->buildCandidateDigests($booking, $selection);

        return array_map(function (array $candidate) use ($booking): array {
            $contextReady = ($candidate['context_ready'] ?? false) === true;
            $cert = $this->certificationMatrix->resolveForSabreGdsCreate(
                $booking,
                (string) ($candidate['strategy_code'] ?? ''),
                $contextReady,
            );

            return array_merge($candidate, $cert);
        }, $candidates);
    }
}
