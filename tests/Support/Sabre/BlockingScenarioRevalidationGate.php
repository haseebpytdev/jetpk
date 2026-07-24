<?php

namespace Tests\Support\Sabre;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationGate;

/**
 * Test double that always blocks scenario revalidation (freshness not satisfied).
 */
class BlockingScenarioRevalidationGate extends SabreGdsLiveScenarioRevalidationGate
{
    public function __construct()
    {
    }

    /**
     * @param  array<string, mixed>  $offerSnap
     * @param  array{passenger: array<string, mixed>, contact: array<string, mixed>}  $passengerBundle
     * @return array<string, mixed>
     */
    public function revalidateSelectedOffer(
        SupplierConnection $connection,
        array $offerSnap,
        array $passengerBundle,
        float $selectedTotal,
        ?int $bookingId = null,
        array $continuity = [],
    ): array {
        return $this->blockedSlice();
    }

    /**
     * @return array<string, mixed>
     */
    public function revalidateForBooking(Booking $booking, SupplierConnection $connection): array
    {
        return $this->blockedSlice();
    }

    public function shouldProceed(array $evidence): bool
    {
        return false;
    }

    public function persistOnBooking(Booking $booking, array $evidence): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    private function blockedSlice(): array
    {
        return [
            'revalidation_attempted' => true,
            'revalidation_success' => false,
            'freshness_satisfied' => false,
            'block_reason' => 'scenario_revalidation_failed',
        ];
    }
}
